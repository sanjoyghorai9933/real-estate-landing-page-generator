<?php
/**
 * generate.php — CORE DISPATCHER (theme-agnostic)
 * ---------------------------------------------------------
 * Receives the landing-page request form (multipart/form-data),
 * validates the request, resolves the selected theme, and hands
 * theme-specific work to that theme's renderer.php.
 * ---------------------------------------------------------
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

require __DIR__ . '/core/helpers.php';
require __DIR__ . '/core/request_validation.php';

// Harden the request before loading any theme renderer. This catches
// malformed scalar/JSON fields and spoofed or oversized image uploads.
validate_generation_request();

$baseDir    = __DIR__;
$themesRoot = $baseDir . '/themes';

$themeId = trim($_POST['themeId'] ?? $_POST['theme'] ?? 'default');
if ($themeId === '' || !preg_match('/^[a-z0-9_-]+$/', $themeId)) {
    fail('Invalid or missing theme.', 400);
}

$themeDir = $themesRoot . '/' . $themeId;
$rendererFile = $themeDir . '/renderer.php';
$templateDir = $themeDir . '/template';

if (!is_dir($themeDir) || !file_exists($rendererFile) || !is_dir($templateDir)) {
    fail("Unknown or misconfigured theme: {$themeId}", 400);
}

ensure_output_dirs($baseDir);

require $rendererFile;

if (!function_exists('render_theme')) {
    fail("Theme '{$themeId}' is misconfigured (renderer.php did not define render_theme()).", 500);
}

$result = render_theme([
    'baseDir'         => $baseDir,
    'themeDir'        => $themeDir,
    'themeId'         => $themeId,
    'templatesDir'    => $templateDir,
    'staticAssetsDir' => $themeDir . '/assets',
]);

if (!is_array($result)) {
    fail("Theme '{$themeId}' returned an invalid generation result.", 500);
}

if (!empty($result['success']) && !empty($result['previewLink'])
    && preg_match('#/previews/([^/]+)/index\.html$#', $result['previewLink'], $m)) {
    $pageId = $m[1];
    $now = date('c');
    upsert_page_record($baseDir, [
        'id'           => $pageId,
        'themeId'      => $themeId,
        'projectName'  => trim($_POST['projectName'] ?? ''),
        'refNumber'    => $result['refNumber'] ?? '',
        'zipName'      => basename(parse_url($result['downloadLink'] ?? '', PHP_URL_PATH) ?: ''),
        'previewLink'  => $result['previewLink'],
        'downloadLink' => $result['downloadLink'] ?? '',
        'createdAt'    => $now,
        'updatedAt'    => $now,
    ]);
    $result['pageId'] = $pageId;

    $previewDir = $baseDir . '/output/previews/' . $pageId;
    if (is_dir($previewDir)) {
        save_page_fields($previewDir, $_POST);
    }
}

$result['success'] = !empty($result['success']);
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
