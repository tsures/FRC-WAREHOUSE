<?php

declare(strict_types=1);

function createPasswordResetToken(
    PDO $pdo,
    int $userId,
    int $validMinutes = 30
): string {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $pdo->beginTransaction();

    try {
        $deleteStatement = $pdo->prepare(
            "DELETE FROM password_reset_tokens
             WHERE user_id = :user_id
                OR expires_at < NOW()
                OR used_at IS NOT NULL"
        );

        $deleteStatement->execute([
            'user_id' => $userId
        ]);

        $insertStatement = $pdo->prepare(
            "INSERT INTO password_reset_tokens (
                user_id,
                token_hash,
                expires_at
            ) VALUES (
                :user_id,
                :token_hash,
                DATE_ADD(
                    NOW(),
                    INTERVAL {$validMinutes} MINUTE
                )
            )"
        );

        $insertStatement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash
        ]);

        $pdo->commit();

        return $token;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function getValidPasswordResetRecord(
    PDO $pdo,
    string $token
): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $tokenHash = hash('sha256', $token);

    $statement = $pdo->prepare(
        "SELECT
            prt.id,
            prt.user_id,
            prt.expires_at,
            u.username,
            u.full_name,
            u.email,
            u.is_active
         FROM password_reset_tokens prt
         INNER JOIN users u
            ON u.id = prt.user_id
         WHERE prt.token_hash = :token_hash
           AND prt.used_at IS NULL
           AND prt.expires_at > NOW()
         LIMIT 1"
    );

    $statement->execute([
        'token_hash' => $tokenHash
    ]);

    $record = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$record || (int) $record['is_active'] !== 1) {
        return null;
    }

    return $record;
}

function consumePasswordResetToken(
    PDO $pdo,
    int $tokenId,
    int $userId,
    string $newPassword
): void {
    $pdo->beginTransaction();

    try {
        $updateUserStatement = $pdo->prepare(
            "UPDATE users
             SET
                password_hash = :password_hash,
                must_change_password = 0,
                password_changed_at = NOW(),
                failed_login_attempts = 0,
                last_failed_login_at = NULL,
                locked_until = NULL,
                updated_by = :updated_by
             WHERE id = :id"
        );

        $updateUserStatement->execute([
            'password_hash' => password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            ),
            'updated_by' => $userId,
            'id' => $userId
        ]);

        $consumeStatement = $pdo->prepare(
            "UPDATE password_reset_tokens
             SET used_at = NOW()
             WHERE id = :id
               AND user_id = :user_id
               AND used_at IS NULL"
        );

        $consumeStatement->execute([
            'id' => $tokenId,
            'user_id' => $userId
        ]);

        if ($consumeStatement->rowCount() !== 1) {
            throw new RuntimeException(
                'קישור האיפוס כבר אינו תקף.'
            );
        }

        $deleteTokensStatement = $pdo->prepare(
            "DELETE FROM remember_tokens
             WHERE user_id = :user_id"
        );

        $deleteTokensStatement->execute([
            'user_id' => $userId
        ]);

        $invalidateOtherTokensStatement = $pdo->prepare(
            "UPDATE password_reset_tokens
             SET used_at = NOW()
             WHERE user_id = :user_id
               AND used_at IS NULL"
        );

        $invalidateOtherTokensStatement->execute([
            'user_id' => $userId
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sendPasswordResetEmail(
    string $email,
    string $fullName,
    string $resetUrl
): bool {
    $fromEmail = defined('MAIL_FROM_EMAIL')
        ? (string) MAIL_FROM_EMAIL
        : 'no-reply@artemis3083.co.il';

    $fromName = defined('MAIL_FROM_NAME')
        ? (string) MAIL_FROM_NAME
        : APP_NAME;

    $subject = 'איפוס סיסמה - ' . APP_NAME;

    $safeName = htmlspecialchars(
        $fullName,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safeUrl = htmlspecialchars(
        $resetUrl,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $message = '
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family:Arial,sans-serif;background:#f8fafc;padding:24px;color:#0f172a">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:24px">
        <h2 style="margin-top:0">איפוס סיסמה</h2>
        <p>שלום ' . $safeName . ',</p>
        <p>התקבלה בקשה לאיפוס הסיסמה שלך במערכת ' .
            htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') .
        '.</p>
        <p style="margin:28px 0">
            <a
                href="' . $safeUrl . '"
                style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:bold"
            >
                איפוס סיסמה
            </a>
        </p>
        <p>הקישור תקף למשך 30 דקות וניתן לשימוש פעם אחת בלבד.</p>
        <p>אם לא ביקשת לאפס את הסיסמה, ניתן להתעלם מהודעה זו.</p>
        <hr style="border:0;border-top:1px solid #e2e8f0;margin:24px 0">
        <p style="font-size:12px;color:#64748b;word-break:break-all">' .
            $safeUrl .
        '</p>
    </div>
</body>
</html>';

    $encodedSubject = '=?UTF-8?B?' .
        base64_encode($subject) .
        '?=';

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'X-Mailer: PHP/' . PHP_VERSION
    ];

    return mail(
        $email,
        $encodedSubject,
        $message,
        implode("\r\n", $headers)
    );
}

function initializeForgotPasswordAntiBot(): array
{
    $token = bin2hex(random_bytes(16));
    $issuedAt = time();

    $_SESSION['forgot_password_antibot'] = [
        'token' => $token,
        'issued_at' => $issuedAt
    ];

    return [
        'token' => $token,
        'issued_at' => $issuedAt
    ];
}

function validateForgotPasswordAntiBot(
    ?string $token,
    string $honeypot
): bool {
    $state = $_SESSION['forgot_password_antibot'] ?? null;

    unset($_SESSION['forgot_password_antibot']);

    if ($honeypot !== '') {
        return false;
    }

    if (
        !is_array($state) ||
        empty($state['token']) ||
        empty($state['issued_at']) ||
        $token === null ||
        !hash_equals((string) $state['token'], $token)
    ) {
        return false;
    }

    $elapsed = time() - (int) $state['issued_at'];

    return $elapsed >= 2 && $elapsed <= 1800;
}
