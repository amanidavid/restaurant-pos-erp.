<?php

namespace App\Services\Tenant\Pos;

use App\Models\Tenant\Branch;
use App\Models\Tenant\PrintDevice;
use App\Models\Tenant\PrintJob;
use App\Models\Tenant\PrintTemplate;
use App\Models\Tenant\RealtimeEventOutbox;
use App\Models\Tenant\Receipt;
use App\Models\Tenant\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrintJobService
{
    public function queueReceipt(Receipt $receipt, Branch $branch, TenantUser $actor): PrintJob
    {
        return DB::connection('tenant')->transaction(function () use ($receipt, $branch, $actor): PrintJob {
            $receipt = Receipt::query()
                ->whereKey($receipt->id)
                ->where('status', 'issued')
                ->whereHas('order', fn ($query) => $query->where('branch_id', $branch->id))
                ->lockForUpdate()
                ->firstOrFail();

            $existing = PrintJob::query()
                ->where('receipt_id', $receipt->id)
                ->where('job_type', 'receipt')
                ->whereIn('status', ['pending', 'printing'])
                ->orderByDesc('id')
                ->first();

            if ($existing !== null) {
                return $existing->load(['printDevice', 'printTemplate', 'receipt']);
            }

            $device = $this->defaultReceiptDevice($branch);
            $template = $this->defaultReceiptTemplate($branch);
            $receipt->loadMissing(['order.items.modifiers']);

            $job = PrintJob::query()->create([
                'branch_id' => $branch->id,
                'print_device_id' => $device->id,
                'print_template_id' => $template->id,
                'order_id' => $receipt->order_id,
                'receipt_id' => $receipt->id,
                'requested_by_tenant_user_id' => $actor->id,
                'job_type' => 'receipt',
                'status' => 'pending',
                'payload' => $this->receiptSnapshot($receipt, $branch, $device, $template),
                'requested_at' => now(),
            ]);

            RealtimeEventOutbox::query()->create([
                'company_id' => $branch->company_id,
                'branch_id' => $branch->id,
                'created_by_tenant_user_id' => $actor->id,
                'print_job_id' => $job->id,
                'event_type' => 'print_job.created',
                'channel_name' => 'tenant.branch.'.$branch->uuid.'.print-device.'.$device->uuid,
                'visibility_scope' => 'device',
                'payload' => [
                    'branch_uuid' => $branch->uuid,
                    'print_job_uuid' => $job->uuid,
                    'print_device_uuid' => $device->uuid,
                    'job_type' => 'receipt',
                    'status' => 'pending',
                ],
                'idempotency_key' => 'receipt-print-job-created:'.$job->uuid,
                'priority' => 6,
                'status' => 'pending',
                'available_at' => now(),
            ]);

            return $job->load(['printDevice', 'printTemplate', 'receipt']);
        });
    }

    private function defaultReceiptDevice(Branch $branch): PrintDevice
    {
        $device = PrintDevice::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'active')
            ->where('is_default_receipt_printer', true)
            ->orderBy('id')
            ->first();

        if ($device === null) {
            throw ValidationException::withMessages([
                'print_device' => ['Configure an active default receipt printer for this branch before printing receipts.'],
            ]);
        }

        return $device;
    }

    private function defaultReceiptTemplate(Branch $branch): PrintTemplate
    {
        $template = PrintTemplate::query()
            ->where('company_id', $branch->company_id)
            ->where('template_type', 'receipt')
            ->where('status', 'active')
            ->where('is_default', true)
            ->where(function ($query) use ($branch): void {
                $query->where('branch_id', $branch->id)
                    ->orWhereNull('branch_id');
            })
            ->orderByRaw('CASE WHEN branch_id = ? THEN 0 ELSE 1 END', [$branch->id])
            ->orderBy('id')
            ->first();

        if ($template === null) {
            throw ValidationException::withMessages([
                'print_template' => ['Configure an active default receipt template before printing receipts.'],
            ]);
        }

        return $template;
    }

    private function receiptSnapshot(Receipt $receipt, Branch $branch, PrintDevice $device, PrintTemplate $template): array
    {
        return [
            'version' => 1,
            'receipt' => [
                'uuid' => $receipt->uuid,
                'number' => $receipt->receipt_number,
                'total_amount' => $receipt->total_amount,
                'issued_at' => $receipt->issued_at?->toIso8601String(),
            ],
            'order' => [
                'uuid' => $receipt->order->uuid,
                'number' => $receipt->order->order_number,
                'subtotal_amount' => $receipt->order->subtotal_amount,
                'discount_amount' => $receipt->order->discount_amount,
                'service_charge_amount' => $receipt->order->service_charge_amount,
                'tax_amount' => $receipt->order->tax_amount,
                'total_amount' => $receipt->order->total_amount,
                'items' => $receipt->order->items
                    ->whereNotIn('status', ['voided', 'cancelled'])
                    ->map(fn ($item): array => [
                        'name' => $item->item_name_snapshot,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price_snapshot,
                        'line_total' => $item->line_total,
                        'modifiers' => $item->modifiers->map(fn ($modifier): array => [
                            'name' => $modifier->modifier_name_snapshot,
                            'quantity' => $modifier->quantity,
                            'amount' => $modifier->line_total,
                        ])->values()->all(),
                    ])->values()->all(),
            ],
            'branch' => ['uuid' => $branch->uuid, 'name' => $branch->name],
            'device' => ['uuid' => $device->uuid, 'paper_width_mm' => $device->paper_width_mm],
            'template' => [
                'uuid' => $template->uuid,
                'paper_width_mm' => $template->paper_width_mm,
                'layout' => $template->layout,
            ],
        ];
    }
}
