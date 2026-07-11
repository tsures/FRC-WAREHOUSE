<?php

declare(strict_types=1);

/**
 * פונקציות עזר עבור מודול המיקומים.
 *
 * סוגי מיקומים נתמכים:
 * warehouse, room, cabinet, shelf, bin, other
 */

/**
 * מחזיר את סוגי המיקומים ואת שמותיהם בעברית.
 *
 * @return array<string, string>
 */
function getLocationTypes(): array
{
    return [
        'warehouse' => 'מחסן',
        'room'      => 'חדר',
        'cabinet'   => 'ארון',
        'shelf'     => 'מדף',
        'bin'       => 'תא',
        'other'     => 'אחר',
    ];
}

/**
 * בודק אם סוג המיקום חוקי.
 */
function isValidLocationType(string $locationType): bool
{
    return array_key_exists($locationType, getLocationTypes());
}

/**
 * מחזיר את שם סוג המיקום בעברית.
 */
function getLocationTypeLabel(string $locationType): string
{
    $types = getLocationTypes();

    return $types[$locationType] ?? 'אחר';
}

/**
 * מחזיר אייקון לפי סוג מיקום.
 */
function getLocationTypeIcon(string $locationType): string
{
    return match ($locationType) {
        'warehouse' => '🏭',
        'room'      => '🚪',
        'cabinet'   => '🗄️',
        'shelf'     => '📚',
        'bin'       => '📦',
        default     => '📍',
    };
}

/**
 * מנרמל קוד מיקום.
 */
function normalizeLocationCode(?string $code): ?string
{
    if ($code === null) {
        return null;
    }

    $code = trim($code);

    if ($code === '') {
        return null;
    }

    $code = strtoupper($code);
    $code = preg_replace('/\s+/u', '-', $code);
    $code = preg_replace('/[^A-Z0-9\-_]/', '', $code);
    $code = preg_replace('/-+/', '-', $code);
    $code = trim($code, '-_');

    return $code !== '' ? $code : null;
}

/**
 * מחזיר מיקום לפי מזהה.
 *
 * @return array<string, mixed>|null
 */
function getLocationById(PDO $pdo, int $locationId): ?array
{
    if ($locationId <= 0) {
        return null;
    }

    $sql = '
        SELECT
            id,
            parent_id,
            name,
            code,
            description,
            location_type,
            sort_order,
            is_active,
            created_by,
            created_at,
            updated_at
        FROM locations
        WHERE id = :id
        LIMIT 1
    ';

    $statement = $pdo->prepare($sql);
    $statement->execute([
        'id' => $locationId,
    ]);

    $location = $statement->fetch(PDO::FETCH_ASSOC);

    return $location !== false ? $location : null;
}

/**
 * מחזיר את כל המיקומים.
 *
 * $activeOnly:
 * true  = פעילים בלבד
 * false = לא פעילים בלבד
 * null  = כולם
 *
 * @return array<int, array<string, mixed>>
 */
function getAllLocations(PDO $pdo, ?bool $activeOnly = null): array
{
    $sql = '
        SELECT
            l.id,
            l.parent_id,
            l.name,
            l.code,
            l.description,
            l.location_type,
            l.sort_order,
            l.is_active,
            l.created_by,
            l.created_at,
            l.updated_at,
            (
                SELECT COUNT(*)
                FROM locations child
                WHERE child.parent_id = l.id
            ) AS direct_children_count
        FROM locations l
    ';

    $parameters = [];

    if ($activeOnly !== null) {
        $sql .= ' WHERE l.is_active = :is_active';

        $parameters['is_active'] = $activeOnly ? 1 : 0;
    }

    $sql .= '
        ORDER BY
            l.sort_order ASC,
            l.name ASC,
            l.id ASC
    ';

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * מחזיר את המיקומים הישירים הנמצאים תחת מיקום מסוים.
 *
 * parentId=null מחזיר את מיקומי השורש.
 *
 * @return array<int, array<string, mixed>>
 */
function getDirectChildLocations(
    PDO $pdo,
    ?int $parentId,
    ?bool $activeOnly = null
): array {
    if ($parentId === null) {
        $sql = '
            SELECT
                id,
                parent_id,
                name,
                code,
                description,
                location_type,
                sort_order,
                is_active,
                created_by,
                created_at,
                updated_at
            FROM locations
            WHERE parent_id IS NULL
        ';

        $parameters = [];
    } else {
        $sql = '
            SELECT
                id,
                parent_id,
                name,
                code,
                description,
                location_type,
                sort_order,
                is_active,
                created_by,
                created_at,
                updated_at
            FROM locations
            WHERE parent_id = :parent_id
        ';

        $parameters = [
            'parent_id' => $parentId,
        ];
    }

    if ($activeOnly !== null) {
        $sql .= ' AND is_active = :is_active';

        $parameters['is_active'] = $activeOnly ? 1 : 0;
    }

    $sql .= '
        ORDER BY
            sort_order ASC,
            name ASC,
            id ASC
    ';

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * בונה עץ היררכי מתוך רשימת מיקומים.
 *
 * @param array<int, array<string, mixed>> $locations
 * @return array<int, array<string, mixed>>
 */
function buildLocationsTree(
    array $locations,
    ?int $parentId = null,
    int $depth = 0
): array {
    $tree = [];

    foreach ($locations as $location) {
        $locationParentId = $location['parent_id'] !== null
            ? (int) $location['parent_id']
            : null;

        if ($locationParentId !== $parentId) {
            continue;
        }

        $locationId = (int) $location['id'];

        $location['id'] = $locationId;
        $location['parent_id'] = $locationParentId;
        $location['sort_order'] = (int) $location['sort_order'];
        $location['is_active'] = (bool) $location['is_active'];
        $location['depth'] = $depth;
        $location['type_label'] = getLocationTypeLabel(
            (string) $location['location_type']
        );
        $location['type_icon'] = getLocationTypeIcon(
            (string) $location['location_type']
        );

        $location['children'] = buildLocationsTree(
            $locations,
            $locationId,
            $depth + 1
        );

        $location['children_count'] = count($location['children']);

        $tree[] = $location;
    }

    return $tree;
}

/**
 * משטח עץ מיקומים לרשימה.
 *
 * שימושי עבור Select של בחירת מיקום אב.
 *
 * @param array<int, array<string, mixed>> $tree
 * @return array<int, array<string, mixed>>
 */
function flattenLocationsTree(array $tree): array
{
    $result = [];

    foreach ($tree as $location) {
        $children = $location['children'] ?? [];

        unset($location['children']);

        $result[] = $location;

        if (is_array($children) && $children !== []) {
            $result = array_merge(
                $result,
                flattenLocationsTree($children)
            );
        }
    }

    return $result;
}

/**
 * מחזיר את כל מזהי הצאצאים של מיקום.
 *
 * @return array<int, int>
 */
function getLocationDescendantIds(PDO $pdo, int $locationId): array
{
    if ($locationId <= 0) {
        return [];
    }

    $allLocations = getAllLocations($pdo);
    $childrenMap = [];

    foreach ($allLocations as $location) {
        if ($location['parent_id'] === null) {
            continue;
        }

        $parentId = (int) $location['parent_id'];
        $childId = (int) $location['id'];

        if (!isset($childrenMap[$parentId])) {
            $childrenMap[$parentId] = [];
        }

        $childrenMap[$parentId][] = $childId;
    }

    $descendantIds = [];
    $queue = $childrenMap[$locationId] ?? [];

    while ($queue !== []) {
        $currentId = array_shift($queue);

        if (!is_int($currentId)) {
            $currentId = (int) $currentId;
        }

        if (in_array($currentId, $descendantIds, true)) {
            continue;
        }

        $descendantIds[] = $currentId;

        if (isset($childrenMap[$currentId])) {
            foreach ($childrenMap[$currentId] as $childId) {
                $queue[] = $childId;
            }
        }
    }

    return $descendantIds;
}

/**
 * בודק אם מיקום קיים.
 */
function locationExists(PDO $pdo, int $locationId): bool
{
    if ($locationId <= 0) {
        return false;
    }

    $statement = $pdo->prepare(
        'SELECT 1 FROM locations WHERE id = :id LIMIT 1'
    );

    $statement->execute([
        'id' => $locationId,
    ]);

    return $statement->fetchColumn() !== false;
}

/**
 * בודק אם קוד מיקום כבר קיים.
 *
 * ניתן להעביר מזהה מיקום להתעלמות בזמן עריכה.
 */
function locationCodeExists(
    PDO $pdo,
    string $code,
    ?int $excludeLocationId = null
): bool {
    $normalizedCode = normalizeLocationCode($code);

    if ($normalizedCode === null) {
        return false;
    }

    $sql = '
        SELECT 1
        FROM locations
        WHERE code = :code
    ';

    $parameters = [
        'code' => $normalizedCode,
    ];

    if ($excludeLocationId !== null && $excludeLocationId > 0) {
        $sql .= ' AND id <> :exclude_id';

        $parameters['exclude_id'] = $excludeLocationId;
    }

    $sql .= ' LIMIT 1';

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchColumn() !== false;
}

/**
 * בודק אם שם מיקום כבר קיים תחת אותו מיקום אב.
 *
 * מותר להשתמש באותו שם בענפים שונים של ההיררכיה.
 */
function locationNameExistsUnderParent(
    PDO $pdo,
    string $name,
    ?int $parentId,
    ?int $excludeLocationId = null
): bool {
    $name = trim($name);

    if ($name === '') {
        return false;
    }

    $sql = '
        SELECT 1
        FROM locations
        WHERE name = :name
    ';

    $parameters = [
        'name' => $name,
    ];

    if ($parentId === null) {
        $sql .= ' AND parent_id IS NULL';
    } else {
        $sql .= ' AND parent_id = :parent_id';

        $parameters['parent_id'] = $parentId;
    }

    if ($excludeLocationId !== null && $excludeLocationId > 0) {
        $sql .= ' AND id <> :exclude_id';

        $parameters['exclude_id'] = $excludeLocationId;
    }

    $sql .= ' LIMIT 1';

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchColumn() !== false;
}

/**
 * בודק אם מיקום האב המבוקש חוקי.
 *
 * מונע:
 * - שיוך מיקום לעצמו
 * - שיוך מיקום לאחד מצאצאיו
 * - שיוך למיקום שאינו קיים
 */
function isValidLocationParent(
    PDO $pdo,
    ?int $parentId,
    ?int $locationId = null
): bool {
    if ($parentId === null) {
        return true;
    }

    if ($parentId <= 0) {
        return false;
    }

    if (!locationExists($pdo, $parentId)) {
        return false;
    }

    if ($locationId === null || $locationId <= 0) {
        return true;
    }

    if ($parentId === $locationId) {
        return false;
    }

    $descendantIds = getLocationDescendantIds($pdo, $locationId);

    return !in_array($parentId, $descendantIds, true);
}

/**
 * מחזיר את המסלול המלא למיקום.
 *
 * לדוגמה:
 * מחסן ראשי ← חדר אלקטרוניקה ← ארון 1 ← מדף A
 *
 * @return array<int, array<string, mixed>>
 */
function getLocationBreadcrumbs(PDO $pdo, int $locationId): array
{
    if ($locationId <= 0) {
        return [];
    }

    $breadcrumbs = [];
    $visitedIds = [];
    $currentId = $locationId;

    while ($currentId > 0) {
        if (in_array($currentId, $visitedIds, true)) {
            break;
        }

        $visitedIds[] = $currentId;

        $location = getLocationById($pdo, $currentId);

        if ($location === null) {
            break;
        }

        array_unshift($breadcrumbs, $location);

        if ($location['parent_id'] === null) {
            break;
        }

        $currentId = (int) $location['parent_id'];
    }

    return $breadcrumbs;
}

/**
 * מחזיר את המסלול המלא של מיקום כמחרוזת.
 */
function getLocationPath(
    PDO $pdo,
    int $locationId,
    string $separator = ' ← '
): string {
    $breadcrumbs = getLocationBreadcrumbs($pdo, $locationId);

    if ($breadcrumbs === []) {
        return '';
    }

    $names = array_map(
        static fn(array $location): string => (string) $location['name'],
        $breadcrumbs
    );

    return implode($separator, $names);
}

/**
 * סופר ילדים ישירים של מיקום.
 */
function countDirectLocationChildren(PDO $pdo, int $locationId): int
{
    if ($locationId <= 0) {
        return 0;
    }

    $statement = $pdo->prepare(
        '
            SELECT COUNT(*)
            FROM locations
            WHERE parent_id = :parent_id
        '
    );

    $statement->execute([
        'parent_id' => $locationId,
    ]);

    return (int) $statement->fetchColumn();
}

/**
 * יוצר מיקום חדש.
 *
 * @return int מזהה המיקום החדש
 */
function createLocation(
    PDO $pdo,
    string $name,
    ?string $code,
    ?string $description,
    string $locationType,
    ?int $parentId,
    int $sortOrder,
    bool $isActive,
    ?int $createdBy
): int {
    $name = trim($name);
    $code = normalizeLocationCode($code);
    $description = $description !== null
        ? trim($description)
        : null;

    if ($name === '') {
        throw new InvalidArgumentException('חובה להזין שם מיקום.');
    }

    if (mb_strlen($name) > 150) {
        throw new InvalidArgumentException(
            'שם המיקום יכול להכיל עד 150 תווים.'
        );
    }

    if ($code !== null && strlen($code) > 80) {
        throw new InvalidArgumentException(
            'קוד המיקום יכול להכיל עד 80 תווים.'
        );
    }

    if (!isValidLocationType($locationType)) {
        throw new InvalidArgumentException('סוג המיקום אינו חוקי.');
    }

    if (!isValidLocationParent($pdo, $parentId)) {
        throw new InvalidArgumentException('מיקום האב שנבחר אינו חוקי.');
    }

    if (
        $code !== null
        && locationCodeExists($pdo, $code)
    ) {
        throw new InvalidArgumentException(
            'קוד המיקום כבר נמצא בשימוש.'
        );
    }

    if (
        locationNameExistsUnderParent(
            $pdo,
            $name,
            $parentId
        )
    ) {
        throw new InvalidArgumentException(
            'כבר קיים מיקום בשם זה תחת אותו מיקום אב.'
        );
    }

    $sql = '
        INSERT INTO locations (
            parent_id,
            name,
            code,
            description,
            location_type,
            sort_order,
            is_active,
            created_by
        ) VALUES (
            :parent_id,
            :name,
            :code,
            :description,
            :location_type,
            :sort_order,
            :is_active,
            :created_by
        )
    ';

    $statement = $pdo->prepare($sql);
    $statement->execute([
        'parent_id'    => $parentId,
        'name'         => $name,
        'code'         => $code,
        'description'  => $description !== '' ? $description : null,
        'location_type'=> $locationType,
        'sort_order'   => max(0, $sortOrder),
        'is_active'    => $isActive ? 1 : 0,
        'created_by'   => $createdBy,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * מעדכן מיקום קיים.
 */
function updateLocation(
    PDO $pdo,
    int $locationId,
    string $name,
    ?string $code,
    ?string $description,
    string $locationType,
    ?int $parentId,
    int $sortOrder,
    bool $isActive
): bool {
    if ($locationId <= 0 || !locationExists($pdo, $locationId)) {
        throw new InvalidArgumentException('המיקום המבוקש אינו קיים.');
    }

    $name = trim($name);
    $code = normalizeLocationCode($code);
    $description = $description !== null
        ? trim($description)
        : null;

    if ($name === '') {
        throw new InvalidArgumentException('חובה להזין שם מיקום.');
    }

    if (mb_strlen($name) > 150) {
        throw new InvalidArgumentException(
            'שם המיקום יכול להכיל עד 150 תווים.'
        );
    }

    if ($code !== null && strlen($code) > 80) {
        throw new InvalidArgumentException(
            'קוד המיקום יכול להכיל עד 80 תווים.'
        );
    }

    if (!isValidLocationType($locationType)) {
        throw new InvalidArgumentException('סוג המיקום אינו חוקי.');
    }

    if (
        !isValidLocationParent(
            $pdo,
            $parentId,
            $locationId
        )
    ) {
        throw new InvalidArgumentException(
            'לא ניתן לשייך מיקום לעצמו או לאחד מצאצאיו.'
        );
    }

    if (
        $code !== null
        && locationCodeExists(
            $pdo,
            $code,
            $locationId
        )
    ) {
        throw new InvalidArgumentException(
            'קוד המיקום כבר נמצא בשימוש.'
        );
    }

    if (
        locationNameExistsUnderParent(
            $pdo,
            $name,
            $parentId,
            $locationId
        )
    ) {
        throw new InvalidArgumentException(
            'כבר קיים מיקום בשם זה תחת אותו מיקום אב.'
        );
    }

    $sql = '
        UPDATE locations
        SET
            parent_id = :parent_id,
            name = :name,
            code = :code,
            description = :description,
            location_type = :location_type,
            sort_order = :sort_order,
            is_active = :is_active
        WHERE id = :id
    ';

    $statement = $pdo->prepare($sql);

    return $statement->execute([
        'parent_id'     => $parentId,
        'name'          => $name,
        'code'          => $code,
        'description'   => $description !== '' ? $description : null,
        'location_type' => $locationType,
        'sort_order'    => max(0, $sortOrder),
        'is_active'     => $isActive ? 1 : 0,
        'id'            => $locationId,
    ]);
}

/**
 * משנה את מצב המיקום: פעיל או מושבת.
 */
function setLocationActiveStatus(
    PDO $pdo,
    int $locationId,
    bool $isActive
): bool {
    if ($locationId <= 0 || !locationExists($pdo, $locationId)) {
        throw new InvalidArgumentException('המיקום המבוקש אינו קיים.');
    }

    $statement = $pdo->prepare(
        '
            UPDATE locations
            SET is_active = :is_active
            WHERE id = :id
        '
    );

    return $statement->execute([
        'is_active' => $isActive ? 1 : 0,
        'id'        => $locationId,
    ]);
}