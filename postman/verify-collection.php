<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$collectionFile = __DIR__.'/RMIS-v1.postman_collection.json';

if (! is_file($collectionFile)) {
    fwrite(STDERR, "Collection not found. Run php postman/build-collection.php first.".PHP_EOL);
    exit(1);
}

$collection = json_decode((string) file_get_contents($collectionFile), true, 512, JSON_THROW_ON_ERROR);
$postmanRoutes = [];

$walk = function (array $items) use (&$walk, &$postmanRoutes): void {
    foreach ($items as $item) {
        if (isset($item['item'])) {
            $walk($item['item']);
            continue;
        }

        if (! isset($item['request']['method'], $item['request']['url']['raw'])) {
            continue;
        }

        $method = strtoupper($item['request']['method']);
        $raw = preg_replace('/\?.*$/', '', $item['request']['url']['raw']);
        $path = preg_replace('#^\{\{base_url\}\}/#', '', (string) $raw);
        $path = preg_replace('/\{\{([a-z0-9_]+)\}\}/i', '{$1}', (string) $path);
        $postmanRoutes[] = $method.' '.$path;
    }
};

$walk($collection['item'] ?? []);
sort($postmanRoutes);

$command = escapeshellarg(PHP_BINARY).' artisan route:list --path=api/v1/web --json';
$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "Laravel route:list failed.".PHP_EOL);
    exit(1);
}

$laravelRoutes = [];
foreach (json_decode(implode(PHP_EOL, $output), true, 512, JSON_THROW_ON_ERROR) as $route) {
    $methods = explode('|', $route['method']);
    $method = in_array('PATCH', $methods, true) ? 'PATCH' : $methods[0];
    $uri = preg_replace('/\{([A-Za-z][A-Za-z0-9]*)\}/', '{${1}_uuid}', $route['uri']);

    // Postman variable names are explicit when Laravel uses semantic bindings.
    $replacements = [
        '{tenant_uuid}' => '{tenant_uuid}',
        '{branch_uuid}' => '{branch_uuid}',
        '{businessDay_uuid}' => '{business_day_uuid}',
        '{role_uuid}' => '{role_uuid}',
        '{tenantUser_uuid}' => '{tenant_user_uuid}',
        '{department_uuid}' => '{department_uuid}',
        '{employmentType_uuid}' => '{employment_type_uuid}',
        '{jobPosition_uuid}' => '{job_position_uuid}',
        '{unit_uuid}' => '{unit_uuid}',
        '{taxCode_uuid}' => '{tax_code_uuid}',
        '{menuCategory_uuid}' => '{menu_category_uuid}',
        '{ingredient_uuid}' => '{ingredient_uuid}',
        '{kitchenSection_uuid}' => '{kitchen_section_uuid}',
        '{menuItem_uuid}' => '{menu_item_uuid}',
        '{modifierGroup_uuid}' => '{modifier_group_uuid}',
        '{warehouse_uuid}' => '{warehouse_uuid}',
        '{stockAdjustment_uuid}' => '{stock_adjustment_uuid}',
        '{stockTransfer_uuid}' => '{stock_transfer_uuid}',
        '{stockCount_uuid}' => '{stock_count_uuid}',
        '{order_uuid}' => '{order_uuid}',
        '{orderItem_uuid}' => '{order_item_uuid}',
        '{kitchenTicket_uuid}' => '{kitchen_ticket_uuid}',
        '{kitchenTicketItem_uuid}' => '{kitchen_ticket_item_uuid}',
        '{receipt_uuid}' => '{receipt_uuid}',
        '{printJob_uuid}' => '{print_job_uuid}',
    ];

    $laravelRoutes[] = $method.' '.strtr($uri, $replacements);
}
sort($laravelRoutes);

$missing = array_values(array_diff($laravelRoutes, $postmanRoutes));
$extra = array_values(array_diff($postmanRoutes, $laravelRoutes));
$duplicates = array_keys(array_filter(array_count_values($postmanRoutes), fn (int $count): bool => $count > 1));

echo 'Laravel routes: '.count($laravelRoutes).PHP_EOL;
echo 'Postman requests: '.count($postmanRoutes).PHP_EOL;

if ($missing !== []) {
    echo PHP_EOL."Missing from Postman:".PHP_EOL.'- '.implode(PHP_EOL.'- ', $missing).PHP_EOL;
}

if ($extra !== []) {
    echo PHP_EOL."Not registered in Laravel:".PHP_EOL.'- '.implode(PHP_EOL.'- ', $extra).PHP_EOL;
}

if ($duplicates !== []) {
    echo PHP_EOL."Duplicate Postman requests:".PHP_EOL.'- '.implode(PHP_EOL.'- ', $duplicates).PHP_EOL;
}

if ($missing !== [] || $extra !== [] || $duplicates !== []) {
    exit(1);
}

echo 'Collection coverage verified successfully.'.PHP_EOL;
