<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/user_helpers.php';

startSecureSession();
requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'שיטת הבקשה אינה נתמכת.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $data = getJsonRequestBody();

    if (!validateCsrfToken(
        isset($data['csrf_token'])
            ? (string) $data['csrf_token']
            : null
    )) {
        http_response_code(419);

        throw new InvalidArgumentException(
            'הבקשה אינה חוקית או שפג תוקפה.'
        );
    }

    $userId = inputInt($data, 'id', 0);
    $username = inputString($data, 'username');
    $fullName = inputString($data, 'full_name');
    $email = nullIfEmpty(inputString($data, 'email'));
    $phone = nullIfEmpty(inputString($data, 'phone'));
    $role = normalizeUserRole(inputString($data, 'role', 'user'));
    $isActive = inputBool($data, 'is_active', true);
    $mustChangePassword = inputBool(
        $data,
        'must_change_password',
        false
    );
    $password = (string) ($data['password'] ?? '');

    if (!validateUsername($username)) {
        throw new InvalidArgumentException(
            'שם המשתמש חייב להכיל 3–80 תווים באנגלית, מספרים, נקודה, מקף או קו תחתון.'
        );
    }

    if ($fullName === '') {
        throw new InvalidArgumentException(
            'יש להזין שם מלא.'
        );
    }

    if (
        $email !== null &&
        filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        throw new InvalidArgumentException(
            'כתובת הדוא״ל אינה תקינה.'
        );
    }

    if ($userId <= 0 && !validateUserPassword($password)) {
        throw new InvalidArgumentException(
            'סיסמה למשתמש חדש חייבת להכיל לפחות 8 תווים.'
        );
    }

    if (
        $userId > 0 &&
        $password !== '' &&
        !validateUserPassword($password)
    ) {
        throw new InvalidArgumentException(
            'הסיסמה חייבת להכיל לפחות 8 תווים.'
        );
    }

    $pdo = Database::getConnection();
    $actorUserId = currentUserId();

    if ($actorUserId === null) {
        throw new RuntimeException('לא נמצא משתמש מחובר.');
    }

    ensureUniqueUserFields(
        $pdo,
        $username,
        $email,
        $userId > 0 ? $userId : null
    );

    $pdo->beginTransaction();

    if ($userId > 0) {
        $existing = getUserById($pdo, $userId);

        if ($existing === null) {
            throw new InvalidArgumentException(
                'המשתמש לא נמצא.'
            );
        }

        if (
            $existing['role'] === 'admin' &&
            (int) $existing['is_active'] === 1 &&
            (
                $role !== 'admin' ||
                !$isActive
            ) &&
            countActiveAdmins($pdo) <= 1
        ) {
            throw new InvalidArgumentException(
                'לא ניתן להשבית או להוריד הרשאה מהמנהל הפעיל האחרון.'
            );
        }

        if ($userId === $actorUserId && !$isActive) {
            throw new InvalidArgumentException(
                'לא ניתן להשבית את המשתמש המחובר.'
            );
        }

        $sql = "
            UPDATE users
            SET
                username = :username,
                full_name = :full_name,
                email = :email,
                phone = :phone,
                role = :role,
                is_active = :is_active,
                must_change_password = :must_change_password,
                updated_by = :updated_by
        ";

        $parameters = [
            'username' => $username,
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'is_active' => $isActive ? 1 : 0,
            'must_change_password' => $mustChangePassword ? 1 : 0,
            'updated_by' => $actorUserId,
            'id' => $userId
        ];

        if ($password !== '') {
            $sql .= ",
                password_hash = :password_hash,
                password_changed_at = NOW(),
                failed_login_attempts = 0,
                last_failed_login_at = NULL,
                locked_until = NULL
            ";

            $parameters['password_hash'] = password_hash(
                $password,
                PASSWORD_DEFAULT
            );
        }

        $sql .= " WHERE id = :id";

        $statement = $pdo->prepare($sql);
        $statement->execute($parameters);

        logUserManagementAction(
            $pdo,
            $actorUserId,
            'user_updated',
            $userId
        );
    } else {
        $statement = $pdo->prepare(
            "INSERT INTO users (
                username,
                password_hash,
                full_name,
                email,
                phone,
                role,
                is_active,
                must_change_password,
                password_changed_at,
                created_by,
                updated_by
            ) VALUES (
                :username,
                :password_hash,
                :full_name,
                :email,
                :phone,
                :role,
                :is_active,
                :must_change_password,
                NOW(),
                :created_by,
                :updated_by
            )"
        );

        $statement->execute([
            'username' => $username,
            'password_hash' => password_hash(
                $password,
                PASSWORD_DEFAULT
            ),
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'is_active' => $isActive ? 1 : 0,
            'must_change_password' => $mustChangePassword ? 1 : 0,
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId
        ]);

        $userId = (int) $pdo->lastInsertId();

        logUserManagementAction(
            $pdo,
            $actorUserId,
            'user_created',
            $userId
        );
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'המשתמש נשמר בהצלחה.',
        'data' => [
            'user' => getUserById($pdo, $userId)
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('User save error: ' . $exception->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'לא ניתן לשמור את המשתמש.'
    ], JSON_UNESCAPED_UNICODE);
}
