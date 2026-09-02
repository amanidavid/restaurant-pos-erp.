<?php

use App\Models\Tenant\Media;

$config = require base_path('vendor/spatie/laravel-medialibrary/config/media-library.php');

$config['disk_name'] = env('MEDIA_DISK', 'tenant_private_media');
$config['conversions_disk_name'] = env('MEDIA_CONVERSIONS_DISK', 'tenant_public_media');
$config['media_model'] = Media::class;
$config['max_file_size'] = (int) env('MEDIA_MAX_FILE_SIZE', 1024 * 1024 * 20);

return $config;
