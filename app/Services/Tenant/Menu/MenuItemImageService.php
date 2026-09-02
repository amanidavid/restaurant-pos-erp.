<?php

namespace App\Services\Tenant\Menu;

use App\Models\Tenant\MenuItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class MenuItemImageService
{
    public function replaceImage(MenuItem $menuItem, UploadedFile $image): MenuItem
    {
        return DB::connection('tenant')->transaction(function () use ($menuItem, $image): MenuItem {
            $media = $menuItem
                ->addMedia($image)
                ->usingFileName($this->fileName($menuItem, $image))
                ->toMediaCollection('menu_image');

            $menuItem->update([
                'image_url' => $media->getUrl(),
            ]);

            return $menuItem->refresh()->load(['menuCategory', 'taxCode']);
        });
    }

    private function fileName(MenuItem $menuItem, UploadedFile $image): string
    {
        return $menuItem->uuid.'.'.$image->getClientOriginalExtension();
    }
}
