<?php

namespace App\Http\Controllers\Web\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Menu\UploadMenuItemImageRequest;
use App\Http\Resources\Web\Menu\MenuItemResource;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\Menu\MenuItemImageService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuItemImageController extends Controller
{
    public function __construct(private readonly MenuItemImageService $imageService) {}

    public function store(UploadMenuItemImageRequest $request, MenuItem $menuItem): JsonResponse
    {
        $this->authorize('uploadImage', [$menuItem, $this->currentBranch()]);

        $menuItem = $this->imageService->replaceImage($menuItem, $request->file('image'));

        return ApiResponse::resource(
            new MenuItemResource($menuItem),
            ApiMessages::updated('menu item image')
        );
    }

}
