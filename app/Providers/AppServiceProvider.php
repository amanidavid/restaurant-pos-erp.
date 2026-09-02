<?php

namespace App\Providers;

use App\Models\Tenant\StockAdjustment;
use App\Models\Tenant\StockCount;
use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Company;
use App\Models\Tenant\Department;
use App\Models\Tenant\EmploymentType;
use App\Models\Tenant\Ingredient;
use App\Models\Tenant\JobPosition;
use App\Models\Tenant\KitchenSection;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\ModifierGroup;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TaxCode;
use App\Models\Tenant\UnitOfMeasure;
use App\Models\Tenant\Warehouse;
use App\Policies\Tenant\Access\PermissionPolicy;
use App\Policies\Tenant\Access\RolePolicy;
use App\Policies\Tenant\Access\TenantUserPolicy;
use App\Policies\Tenant\Foundation\BranchPolicy;
use App\Policies\Tenant\Foundation\CompanyPolicy;
use App\Policies\Tenant\Inventory\StockAdjustmentPolicy;
use App\Policies\Tenant\Inventory\StockCountPolicy;
use App\Policies\Tenant\Inventory\StockTransferPolicy;
use App\Policies\Tenant\Inventory\WarehousePolicy;
use App\Policies\Tenant\Menu\IngredientPolicy;
use App\Policies\Tenant\Menu\KitchenSectionPolicy;
use App\Policies\Tenant\Menu\MenuCategoryPolicy;
use App\Policies\Tenant\Menu\MenuItemPolicy;
use App\Policies\Tenant\Menu\ModifierGroupPolicy;
use App\Policies\Tenant\Menu\TaxCodePolicy;
use App\Policies\Tenant\Menu\UnitOfMeasurePolicy;
use App\Policies\Tenant\Organisation\DepartmentPolicy;
use App\Policies\Tenant\Organisation\EmploymentTypePolicy;
use App\Policies\Tenant\Organisation\JobPositionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(StockAdjustment::class, StockAdjustmentPolicy::class);
        Gate::policy(StockTransfer::class, StockTransferPolicy::class);
        Gate::policy(StockCount::class, StockCountPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(EmploymentType::class, EmploymentTypePolicy::class);
        Gate::policy(JobPosition::class, JobPositionPolicy::class);
        Gate::policy(TenantUser::class, TenantUserPolicy::class);
        Gate::policy(MenuItem::class, MenuItemPolicy::class);
        Gate::policy(UnitOfMeasure::class, UnitOfMeasurePolicy::class);
        Gate::policy(TaxCode::class, TaxCodePolicy::class);
        Gate::policy(MenuCategory::class, MenuCategoryPolicy::class);
        Gate::policy(Ingredient::class, IngredientPolicy::class);
        Gate::policy(KitchenSection::class, KitchenSectionPolicy::class);
        Gate::policy(ModifierGroup::class, ModifierGroupPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        $this->loadMigrationsFrom(database_path('migrations/landlord'));
    }
}
