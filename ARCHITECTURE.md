# Architecture: ps_linklist

## Purpose

A PrestaShop module that allows back-office users to manage configurable link blocks (footer link lists) and display them in theme hook positions. Supports custom URLs, CMS pages, category pages, and static page links per block.

## Directory Structure

```
ps_linklist.php                          - Module bootstrap; hook registration
src/
  Adapter/Object_Model_Handler.php       - Bridges modern repository to legacy ObjectModel
  Cache/                                 - Interface + implementation for link block caching
  Controller/Admin/Improve/Design/
    Link_Block_Controller.php            - Symfony controller for the admin CRUD UI
  Core/Grid/                             - Grid definition, factory, and query builder for the block list
  Core/Search/Filters/                   - Filter class for the admin grid
  Data_Migration.php                     - Migrates data from the ps_1.6 equivalent module
  Filter/                                - Route filter decorators for link URL generation
  Form/                                  - Symfony form types and data providers for the link block form
  Model/                                 - Link_Block and Link_Block_Lang ObjectModel classes
  Presenter/Link_Block_Presenter.php     - Transforms model data for the front-office template
  Repository/Link_Block_Repository.php   - Doctrine-style repository for CRUD operations
views/
  templates/                             - Smarty front-office and admin templates
  js/                                    - Admin grid JS (sorting, filtering)
upgrade/                                 - SQL/PHP migration scripts
tests/                                   - PHPUnit test stubs and PHPStan bootstrap
```

## Key Design Decisions

- **Symfony integration**: Uses PrestaShop's Symfony layer for admin controllers, Symfony form types, and dependency injection — not the legacy `AdminController` pattern.
- **Separation of concerns**: Repository handles persistence, Presenter handles view transformation, Controller handles HTTP.
- **Legacy bridge**: `Object_Model_Handler` and `Legacy_Link_Block_Repository` allow the Symfony layer to work alongside PrestaShop's ObjectModel ORM.
- **Cache abstraction**: `Link_Block_Cache_Interface` allows swapping cache backends (e.g., Redis or filesystem).

## Extension Points

- Implement `Link_Block_Cache_Interface` to add a custom cache backend.
- Implement `Route_Filter_Interface` to add new link types (e.g., brand pages).
- Add new columns to the admin grid via `Link_Block_Definition_Factory`.

## Dependency Flow

```
ps_linklist (Module)
  └─> Admin CRUD
        └─> Link_Block_Controller (Symfony)
              └─> Link_Block_Repository
              └─> Link_Block_Form_Data_Provider
  └─> Front-office display
        └─> hookDisplayFooter / hookDisplayNav
              └─> Link_Block_Presenter
                    └─> Link_Block_Repository
```
