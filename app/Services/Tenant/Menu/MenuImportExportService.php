<?php

namespace App\Services\Tenant\Menu;

use App\Jobs\Tenant\Menu\ProcessMenuImportJob;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Company;
use App\Models\Tenant\DataImportBatch;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MenuImportExportService
{
    public function createImportBatch(UploadedFile $file, User $actor, ?string $branchUuid = null): DataImportBatch
    {
        $path = Storage::disk('tenant_private_media')->putFileAs(
            'imports/menu',
            $file,
            Str::uuid7().'.'.$file->getClientOriginalExtension()
        );

        $batch = DataImportBatch::query()->create([
            'company_id' => $this->companyId(),
            'branch_id' => $this->branchId($branchUuid),
            'uploaded_by_tenant_user_id' => $actor->tenantUser?->id,
            'import_area' => 'menu',
            'status' => 'pending',
            'summary' => [
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'processing_note' => 'Stored for queued menu import processing.',
            ],
        ]);

        ProcessMenuImportJob::dispatch(app('tenantUuid'), $batch->id)
            ->onQueue('imports')
            ->afterCommit();

        return $batch;
    }

    public function menuItemsCsv(): StreamedResponse
    {
        $fileName = 'menu-items-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'uuid',
                'name',
                'sku',
                'category',
                'tax_code',
                'is_active',
                'allows_open_price',
                'is_combo',
            ]);

            MenuItem::query()
                ->with(['menuCategory:id,name', 'taxCode:id,name'])
                ->orderBy('id')
                ->chunkById(500, function (Collection $items) use ($handle): void {
                    foreach ($items as $item) {
                        fputcsv($handle, [
                            $item->uuid,
                            $item->name,
                            $item->sku,
                            $item->menuCategory?->name,
                            $item->taxCode?->name,
                            $item->is_active ? 'yes' : 'no',
                            $item->allows_open_price ? 'yes' : 'no',
                            $item->is_combo ? 'yes' : 'no',
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function companyId(): int
    {
        $companyId = Company::query()->orderBy('id')->value('id');

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company' => ['Company profile must be created before menu import.'],
            ]);
        }

        return (int) $companyId;
    }

    private function branchId(?string $branchUuid): ?int
    {
        if (! $branchUuid) {
            return null;
        }

        $branchId = Branch::query()->where('uuid', $branchUuid)->value('id');

        if (! $branchId) {
            throw ValidationException::withMessages([
                'branch_uuid' => ['The selected branch could not be found.'],
            ]);
        }

        return (int) $branchId;
    }
}
