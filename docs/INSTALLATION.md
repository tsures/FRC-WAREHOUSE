# Installation Guide

## 1. Server requirements

Use PHP 8.0 or newer with PDO MySQL, mbstring, JSON, fileinfo, and sessions enabled. The project is intended for Apache and uses `.htaccess` rules.

## 2. Upload the files

Upload the repository to the target web directory. Keep the directory structure unchanged.

Example Hostinger path:

```text
/home/ACCOUNT/domains/DOMAIN/public_html/warehouse/
```

## 3. Create the database

Create a MariaDB/MySQL database and a database user with access to it. Use `utf8mb4` where the hosting panel exposes a character-set option.

Import:

```text
database/warehouse.sql
```

Optionally import:

```text
database/seed.example.sql
```

## 4. Configure the application

Copy:

```text
includes/config.example.php
```

to:

```text
includes/config.php
```

Set these values:

```php
const APP_ENV = 'production';
const APP_URL = 'https://your-domain.example/warehouse';
const DB_HOST = 'localhost';
const DB_NAME = 'your_database';
const DB_USER = 'your_database_user';
const DB_PASS = 'your_database_password';
```

Do not add a trailing slash to `APP_URL`.

## 5. File permissions

The web server needs write permission for:

```text
storage/uploads/
```

Typical Linux permissions are `755` for directories. Some shared hosts require `775`. Do not use `777` unless the hosting provider explicitly requires it.

## 6. Create the first administrator

From SSH or a local command line in the project directory:

```bash
php scripts/create-admin.php admin "Administrator" admin@example.com
```

The command prompts for a password. On hosting without SSH, generate a password hash locally with:

```bash
php -r "echo password_hash('ReplaceMe', PASSWORD_DEFAULT), PHP_EOL;"
```

Then insert the resulting hash through phpMyAdmin:

```sql
INSERT INTO users
    (username, password_hash, full_name, email, role, is_active, must_change_password)
VALUES
    ('admin', 'PASTE_HASH_HERE', 'Administrator', 'admin@example.com', 'admin', 1, 1);
```

## 7. Open the application

Browse to:

```text
https://your-domain.example/warehouse/public/login.php
```

## 8. Production checklist

- `APP_ENV` is `production`
- HTTPS is enabled
- Database credentials are not committed to Git
- Directory listing is disabled
- `storage/uploads/.htaccess` exists
- Database and uploads are backed up regularly
- Default administrator password has been changed
