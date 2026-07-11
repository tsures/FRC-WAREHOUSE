<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/location_helpers.php';

startSecureSession();
requireAdmin();

if (!isGetRequest()) {
    jsonError('שיטת הבקשה אינה נתמכת.', 405);
}

try {
    $pdo = Database::getConnection();

    $search = trim((string) ($_GET['search'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? 'all'));
    $view = trim((string) ($_GET['view'] ?? 'tree'));

    $activeFilter = match ($status) {
        'active'   => true,
        'inactive' => false,
        default    => null,
    };

    if (!in_array($view, ['tree', 'flat'], true)) {
        $view = 'tree';
    }

    $locations = getAllLocations($pdo, $activeFilter);

    /*
     * חיפוש מתבצע על:
     * - שם המיקום
     * - קוד המיקום
     * - תיאור
     * - סוג המיקום בעברית ובאנגלית
     */
    if ($search !== '') {
        $normalizedSearch = mb_strtolower($search, 'UTF-8');

        $locations = array_values(
            array_filter(
                $locations,
                static function (array $location) use (
                    $normalizedSearch
                ): bool {
                    $name = mb_strtolower(
                        (string) ($location['name'] ?? ''),
                        'UTF-8'
                    );

                    $code = mb_strtolower(
                        (string) ($location['code'] ?? ''),
                        'UTF-8'
                    );

                    $description = mb_strtolower(
                        (string) ($location['description'] ?? ''),
                        'UTF-8'
                    );

                    $locationType = mb_strtolower(
                        (string) ($location['location_type'] ?? ''),
                        'UTF-8'
                    );

                    $typeLabel = mb_strtolower(
                        getLocationTypeLabel($locationType),
                        'UTF-8'
                    );

                    return mb_strpos(
                        $name,
                        $normalizedSearch
                    ) !== false
                        || mb_strpos(
                            $code,
                            $normalizedSearch
                        ) !== false
                        || mb_strpos(
                            $description,
                            $normalizedSearch
                        ) !== false
                        || mb_strpos(
                            $locationType,
                            $normalizedSearch
                        ) !== false
                        || mb_strpos(
                            $typeLabel,
                            $normalizedSearch
                        ) !== false;
                }
            )
        );
    }

    /*
     * הוספת נתונים מחושבים לכל מיקום.
     */
    foreach ($locations as &$location) {
        $locationId = (int) $location['id'];

        $location['id'] = $locationId;

        $location['parent_id'] = $location['parent_id'] !== null
            ? (int) $location['parent_id']
            : null;

        $location['sort_order'] = (int) (
            $location['sort_order'] ?? 0
        );

        $location['is_active'] = (bool) (
            $location['is_active'] ?? false
        );

        $location['direct_children_count'] = (int) (
            $location['direct_children_count'] ?? 0
        );

        $location['type_label'] = getLocationTypeLabel(
            (string) $location['location_type']
        );

        $location['type_icon'] = getLocationTypeIcon(
            (string) $location['location_type']
        );

        $location['path'] = getLocationPath(
            $pdo,
            $locationId
        );
    }

    unset($location);

    /*
     * בעת חיפוש מחזירים רשימה שטוחה כדי שלא לאבד תוצאות
     * שההורה שלהן לא נמצא בתוצאת החיפוש.
     */
    if ($search !== '' || $view === 'flat') {
        $result = $locations;
    } else {
        $result = buildLocationsTree($locations);
    }

    $allLocations = getAllLocations($pdo);

    $totalCount = count($allLocations);
    $activeCount = 0;
    $inactiveCount = 0;
    $rootCount = 0;

    foreach ($allLocations as $location) {
        if ((bool) $location['is_active']) {
            $activeCount++;
        } else {
            $inactiveCount++;
        }

        if ($location['parent_id'] === null) {
            $rootCount++;
        }
    }

    jsonSuccess(
        [
            'locations' => $result,
            'location_types' => getLocationTypes(),
            'meta' => [
                'view' => $search !== ''
                    ? 'flat'
                    : $view,
                'search' => $search,
                'status' => $status,
                'returned_count' => count($locations),
                'total_count' => $totalCount,
                'active_count' => $activeCount,
                'inactive_count' => $inactiveCount,
                'root_count' => $rootCount,
            ],
        ],
        'המיקומים נטענו בהצלחה.'
    );
} catch (Throwable $exception) {
    jsonError(
        'שגיאה: ' . $exception->getMessage(),
        500,
        [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]
    );
}
