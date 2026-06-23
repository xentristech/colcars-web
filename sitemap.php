<?php
/**
 * Dynamic XML sitemap for ColCars / Easy Car Luxury.
 *
 * Important:
 * - Keep this file at the project root, next to .htaccess and config/.
 * - Serve it publicly as /sitemap.xml using the .htaccess rewrite rule.
 */

header('Content-Type: application/xml; charset=UTF-8');

require_once __DIR__ . '/config/database.php';

$baseUrl = 'https://colcars.com';
$today = date('Y-m-d');

function xml_escape($value) {
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function normalize_slug($text) {
    $text = trim((string) $text);
    if ($text === '') {
        return 'vehiculo';
    }

    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($converted !== false) {
        $text = $converted;
    }

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');

    return $text !== '' ? $text : 'vehiculo';
}

function valid_lastmod($value, $fallback) {
    if (empty($value)) {
        return $fallback;
    }

    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return $fallback;
    }

    return date('Y-m-d', $timestamp);
}

try {
    $database = Database::getInstance();
    $pdo = $database->getConnection();

    $categoriesStmt = $pdo->query(
        "SELECT id, COALESCE(updated_at, created_at) AS lastmod
         FROM categorias
         WHERE activo = 1
         ORDER BY orden ASC, id ASC"
    );
    $categories = $categoriesStmt ? $categoriesStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $publicationsStmt = $pdo->query(
        "SELECT p.id, p.titulo, p.slug, p.updated_at, p.created_at
         FROM publicaciones p
         INNER JOIN usuarios u ON p.usuario_id = u.id
         WHERE p.status = 'active' AND u.activo = 1
         ORDER BY p.updated_at DESC, p.id DESC
         LIMIT 10000"
    );
    $publications = $publicationsStmt ? $publicationsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    error_log('Sitemap generation error: ' . $e->getMessage());
    $categories = [];
    $publications = [];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?php echo xml_escape($baseUrl . '/'); ?></loc>
        <lastmod><?php echo $today; ?></lastmod>
    </url>
    <url>
        <loc><?php echo xml_escape($baseUrl . '/catalog'); ?></loc>
        <lastmod><?php echo $today; ?></lastmod>
    </url>
<?php foreach ($categories as $cat): ?>
    <url>
        <loc><?php echo xml_escape($baseUrl . '/catalog/category/' . (int) $cat['id']); ?></loc>
        <lastmod><?php echo valid_lastmod($cat['lastmod'] ?? null, $today); ?></lastmod>
    </url>
<?php endforeach; ?>
<?php foreach ($publications as $pub):
    $slug = !empty($pub['slug']) ? normalize_slug($pub['slug']) : normalize_slug($pub['titulo'] ?? '');
    $loc = $baseUrl . '/vehicle/' . (int) $pub['id'] . '/' . $slug;
    $lastmod = valid_lastmod($pub['updated_at'] ?? ($pub['created_at'] ?? null), $today);
?>
    <url>
        <loc><?php echo xml_escape($loc); ?></loc>
        <lastmod><?php echo $lastmod; ?></lastmod>
    </url>
<?php endforeach; ?>
</urlset>