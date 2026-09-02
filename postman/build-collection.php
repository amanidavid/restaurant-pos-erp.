<?php

declare(strict_types=1);

/**
 * Rebuild the version-controlled Postman assets.
 *
 * Run from the project root:
 *     php postman/build-collection.php
 */

$root = __DIR__;
$collectionPath = $root.'/RMIS-v1.postman_collection.json';
$environmentPath = $root.'/RMIS-local.postman_environment.json';

function jsonBody(array $payload): array
{
    return [
        'mode' => 'raw',
        'raw' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'options' => ['raw' => ['language' => 'json']],
    ];
}

function query(array $values): array
{
    $result = [];

    foreach ($values as $key => $definition) {
        if (is_array($definition)) {
            $result[] = [
                'key' => $key,
                'value' => (string) $definition['value'],
                'disabled' => (bool) ($definition['disabled'] ?? false),
                'description' => $definition['description'] ?? null,
            ];
        } else {
            $result[] = ['key' => $key, 'value' => (string) $definition];
        }
    }

    return $result;
}

function testScript(array $lines): array
{
    return [
        'listen' => 'test',
        'script' => ['type' => 'text/javascript', 'exec' => $lines],
    ];
}

function requestItem(string $name, string $method, string $path, array $options = []): array
{
    $protected = $options['protected'] ?? true;
    $tenantHeader = $options['tenant_header'] ?? $protected;
    $headers = [['key' => 'Accept', 'value' => 'application/json', 'type' => 'text']];

    if ($tenantHeader) {
        $headers[] = ['key' => 'X-Tenant-UUID', 'value' => '{{tenant_uuid}}', 'type' => 'text'];
    }

    if (isset($options['body']) && ($options['body']['mode'] ?? null) === 'raw') {
        $headers[] = ['key' => 'Content-Type', 'value' => 'application/json', 'type' => 'text'];
    }

    $url = [
        'raw' => '{{base_url}}/api/v1/web/'.ltrim($path, '/'),
        'host' => ['{{base_url}}'],
        'path' => array_values(array_filter(explode('/', 'api/v1/web/'.ltrim($path, '/')), fn (string $part): bool => $part !== '')),
    ];

    if (! empty($options['query'])) {
        $url['query'] = query($options['query']);
    }

    $item = [
        'name' => $name,
        'request' => [
            'method' => $method,
            'header' => $headers,
            'url' => $url,
            'description' => $options['description'] ?? null,
        ],
        'response' => [],
    ];

    if (! $protected) {
        $item['request']['auth'] = ['type' => 'noauth'];
    }

    if (isset($options['body'])) {
        $item['request']['body'] = $options['body'];
    }

    $tests = $options['tests'] ?? [];
    if ($tests !== []) {
        $item['event'] = [testScript($tests)];
    }

    return $item;
}

function folder(string $name, array $items, ?string $description = null): array
{
    $folder = ['name' => $name, 'item' => $items];
    if ($description !== null) {
        $folder['description'] = $description;
    }

    return $folder;
}

$captureUuid = fn (string $variable): array => [
    'const json = pm.response.json();',
    "if (json.data && json.data.uuid) pm.environment.set('{$variable}', json.data.uuid);",
];

$auth = folder('01 - Authentication', [
    requestItem('Register restaurant workspace', 'POST', 'auth/register', [
        'protected' => false,
        'body' => jsonBody([
            'business_name' => 'RMIS Demo Restaurant',
            'first_name' => 'Amina',
            'last_name' => 'Mashauri',
            'email' => '{{login}}',
            'phone' => null,
            'password' => '{{password}}',
            'password_confirmation' => '{{password}}',
        ]),
        'tests' => [
            'const json = pm.response.json();',
            "if (json.data && json.data.tenant_uuid) pm.environment.set('tenant_uuid', json.data.tenant_uuid);",
            "if (json.data && json.data.provisioning_run_uuid) pm.environment.set('provisioning_run_uuid', json.data.provisioning_run_uuid);",
        ],
        'description' => 'Starts landlord registration and asynchronous tenant provisioning. Email or Tanzanian phone is required.',
    ]),
    requestItem('Get registration status', 'GET', 'auth/registration-status/{{tenant_uuid}}', [
        'protected' => false,
        'description' => 'Poll until tenant provisioning reports a completed or failed status.',
    ]),
    requestItem('Login', 'POST', 'auth/login', [
        'protected' => false,
        'body' => jsonBody([
            'login' => '{{login}}',
            'password' => '{{password}}',
            'device_name' => 'Postman Desktop',
            'device_type' => 'desktop',
            'branch_uuid' => '{{branch_uuid}}',
        ]),
        'tests' => [
            'const json = pm.response.json();',
            "if (json.data && json.data.tenant_uuid) pm.environment.set('tenant_uuid', json.data.tenant_uuid);",
            "if (json.data && json.data.access_token) pm.environment.set('access_token', json.data.access_token);",
            "if (json.data && json.data.tenant_user && json.data.tenant_user.uuid) pm.environment.set('tenant_user_uuid', json.data.tenant_user.uuid);",
            "if (json.data && json.data.active_branch_uuid) pm.environment.set('branch_uuid', json.data.active_branch_uuid);",
        ],
        'description' => 'Authenticates centrally and automatically resolves a single active restaurant workspace. A successful response stores the tenant UUID, access token, and active branch UUID. If multiple workspaces are returned, repeat login with the selected tenant_uuid in the JSON body.',
    ]),
    requestItem('Replace active device session', 'POST', 'auth/logout-other-device', [
        'protected' => false,
        'tenant_header' => true,
        'body' => jsonBody([
            'login' => '{{login}}',
            'password' => '{{password}}',
            'device_name' => 'Postman Desktop',
            'device_type' => 'desktop',
            'branch_uuid' => '{{branch_uuid}}',
        ]),
        'tests' => [
            'const json = pm.response.json();',
            "if (json.data && json.data.access_token) pm.environment.set('access_token', json.data.access_token);",
            "if (json.data && json.data.active_branch_uuid) pm.environment.set('branch_uuid', json.data.active_branch_uuid);",
        ],
    ]),
    requestItem('Forgot password', 'POST', 'auth/forgot-password', [
        'protected' => false,
        'tenant_header' => true,
        'body' => jsonBody(['login' => '{{login}}', 'channel' => 'email']),
    ]),
    requestItem('Verify reset OTP', 'POST', 'auth/verify-otp', [
        'protected' => false,
        'tenant_header' => true,
        'body' => jsonBody(['login' => '{{login}}', 'otp' => '{{otp}}']),
    ]),
    requestItem('Reset password', 'POST', 'auth/reset-password', [
        'protected' => false,
        'tenant_header' => true,
        'body' => jsonBody([
            'login' => '{{login}}',
            'otp' => '{{otp}}',
            'password' => '{{new_password}}',
            'password_confirmation' => '{{new_password}}',
        ]),
    ]),
    requestItem('Logout', 'POST', 'auth/logout', [
        'tests' => ["pm.environment.unset('access_token');"],
    ]),
]);

$me = folder('02 - Current User', [
    requestItem('Get current user', 'GET', 'me'),
    requestItem('Get current permissions', 'GET', 'me/permissions'),
]);

$foundation = folder('03 - Company and Branches', [
    requestItem('Get company profile', 'GET', 'company'),
    requestItem('Update company profile', 'PATCH', 'company', [
        'body' => jsonBody([
            'legal_name' => 'RMIS Demo Restaurant Limited',
            'trading_name' => 'RMIS Demo Restaurant',
            'tin' => '123456789',
            'vrn' => 'VRN123456',
            'email' => 'info@example.test',
            'phone' => '+255712345678',
        ]),
    ]),
    requestItem('List branches', 'GET', 'branches', [
        'query' => [
            'per_page' => '{{per_page}}',
            'status' => ['value' => 'active', 'disabled' => true],
            'search' => ['value' => 'Main', 'disabled' => true],
        ],
    ]),
    requestItem('Create branch', 'POST', 'branches', [
        'body' => jsonBody([
            'name' => 'Main Branch',
            'email' => 'branch@example.test',
            'phone' => '+255712345678',
            'address_line_1' => 'Samora Avenue',
            'address_line_2' => null,
            'city' => 'Dar Es Salaam',
            'region' => 'Dar Es Salaam',
            'postal_code' => '11101',
            'latitude' => -6.7924,
            'longitude' => 39.2083,
            'opened_on' => '2026-09-01',
            'configuration' => [
                'business_day_opens_at' => '06:00',
                'business_day_closes_at' => '23:00',
                'allow_negative_stock' => false,
            ],
        ]),
        'tests' => $captureUuid('branch_uuid'),
    ]),
    requestItem('Get branch', 'GET', 'branches/{{branch_uuid}}'),
    requestItem('Update branch', 'PATCH', 'branches/{{branch_uuid}}', [
        'body' => jsonBody(['name' => 'Main Branch', 'status' => 'active']),
    ]),
    requestItem('Get branch configuration', 'GET', 'branches/{{branch_uuid}}/configuration'),
    requestItem('Update branch configuration', 'PATCH', 'branches/{{branch_uuid}}/configuration', [
        'body' => jsonBody([
            'effective_from' => '2026-09-01',
            'business_day_opens_at' => '06:00',
            'business_day_closes_at' => '23:00',
            'allow_negative_stock' => false,
        ]),
    ]),
    requestItem('Get current business day', 'GET', 'branches/{{branch_uuid}}/business-day/current'),
    requestItem('Open business day', 'POST', 'branches/{{branch_uuid}}/business-days/open', [
        'body' => jsonBody([
            'business_date' => '2026-09-02',
            'opening_float_amount' => '100000.00',
            'opening_notes' => 'Opened from Postman.',
        ]),
        'tests' => $captureUuid('business_day_uuid'),
    ]),
    requestItem('Close business day', 'POST', 'branches/{{branch_uuid}}/business-days/{{business_day_uuid}}/close', [
        'body' => jsonBody([
            'counted_cash_amount' => '100000.00',
            'closing_notes' => 'Closed from Postman.',
        ]),
    ]),
]);

$access = folder('04 - Access Control', [
    requestItem('List permissions', 'GET', 'access/permissions', ['query' => ['per_page' => '100']]),
    requestItem('List roles', 'GET', 'access/roles', ['query' => ['per_page' => '{{per_page}}']]),
    requestItem('Create role', 'POST', 'access/roles', [
        'body' => jsonBody([
            'name' => 'inventory-supervisor',
            'display_name' => 'Inventory Supervisor',
            'description' => 'Manages branch inventory operations.',
            'branch_uuid' => '{{branch_uuid}}',
        ]),
        'tests' => $captureUuid('role_uuid'),
    ]),
    requestItem('Update role', 'PATCH', 'access/roles/{{role_uuid}}', [
        'body' => jsonBody([
            'display_name' => 'Inventory Supervisor',
            'description' => 'Manages branch inventory operations.',
        ]),
    ]),
    requestItem('Delete role', 'DELETE', 'access/roles/{{role_uuid}}'),
    requestItem('Replace role permissions', 'PUT', 'access/roles/{{role_uuid}}/permissions', [
        'body' => jsonBody(['permission_uuids' => ['{{permission_uuid}}']]),
    ]),
    requestItem('List tenant users', 'GET', 'access/users', [
        'query' => [
            'per_page' => '{{per_page}}',
            'status' => ['value' => 'active', 'disabled' => true],
            'search' => ['value' => 'Amina', 'disabled' => true],
        ],
    ]),
    requestItem('Create tenant user', 'POST', 'access/users', [
        'body' => jsonBody([
            'first_name' => 'Juma',
            'last_name' => 'Mashauri',
            'email' => 'juma@example.test',
            'phone' => null,
            'password' => '{{new_password}}',
            'password_confirmation' => '{{new_password}}',
            'branches' => [[
                'branch_uuid' => '{{branch_uuid}}',
                'is_primary' => true,
                'valid_from' => '2026-09-02',
                'valid_to' => null,
            ]],
        ]),
        'tests' => $captureUuid('tenant_user_uuid'),
    ]),
    requestItem('Get tenant user', 'GET', 'access/users/{{tenant_user_uuid}}'),
    requestItem('Update tenant user status', 'PATCH', 'access/users/{{tenant_user_uuid}}/status', [
        'body' => jsonBody(['status' => 'active']),
    ]),
    requestItem('Replace tenant user branch access', 'PUT', 'access/users/{{tenant_user_uuid}}/branches', [
        'body' => jsonBody(['branches' => [[
            'branch_uuid' => '{{branch_uuid}}',
            'is_primary' => true,
            'valid_from' => '2026-09-02',
            'valid_to' => null,
        ]]]),
    ]),
    requestItem('Replace tenant user branch roles', 'PUT', 'access/users/{{tenant_user_uuid}}/branches/{{branch_uuid}}/roles', [
        'body' => jsonBody(['role_uuids' => ['{{role_uuid}}']]),
    ]),
    requestItem('Replace tenant user branch permissions', 'PUT', 'access/users/{{tenant_user_uuid}}/branches/{{branch_uuid}}/permissions', [
        'body' => jsonBody(['permission_uuids' => ['{{permission_uuid}}']]),
    ]),
]);

$organisation = folder('05 - Organisation and Staff', [
    requestItem('List departments', 'GET', 'organisation/departments', ['query' => ['per_page' => '{{per_page}}']]),
    requestItem('Create department', 'POST', 'organisation/departments', [
        'body' => jsonBody([
            'name' => 'Operations',
            'description' => 'Restaurant operations team.',
            'branch_uuid' => '{{branch_uuid}}',
            'parent_uuid' => null,
            'status' => 'active',
        ]),
        'tests' => $captureUuid('department_uuid'),
    ]),
    requestItem('Update department', 'PATCH', 'organisation/departments/{{department_uuid}}', [
        'body' => jsonBody(['name' => 'Operations', 'status' => 'active']),
    ]),
    requestItem('List employment types', 'GET', 'organisation/employment-types', ['query' => ['per_page' => '{{per_page}}']]),
    requestItem('Create employment type', 'POST', 'organisation/employment-types', [
        'body' => jsonBody([
            'name' => 'Full Time',
            'description' => 'Permanent salaried employee.',
            'is_salary_based' => true,
            'status' => 'active',
        ]),
        'tests' => $captureUuid('employment_type_uuid'),
    ]),
    requestItem('Update employment type', 'PATCH', 'organisation/employment-types/{{employment_type_uuid}}', [
        'body' => jsonBody(['name' => 'Full Time', 'is_salary_based' => true, 'status' => 'active']),
    ]),
    requestItem('List job positions', 'GET', 'organisation/job-positions', ['query' => ['per_page' => '{{per_page}}']]),
    requestItem('Create job position', 'POST', 'organisation/job-positions', [
        'body' => jsonBody([
            'name' => 'Restaurant Supervisor',
            'department_uuid' => '{{department_uuid}}',
            'description' => 'Supervises daily restaurant operations.',
            'level_rank' => 10,
            'status' => 'active',
        ]),
        'tests' => $captureUuid('job_position_uuid'),
    ]),
    requestItem('Update job position', 'PATCH', 'organisation/job-positions/{{job_position_uuid}}', [
        'body' => jsonBody(['name' => 'Restaurant Supervisor', 'level_rank' => 10, 'status' => 'active']),
    ]),
    requestItem('Get staff profile', 'GET', 'staff/users/{{tenant_user_uuid}}/profile'),
    requestItem('Update staff profile', 'PATCH', 'staff/users/{{tenant_user_uuid}}/profile', [
        'body' => jsonBody([
            'employee_number' => 'EMP-0001',
            'employment_type_uuid' => '{{employment_type_uuid}}',
            'home_branch_uuid' => '{{branch_uuid}}',
            'department_uuid' => '{{department_uuid}}',
            'job_position_uuid' => '{{job_position_uuid}}',
            'date_of_birth' => '1995-01-15',
            'hired_on' => '2026-09-01',
            'confirmed_on' => null,
            'ended_on' => null,
            'employment_status' => 'active',
            'gender' => 'female',
            'national_id_number' => null,
            'tax_number' => null,
            'nssf_number' => null,
            'nhif_number' => null,
        ]),
    ]),
    requestItem('List staff job history', 'GET', 'staff/users/{{tenant_user_uuid}}/job-history', ['query' => ['per_page' => '{{per_page}}']]),
    requestItem('Add staff job history', 'POST', 'staff/users/{{tenant_user_uuid}}/job-history', [
        'body' => jsonBody([
            'branch_uuid' => '{{branch_uuid}}',
            'department_uuid' => '{{department_uuid}}',
            'job_position_uuid' => '{{job_position_uuid}}',
            'employment_type_uuid' => '{{employment_type_uuid}}',
            'base_salary_amount' => '1200000.00',
            'effective_from' => '2026-09-01',
            'reason' => 'Initial appointment.',
        ]),
    ]),
]);

$catalog = folder('Catalog Masters', [
    requestItem('List units of measure', 'GET', 'units-of-measure', ['query' => ['per_page' => '{{per_page}}']]),
    requestItem('Create unit of measure', 'POST', 'units-of-measure', [
        'body' => jsonBody(['name' => 'Kilogram', 'symbol' => 'kg', 'unit_type' => 'weight', 'base_factor' => '1.000000', 'is_base_unit' => true, 'status' => 'active']),
        'tests' => $captureUuid('unit_uuid'),
    ]),
    requestItem('Update unit of measure', 'PATCH', 'units-of-measure/{{unit_uuid}}', [
        'body' => jsonBody(['name' => 'Kilogram', 'symbol' => 'kg', 'status' => 'active']),
    ]),
    requestItem('List tax codes', 'GET', 'tax-codes', ['query' => ['per_page' => '{{per_page}}']]),
    requestItem('Create tax code', 'POST', 'tax-codes', [
        'body' => jsonBody(['name' => 'VAT 18%', 'rate_percentage' => '18.0000', 'is_inclusive' => true, 'status' => 'active', 'effective_from' => '2026-01-01', 'effective_to' => null]),
        'tests' => $captureUuid('tax_code_uuid'),
    ]),
    requestItem('Update tax code', 'PATCH', 'tax-codes/{{tax_code_uuid}}', [
        'body' => jsonBody(['name' => 'VAT 18%', 'rate_percentage' => '18.0000', 'is_inclusive' => true, 'status' => 'active']),
    ]),
    requestItem('List menu categories', 'GET', 'menu-categories', ['query' => ['per_page' => '{{per_page}}']]),
    requestItem('Create menu category', 'POST', 'menu-categories', [
        'body' => jsonBody(['name' => 'Main Courses', 'parent_uuid' => null, 'description' => 'Main meal items.', 'sort_order' => 1, 'status' => 'active']),
        'tests' => $captureUuid('menu_category_uuid'),
    ]),
    requestItem('Update menu category', 'PATCH', 'menu-categories/{{menu_category_uuid}}', [
        'body' => jsonBody(['name' => 'Main Courses', 'sort_order' => 1, 'status' => 'active']),
    ]),
    requestItem('List ingredients', 'GET', 'ingredients', ['query' => ['per_page' => '{{per_page}}']]),
    requestItem('Create ingredient', 'POST', 'ingredients', [
        'body' => jsonBody(['name' => 'Rice', 'sku' => 'ING-RICE', 'base_unit_uuid' => '{{unit_uuid}}', 'tax_code_uuid' => '{{tax_code_uuid}}', 'description' => 'Uncooked rice.', 'is_active' => true]),
        'tests' => $captureUuid('ingredient_uuid'),
    ]),
    requestItem('Update ingredient', 'PATCH', 'ingredients/{{ingredient_uuid}}', [
        'body' => jsonBody(['name' => 'Rice', 'sku' => 'ING-RICE', 'is_active' => true]),
    ]),
    requestItem('List kitchen sections', 'GET', 'kitchen-sections', ['query' => ['per_page' => '{{per_page}}', 'branch_uuid' => '{{branch_uuid}}']]),
    requestItem('Create kitchen section', 'POST', 'kitchen-sections', [
        'body' => jsonBody(['name' => 'Main Kitchen', 'branch_uuid' => '{{branch_uuid}}', 'description' => 'Primary preparation area.', 'sort_order' => 1, 'status' => 'active']),
        'tests' => $captureUuid('kitchen_section_uuid'),
    ]),
    requestItem('Update kitchen section', 'PATCH', 'kitchen-sections/{{kitchen_section_uuid}}', [
        'body' => jsonBody(['name' => 'Main Kitchen', 'sort_order' => 1, 'status' => 'active']),
    ]),
]);

$menuItems = folder('Menu Items and Recipes', [
    requestItem('List menu items', 'GET', 'menu-items', ['query' => ['per_page' => '{{per_page}}']]),
    requestItem('Create menu item', 'POST', 'menu-items', [
        'body' => jsonBody([
            'name' => 'Beef Pilau',
            'sku' => 'MENU-PILAU',
            'menu_category_uuid' => '{{menu_category_uuid}}',
            'tax_code_uuid' => '{{tax_code_uuid}}',
            'description' => 'Beef pilau served with salad.',
            'allows_open_price' => false,
            'is_combo' => false,
            'is_active' => true,
            'branches' => [[
                'branch_uuid' => '{{branch_uuid}}',
                'kitchen_section_uuid' => '{{kitchen_section_uuid}}',
                'price' => '15000.00',
                'cost_price' => '6500.00',
                'preparation_minutes' => 20,
                'is_available' => true,
                'is_visible' => true,
                'sort_order' => 1,
            ]],
        ]),
        'tests' => $captureUuid('menu_item_uuid'),
    ]),
    requestItem('Update menu item', 'PATCH', 'menu-items/{{menu_item_uuid}}', [
        'body' => jsonBody(['name' => 'Beef Pilau', 'description' => 'Beef pilau served with salad.', 'is_active' => true]),
    ]),
    requestItem('Upload menu item image', 'POST', 'menu-items/{{menu_item_uuid}}/image', [
        'body' => ['mode' => 'formdata', 'formdata' => [[
            'key' => 'image', 'type' => 'file', 'src' => '{{menu_image_path}}',
        ]]],
    ]),
    requestItem('Export menu items CSV', 'GET', 'menu-items/export', [
        'query' => ['branch_uuid' => '{{branch_uuid}}'],
        'description' => 'Returns a CSV file rather than JSON.',
    ]),
    requestItem('Import menu items CSV', 'POST', 'menu-items/import', [
        'body' => ['mode' => 'formdata', 'formdata' => [
            ['key' => 'file', 'type' => 'file', 'src' => '{{menu_csv_path}}'],
            ['key' => 'branch_uuid', 'type' => 'text', 'value' => '{{branch_uuid}}'],
        ]],
    ]),
    requestItem('Get recipe', 'GET', 'menu-items/{{menu_item_uuid}}/recipe'),
    requestItem('Replace recipe', 'PUT', 'menu-items/{{menu_item_uuid}}/recipe', [
        'body' => jsonBody([
            'name' => 'Beef Pilau Recipe',
            'yield_quantity' => '1.0000',
            'yield_unit_uuid' => '{{unit_uuid}}',
            'ingredients' => [[
                'ingredient_uuid' => '{{ingredient_uuid}}',
                'unit_uuid' => '{{unit_uuid}}',
                'quantity' => '0.2500',
                'waste_percentage' => '2.00',
                'notes' => null,
                'sort_order' => 1,
            ]],
        ]),
    ]),
    requestItem('Get combo definition', 'GET', 'menu-items/{{menu_item_uuid}}/combo'),
    requestItem('Replace combo definition', 'PUT', 'menu-items/{{menu_item_uuid}}/combo', [
        'body' => jsonBody([
            'is_active' => true,
            'items' => [[
                'component_menu_item_uuid' => '{{component_menu_item_uuid}}',
                'quantity' => 1,
                'is_required' => true,
                'sort_order' => 1,
            ]],
        ]),
    ]),
    requestItem('Replace menu item modifier groups', 'PUT', 'menu-items/{{menu_item_uuid}}/modifier-groups', [
        'body' => jsonBody(['modifier_groups' => [[
            'modifier_group_uuid' => '{{modifier_group_uuid}}',
            'sort_order' => 1,
            'is_active' => true,
        ]]]),
    ]),
]);

$modifiers = folder('Modifiers and Branch Menu', [
    requestItem('List modifier groups', 'GET', 'modifier-groups', ['query' => ['per_page' => '{{per_page}}']]),
    requestItem('Create modifier group', 'POST', 'modifier-groups', [
        'body' => jsonBody([
            'name' => 'Extra Sides',
            'description' => 'Optional side dishes.',
            'min_select' => 0,
            'max_select' => 2,
            'is_required' => false,
            'status' => 'active',
            'options' => [[
                'name' => 'Extra Salad',
                'tax_code_uuid' => '{{tax_code_uuid}}',
                'price_delta' => '2000.00',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 1,
            ]],
        ]),
        'tests' => $captureUuid('modifier_group_uuid'),
    ]),
    requestItem('Update modifier group', 'PATCH', 'modifier-groups/{{modifier_group_uuid}}', [
        'body' => jsonBody(['name' => 'Extra Sides', 'min_select' => 0, 'max_select' => 2, 'is_required' => false, 'status' => 'active']),
    ]),
    requestItem('Replace branch menu', 'PUT', 'branches/{{branch_uuid}}/menu-items', [
        'body' => jsonBody(['items' => [[
            'menu_item_uuid' => '{{menu_item_uuid}}',
            'kitchen_section_uuid' => '{{kitchen_section_uuid}}',
            'price' => '15000.00',
            'cost_price' => '6500.00',
            'preparation_minutes' => 20,
            'is_available' => true,
            'is_visible' => true,
            'sort_order' => 1,
        ]]]),
    ]),
    requestItem('Update branch menu availability', 'PATCH', 'branches/{{branch_uuid}}/menu-items/availability', [
        'body' => jsonBody([
            'items' => [['branch_menu_item_uuid' => '{{branch_menu_item_uuid}}', 'is_available' => true]],
            'reason' => 'Item is back in stock.',
        ]),
    ]),
]);

$menu = folder('06 - Menu and Catalog', [$catalog, $menuItems, $modifiers]);

$inventory = folder('07 - Inventory', [
    requestItem('List warehouses', 'GET', 'inventory/warehouses', ['query' => ['branch_uuid' => '{{branch_uuid}}', 'per_page' => '{{per_page}}']]),
    requestItem('Create warehouse', 'POST', 'inventory/warehouses', [
        'body' => jsonBody(['branch_uuid' => '{{branch_uuid}}', 'name' => 'Main Store', 'type' => 'main', 'description' => 'Main branch stock store.', 'status' => 'active']),
        'tests' => $captureUuid('warehouse_uuid'),
    ]),
    requestItem('Update warehouse', 'PATCH', 'inventory/warehouses/{{warehouse_uuid}}', [
        'body' => jsonBody(['name' => 'Main Store', 'type' => 'main', 'status' => 'active']),
    ]),
    requestItem('Get stock summary', 'GET', 'inventory/stock-summary', ['query' => ['branch_uuid' => '{{branch_uuid}}', 'warehouse_uuid' => '{{warehouse_uuid}}', 'per_page' => '{{per_page}}']]),
    requestItem('List stock batches', 'GET', 'inventory/stock-batches', ['query' => ['branch_uuid' => '{{branch_uuid}}', 'warehouse_uuid' => '{{warehouse_uuid}}', 'per_page' => '{{per_page}}']]),
    requestItem('List stock movements', 'GET', 'inventory/stock-movements', ['query' => ['branch_uuid' => '{{branch_uuid}}', 'warehouse_uuid' => '{{warehouse_uuid}}', 'per_page' => '{{per_page}}']]),
    requestItem('List stock adjustments', 'GET', 'inventory/stock-adjustments', ['query' => ['branch_uuid' => '{{branch_uuid}}', 'warehouse_uuid' => '{{warehouse_uuid}}', 'per_page' => '{{per_page}}']]),
    requestItem('Create stock adjustment', 'POST', 'inventory/stock-adjustments', [
        'body' => jsonBody([
            'branch_uuid' => '{{branch_uuid}}',
            'warehouse_uuid' => '{{warehouse_uuid}}',
            'reason_type' => 'manual_correction',
            'adjusted_on' => '2026-09-02',
            'notes' => 'Opening inventory adjustment.',
            'items' => [[
                'ingredient_uuid' => '{{ingredient_uuid}}',
                'location_uuid' => null,
                'unit_uuid' => '{{unit_uuid}}',
                'quantity_delta' => '10.0000',
                'unit_cost' => '2500.00',
                'reason' => 'Opening balance.',
            ]],
        ]),
        'tests' => $captureUuid('stock_adjustment_uuid'),
    ]),
    requestItem('Get stock adjustment', 'GET', 'inventory/stock-adjustments/{{stock_adjustment_uuid}}'),
    requestItem('Update stock adjustment', 'PATCH', 'inventory/stock-adjustments/{{stock_adjustment_uuid}}', [
        'body' => jsonBody(['notes' => 'Updated opening inventory adjustment.']),
    ]),
    requestItem('Post stock adjustment', 'POST', 'inventory/stock-adjustments/{{stock_adjustment_uuid}}/post'),
    requestItem('List stock transfers', 'GET', 'inventory/stock-transfers', ['query' => ['branch_uuid' => '{{branch_uuid}}', 'direction' => 'outgoing', 'per_page' => '{{per_page}}']]),
    requestItem('Create stock transfer', 'POST', 'inventory/stock-transfers', [
        'body' => jsonBody([
            'from_branch_uuid' => '{{branch_uuid}}',
            'from_warehouse_uuid' => '{{warehouse_uuid}}',
            'to_branch_uuid' => '{{to_branch_uuid}}',
            'to_warehouse_uuid' => '{{to_warehouse_uuid}}',
            'requested_on' => '2026-09-02',
            'notes' => 'Restock destination branch.',
            'items' => [[
                'ingredient_uuid' => '{{ingredient_uuid}}',
                'unit_uuid' => '{{unit_uuid}}',
                'quantity_requested' => '5.0000',
            ]],
        ]),
        'tests' => $captureUuid('stock_transfer_uuid'),
    ]),
    requestItem('Get stock transfer', 'GET', 'inventory/stock-transfers/{{stock_transfer_uuid}}'),
    requestItem('Update stock transfer', 'PATCH', 'inventory/stock-transfers/{{stock_transfer_uuid}}', [
        'body' => jsonBody(['requested_on' => '2026-09-02', 'notes' => 'Updated transfer request.']),
    ]),
    requestItem('Dispatch stock transfer', 'POST', 'inventory/stock-transfers/{{stock_transfer_uuid}}/dispatch'),
    requestItem('Receive stock transfer', 'POST', 'inventory/stock-transfers/{{stock_transfer_uuid}}/receive'),
    requestItem('List stock counts', 'GET', 'inventory/stock-counts', ['query' => ['branch_uuid' => '{{branch_uuid}}', 'warehouse_uuid' => '{{warehouse_uuid}}', 'per_page' => '{{per_page}}']]),
    requestItem('Create stock count', 'POST', 'inventory/stock-counts', [
        'body' => jsonBody([
            'branch_uuid' => '{{branch_uuid}}',
            'warehouse_uuid' => '{{warehouse_uuid}}',
            'counted_on' => '2026-09-02',
            'notes' => 'Routine count.',
            'items' => [[
                'ingredient_uuid' => '{{ingredient_uuid}}',
                'location_uuid' => null,
                'unit_uuid' => '{{unit_uuid}}',
                'counted_quantity' => '10.0000',
            ]],
        ]),
        'tests' => $captureUuid('stock_count_uuid'),
    ]),
    requestItem('Get stock count', 'GET', 'inventory/stock-counts/{{stock_count_uuid}}'),
    requestItem('Update stock count', 'PATCH', 'inventory/stock-counts/{{stock_count_uuid}}', [
        'body' => jsonBody(['notes' => 'Updated routine count.']),
    ]),
    requestItem('Post stock count', 'POST', 'inventory/stock-counts/{{stock_count_uuid}}/post'),
]);

$collection = [
    'info' => [
        '_postman_id' => '8f476c61-5399-4dca-bd4c-05f16c83bf75',
        'name' => 'RMIS Restaurant POS ERP - API v1',
        'description' => "Version-controlled Postman collection generated from the implemented Laravel API. Import RMIS-local.postman_environment.json, select it, then run Login with email/phone and password. Login resolves a single active tenant automatically. All protected requests send the returned X-Tenant-UUID and Bearer access_token.",
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'auth' => [
        'type' => 'bearer',
        'bearer' => [['key' => 'token', 'value' => '{{access_token}}', 'type' => 'string']],
    ],
    'item' => [$auth, $me, $foundation, $access, $organisation, $menu, $inventory],
    'event' => [[
        'listen' => 'prerequest',
        'script' => [
            'type' => 'text/javascript',
            'exec' => [
                "if (!pm.environment.get('base_url')) { throw new Error('Select the RMIS local environment before sending requests.'); }",
            ],
        ],
    ]],
];

$environmentValues = [
    ['key' => 'base_url', 'value' => 'http://localhost:8000', 'enabled' => true, 'type' => 'default'],
    ['key' => 'tenant_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'access_token', 'value' => '', 'enabled' => true, 'type' => 'secret'],
    ['key' => 'login', 'value' => 'owner@example.test', 'enabled' => true, 'type' => 'default'],
    ['key' => 'password', 'value' => '', 'enabled' => true, 'type' => 'secret'],
    ['key' => 'new_password', 'value' => '', 'enabled' => true, 'type' => 'secret'],
    ['key' => 'otp', 'value' => '', 'enabled' => true, 'type' => 'secret'],
    ['key' => 'per_page', 'value' => '15', 'enabled' => true, 'type' => 'default'],
    ['key' => 'provisioning_run_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'branch_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'to_branch_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'business_day_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'role_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'permission_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'tenant_user_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'department_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'employment_type_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'job_position_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'unit_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'tax_code_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'menu_category_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'ingredient_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'kitchen_section_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'menu_item_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'component_menu_item_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'modifier_group_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'branch_menu_item_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'warehouse_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'to_warehouse_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'location_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'movement_type_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'stock_adjustment_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'stock_transfer_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'stock_count_uuid', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'menu_image_path', 'value' => '', 'enabled' => true, 'type' => 'default'],
    ['key' => 'menu_csv_path', 'value' => '', 'enabled' => true, 'type' => 'default'],
];

$environment = [
    'id' => '09fb65da-3eae-4820-9586-8fbf48c62059',
    'name' => 'RMIS Local',
    'values' => $environmentValues,
    '_postman_variable_scope' => 'environment',
    '_postman_exported_at' => gmdate('Y-m-d\TH:i:s.000\Z'),
    '_postman_exported_using' => 'RMIS postman/build-collection.php',
];

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

file_put_contents($collectionPath, json_encode($collection, $flags).PHP_EOL);
file_put_contents($environmentPath, json_encode($environment, $flags).PHP_EOL);

echo "Created {$collectionPath}".PHP_EOL;
echo "Created {$environmentPath}".PHP_EOL;
