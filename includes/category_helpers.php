<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function getAllCategories(bool $includeInactive = true): array
{
    $pdo = Database::getConnection();

    $sql = "
        SELECT
            c.id,
            c.parent_id,
            c.name_he,
            c.name_en,
            c.description,
            c.icon,
            c.color,
            c.sort_order,
            c.is_active,
            c.created_at,
            c.updated_at,
            parent.name_he AS parent_name
        FROM categories c
        LEFT JOIN categories parent
            ON parent.id = c.parent_id
    ";

    if (!$includeInactive) {
        $sql .= " WHERE c.is_active = 1 ";
    }

    $sql .= "
        ORDER BY
            c.sort_order ASC,
            c.name_he ASC
    ";

    return $pdo->query($sql)->fetchAll();
}

function buildCategoryTree(
    array $categories,
    ?int $parentId = null
): array {
    $tree = [];

    foreach ($categories as $category) {
        $categoryParentId = $category['parent_id'] !== null
            ? (int) $category['parent_id']
            : null;

        if ($categoryParentId !== $parentId) {
            continue;
        }

        $category['children'] = buildCategoryTree(
            $categories,
            (int) $category['id']
        );

        $tree[] = $category;
    }

    return $tree;
}

function flattenCategoryTree(
    array $tree,
    int $depth = 0
): array {
    $result = [];

    foreach ($tree as $category) {
        $category['depth'] = $depth;
        $children = $category['children'] ?? [];

        unset($category['children']);

        $result[] = $category;

        if ($children !== []) {
            $result = array_merge(
                $result,
                flattenCategoryTree($children, $depth + 1)
            );
        }
    }

    return $result;
}

function getCategoryById(int $categoryId): ?array
{
    $pdo = Database::getConnection();

    $statement = $pdo->prepare(
        "SELECT *
         FROM categories
         WHERE id = :id
         LIMIT 1"
    );

    $statement->execute([
        'id' => $categoryId
    ]);

    $category = $statement->fetch();

    return $category ?: null;
}

function categoryHasChildren(int $categoryId): bool
{
    $pdo = Database::getConnection();

    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM categories
         WHERE parent_id = :parent_id"
    );

    $statement->execute([
        'parent_id' => $categoryId
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function categoryHasItems(int $categoryId): bool
{
    $pdo = Database::getConnection();

    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM inventory_items
         WHERE category_id = :category_id
           AND is_active = 1"
    );

    $statement->execute([
        'category_id' => $categoryId
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function getCategoryDescendantIds(int $categoryId): array
{
    $categories = getAllCategories(true);

    $childrenMap = [];

    foreach ($categories as $category) {
        $parentId = $category['parent_id'] !== null
            ? (int) $category['parent_id']
            : null;

        if ($parentId === null) {
            continue;
        }

        $childrenMap[$parentId][] = (int) $category['id'];
    }

    $descendants = [];
    $queue = $childrenMap[$categoryId] ?? [];

    while ($queue !== []) {
        $currentId = array_shift($queue);

        if (in_array($currentId, $descendants, true)) {
            continue;
        }

        $descendants[] = $currentId;

        foreach ($childrenMap[$currentId] ?? [] as $childId) {
            $queue[] = $childId;
        }
    }

    return $descendants;
}

function isValidCategoryParent(
    int $categoryId,
    ?int $parentId
): bool {
    if ($parentId === null) {
        return true;
    }

    if ($categoryId === $parentId) {
        return false;
    }

    $descendants = getCategoryDescendantIds($categoryId);

    return !in_array($parentId, $descendants, true);
}