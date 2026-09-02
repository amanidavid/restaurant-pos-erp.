# API Design 05: Menu, Ingredients, Recipes, Modifiers, and Branch Menu Configuration

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-24

## 1. Purpose

This design defines how tenant users manage restaurant menu structure, sellable items, ingredients, recipes, modifiers, kitchen routing and branch-specific menu availability.

This module comes after:

```text
Auth
Registration/provisioning
Company/branch/business date/numbering
Tenant users/roles/permissions
```

Reason:

Menu records need tenant context, company, branch, kitchen sections, permissions and later inventory integration.

## 2. Core design decision

Do not use one confusing `item_type` column to represent everything.

Use separate concepts:

```text
menu_items
  sellable things customers can order
  examples: Beef Burger, Chips, Soda, Delivery Fee

ingredients
  stock or recipe input things
  examples: Beef patty, Salt, Cooking oil, Flour

recipes
  how a menu item consumes ingredients
  example: Beef Burger uses bun + patty + lettuce + sauce

branch_menu_items
  branch-specific price, availability, visibility and kitchen routing
```

This avoids redundancy and makes the design easier to understand.

## 3. Route placement

Tenant/business APIs belong in:

```text
routes/api/v1/web.php
```

Recommended source folders:

```text
app/Http/Controllers/Web/Menu
app/Http/Requests/Web/Menu
app/Http/Resources/Web/Menu
app/Services/Menu
```

## 4. Tables used

Tenant tables:

- `units_of_measure`
- `tax_codes`
- `menu_categories`
- `price_lists`
- `menu_items`
- `ingredients`
- `kitchen_sections`
- `branch_menu_items`
- `modifier_groups`
- `modifier_options`
- `menu_item_modifier_groups`
- `menu_item_prices`
- `recipes`
- `recipe_ingredients`
- `combo_products`
- `combo_product_items`
- `media`

Related later:

- stock batches/movements;
- production/kitchen tickets;
- sales orders;
- reports.

## 5. Media/image rule

Menu images, ingredient images, category images and logos should use Spatie Media Library metadata.

Do not store large binary files in the database.

Menu item response can expose:

```json
{
  "image": {
    "uuid": "018f8e00-media",
    "url": "https://cdn.example.com/tenant/menu/burger.jpg",
    "thumb_url": "https://cdn.example.com/tenant/menu/conversions/burger-thumb.jpg"
  }
}
```

For first release, `menu_items.image_url` can remain as a simple display field if already present, but implementation should prefer Media Library for upload and conversion.

## 6. Menu category list

Endpoint:

```text
GET /api/v1/menu-categories
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\MenuCategoryController
Permission: menu_categories.view
Complexity: simple
Pagination: simplePaginate
Default per_page: 15
Max per_page: 100
```

Filters:

- `status=active|inactive`
- `parent_uuid`
- `search=<prefix>`

Search:

- prefix search on category name;
- avoid broad `%term%` without trigram index.

## 7. Create/update menu category

Endpoints:

```text
POST /api/v1/menu-categories
PATCH /api/v1/menu-categories/{category_uuid}
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\MenuCategoryController
Permission: menu_categories.create / menu_categories.update
Complexity: simple
Transaction required: no for simple update, yes if reordering tree
```

Request:

```json
{
  "parent_uuid": null,
  "name": "Burgers",
  "description": "Burger menu",
  "sort_order": 1,
  "status": "active"
}
```

Rules:

- unique category name inside same company and parent;
- category can be nested;
- status inactive hides category from selling screens but does not delete historical order data.

## 8. Ingredient list

Endpoint:

```text
GET /api/v1/ingredients
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\IngredientController
Permission: ingredients.view
Complexity: simple
Pagination: simplePaginate
Default per_page: 15
Max per_page: 100
```

Filters:

- `is_active=true|false`
- `base_unit_uuid`
- `search=<prefix>`

Response:

```json
{
  "success": true,
  "message": "Ingredients loaded successfully.",
  "data": [
    {
      "uuid": "018f8e00-ing",
      "name": "Salt",
      "sku": null,
      "base_unit": {
        "uuid": "018f8e00-unit",
        "name": "Gram",
        "symbol": "g"
      },
      "is_active": true
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15
  },
  "errors": null
}
```

## 9. Create/update ingredient

Endpoints:

```text
POST /api/v1/ingredients
PATCH /api/v1/ingredients/{ingredient_uuid}
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\IngredientController
Permission: ingredients.create / ingredients.update
Complexity: medium
Service required: yes when stock settings are created together
Transaction required: yes when stock settings are included
```

Request:

```json
{
  "name": "Salt",
  "sku": null,
  "base_unit_uuid": "018f8e00-unit",
  "tax_code_uuid": null,
  "description": null,
  "is_active": true,
  "stock_settings": {
    "minimum_stock_level": "2.0000",
    "reorder_level": "5.0000",
    "maximum_stock_level": "50.0000"
  }
}
```

Rules:

- `sku` is optional;
- no unnecessary code attribute required;
- name is unique within company;
- stock settings are inventory module data, so include only if screen needs setup together.

## 10. Menu item list

Endpoint:

```text
GET /api/v1/menu-items
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\MenuItemController
Permission: menu_items.view
Complexity: simple/medium
Pagination: simplePaginate
Default per_page: 15
Max per_page: 100
```

Filters:

- `category_uuid`
- `is_active=true|false`
- `is_combo=true|false`
- `branch_uuid`
- `is_available=true|false`
- `search=<prefix>`

If `branch_uuid` is supplied, include branch price/availability/routing from `branch_menu_items`.

Use eager loading carefully:

- category;
- tax code;
- branch menu row only for selected branch;
- media thumbnail only if needed.

## 11. Create menu item

Endpoint:

```text
POST /api/v1/menu-items
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\MenuItemController
Request: App\Http\Requests\Web\Menu\StoreMenuItemRequest
Service: App\Services\Menu\MenuItemService
Permission: menu_items.create
Complexity: complex
Transaction required: yes
```

Recommended request:

```json
{
  "name": "Beef Burger",
  "sku": null,
  "menu_category_uuid": "018f8e00-category",
  "tax_code_uuid": "018f8e00-tax",
  "description": "Beef burger with chips",
  "allows_open_price": false,
  "is_combo": false,
  "is_active": true,
  "branches": [
    {
      "branch_uuid": "018f8e00-main-branch",
      "kitchen_section_uuid": "018f8e00-grill",
      "price": "12000.00",
      "cost_price": "6500.00",
      "preparation_minutes": 15,
      "is_available": true,
      "is_visible": true,
      "sort_order": 1
    }
  ],
  "modifier_group_uuids": [
    "018f8e00-modifier-group"
  ],
  "recipe": {
    "name": "Beef Burger Recipe",
    "yield_quantity": "1.0000",
    "yield_unit_uuid": null,
    "ingredients": [
      {
        "ingredient_uuid": "018f8e00-bun",
        "unit_uuid": "018f8e00-piece",
        "quantity": "1.0000",
        "waste_percentage": "0.00",
        "notes": null,
        "sort_order": 1
      },
      {
        "ingredient_uuid": "018f8e00-beef-patty",
        "unit_uuid": "018f8e00-gram",
        "quantity": "150.0000",
        "waste_percentage": "2.00",
        "notes": null,
        "sort_order": 2
      }
    ]
  }
}
```

Why array payload?

The menu setup screen naturally creates one sellable item with:

- branch prices;
- kitchen routing;
- modifier groups;
- recipe ingredients.

It would be slow and error-prone to force frontend to call one endpoint per child row.

## 12. Update menu item

Endpoint:

```text
PATCH /api/v1/menu-items/{menu_item_uuid}
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\MenuItemController
Request: App\Http\Requests\Web\Menu\UpdateMenuItemRequest
Service: App\Services\Menu\MenuItemService
Permission: menu_items.update
Complexity: complex
Transaction required: yes
```

Rules:

- update master item fields;
- do not rewrite branch configuration unless branch array is supplied;
- do not rewrite recipe unless recipe payload is supplied;
- preserve historical orders because order lines snapshot name/price/tax later;
- changing recipe affects future production consumption only.

## 13. Branch menu configuration

Endpoint:

```text
PUT /api/v1/branches/{branch_uuid}/menu-items
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\BranchMenuItemController
Request: App\Http\Requests\Web\Menu\SyncBranchMenuItemsRequest
Service: App\Services\Menu\BranchMenuItemService
Permission: branch_menu_items.update
Complexity: complex
Transaction required: yes
```

Request:

```json
{
  "items": [
    {
      "menu_item_uuid": "018f8e00-burger",
      "kitchen_section_uuid": "018f8e00-grill",
      "price": "12000.00",
      "cost_price": "6500.00",
      "preparation_minutes": 15,
      "is_available": true,
      "is_visible": true,
      "sort_order": 1
    },
    {
      "menu_item_uuid": "018f8e00-delivery-fee",
      "kitchen_section_uuid": null,
      "price": "2000.00",
      "cost_price": "0.00",
      "preparation_minutes": 0,
      "is_available": true,
      "is_visible": true,
      "sort_order": 99
    }
  ]
}
```

Rules:

- array payload is required because branch menu is usually edited in batches;
- full replacement or partial update must be explicit;
- verify each menu item belongs to company;
- verify kitchen section belongs to branch;
- price cannot be negative;
- availability changes may write realtime outbox event for POS/KDS refresh;
- do not broadcast inside transaction.

## 14. Bulk availability update

Endpoint:

```text
PATCH /api/v1/branches/{branch_uuid}/menu-items/availability
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\BranchMenuAvailabilityController
Request: App\Http\Requests\Web\Menu\UpdateBranchMenuAvailabilityRequest
Service: App\Services\Menu\BranchMenuAvailabilityService
Permission: branch_menu_items.availability.update
Complexity: medium
Transaction required: yes
Realtime outbox: yes
```

Request:

```json
{
  "items": [
    {
      "branch_menu_item_uuid": "018f8e00-branch-menu",
      "is_available": false
    }
  ],
  "reason": "Beef is out of stock."
}
```

This supports 86'd items during service.

## 15. Kitchen sections

Endpoint:

```text
GET /api/v1/kitchen-sections
POST /api/v1/kitchen-sections
PATCH /api/v1/kitchen-sections/{kitchen_section_uuid}
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\KitchenSectionController
Permission: kitchen_sections.view/create/update
Complexity: simple
Pagination: simplePaginate for list
```

Kitchen sections are branch-specific.

Examples:

- Grill;
- Fryer;
- Bar;
- Pastry;
- Cold Kitchen.

Routing:

```text
branch_menu_items.kitchen_section_id
```

This means the same burger can route to Grill in Main Branch and Kitchen 2 in another branch.

## 16. Modifier groups

Endpoint:

```text
GET /api/v1/modifier-groups
POST /api/v1/modifier-groups
PATCH /api/v1/modifier-groups/{modifier_group_uuid}
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\ModifierGroupController
Permission: modifier_groups.view/create/update
Complexity: medium
Transaction required: yes when options included
Pagination: simplePaginate for list
```

Request:

```json
{
  "name": "Burger Add-ons",
  "description": "Extra options for burgers",
  "min_select": 0,
  "max_select": 3,
  "is_required": false,
  "status": "active",
  "options": [
    {
      "name": "Extra Cheese",
      "price_delta": "1000.00",
      "is_default": false,
      "is_active": true,
      "sort_order": 1
    }
  ]
}
```

Rules:

- `max_select` must be greater than or equal to `min_select`;
- only one active default option per group;
- options are array payload because they are edited together with the group.

## 17. Attach modifier groups to menu item

Endpoint:

```text
PUT /api/v1/menu-items/{menu_item_uuid}/modifier-groups
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\MenuItemModifierGroupController
Request: App\Http\Requests\Web\Menu\SyncMenuItemModifierGroupsRequest
Service: App\Services\Menu\MenuItemModifierGroupService
Permission: menu_items.modifiers.update
Complexity: medium
Transaction required: yes
```

Request:

```json
{
  "modifier_groups": [
    {
      "modifier_group_uuid": "018f8e00-modifier-group",
      "sort_order": 1,
      "is_active": true
    }
  ]
}
```

Full replacement semantics.

## 18. Recipe details

Endpoint:

```text
GET /api/v1/menu-items/{menu_item_uuid}/recipe
PUT /api/v1/menu-items/{menu_item_uuid}/recipe
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\RecipeController
Request: App\Http\Requests\Web\Menu\SyncRecipeRequest
Service: App\Services\Menu\RecipeService
Permission: recipes.view / recipes.update
Complexity: complex
Transaction required: yes for update
```

Request:

```json
{
  "name": "Beef Burger Recipe",
  "yield_quantity": "1.0000",
  "yield_unit_uuid": null,
  "ingredients": [
    {
      "ingredient_uuid": "018f8e00-bun",
      "unit_uuid": "018f8e00-piece",
      "quantity": "1.0000",
      "waste_percentage": "0.00",
      "notes": null,
      "sort_order": 1
    }
  ]
}
```

Rules:

- recipe ingredients are array payload;
- verify all ingredients belong to same company;
- quantities use 4 decimals in current schema because recipe can use fractional quantities;
- waste percentage must be between 0 and 100;
- updating recipe affects future production consumption;
- later recipe versioning may be required for strict historical costing.

## 19. Combo products

Endpoint:

```text
PUT /api/v1/menu-items/{menu_item_uuid}/combo
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\ComboProductController
Request: App\Http\Requests\Web\Menu\SyncComboProductRequest
Service: App\Services\Menu\ComboProductService
Permission: menu_items.combo.update
Complexity: complex
Transaction required: yes
```

Request:

```json
{
  "is_active": true,
  "items": [
    {
      "component_menu_item_uuid": "018f8e00-burger",
      "quantity": 1,
      "is_required": true,
      "sort_order": 1
    },
    {
      "component_menu_item_uuid": "018f8e00-soda",
      "quantity": 1,
      "is_required": false,
      "sort_order": 2
    }
  ]
}
```

Rules:

- menu item must have `is_combo=true`;
- component cannot be itself recursively invalid;
- prevent circular combo references;
- combo rows are array payload.

## 20. Image upload

Endpoint:

```text
POST /api/v1/menu-items/{menu_item_uuid}/image
Route file: routes/api/v1/web.php
Controller: App\Http\Controllers\Web\Menu\MenuItemImageController
Request: App\Http\Requests\Web\Menu\UploadMenuItemImageRequest
Service: App\Services\Menu\MenuItemImageService
Permission: menu_items.image.update
Complexity: medium
Transaction required: yes for metadata
Queue required: yes for image conversions if heavy
```

Rules:

- validate image type and size;
- store file in tenant-prefixed object path;
- attach through Spatie Media Library;
- generate thumbnails/conversions in queue if needed;
- do not store binary in database;
- audit image replacement for menu item.

## 21. Import/export

Imports:

- menu categories;
- ingredients;
- menu items;
- recipes;
- branch prices/availability.

Large imports must be queued:

```text
Upload file
  -> create data_import_batch
  -> queue import job
  -> validate rows
  -> upsert in chunks
  -> store row errors
```

Exports:

- menu catalogue;
- ingredient list;
- branch menu price list;
- recipe costing sheet.

Large exports should be queued and filtered.

## 22. Permissions

Recommended permissions:

```text
menu_categories.view
menu_categories.create
menu_categories.update

ingredients.view
ingredients.create
ingredients.update

menu_items.view
menu_items.create
menu_items.update
menu_items.image.update
menu_items.modifiers.update
menu_items.combo.update

branch_menu_items.view
branch_menu_items.update
branch_menu_items.availability.update

kitchen_sections.view
kitchen_sections.create
kitchen_sections.update

modifier_groups.view
modifier_groups.create
modifier_groups.update

recipes.view
recipes.update
```

## 23. Complexity summary

| Area | Complexity | Reason |
|---|---:|---|
| Menu category CRUD | Simple | One table unless tree reordering. |
| Ingredient CRUD | Medium | May create stock settings. |
| Menu item list | Simple/medium | Branch filter and eager loading. |
| Menu item create/update | Complex | Item + branches + modifiers + recipe. |
| Branch menu sync | Complex | Array update, branch routing, realtime. |
| Availability update | Medium | 86'd item flow and realtime. |
| Kitchen sections | Simple | Branch lookup. |
| Modifier group with options | Medium | Parent + option array. |
| Recipe sync | Complex | Recipe + ingredient array. |
| Combo sync | Complex | Component array and circular checks. |
| Image upload | Medium/heavy | File validation and queued conversions. |
| Imports/exports | Heavy | Queue required for large files. |

## 24. Performance rules

- Use simple pagination for category, ingredient, menu item, kitchen section and modifier lists.
- Use exact UUID lookup for details.
- Use indexes on company, category, branch availability and kitchen routing.
- Avoid N+1 by eager-loading only selected relationships.
- Do not load all menu items for POS if branch and visibility filters are required.
- POS menu query must filter:
  - selected branch;
  - `branch_menu_items.is_visible=true`;
  - `branch_menu_items.is_available=true`;
  - `menu_items.is_active=true`.
- Use branch-specific cache for POS menu read model if needed later.
- Invalidate menu cache after menu/price/availability update.
- Do not broadcast inside transaction; write realtime outbox event after changes are committed.

## 25. Standard errors

Use `ApiResponse`.

Examples:

```text
422 VALIDATION_FAILED
403 ACCESS_DENIED
404 RESOURCE_NOT_FOUND
409 DUPLICATE_MENU_ITEM_NAME
409 DUPLICATE_INGREDIENT_NAME
409 INVALID_KITCHEN_SECTION_BRANCH
409 INVALID_RECIPE_INGREDIENT
409 COMBO_CIRCULAR_REFERENCE
409 MENU_ITEM_HAS_HISTORICAL_ORDERS
500 SERVER_ERROR
```

## 26. Frontend flow

Menu setup screen:

```text
Load categories
Load tax codes
Load units
Load ingredients
Create menu item
  -> set branch price/routing
  -> set modifiers
  -> set recipe ingredients
Upload image if needed
```

Branch menu screen:

```text
Select branch
Load branch menu
Bulk edit price/visibility/availability/kitchen section
Save array payload
POS/KDS refresh through realtime outbox
```

Kitchen routing screen:

```text
Create kitchen sections per branch
Assign branch menu items to kitchen sections
```

## 27. Implementation components

Recommended files:

```text
routes/api/v1/web.php

app/Http/Controllers/Web/Menu/MenuCategoryController.php
app/Http/Controllers/Web/Menu/IngredientController.php
app/Http/Controllers/Web/Menu/MenuItemController.php
app/Http/Controllers/Web/Menu/BranchMenuItemController.php
app/Http/Controllers/Web/Menu/BranchMenuAvailabilityController.php
app/Http/Controllers/Web/Menu/KitchenSectionController.php
app/Http/Controllers/Web/Menu/ModifierGroupController.php
app/Http/Controllers/Web/Menu/MenuItemModifierGroupController.php
app/Http/Controllers/Web/Menu/RecipeController.php
app/Http/Controllers/Web/Menu/ComboProductController.php
app/Http/Controllers/Web/Menu/MenuItemImageController.php

app/Http/Requests/Web/Menu/StoreMenuItemRequest.php
app/Http/Requests/Web/Menu/UpdateMenuItemRequest.php
app/Http/Requests/Web/Menu/SyncBranchMenuItemsRequest.php
app/Http/Requests/Web/Menu/UpdateBranchMenuAvailabilityRequest.php
app/Http/Requests/Web/Menu/SyncRecipeRequest.php
app/Http/Requests/Web/Menu/SyncComboProductRequest.php
app/Http/Requests/Web/Menu/UploadMenuItemImageRequest.php

app/Http/Resources/Web/Menu/MenuCategoryResource.php
app/Http/Resources/Web/Menu/IngredientResource.php
app/Http/Resources/Web/Menu/MenuItemResource.php
app/Http/Resources/Web/Menu/BranchMenuItemResource.php
app/Http/Resources/Web/Menu/KitchenSectionResource.php
app/Http/Resources/Web/Menu/ModifierGroupResource.php
app/Http/Resources/Web/Menu/RecipeResource.php

app/Services/Menu/MenuItemService.php
app/Services/Menu/BranchMenuItemService.php
app/Services/Menu/BranchMenuAvailabilityService.php
app/Services/Menu/ModifierGroupService.php
app/Services/Menu/RecipeService.php
app/Services/Menu/ComboProductService.php
app/Services/Menu/MenuItemImageService.php
```

## 28. Final implementation decision

Implement this design after access management and before POS orders.

Reason:

POS order entry depends on:

- active branch menu item;
- price;
- modifiers;
- kitchen routing;
- recipe later for production stock consumption;
- image/category data for frontend display.

