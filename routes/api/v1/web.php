<?php

use App\Http\Controllers\Web\Access\RoleController;
use App\Http\Controllers\Web\Access\TenantUserController;
use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\LogoutController;
use App\Http\Controllers\Web\Auth\LogoutOtherDeviceController;
use App\Http\Controllers\Web\Auth\RegisterTenantController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use App\Http\Controllers\Web\Auth\TenantRegistrationStatusController;
use App\Http\Controllers\Web\Auth\VerifyPasswordResetOtpController;
use App\Http\Controllers\Web\Customers\CustomerController;
use App\Http\Controllers\Web\Finance\CashAccountHistoryController;
use App\Http\Controllers\Web\Finance\CashReconciliationController;
use App\Http\Controllers\Web\Foundation\BranchConfigurationController;
use App\Http\Controllers\Web\Foundation\BranchController;
use App\Http\Controllers\Web\Foundation\BusinessDateController;
use App\Http\Controllers\Web\Foundation\BusinessDayHistoryController;
use App\Http\Controllers\Web\Foundation\CompanyProfileController;
use App\Http\Controllers\Web\Hr\TimekeepingController;
use App\Http\Controllers\Web\Inventory\InventoryController;
use App\Http\Controllers\Web\Kitchen\KitchenTicketController;
use App\Http\Controllers\Web\Me\CurrentUserController;
use App\Http\Controllers\Web\Me\CurrentUserPermissionsController;
use App\Http\Controllers\Web\Menu\CatalogMasterController;
use App\Http\Controllers\Web\Menu\ComboProductController;
use App\Http\Controllers\Web\Menu\MenuConfigurationController;
use App\Http\Controllers\Web\Menu\MenuImportExportController;
use App\Http\Controllers\Web\Menu\MenuItemController;
use App\Http\Controllers\Web\Menu\MenuItemImageController;
use App\Http\Controllers\Web\Organisation\OrganisationController;
use App\Http\Controllers\Web\Organisation\StaffProfileController;
use App\Http\Controllers\Web\Pos\Counter\CounterSetupController;
use App\Http\Controllers\Web\Pos\Dining\DiningFloorPlanController;
use App\Http\Controllers\Web\Pos\OrderController;
use App\Http\Controllers\Web\Pos\PaymentController;
use App\Http\Controllers\Web\Pos\PosReferenceController;
use App\Http\Controllers\Web\Pos\PrintJobController;
use App\Http\Controllers\Web\Pos\ReceiptController;
use App\Http\Controllers\Web\Reservations\ReservationController;
use Illuminate\Support\Facades\Route;

Route::prefix('web')
    ->name('web.')
    ->group(function (): void {
        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                Route::post('register', RegisterTenantController::class)
                    ->middleware('throttle:5,1')
                    ->name('register');

                Route::post('login', LoginController::class)
                    ->middleware('throttle:10,1')
                    ->name('login');

                Route::post('logout-other-device', LogoutOtherDeviceController::class)
                    ->middleware('throttle:5,1')
                    ->name('logout-other-device');

                Route::post('forgot-password', ForgotPasswordController::class)
                    ->middleware('throttle:5,1')
                    ->name('forgot-password');

                Route::post('verify-otp', VerifyPasswordResetOtpController::class)
                    ->middleware('throttle:10,1')
                    ->name('verify-otp');

                Route::post('reset-password', ResetPasswordController::class)
                    ->middleware('throttle:5,1')
                    ->name('reset-password');

                Route::get('registration-status/{tenant}', TenantRegistrationStatusController::class)
                    ->middleware('throttle:30,1')
                    ->name('registration-status');
            });

        Route::middleware(['tenant.auth', 'tenant.session'])
            ->group(function (): void {
                Route::get('me', CurrentUserController::class)
                    ->name('me');

                Route::get('me/permissions', CurrentUserPermissionsController::class)
                    ->name('me.permissions');

                Route::post('auth/logout', LogoutController::class)
                    ->name('auth.logout');

                Route::get('sales-channels', [PosReferenceController::class, 'salesChannels'])->name('pos.sales-channels.index');
                Route::get('service-types', [PosReferenceController::class, 'serviceTypes'])->name('pos.service-types.index');
                Route::get('payment-methods', [PosReferenceController::class, 'paymentMethods'])->name('pos.payment-methods.index');
                Route::get('counters', [PosReferenceController::class, 'counters'])->name('pos.counters.index');
                Route::post('counters', [CounterSetupController::class, 'store'])->name('pos.counters.store');
                Route::patch('counters/{counter}', [CounterSetupController::class, 'update'])->name('pos.counters.update');
                Route::middleware('tenant.branch')->group(function (): void {
                    Route::get('me/shifts', [TimekeepingController::class, 'myShifts'])->name('me.shifts.index');
                    Route::get('dining-areas', [PosReferenceController::class, 'diningAreas'])->name('pos.dining-areas.index');
                    Route::post('dining-areas', [DiningFloorPlanController::class, 'store'])->name('pos.dining-areas.store');
                    Route::get('dining-areas/{diningArea}', [DiningFloorPlanController::class, 'showArea'])->name('pos.dining-areas.show');
                    Route::patch('dining-areas/{diningArea}', [DiningFloorPlanController::class, 'updateArea'])->name('pos.dining-areas.update');
                    Route::get('dining-tables', [PosReferenceController::class, 'diningTables'])->name('pos.dining-tables.index');
                    Route::post('dining-tables', [DiningFloorPlanController::class, 'storeTable'])->name('pos.dining-tables.store');
                    Route::get('dining-tables/{diningTable}', [DiningFloorPlanController::class, 'showTable'])->name('pos.dining-tables.show');
                    Route::patch('dining-tables/{diningTable}', [DiningFloorPlanController::class, 'updateTable'])->name('pos.dining-tables.update');
                });
                Route::apiResource('customers', CustomerController::class)->only(['index', 'store', 'show', 'update']);
                Route::get('orders', [OrderController::class, 'index'])->name('pos.orders.index');
                Route::post('orders', [OrderController::class, 'store'])->name('pos.orders.store');
                Route::get('orders/{order}', [OrderController::class, 'show'])->name('pos.orders.show');
                Route::patch('orders/{order}', [OrderController::class, 'update'])->name('pos.orders.update');
                Route::post('orders/{order}/items', [OrderController::class, 'addItems'])->name('pos.orders.items.add');
                Route::patch('orders/{order}/items/{orderItem}', [OrderController::class, 'updateItem'])->name('pos.orders.items.update');
                Route::post('orders/{order}/items/{orderItem}/void', [OrderController::class, 'voidItem'])->name('pos.orders.items.void');
                Route::post('orders/{order}/items/{orderItem}/serve', [OrderController::class, 'serveItem'])->name('pos.orders.items.serve');
                Route::post('orders/{order}/send-to-kitchen', [OrderController::class, 'sendToKitchen'])->name('pos.orders.send-to-kitchen');
                Route::get('orders/{order}/payments', [PaymentController::class, 'index'])->name('pos.orders.payments.index');
                Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->name('pos.orders.payments.store');
                Route::post('orders/{order}/receipts', [ReceiptController::class, 'store'])->name('pos.orders.receipts.store');
                Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('pos.receipts.show');
                Route::post('receipts/{receipt}/void', [ReceiptController::class, 'void'])->name('pos.receipts.void');
                Route::post('receipts/{receipt}/print', [PrintJobController::class, 'receipt'])->name('pos.receipts.print');
                Route::get('print-jobs/{printJob}', [PrintJobController::class, 'show'])->name('pos.print-jobs.show');
                Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('pos.orders.cancel');
                Route::post('orders/{order}/serve', [OrderController::class, 'serve'])->name('pos.orders.serve');
                Route::post('orders/{order}/close', [OrderController::class, 'close'])->name('pos.orders.close');

                Route::get('reservations', [ReservationController::class, 'index'])->name('reservations.index');
                Route::post('reservations', [ReservationController::class, 'store'])->name('reservations.store');
                Route::get('reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
                Route::post('reservations/{reservation}/seat', [ReservationController::class, 'seat'])->name('reservations.seat');
                Route::post('reservations/{reservation}/complete', [ReservationController::class, 'complete'])->name('reservations.complete');
                Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
                Route::post('reservations/{reservation}/no-show', [ReservationController::class, 'noShow'])->name('reservations.no-show');

                Route::get('kitchen-tickets', [KitchenTicketController::class, 'index'])->name('kitchen.tickets.index');
                Route::get('kitchen-tickets/{kitchenTicket}', [KitchenTicketController::class, 'show'])->name('kitchen.tickets.show');
                Route::post('kitchen-tickets/{kitchenTicket}/items/{kitchenTicketItem}/start', [KitchenTicketController::class, 'start'])->name('kitchen.tickets.items.start');
                Route::post('kitchen-tickets/{kitchenTicket}/items/{kitchenTicketItem}/ready', [KitchenTicketController::class, 'ready'])->name('kitchen.tickets.items.ready');

                Route::get('company', [CompanyProfileController::class, 'show'])
                    ->name('company.show');

                Route::patch('company', [CompanyProfileController::class, 'update'])
                    ->name('company.update');

                Route::get('branches/{branch}/business-day/current', [BusinessDateController::class, 'current'])
                    ->name('branches.business-day.current');

                Route::get('branches/{branch}/business-days', [BusinessDayHistoryController::class, 'index'])
                    ->middleware(['tenant.branch', 'throttle:60,1'])->name('branches.business-days.index');

                Route::get('cash-accounts/{cashAccount}/reconciliations', [CashAccountHistoryController::class, 'index'])
                    ->middleware(['tenant.branch', 'throttle:60,1'])->name('cash-accounts.reconciliations.index');

                Route::get('cash-reconciliations/{reconciliation}/movements', [CashAccountHistoryController::class, 'movements'])
                    ->middleware(['tenant.branch', 'throttle:60,1'])->name('cash-reconciliations.movements.index');

                Route::post('branches/{branch}/business-days/open', [BusinessDateController::class, 'open'])
                    ->name('branches.business-days.open');

                Route::post('branches/{branch}/business-days/{businessDay}/close', [BusinessDateController::class, 'close'])
                    ->name('branches.business-days.close');

                Route::middleware('tenant.branch')->group(function (): void {
                    Route::post('business-days/{businessDay}/cash-reconciliations', [CashReconciliationController::class, 'open'])->name('cash-reconciliations.open');
                    Route::get('business-days/{businessDay}/cash-reconciliations', [CashReconciliationController::class, 'index'])->name('cash-reconciliations.index');
                    Route::get('cash-reconciliations/{reconciliation}', [CashReconciliationController::class, 'show'])->name('cash-reconciliations.show');
                    Route::put('cash-reconciliations/{reconciliation}/count', [CashReconciliationController::class, 'count'])->name('cash-reconciliations.count');
                    Route::post('cash-reconciliations/{reconciliation}/close', [CashReconciliationController::class, 'close'])->name('cash-reconciliations.close');
                    Route::post('cash-accounts/{cashAccount}/movements', [CashReconciliationController::class, 'movement'])->name('cash-movements.store');
                });

                Route::apiResource('branches', BranchController::class)
                    ->only(['index', 'store', 'show', 'update']);

                Route::get('branches/{branch}/configuration', [BranchConfigurationController::class, 'show'])
                    ->name('branches.configuration.show');

                Route::patch('branches/{branch}/configuration', [BranchConfigurationController::class, 'update'])
                    ->name('branches.configuration.update');

                Route::prefix('inventory')
                    ->name('inventory.')
                    ->group(function (): void {
                        Route::get('warehouses', [InventoryController::class, 'warehouses'])->name('warehouses.index');
                        Route::post('warehouses', [InventoryController::class, 'storeWarehouse'])->name('warehouses.store');
                        Route::get('warehouses/{warehouse}', [InventoryController::class, 'showWarehouse'])->name('warehouses.show');
                        Route::patch('warehouses/{warehouse}', [InventoryController::class, 'updateWarehouse'])->name('warehouses.update');
                        Route::get('stock-summary', [InventoryController::class, 'summary'])->name('stock-summary.index');
                        Route::get('stock-batches', [InventoryController::class, 'batches'])->name('stock-batches.index');
                        Route::get('stock-movements', [InventoryController::class, 'movements'])->name('stock-movements.index');
                        Route::get('stock-adjustments', [InventoryController::class, 'adjustments'])->name('stock-adjustments.index');
                        Route::post('stock-adjustments', [InventoryController::class, 'storeAdjustment'])->name('stock-adjustments.store');
                        Route::get('stock-adjustments/{stockAdjustment}', [InventoryController::class, 'showAdjustment'])->name('stock-adjustments.show');
                        Route::patch('stock-adjustments/{stockAdjustment}', [InventoryController::class, 'updateAdjustment'])->name('stock-adjustments.update');
                        Route::post('stock-adjustments/{stockAdjustment}/post', [InventoryController::class, 'postAdjustment'])->name('stock-adjustments.post');
                        Route::get('stock-transfers', [InventoryController::class, 'transfers'])->name('stock-transfers.index');
                        Route::post('stock-transfers', [InventoryController::class, 'storeTransfer'])->name('stock-transfers.store');
                        Route::get('stock-transfers/{stockTransfer}', [InventoryController::class, 'showTransfer'])->name('stock-transfers.show');
                        Route::patch('stock-transfers/{stockTransfer}', [InventoryController::class, 'updateTransfer'])->name('stock-transfers.update');
                        Route::post('stock-transfers/{stockTransfer}/dispatch', [InventoryController::class, 'dispatchTransfer'])->name('stock-transfers.dispatch');
                        Route::post('stock-transfers/{stockTransfer}/receive', [InventoryController::class, 'receiveTransfer'])->name('stock-transfers.receive');
                        Route::get('stock-counts', [InventoryController::class, 'counts'])->name('stock-counts.index');
                        Route::post('stock-counts', [InventoryController::class, 'storeCount'])->name('stock-counts.store');
                        Route::get('stock-counts/{stockCount}', [InventoryController::class, 'showCount'])->name('stock-counts.show');
                        Route::patch('stock-counts/{stockCount}', [InventoryController::class, 'updateCount'])->name('stock-counts.update');
                        Route::post('stock-counts/{stockCount}/post', [InventoryController::class, 'postCount'])->name('stock-counts.post');
                    });

                Route::get('access/permissions', [RoleController::class, 'permissions'])
                    ->name('access.permissions.index');

                Route::get('access/roles', [RoleController::class, 'index'])
                    ->name('access.roles.index');

                Route::post('access/roles', [RoleController::class, 'store'])
                    ->name('access.roles.store');

                Route::patch('access/roles/{role}', [RoleController::class, 'update'])
                    ->name('access.roles.update');

                Route::delete('access/roles/{role}', [RoleController::class, 'destroy'])
                    ->name('access.roles.destroy');

                Route::put('access/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])
                    ->name('access.roles.permissions.sync');

                Route::get('access/users', [TenantUserController::class, 'index'])
                    ->name('access.users.index');

                Route::post('access/users', [TenantUserController::class, 'store'])
                    ->name('access.users.store');

                Route::get('access/users/{tenantUser}', [TenantUserController::class, 'show'])
                    ->name('access.users.show');

                Route::patch('access/users/{tenantUser}/status', [TenantUserController::class, 'updateStatus'])
                    ->name('access.users.status.update');

                Route::put('access/users/{tenantUser}/branches', [TenantUserController::class, 'replaceBranchAccess'])
                    ->name('access.users.branches.replace');

                Route::put('access/users/{tenantUser}/branches/{branch}/roles', [TenantUserController::class, 'syncRoles'])
                    ->name('access.users.branch-roles.sync');

                Route::put('access/users/{tenantUser}/branches/{branch}/permissions', [TenantUserController::class, 'syncPermissions'])
                    ->name('access.users.branch-permissions.sync');

                Route::middleware('tenant.branch')->group(function (): void {
                    Route::get('organisation/departments', [OrganisationController::class, 'departments'])->name('organisation.departments.index');
                    Route::get('organisation/departments/tree', [OrganisationController::class, 'departmentTree'])->name('organisation.departments.tree');
                    Route::post('organisation/departments', [OrganisationController::class, 'storeDepartment'])->name('organisation.departments.store');
                    Route::get('organisation/departments/{department}', [OrganisationController::class, 'showDepartment'])->name('organisation.departments.show');
                    Route::patch('organisation/departments/{department}', [OrganisationController::class, 'updateDepartment'])->name('organisation.departments.update');

                    Route::get('organisation/employment-types', [OrganisationController::class, 'employmentTypes'])->name('organisation.employment-types.index');
                    Route::post('organisation/employment-types', [OrganisationController::class, 'storeEmploymentType'])->name('organisation.employment-types.store');
                    Route::patch('organisation/employment-types/{employmentType}', [OrganisationController::class, 'updateEmploymentType'])->name('organisation.employment-types.update');

                    Route::get('organisation/job-positions', [OrganisationController::class, 'jobPositions'])->name('organisation.job-positions.index');
                    Route::post('organisation/job-positions', [OrganisationController::class, 'storeJobPosition'])->name('organisation.job-positions.store');
                    Route::patch('organisation/job-positions/{jobPosition}', [OrganisationController::class, 'updateJobPosition'])->name('organisation.job-positions.update');
                    Route::post('staff/users', [StaffProfileController::class, 'store'])->name('staff.users.store');
                    Route::get('staff/users/{tenantUser}/profile', [StaffProfileController::class, 'show'])->name('staff.users.profile.show');
                    Route::patch('staff/users/{tenantUser}/profile', [StaffProfileController::class, 'update'])->name('staff.users.profile.update');
                    Route::get('staff/users/{tenantUser}/job-history', [StaffProfileController::class, 'jobHistory'])->name('staff.users.job-history.index');
                    Route::post('staff/users/{tenantUser}/job-history', [StaffProfileController::class, 'storeJobHistory'])->name('staff.users.job-history.store');

                    Route::get('hr/shift-definitions', [TimekeepingController::class, 'shiftDefinitions'])->name('hr.shift-definitions.index');
                    Route::post('hr/shift-definitions', [TimekeepingController::class, 'storeShiftDefinition'])->name('hr.shift-definitions.store');
                    Route::patch('hr/shift-definitions/{shiftDefinition}', [TimekeepingController::class, 'updateShiftDefinition'])->name('hr.shift-definitions.update');

                    Route::get('hr/shift-assignments', [TimekeepingController::class, 'shiftAssignments'])->name('hr.shift-assignments.index');
                    Route::post('hr/shift-assignments', [TimekeepingController::class, 'storeShiftAssignment'])->name('hr.shift-assignments.store');
                    Route::post('hr/shift-assignments/batch', [TimekeepingController::class, 'storeBatchShiftAssignments'])->name('hr.shift-assignments.batch.store');
                    Route::patch('hr/shift-assignments/{shiftAssignment}', [TimekeepingController::class, 'updateShiftAssignment'])->name('hr.shift-assignments.update');

                    Route::post('hr/attendance/clock-in', [TimekeepingController::class, 'clockIn'])->middleware('throttle:10,1')->name('hr.attendance.clock-in');
                    Route::post('hr/attendance/clock-out', [TimekeepingController::class, 'clockOut'])->middleware('throttle:10,1')->name('hr.attendance.clock-out');
                    Route::post('hr/attendance/clock-in/batch', [TimekeepingController::class, 'batchClockIn'])->middleware('throttle:10,1')->name('hr.attendance.clock-in.batch');
                    Route::post('hr/attendance/clock-out/batch', [TimekeepingController::class, 'batchClockOut'])->middleware('throttle:10,1')->name('hr.attendance.clock-out.batch');
                    Route::get('hr/attendance/me', [TimekeepingController::class, 'myAttendance'])->name('hr.attendance.me');
                    Route::get('hr/attendance-records', [TimekeepingController::class, 'attendance'])->name('hr.attendance-records.index');
                    Route::post('hr/attendance-records/manual', [TimekeepingController::class, 'storeManualAttendance'])->name('hr.attendance-records.manual.store');
                    Route::patch('hr/attendance-records/{attendanceRecord}/adjust', [TimekeepingController::class, 'adjustAttendance'])->name('hr.attendance-records.adjust');
                });

                Route::get('menu-items', [MenuItemController::class, 'index'])->name('menu-items.index');
                Route::post('menu-items', [MenuItemController::class, 'store'])->name('menu-items.store');
                Route::get('menu-items/export', [MenuImportExportController::class, 'export'])->name('menu-items.export');
                Route::post('menu-items/import', [MenuImportExportController::class, 'import'])->name('menu-items.import');
                Route::get('branches/{branch}/menu-items', [MenuConfigurationController::class, 'branchMenuItems'])->name('branches.menu-items.index');
                Route::get('menu-items/{menuItem}', [MenuItemController::class, 'show'])->name('menu-items.show');
                Route::patch('menu-items/{menuItem}', [MenuItemController::class, 'update'])->name('menu-items.update');
                Route::post('menu-items/{menuItem}/image', [MenuItemImageController::class, 'store'])->name('menu-items.image.store');
                Route::get('menu-items/{menuItem}/combo', [ComboProductController::class, 'show'])->name('menu-items.combo.show');
                Route::put('menu-items/{menuItem}/combo', [ComboProductController::class, 'sync'])->name('menu-items.combo.sync');
                Route::get('menu-items/{menuItem}/recipe', [MenuItemController::class, 'recipe'])->name('menu-items.recipe.show');
                Route::put('menu-items/{menuItem}/recipe', [MenuItemController::class, 'syncRecipe'])->name('menu-items.recipe.sync');

                Route::prefix('units-of-measure')
                    ->name('menu.units.')
                    ->group(function (): void {
                        Route::get('/', [CatalogMasterController::class, 'units'])->name('index');
                        Route::post('/', [CatalogMasterController::class, 'storeUnit'])->name('store');
                        Route::patch('{unit}', [CatalogMasterController::class, 'updateUnit'])->name('update');
                    });

                Route::prefix('tax-codes')
                    ->name('menu.taxes.')
                    ->group(function (): void {
                        Route::get('/', [CatalogMasterController::class, 'taxes'])->name('index');
                        Route::post('/', [CatalogMasterController::class, 'storeTax'])->name('store');
                        Route::patch('{taxCode}', [CatalogMasterController::class, 'updateTax'])->name('update');
                    });

                Route::prefix('menu-categories')
                    ->name('menu.categories.')
                    ->group(function (): void {
                        Route::get('/', [CatalogMasterController::class, 'categories'])->name('index');
                        Route::post('/', [CatalogMasterController::class, 'storeCategory'])->name('store');
                        Route::patch('{menuCategory}', [CatalogMasterController::class, 'updateCategory'])->name('update');
                    });

                Route::prefix('ingredients')
                    ->name('menu.ingredients.')
                    ->group(function (): void {
                        Route::get('/', [CatalogMasterController::class, 'ingredients'])->name('index');
                        Route::post('/', [CatalogMasterController::class, 'storeIngredient'])->name('store');
                        Route::patch('{ingredient}', [CatalogMasterController::class, 'updateIngredient'])->name('update');
                    });

                Route::prefix('kitchen-sections')
                    ->name('menu.kitchen-sections.')
                    ->group(function (): void {
                        Route::get('/', [CatalogMasterController::class, 'kitchenSections'])->name('index');
                        Route::post('/', [CatalogMasterController::class, 'storeKitchenSection'])->name('store');
                        Route::patch('{kitchenSection}', [CatalogMasterController::class, 'updateKitchenSection'])->name('update');
                    });

                Route::prefix('modifier-groups')
                    ->name('menu.modifier-groups.')
                    ->group(function (): void {
                        Route::get('/', [MenuConfigurationController::class, 'modifierGroups'])->name('index');
                        Route::post('/', [MenuConfigurationController::class, 'storeModifierGroup'])->name('store');
                        Route::patch('{modifierGroup}', [MenuConfigurationController::class, 'updateModifierGroup'])->name('update');
                    });

                Route::put('menu-items/{menuItem}/modifier-groups', [MenuConfigurationController::class, 'syncModifiers'])
                    ->name('menu.menu-items.modifiers.sync');

                Route::put('branches/{branch}/menu-items', [MenuConfigurationController::class, 'syncBranchMenu'])
                    ->name('menu.branches.items.sync');

                Route::patch('branches/{branch}/menu-items/availability', [MenuConfigurationController::class, 'availability'])
                    ->name('menu.branches.items.availability');
            });
    });
