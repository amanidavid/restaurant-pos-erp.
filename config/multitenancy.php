<?php

use App\Models\Landlord\Tenant;
use App\Services\Landlord\Tenancy\SwitchTenantDatabaseConnectionTask;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\CallQueuedClosure;
use Spatie\Multitenancy\Actions\ForgetCurrentTenantAction;
use Spatie\Multitenancy\Actions\MakeQueueTenantAwareAction;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;
use Spatie\Multitenancy\Actions\MigrateTenantAction;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Spatie\Multitenancy\Jobs\TenantAware;

return [
    'tenant_finder' => null,
    // Tenant commands use the public UUID only. Including the bigint primary key
    // makes PostgreSQL attempt to cast a UUID value to bigint during an OR query.
    'tenant_artisan_search_fields' => ['uuid'],
    'switch_tenant_tasks' => [
        SwitchTenantDatabaseConnectionTask::class,
    ],
    'tenant_model' => Tenant::class,
    'queues_are_tenant_aware_by_default' => true,
    'tenant_database_connection_name' => 'tenant',
    'landlord_database_connection_name' => 'landlord',
    'current_tenant_context_key' => 'tenantUuid',
    'current_tenant_container_key' => 'currentTenant',
    'shared_routes_cache' => false,
    'actions' => [
        'make_tenant_current_action' => MakeTenantCurrentAction::class,
        'forget_current_tenant_action' => ForgetCurrentTenantAction::class,
        'make_queue_tenant_aware_action' => MakeQueueTenantAwareAction::class,
        'migrate_tenant' => MigrateTenantAction::class,
    ],
    'queueable_to_job' => [
        SendQueuedMailable::class => 'mailable',
        SendQueuedNotifications::class => 'notification',
        CallQueuedClosure::class => 'class',
        CallQueuedListener::class => 'class',
        BroadcastEvent::class => 'event',
    ],
    'tenant_aware_interface' => TenantAware::class,
    'not_tenant_aware_interface' => NotTenantAware::class,
    'tenant_aware_jobs' => [],
    'not_tenant_aware_jobs' => [],
];
