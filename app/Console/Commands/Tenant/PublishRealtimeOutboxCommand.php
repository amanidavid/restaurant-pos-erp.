<?php

namespace App\Console\Commands\Tenant;

use App\Events\Tenant\RealtimeOutboxPublished;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\RealtimeEventOutbox;
use App\Services\Landlord\Tenancy\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class PublishRealtimeOutboxCommand extends Command
{
    protected $signature = 'realtime:publish-outbox
        {--tenant= : One tenant public UUID}
        {--all : Process every active tenant}
        {--limit=100 : Maximum events per tenant}';

    protected $description = 'Publish committed tenant realtime outbox events through the configured broadcaster';

    public function handle(TenantConnectionManager $connectionManager): int
    {
        $tenantUuid = $this->option('tenant');
        if (! $this->option('all') && (! is_string($tenantUuid) || trim($tenantUuid) === '')) {
            $this->error('Provide --tenant=<tenant-uuid> or use --all.');

            return SymfonyCommand::INVALID;
        }

        $limit = min(max((int) $this->option('limit'), 1), 1000);
        $failures = 0;
        foreach (Tenant::query()->select(['id', 'uuid'])->whereIn('status', ['trial', 'active'])->when(is_string($tenantUuid) && trim($tenantUuid) !== '', fn ($query) => $query->where('uuid', trim($tenantUuid)))->orderBy('id')->cursor() as $tenant) {
            try {
                $connectionManager->connectByUuid($tenant->uuid);
                $published = $this->publishTenant($limit);
                $this->info("Published {$published} realtime event(s) for {$tenant->uuid}.");
            } catch (\Throwable $exception) {
                $failures++;
                $this->error("Realtime publishing failed for {$tenant->uuid}: {$exception->getMessage()}");
            } finally {
                DB::disconnect('tenant');
            }
        }

        return $failures === 0 ? SymfonyCommand::SUCCESS : SymfonyCommand::FAILURE;
    }

    private function publishTenant(int $limit): int
    {
        $published = 0;
        while ($published < $limit && ($event = $this->claim()) !== null) {
            $startedAt = microtime(true);
            try {
                broadcast(new RealtimeOutboxPublished($event->channel_name, $event->event_type, $event->uuid, $event->payload));
                $this->markPublished($event, $startedAt);
                $published++;
            } catch (\Throwable $exception) {
                $this->markFailed($event, $startedAt, $exception);
            }
        }

        return $published;
    }

    private function claim(): ?RealtimeEventOutbox
    {
        return DB::connection('tenant')->transaction(function (): ?RealtimeEventOutbox {
            $event = RealtimeEventOutbox::query()
                ->where('status', 'pending')
                ->where('available_at', '<=', now())
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderByDesc('priority')->orderBy('id')
                ->lock('FOR UPDATE SKIP LOCKED')
                ->first();
            if (! $event) {
                return null;
            }

            $attempt = $event->attempts + 1;
            $event->update(['status' => 'publishing', 'attempts' => $attempt, 'locked_at' => now(), 'locked_by' => gethostname() ?: 'realtime-publisher']);
            DB::connection('tenant')->table('realtime_event_publish_attempts')->insert([
                'uuid' => (string) Str::uuid7(), 'realtime_event_outbox_id' => $event->id, 'worker_name' => gethostname() ?: 'realtime-publisher',
                'attempt_number' => $attempt, 'status' => 'started', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $event->fresh();
        });
    }

    private function markPublished(RealtimeEventOutbox $event, float $startedAt): void
    {
        $completedAt = now();
        DB::connection('tenant')->transaction(function () use ($event, $startedAt, $completedAt): void {
            $event->update(['status' => 'published', 'published_at' => $completedAt, 'locked_at' => null, 'locked_by' => null, 'failure_reason' => null]);
            DB::connection('tenant')->table('realtime_event_publish_attempts')->where('realtime_event_outbox_id', $event->id)->where('attempt_number', $event->attempts)->update(['status' => 'published', 'completed_at' => $completedAt, 'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000), 'updated_at' => $completedAt]);
        });
    }

    private function markFailed(RealtimeEventOutbox $event, float $startedAt, \Throwable $exception): void
    {
        $completedAt = now();
        $retryAt = now()->addSeconds(min(2 ** $event->attempts, 300));
        DB::connection('tenant')->transaction(function () use ($event, $startedAt, $exception, $completedAt, $retryAt): void {
            $status = $event->attempts >= $event->max_attempts ? 'failed' : 'pending';
            $event->update(['status' => $status, 'available_at' => $retryAt, 'locked_at' => null, 'locked_by' => null, 'failure_reason' => Str::limit($exception->getMessage(), 2000, '')]);
            DB::connection('tenant')->table('realtime_event_publish_attempts')->where('realtime_event_outbox_id', $event->id)->where('attempt_number', $event->attempts)->update(['status' => 'failed', 'completed_at' => $completedAt, 'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000), 'failure_reason' => Str::limit($exception->getMessage(), 2000, ''), 'updated_at' => $completedAt]);
        });
    }
}
