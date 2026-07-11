<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps =
        !empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off';

    session_name('frc_warehouse_session');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();

    if (!isset($_SESSION['created_at'])) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = time();
    }

    restoreRememberedLogin();

    validateSessionTimeout();
}

function validateSessionTimeout(): void
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $lastActivity = (int) ($_SESSION['last_activity'] ?? time());

    if (time() - $lastActivity > SESSION_TIMEOUT) {
        logoutUser(false);
        return;
    }

    $_SESSION['last_activity'] = time();
}

function loginUser(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['full_name'] = (string) $user['full_name'];
    $_SESSION['user_role'] = (string) $user['role'];
    $_SESSION['must_change_password'] =
        (int) ($user['must_change_password'] ?? 0);
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
}

function attemptLogin(
    string $username,
    string $password,
    bool $rememberMe = false
): array {
    $username = trim($username);

    if ($username === '' || $password === '') {
        return [
            'success' => false,
            'message' => 'יש להזין שם משתמש וסיסמה.'
        ];
    }

    $pdo = Database::getConnection();

    $statement = $pdo->prepare(
        "SELECT
            id,
            username,
            password_hash,
            full_name,
            role,
            is_active,
            must_change_password
         FROM users
         WHERE username = :username
         LIMIT 1"
    );

    $statement->execute([
        'username' => $username
    ]);

    $user = $statement->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        usleep(400000);

        return [
            'success' => false,
            'message' => 'שם המשתמש או הסיסמה אינם נכונים.'
        ];
    }

    if ((int) $user['is_active'] !== 1) {
        return [
            'success' => false,
            'message' => 'המשתמש אינו פעיל. יש לפנות למנהל המערכת.'
        ];
    }

    loginUser($user);

    $updateStatement = $pdo->prepare(
        "UPDATE users
         SET
            last_login_at = NOW(),
            last_login_ip = :last_login_ip
         WHERE id = :id"
    );

    $updateStatement->execute([
        'last_login_ip' => getAuthClientIp(),
        'id' => $user['id']
    ]);

    if ($rememberMe) {
        createRememberToken((int) $user['id']);
    }

    logAuthenticationAction(
        (int) $user['id'],
        'login'
    );

    return [
        'success' => true,
        'message' => 'התחברת בהצלחה.'
    ];
}

function createRememberToken(int $userId): void
{
    $pdo = Database::getConnection();

    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $validatorHash = hash('sha256', $validator);

    $expiresTimestamp = time() + (REMEMBER_ME_DAYS * 86400);
    $expiresAt = date('Y-m-d H:i:s', $expiresTimestamp);

    $deleteStatement = $pdo->prepare(
        "DELETE FROM remember_tokens
         WHERE user_id = :user_id
            OR expires_at < NOW()"
    );

    $deleteStatement->execute([
        'user_id' => $userId
    ]);

    $insertStatement = $pdo->prepare(
        "INSERT INTO remember_tokens (
            user_id,
            selector,
            token_hash,
            expires_at
        ) VALUES (
            :user_id,
            :selector,
            :token_hash,
            :expires_at
        )"
    );

    $insertStatement->execute([
        'user_id' => $userId,
        'selector' => $selector,
        'token_hash' => $validatorHash,
        'expires_at' => $expiresAt
    ]);

    setcookie(
        'frc_remember',
        $selector . ':' . $validator,
        [
            'expires' => $expiresTimestamp,
            'path' => '/',
            'secure' => isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

function restoreRememberedLogin(): void
{
    if (isset($_SESSION['user_id'])) {
        return;
    }

    $cookie = $_COOKIE['frc_remember'] ?? '';

    if (!is_string($cookie) || !str_contains($cookie, ':')) {
        return;
    }

    [$selector, $validator] = explode(':', $cookie, 2);

    if (
        !preg_match('/^[a-f0-9]{24}$/', $selector) ||
        !preg_match('/^[a-f0-9]{64}$/', $validator)
    ) {
        clearRememberCookie();
        return;
    }

    $pdo = Database::getConnection();

    $statement = $pdo->prepare(
        "SELECT
            rt.id AS token_id,
            rt.token_hash,
            rt.expires_at,
            u.id,
            u.username,
            u.full_name,
            u.role,
            u.is_active,
            u.must_change_password
         FROM remember_tokens rt
         INNER JOIN users u
            ON u.id = rt.user_id
         WHERE rt.selector = :selector
         LIMIT 1"
    );

    $statement->execute([
        'selector' => $selector
    ]);

    $record = $statement->fetch();

    if (!$record) {
        clearRememberCookie();
        return;
    }

    if (
        strtotime($record['expires_at']) < time() ||
        (int) $record['is_active'] !== 1
    ) {
        deleteRememberTokenBySelector($selector);
        clearRememberCookie();
        return;
    }

    $validatorHash = hash('sha256', $validator);

    if (!hash_equals($record['token_hash'], $validatorHash)) {
        deleteRememberTokenBySelector($selector);
        clearRememberCookie();
        return;
    }

    loginUser($record);

    deleteRememberTokenBySelector($selector);
    createRememberToken((int) $record['id']);
}

function logoutUser(bool $redirectAfterLogout = true): void
{
    $userId = currentUserId();

    deleteCurrentRememberToken();
    clearRememberCookie();

    if ($userId !== null) {
        logAuthenticationAction($userId, 'logout');
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookieParams = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $cookieParams['path'],
            $cookieParams['domain'],
            (bool) $cookieParams['secure'],
            (bool) $cookieParams['httponly']
        );
    }

    session_destroy();

    if ($redirectAfterLogout) {
        header('Location: ' . APP_URL . '/public/login.php');
        exit;
    }
}

function deleteCurrentRememberToken(): void
{
    $cookie = $_COOKIE['frc_remember'] ?? '';

    if (!is_string($cookie) || !str_contains($cookie, ':')) {
        return;
    }

    [$selector] = explode(':', $cookie, 2);

    deleteRememberTokenBySelector($selector);
}

function deleteRememberTokenBySelector(string $selector): void
{
    if ($selector === '') {
        return;
    }

    $pdo = Database::getConnection();

    $statement = $pdo->prepare(
        "DELETE FROM remember_tokens
         WHERE selector = :selector"
    );

    $statement->execute([
        'selector' => $selector
    ]);
}

function clearRememberCookie(): void
{
    setcookie(
        'frc_remember',
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    unset($_COOKIE['frc_remember']);
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        $returnUrl = $_SERVER['REQUEST_URI'] ?? '';

        $_SESSION['return_url'] = $returnUrl;

        header('Location: ' . APP_URL . '/public/login.php');
        exit;
    }

    enforceRequiredPasswordChange();
}

function enforceRequiredPasswordChange(): void
{
    $userId = currentUserId();

    if ($userId === null) {
        return;
    }

    $requestPath = parse_url(
        $_SERVER['REQUEST_URI'] ?? '',
        PHP_URL_PATH
    );

    $allowedPaths = [
        APP_URL . '/public/change-password.php',
        APP_URL . '/public/logout.php'
    ];

    if (
        is_string($requestPath) &&
        in_array($requestPath, $allowedPaths, true)
    ) {
        return;
    }

    $pdo = Database::getConnection();

    $statement = $pdo->prepare(
        "SELECT
            is_active,
            must_change_password
         FROM users
         WHERE id = :id
         LIMIT 1"
    );

    $statement->execute([
        'id' => $userId
    ]);

    $userState = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$userState || (int) $userState['is_active'] !== 1) {
        logoutUser(false);

        header('Location: ' . APP_URL . '/public/login.php');
        exit;
    }

    $_SESSION['must_change_password'] =
        (int) $userState['must_change_password'];

    if ((int) $userState['must_change_password'] === 1) {
        header(
            'Location: ' .
            APP_URL .
            '/public/change-password.php'
        );

        exit;
    }
}

function requireGuest(): void
{
    if (isLoggedIn()) {
        header('Location: ' . APP_URL . '/public/');
        exit;
    }
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : null;
}

function currentUsername(): ?string
{
    return isset($_SESSION['username'])
        ? (string) $_SESSION['username']
        : null;
}

function currentUserFullName(): ?string
{
    return isset($_SESSION['full_name'])
        ? (string) $_SESSION['full_name']
        : null;
}

function currentUserRole(): ?string
{
    return isset($_SESSION['user_role'])
        ? (string) $_SESSION['user_role']
        : null;
}

function isAdmin(): bool
{
    return currentUserRole() === 'admin';
}

function requireAdmin(): void
{
    requireLogin();

    if (!isAdmin()) {
        http_response_code(403);

        exit('אין לך הרשאה לבצע פעולה זו.');
    }
}

function isHttpsRequest(): bool
{
    return
        !empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off';
}

function getAuthClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function logAuthenticationAction(
    int $userId,
    string $action
): void {
    try {
        $pdo = Database::getConnection();

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
            'user_id' => $userId,
            'action' => $action,
            'entity_id' => $userId,
            'ip_address' => getAuthClientIp(),
            'user_agent' => substr(
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                0,
                500
            )
        ]);
    } catch (Throwable $exception) {
        error_log(
            'Authentication log error: ' .
            $exception->getMessage()
        );
    }
}