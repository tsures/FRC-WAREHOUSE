# Project Context

## Purpose

This project is a Hebrew RTL warehouse-management system developed for an FRC robotics team. It is designed for desktop and mobile use and supports day-to-day stock control without a framework dependency.

## Technology

- PHP 8+
- MariaDB/MySQL through PDO
- HTML, CSS, and vanilla JavaScript
- Apache `.htaccess`
- Session-based authentication
- JSON API endpoints under `api/`

## Architectural conventions

The application uses a lightweight separation of concerns:

- `public/`: pages rendered in the browser
- `api/`: JSON endpoints used by front-end JavaScript
- `includes/`: authentication, database, validation, CSRF, response, and domain helpers
- `assets/js/`: page-specific view logic and API calls
- `assets/css/`: shared and page-specific responsive styles
- `views/layouts/`: common application header and footer

Database access must use `Database::getConnection()` and prepared PDO statements. API endpoints should return consistent JSON responses and validate CSRF/authentication as appropriate.

## Main domain model

### Inventory

`inventory_items` stores the current item state and quantity. Each quantity-changing operation must also create a matching `inventory_transactions` record with before/after quantities.

Supported transaction types:

- `initial`
- `add`
- `remove`
- `transfer`
- `adjustment`
- `borrow`
- `return`
- `damage`
- `retire`

### Locations

`locations` is hierarchical through `parent_id`. Supported location types are warehouse, room, cabinet, shelf, bin, and other.

### Security and users

The application supports administrator and regular-user roles. Passwords use PHP `password_hash()` and `password_verify()`. Login attempts, account locks, reset tokens, and remember-me tokens are stored in dedicated tables.

## Implemented modules

- Login/logout and session handling
- Forgot/reset/change password
- User management and login-attempt review
- Inventory list and item form
- Stock movement and item issue pages
- Inventory transaction/history screens
- Warehouse/location management
- Category management
- Shortage view
- Profile and settings pages
- Activity logging and notifications schema

## UI conventions

- Hebrew-first RTL interface
- Responsive mobile layouts
- Shared layout in `views/layouts/`
- Shared CSS in `assets/css/app.css`, `layout.css`, `components.css`, and `responsive.css`
- Page-specific assets should retain version query strings when changed to prevent stale browser caches

## Database rules

- Character set: `utf8mb4`
- Storage engine: InnoDB
- Use foreign keys where the schema already defines them
- Do not update inventory quantity without recording an inventory transaction
- Prefer soft disabling with `is_active` where supported
- Preserve audit columns such as `created_by`, `updated_by`, `created_at`, and `updated_at`

## Deployment notes

- Production configuration is in `includes/config.php` and must not be committed with secrets
- `APP_URL` must match the exact public base URL without a trailing slash
- `storage/uploads` must be writable but must not allow script execution
- The application expects Apache rules from the included `.htaccess` files

## Known considerations

- The project does not currently use Composer or a PHP framework
- `.env.example` is documentation only; the running application reads constants from `includes/config.php`
- `database/warehouse.sql` was exported from MariaDB and includes schema, indexes, auto-increment definitions, and foreign keys
- `views/layouts/app-header.php.old` is a backup file and can be removed after confirming it is no longer needed
