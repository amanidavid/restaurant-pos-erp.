<?php

namespace App\Models\Tenant;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class BusinessDocumentVersion extends TenantModel implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('document_file')
            ->useDisk('tenant_private_media')
            ->singleFile();
    }
}
