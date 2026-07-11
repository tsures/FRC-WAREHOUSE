<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/location_helpers.php';

startSecureSession();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('שיטת בקשה לא נתמכת.', 405);
}

$data = getJsonRequestBody();

$csrfToken = $data['csrf_token'] ?? null;

if (
    !is_string($csrfToken) ||
    !validateCsrfToken($csrfToken)
) {
    jsonError(
        'הבקשה אינה חוקית או שפג תוקפה.',
        419
    );
}

$locationId = isset($data['id']) && $data['id'] !== ''
    ? (int) $data['id']
    : null;

$parentId = isset($data['parent_id']) && $data['parent_id'] !== ''
    ? (int) $data['parent_id']
    : null;

$name = trim((string) ($data['name'] ?? ''));
$code = trim((string) ($data['code'] ?? ''));
$description = trim((string) ($data['description'] ?? ''));
$locationType = trim(
    (string) ($data['location_type'] ?? 'other')
);
$sortOrder = (int) ($data['sort_order'] ?? 0);

if ($locationId !== null && $locationId <= 0) {
    jsonError('מזהה המיקום אינו תקין.');
}

if ($parentId !== null && $parentId <= 0) {
    jsonError('מזהה מיקום האב אינו תקין.');
}

if ($name === '') {
    jsonError('יש להזין שם מיקום.');
}

if (mb_strlen($name) > 150) {
    jsonError('שם המיקום ארוך מדי.');
}

if (mb_strlen($code) > 80) {
    jsonError('קוד המיקום ארוך מדי.');
}

if (mb_strlen($description) > 10000) {
    jsonError('תיאור המיקום ארוך מדי.');
}

if (!isValidLocationType($locationType)) {
    jsonError('סוג המיקום שנבחר אינו חוקי.');
}

if ($sortOrder < 0) {
    $sortOrder = 0;
}

$normalizedCode = normalizeLocationCode($code);

if ($code !== '' && $normalizedCode === null) {
    jsonError(
        'קוד המיקום חייב להכיל אותיות באנגלית, מספרים, מקף או קו תחתון.'
    );
}

$pdo = Database::getConnection();

try {
    /*
     * בדיקת קיום המיקום בזמן עריכה.
     */
    $existingLocation = null;

    if ($locationId !== null) {
        $existingLocation = getLocationById(
            $pdo,
            $locationId
        );

        if ($existingLocation === null) {
            jsonError('המיקום לא נמצא.', 404);
        }
    }

    /*
     * בדיקת מיקום האב.
     */
    if (
        $parentId !== null &&
        getLocationById($pdo, $parentId) === null
    ) {
        jsonError(
            'מיקום האב שנבחר אינו קיים.'
        );
    }

    /*
     * מניעת שיוך המיקום לעצמו או לאחד מצאצאיו.
     */
    if (
        !isValidLocationParent(
            $pdo,
            $parentId,
            $locationId
        )
    ) {
        jsonError(
            'לא ניתן להגדיר את המיקום כמיקום אב של עצמו או של אחד מצאצאיו.'
        );
    }

    /*
     * בדיקת קוד ייחודי.
     */
    if (
        $normalizedCode !== null &&
        locationCodeExists(
            $pdo,
            $normalizedCode,
            $locationId
        )
    ) {
        jsonError(
            'קוד המיקום כבר נמצא בשימוש.'
        );
    }

    /*
     * מניעת שם כפול תחת אותו מיקום אב.
     */
    if (
        locationNameExistsUnderParent(
            $pdo,
            $name,
            $parentId,
            $locationId
        )
    ) {
        jsonError(
            'כבר קיים מיקום בשם זה תחת אותו מיקום אב.'
        );
    }

    $pdo->beginTransaction();

    if ($locationId === null) {
        $locationId = createLocation(
            $pdo,
            $name,
            $normalizedCode,
            $description !== '' ? $description : null,
            $locationType,
            $parentId,
            $sortOrder,
            true,
            currentUserId()
        );

        $action = 'location_created';
        $message = 'המיקום נוסף בהצלחה.';
    } else {
        /*
         * מצב הפעילות נשמר כפי שהוא.
         * הפעלה והשבתה יתבצעו דרך toggle.php.
         */
        $currentIsActive = isset(
            $existingLocation['is_active']
        )
            ? (bool) $existingLocation['is_active']
            : true;

        updateLocation(
            $pdo,
            $locationId,
            $name,
            $normalizedCode,
            $description !== '' ? $description : null,
            $locationType,
            $parentId,
            $sortOrder,
            $currentIsActive
        );

        $action = 'location_updated';
        $message = 'המיקום עודכן בהצלחה.';
    }

    $savedLocation = getLocationById(
        $pdo,
        $locationId
    );

    $logStatement = $pdo->prepare(
        "INSERT INTO activity_logs (
            user_id,
            action,
            entity_type,
            entity_id,
            new_values,
            ip_address,
            user_agent
        ) VALUES (
            :user_id,
            :action,
            'location',
            :entity_id,
            :new_values,
            :ip_address,
            :user_agent
        )"
    );

    $logStatement->execute([
        'user_id' => currentUserId(),
        'action' => $action,
        'entity_id' => $locationId,
        'new_values' => json_encode(
            [
                'name' => $name,
                'code' => $normalizedCode,
                'description' => (
                    $description !== ''
                        ? $description
                        : null
                ),
                'location_type' => $locationType,
                'parent_id' => $parentId,
                'sort_order' => $sortOrder,
            ],
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ),
        'ip_address' => getClientIp(),
        'user_agent' => substr(
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            0,
            500
        ),
    ]);

    $pdo->commit();

    if ($savedLocation !== null) {
        $savedLocation['id'] = (int) $savedLocation['id'];

        $savedLocation['parent_id'] =
            $savedLocation['parent_id'] !== null
                ? (int) $savedLocation['parent_id']
                : null;

        $savedLocation['sort_order'] = (int) (
            $savedLocation['sort_order'] ?? 0
        );

        $savedLocation['is_active'] = (bool) (
            $savedLocation['is_active'] ?? false
        );

        $savedLocation['type_label'] =
            getLocationTypeLabel(
                (string) $savedLocation['location_type']
            );

        $savedLocation['type_icon'] =
            getLocationTypeIcon(
                (string) $savedLocation['location_type']
            );

        $savedLocation['path'] = getLocationPath(
            $pdo,
            $locationId
        );
    }

    jsonSuccess(
        [
            'id' => $locationId,
            'location' => $savedLocation,
        ],
        $message
    );
} catch (InvalidArgumentException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonError(
        $exception->getMessage(),
        422
    );
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        sprintf(
            '[Locations Save API - Database] %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    /*
     * הגנה נוספת למקרה של התנגשות באינדקס הקוד
     * הייחודי בין שתי בקשות מקבילות.
     */
    if (
        $exception->getCode() === '23000' &&
        str_contains(
            $exception->getMessage(),
            'uq_locations_code'
        )
    ) {
        jsonError(
            'קוד המיקום כבר נמצא בשימוש.',
            409
        );
    }

    jsonError(
        'לא ניתן לשמור את המיקום.',
        500
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        sprintf(
            '[Locations Save API] %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    jsonError(
        'לא ניתן לשמור את המיקום.',
        500
    );
}