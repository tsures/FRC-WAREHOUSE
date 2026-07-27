<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from the command line.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/database.php';

$username = trim((string) ($argv[1] ?? ''));
$fullName = trim((string) ($argv[2] ?? ''));
$email = trim((string) ($argv[3] ?? ''));

if ($username === '' || $fullName === '') {
    fwrite(STDERR, "Usage: php scripts/create-admin.php <username> <full-name> [email]\n");
    exit(1);
}

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "The email address is invalid.\n");
    exit(1);
}

fwrite(STDOUT, 'Password: ');
$password = trim((string) fgets(STDIN));

if (strlen($password) < 8) {
    fwrite(STDERR, "The password must contain at least 8 characters.\n");
    exit(1);
}

try {
    $pdo = Database::getConnection();

    $check = $pdo->prepare(
        'SELECT id FROM users WHERE username = :username LIMIT 1'
    );
    $check->execute(['username' => $username]);

    if ($check->fetch() !== false) {
        fwrite(STDERR, "A user with this username already exists.\n");
        exit(1);
    }

    $statement = $pdo->prepare(
        'INSERT INTO users
            (username, password_hash, full_name, email, role, is_active,
             must_change_password, password_changed_at)
         VALUES
            (:username, :password_hash, :full_name, :email, :role, 1, 1, NOW())'
    );

    $statement->execute([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'full_name' => $fullName,
        'email' => $email !== '' ? $email : null,
        'role' => 'admin'
    ]);

    fwrite(STDOUT, "Administrator created successfully.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "Failed to create administrator: {$exception->getMessage()}\n");
    exit(1);
}
