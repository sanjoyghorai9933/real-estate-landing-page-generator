<?php
/**
 * generate.php — CORE DISPATCHER (theme-agnostic)
 * ---------------------------------------------------------
 * Receives the landing-page request form (multipart/form-data),
 * figures out which theme was selected, and hands off ALL
 * theme-specific work (field validation, token filling, section
 * building) to that theme's own themes/<id>/renderer.php.
 *
 * This file must NEVER contain logic specific to any one theme.
 * Adding a new theme means adding a new themes/<id>/ folder with
 * its own config.php + renderer.php + template/ + assets/ — this
 * dispatcher does not change.
 *
 * Host this file + the /themes and /output folders on any normal
 * PHP hosting (PHP 7.4+, ZipArchive extension).
 * ---------------------------------------------------------
 */

header('Content-Type: application/json');

// ---- CORS: allow the web app (hosted elsewhere) to call this ----
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require __DIR__ . '/core/helpers.php';

$baseDir    = __DIR__;
$themesRoot = $baseDir . '/themes';

// ---------------------------------------------------------
// Resolve + validate the requested theme (whitelist by directory
// existence — never trust the raw string beyond that).
// ---------------------------------------------------------
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

// ---------------------------------------------------------
// Shared output tree (zips/previews/submissions log) is the same
// physical folder for every theme — created once here so no theme
// renderer needs to duplicate this bookkeeping.
// ---------------------------------------------------------
ensure_output_dirs($baseDir);

// ---------------------------------------------------------
// Hand off to the theme's own renderer. Every renderer.php defines
// render_theme(array $ctx): array — same contract regardless of
// how different that theme's layout/sections/fields are internally.
// ---------------------------------------------------------
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

// ---------------------------------------------------------
// Record this page in the shared registry (output/pages.json) so it
// shows up in "My Landing Pages" and can be edited later. This is
// purely bookkeeping — every theme's render_theme() already returns
// previewLink/downloadLink the same way, so no renderer.php needs to
// change to support this.
// ---------------------------------------------------------
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

    // Snapshot the submitted fields (all scalar POST values — no files)
    // so the edit screen can rebuild a field-based form later, and so
    // saved edits can be diffed against what's already in the HTML.
    $previewDir = $baseDir . '/output/previews/' . $pageId;
    if (is_dir($previewDir)) {
        save_page_fields($previewDir, $_POST);
    }
}

echo json_encode($result);
