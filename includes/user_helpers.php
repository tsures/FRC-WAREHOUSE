<?php

declare(strict_types=1);

function getUserById(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare(
        "SELECT
            u.id,
            u.username,
            u.full_name,
            u.email,
            u.phone,
            u.role,
            u.is_active,
            u.failed_login_attempts,
            u.last_failed_login_at,
            u.locked_until,
            u.must_change_password,
            u.last_login_at,
            u.last_login_ip,
            u.password_changed_at,
            u.created_at,
            u.updated_at,
            u.created_by,
            u.updated_by,
            creator.full_name AS created_by_name,
            updater.full_name AS updated_by_name
         FROM users u
         LEFT JOIN users creator
            ON creator.id = u.created_by
         LEFT JOIN users updater
            ON updater.id = u.updated_by
         WHERE u.id = :id
         LIMIT 1"
    );

    $statement->execute([
        'id' => $userId
    ]);

    $user = $statement->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function getUsers(
    PDO $pdo,
    string $search = '',
    string $role = 'all',
    string $active = 'all'
): array {
    $sql = "
        SELECT
            u.id,
            u.username,
            u.full_name,
            u.email,
            u.phone,
            u.role,
            u.is_active,
            u.failed_login_attempts,
            u.last_failed_login_at,
            u.locked_until,
            u.must_change_password,
            u.last_login_at,
            u.last_login_ip,
            u.password_changed_at,
            u.created_at,
            u.updated_at,
            creator.full_name AS created_by_name,
            updater.full_name AS updated_by_name
        FROM users u
        LEFT JOIN users creator
            ON creator.id = u.created_by
        LEFT JOIN users updater
            ON updater.id = u.updated_by
        WHERE 1 = 1
    ";

    $parameters = [];

    if ($search !== '') {
        $searchValue = '%' . $search . '%';

        $sql .= "
            AND (
                u.username LIKE :search_username
                OR u.full_name LIKE :search_full_name
                OR u.email LIKE :search_email
                OR u.phone LIKE :search_phone
            )
        ";

        $parameters['search_username'] = $searchValue;
        $parameters['search_full_name'] = $searchValue;
        $parameters['search_email'] = $searchValue;
        $parameters['search_phone'] = $searchValue;
    }

    if (in_array($role, ['admin', 'user'], true)) {
        $sql .= " AND u.role = :role";
        $parameters['role'] = $role;
    }

    if ($active === 'active') {
        $sql .= " AND u.is_active = 1";
    } elseif ($active === 'inactive') {
        $sql .= " AND u.is_active = 0";
    }

    $sql .= "
        ORDER BY
            u.is_active DESC,
            CASE WHEN u.role = 'admin' THEN 0 ELSE 1 END,
            u.full_name ASC,
            u.id ASC
    ";

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function normalizeUserRole(string $role): string
{
    return in_array($role, ['admin', 'user'], true)
        ? $role
        : 'user';
}

function validateUsername(string $username): bool
{
    return preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username) === 1;
}

function validateUserPassword(string $password): bool
{
    return mb_strlen($password) >= 8;
}

function ensureUniqueUserFields(
    PDO $pdo,
    string $username,
    ?string $email,
    ?int $excludeUserId = null
): void {
    $sql = "
        SELECT id
        FROM users
        WHERE (
            username = :username
    ";

    $parameters = [
        'username' => $username
    ];

    if ($email !== null) {
        $sql .= " OR email = :email";
        $parameters['email'] = $email;
    }

    $sql .= ")";

    if ($excludeUserId !== null) {
        $sql .= " AND id <> :exclude_id";
        $parameters['exclude_id'] = $excludeUserId;
    }

    $sql .= " LIMIT 1";

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    if ($statement->fetch()) {
        throw new InvalidArgumentException(
            'שם המשתמש או כתובת הדוא״ל כבר קיימים במערכת.'
        );
    }
}

function countActiveAdmins(PDO $pdo): int
{
    $statement = $pdo->query(
        "SELECT COUNT(*)
         FROM users
         WHERE role = 'admin'
           AND is_active = 1"
    );

    return (int) $statement->fetchColumn();
}

function logUserManagementAction(
    PDO $pdo,
    int $actorUserId,
    string $action,
    int $targetUserId
): void {
    try {
        $statement = $pdo->prepare(
            "INSERT INTO activity_logs (
                user_id,
                action,
                entity_type,
                entity_id,
                ip_address,
                user_agent
            ) VALUES (
                :user_id,
                :action,
                'user',
                :entity_id,
                :ip_address,
                :user_agent
            )"
        );

        $statement->execute([
            'user_id' => $actorUserId,
            'action' => $action,
            'entity_id' => $targetUserId,
            'ip_address' => getClientIp(),
            'user_agent' => getUserAgent()
        ]);
    } catch (Throwable $exception) {
        error_log(
            'User management log error: ' .
            $exception->getMessage()
        );
    }
}
