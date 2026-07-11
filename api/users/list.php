<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/user_helpers.php';

startSecureSession();
requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'שיטת הבקשה אינה נתמכת.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $pdo = Database::getConnection();

    $search = trim((string) ($_GET['search'] ?? ''));
    $role = trim((string) ($_GET['role'] ?? 'all'));
    $active = trim((string) ($_GET['active'] ?? 'all'));

    $users = getUsers(
        $pdo,
        $search,
        $role,
        $active
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'users' => $users,
            'count' => count($users)
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log('Users list error: ' . $exception->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'לא ניתן לטעון את רשימת המשתמשים.'
    ], JSON_UNESCAPED_UNICODE);
}
