<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

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
    $result = trim((string) ($_GET['result'] ?? 'all'));
    $reason = trim((string) ($_GET['reason'] ?? 'all'));
    $userId = (int) ($_GET['user_id'] ?? 0);
    $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
    $dateTo = trim((string) ($_GET['date_to'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pageSize = min(100, max(10, (int) ($_GET['page_size'] ?? 25)));
    $offset = ($page - 1) * $pageSize;

    $where = ['1 = 1'];
    $parameters = [];

    if ($search !== '') {
        $searchValue = '%' . $search . '%';
        $where[] = "(
            la.username_attempted LIKE :search_username
            OR u.full_name LIKE :search_full_name
            OR la.ip_address LIKE :search_ip
        )";
        $parameters['search_username'] = $searchValue;
        $parameters['search_full_name'] = $searchValue;
        $parameters['search_ip'] = $searchValue;
    }

    if ($result === 'success') {
        $where[] = 'la.was_successful = 1';
    } elseif ($result === 'failed') {
        $where[] = 'la.was_successful = 0';
    }

    if ($reason !== 'all' && $reason !== '') {
        $where[] = 'la.failure_reason = :failure_reason';
        $parameters['failure_reason'] = $reason;
    }

    if ($userId > 0) {
        $where[] = 'la.user_id = :user_id';
        $parameters['user_id'] = $userId;
    }

    if ($dateFrom !== '') {
        $fromTimestamp = strtotime($dateFrom . ' 00:00:00');
        if ($fromTimestamp === false) {
            throw new InvalidArgumentException('תאריך ההתחלה אינו תקין.');
        }
        $where[] = 'la.created_at >= :date_from';
        $parameters['date_from'] = date('Y-m-d H:i:s', $fromTimestamp);
    }

    if ($dateTo !== '') {
        $toTimestamp = strtotime($dateTo . ' 23:59:59');
        if ($toTimestamp === false) {
            throw new InvalidArgumentException('תאריך הסיום אינו תקין.');
        }
        $where[] = 'la.created_at <= :date_to';
        $parameters['date_to'] = date('Y-m-d H:i:s', $toTimestamp);
    }

    $whereSql = implode(' AND ', $where);

    $countStatement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM login_attempts la
         LEFT JOIN users u ON u.id = la.user_id
         WHERE {$whereSql}"
    );
    $countStatement->execute($parameters);
    $totalCount = (int) $countStatement->fetchColumn();

    $sql = "SELECT
                la.id,
                la.user_id,
                la.username_attempted,
                la.was_successful,
                la.failure_reason,
                la.ip_address,
                la.user_agent,
                la.created_at,
                u.full_name,
                u.username AS actual_username
            FROM login_attempts la
            LEFT JOIN users u ON u.id = la.user_id
            WHERE {$whereSql}
            ORDER BY la.id DESC
            LIMIT :limit OFFSET :offset";

    $statement = $pdo->prepare($sql);

    foreach ($parameters as $key => $value) {
        $statement->bindValue(
            ':' . $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $statement->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    $attempts = $statement->fetchAll(PDO::FETCH_ASSOC);

    $summaryStatement = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN la.was_successful = 1 THEN 1 ELSE 0 END)
                AS success_count,
            SUM(CASE WHEN la.was_successful = 0 THEN 1 ELSE 0 END)
                AS failed_count,
            COUNT(DISTINCT CASE
                WHEN la.was_successful = 0 THEN la.ip_address
                ELSE NULL
            END) AS failed_ip_count
         FROM login_attempts la
         LEFT JOIN users u ON u.id = la.user_id
         WHERE {$whereSql}"
    );
    $summaryStatement->execute($parameters);
    $summary = $summaryStatement->fetch(PDO::FETCH_ASSOC);

    $users = $pdo->query(
        "SELECT id, username, full_name
         FROM users
         ORDER BY full_name ASC, username ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $reasons = $pdo->query(
        "SELECT DISTINCT failure_reason
         FROM login_attempts
         WHERE failure_reason IS NOT NULL
           AND failure_reason <> ''
         ORDER BY failure_reason ASC"
    )->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'data' => [
            'attempts' => $attempts,
            'summary' => [
                'total_count' => (int) ($summary['total_count'] ?? 0),
                'success_count' => (int) ($summary['success_count'] ?? 0),
                'failed_count' => (int) ($summary['failed_count'] ?? 0),
                'failed_ip_count' => (int) ($summary['failed_ip_count'] ?? 0)
            ],
            'users' => $users,
            'reasons' => $reasons,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total_count' => $totalCount,
                'total_pages' => max(
                    1,
                    (int) ceil($totalCount / $pageSize)
                )
            ]
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('Login attempts list error: ' . $exception->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'לא ניתן לטעון את היסטוריית ההתחברויות.'
    ], JSON_UNESCAPED_UNICODE);
}
