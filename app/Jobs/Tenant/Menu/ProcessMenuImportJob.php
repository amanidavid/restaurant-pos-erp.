<?php

namespace App\Jobs\Tenant\Menu;

use App\Models\Tenant\DataImportBatch;
use App\Models\Tenant\DataImportError;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TaxCode;
use App\Services\Landlord\Tenancy\TenantConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Throwable;

class ProcessMenuImportJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantUuid,
        public readonly int $dataImportBatchId
    ) {}

    public function handle(TenantConnectionManager $tenantConnectionManager): void
    {
        $tenantConnectionManager->connectByUuid($this->tenantUuid);

        $batch = DataImportBatch::query()->findOrFail($this->dataImportBatchId);
        $summary = $batch->summary ?? [];
        $path = $summary['file_path'] ?? null;

        if (! $path || ! Storage::disk('tenant_private_media')->exists($path)) {
            $this->markFailed($batch, 'Uploaded import file could not be found.');

            return;
        }

        $batch->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $handle = fopen(Storage::disk('tenant_private_media')->path($path), 'r');

        if ($handle === false) {
            $this->markFailed($batch, 'Uploaded import file could not be opened.');

            return;
        }

        try {
            $headers = $this->headers((array) fgetcsv($handle));
            $counters = ['total_rows' => 0, 'successful_rows' => 0, 'failed_rows' => 0];
            $categoryCache = [];
            $taxCodeCache = [];

            while (($row = fgetcsv($handle)) !== false) {
                $counters['total_rows']++;
                $rowNumber = $counters['total_rows'] + 1;

                try {
                    $payload = $this->payload($headers, $row);
                    $this->upsertMenuItem($batch, $payload, $categoryCache, $taxCodeCache);
                    $counters['successful_rows']++;
                } catch (Throwable $exception) {
                    $counters['failed_rows']++;
                    DataImportError::query()->create([
                        'data_import_batch_id' => $batch->id,
                        'row_number' => $rowNumber,
                        'error_message' => Str::limit($exception->getMessage(), 1000),
                        'row_payload' => $this->payload($headers, $row, false),
                    ]);
                }
            }

            $batch->update([
                ...$counters,
                'status' => $counters['failed_rows'] > 0 ? 'completed_with_errors' : 'completed',
                'completed_at' => now(),
                'summary' => array_merge($summary, [
                    'processed_at' => now()->toISOString(),
                ]),
            ]);
        } catch (Throwable $exception) {
            $this->markFailed($batch, Str::limit($exception->getMessage(), 1000));

            throw $exception;
        } finally {
            fclose($handle);
        }
    }

    private function upsertMenuItem(
        DataImportBatch $batch,
        array $payload,
        array &$categoryCache,
        array &$taxCodeCache
    ): void {
        if (empty($payload['name'])) {
            throw new \RuntimeException('Menu item name is required.');
        }

        $name = Str::title(Str::lower(Str::squish($payload['name'])));
        $uuid = $payload['uuid'] ?? null;

        DB::connection('tenant')->transaction(function () use ($batch, $payload, $name, $uuid, &$categoryCache, &$taxCodeCache): void {
            $menuItem = $uuid
                ? MenuItem::query()->where('uuid', $uuid)->first()
                : MenuItem::query()
                    ->where('company_id', $batch->company_id)
                    ->where('name', $name)
                    ->first();

            $menuItem ??= new MenuItem(['company_id' => $batch->company_id]);

            $menuItem->fill([
                'name' => $name,
                'sku' => $payload['sku'] ?? null,
                'menu_category_id' => $this->categoryId($batch->company_id, $payload['category'] ?? null, $categoryCache),
                'tax_code_id' => $this->taxCodeId($batch->company_id, $payload['tax_code'] ?? null, $taxCodeCache),
                'is_active' => $this->bool($payload['is_active'] ?? true),
                'allows_open_price' => $this->bool($payload['allows_open_price'] ?? false),
                'is_combo' => $this->bool($payload['is_combo'] ?? false),
            ]);

            $menuItem->save();
        });
    }

    private function headers(array $headers): array
    {
        return collect($headers)
            ->map(fn (?string $header): string => Str::of((string) $header)->lower()->replace(' ', '_')->toString())
            ->all();
    }

    private function payload(array $headers, array $row, bool $dropEmpty = true): array
    {
        $payload = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $value = trim((string) ($row[$index] ?? ''));

            if ($dropEmpty && $value === '') {
                continue;
            }

            $payload[$header] = $value;
        }

        return $payload;
    }

    private function categoryId(int $companyId, ?string $name, array &$cache): ?int
    {
        if (! $name) {
            return null;
        }

        $id = $cache[$name] ??= MenuCategory::query()
            ->where('company_id', $companyId)
            ->where('name', Str::title(Str::lower(Str::squish($name))))
            ->value('id');

        if (! $id) {
            throw new \RuntimeException("Menu category {$name} could not be found.");
        }

        return (int) $id;
    }

    private function taxCodeId(int $companyId, ?string $name, array &$cache): ?int
    {
        if (! $name) {
            return null;
        }

        $id = $cache[$name] ??= TaxCode::query()
            ->where('company_id', $companyId)
            ->where('name', Str::title(Str::lower(Str::squish($name))))
            ->value('id');

        if (! $id) {
            throw new \RuntimeException("Tax code {$name} could not be found.");
        }

        return (int) $id;
    }

    private function bool(mixed $value): bool
    {
        return in_array(Str::lower((string) $value), ['1', 'true', 'yes', 'y', 'active'], true);
    }

    private function markFailed(DataImportBatch $batch, string $message): void
    {
        $batch->update([
            'status' => 'failed',
            'completed_at' => now(),
            'summary' => array_merge($batch->summary ?? [], [
                'failure_message' => $message,
            ]),
        ]);
    }
}
