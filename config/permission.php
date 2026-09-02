<?php

use App\Models\Landlord\AdminPermission;
use App\Models\Landlord\AdminRole;
use Spatie\Permission\DefaultTeamResolver;

return [
    // Admin is the safe boot-time context. Web tenant middleware switches this
    // profile before resolving the tenant user or checking permissions.
    'models' => [
        'permission' => AdminPermission::class,
        'role' => AdminRole::class,
        'team' => null,
        'default_model' => null,
    ],

    'table_names' => [
        'roles' => 'admin_roles',
        'permissions' => 'admin_permissions',
        'model_has_permissions' => 'admin_model_has_permissions',
        'model_has_roles' => 'admin_model_has_roles',
        'role_has_permissions' => 'admin_role_has_permissions',
    ],

    'column_names' => [
        'role_pivot_key' => 'role_id',
        'permission_pivot_key' => 'permission_id',
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'branch_id',
    ],

    'register_permission_check_method' => true,
    'register_octane_reset_listener' => true,
    'events_enabled' => true,
    'teams' => false,
    'team_resolver' => DefaultTeamResolver::class,
    'use_passport_client_credentials' => false,
    'display_permission_in_exception' => false,
    'display_role_in_exception' => false,
    'enable_wildcard_permission' => false,

    'cache' => [
        'expiration_time' => DateInterval::createFromDateString('24 hours'),
        'key' => 'rmis.permission.admin',
        'store' => 'default',
    ],
];
