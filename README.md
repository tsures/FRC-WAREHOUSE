# FRC Warehouse Management System
## This project is dedicated with lots of love to frc team "ARTEMIS 3083" which i was proud to be their mentor, love you guys
## tsures
A responsive Hebrew RTL warehouse-management application for tracking inventory, locations, categories, suppliers, stock movements, users, notifications, and audit activity.

## Requirements

- PHP 8.0 or newer
- MariaDB 10.5+ or MySQL 8.0+
- Apache with `mod_rewrite` and `mod_headers`
- PHP extensions: `pdo`, `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `session`

## Main features

- User authentication, password reset, account locking, and remember-me tokens
- Inventory item creation and editing
- Stock add, remove, transfer, adjustment, borrow, return, damage, and retire operations
- Hierarchical warehouse locations
- Categories and suppliers
- Low-stock and shortage views
- Transaction history and activity logs
- Responsive Hebrew RTL interface

## Installation

1. Clone or extract the repository into your web directory.
2. Create an empty MariaDB/MySQL database using `utf8mb4`.
3. Import `database/warehouse.sql`.
4. Copy `includes/config.example.php` to `includes/config.php` if a real configuration file is not already present.
5. Edit `includes/config.php` and set `APP_URL`, `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`.
6. Ensure `storage/uploads` is writable by the web-server user.
7. Point the browser to `<APP_URL>/public/login.php`.
8. Create the first administrator from the command line:

```bash
php scripts/create-admin.php admin "Administrator" admin@example.com
```

The script asks for the password without storing it in the repository.

Detailed hosting instructions are available in [`docs/INSTALLATION.md`](docs/INSTALLATION.md).

## Configuration

The application currently reads PHP constants from `includes/config.php`. An `.env.example` file is included as a deployment reference, but `.env` values are not loaded automatically by the current codebase.

Never commit a real `includes/config.php` containing production credentials. The repository includes `includes/config.example.php` for safe setup.

## Database

The complete schema is in `database/warehouse.sql`.

Main tables:

- `users`
- `inventory_items`
- `inventory_transactions`
- `locations`
- `categories`
- `suppliers`
- `attachments`
- `activity_logs`
- `notifications`
- `login_attempts`
- `password_reset_tokens`
- `remember_tokens`
- `recently_viewed_items`
- `settings`

Optional starter records are available in `database/seed.example.sql`.

## Project structure

```text
api/                 JSON endpoints
assets/css/          Application styles
assets/js/           Front-end JavaScript
includes/            Configuration and shared PHP services/helpers
public/              Browser-facing pages
views/layouts/        Shared header and footer
storage/uploads/      User-uploaded files
database/             Schema and optional seed data
scripts/              Command-line maintenance utilities
docs/                 Installation and project documentation
```

## Security notes

- Keep database credentials outside Git.
- Use HTTPS in production.
- Set `APP_ENV` to `production` before deployment.
- Keep `storage/uploads/.htaccess` and the root `.htaccess` enabled.
- Back up both the database and uploaded files.

## Development context

See [`PROJECT_CONTEXT.md`](PROJECT_CONTEXT.md) for architecture, conventions, completed modules, and known implementation details.

## License

See [`LICENSE`](LICENSE).
