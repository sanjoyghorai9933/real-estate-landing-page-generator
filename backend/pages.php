<?php
/**
 * backend/pages.php — Generated pages: list / get / save (edit-in-place)
 * ---------------------------------------------------------
 * Theme-agnostic API that lets the web app show every previously
 * generated landing page and edit it using the SAME KIND of fields
 * (and images) as that theme's generate form — instead of raw code.
 *
 * IMPORTANT: saving an edit NEVER calls render_theme() again.
 *   - Text/array fields: the old rendered value is found inside the
 *     already-generated index.html (and crm_connect.php, for a few
 *     lead-delivery fields) and swapped for the new value.
 *   - Images: a replacement file is written to the exact same path the
 *     original renderer already used (e.g. assets/img/slider2.jpg), so
 *     the existing HTML's <img> references keep working unchanged.
 * No theme's renderer.php runs again and no folder/zip is rebuilt from
 * scratch — only the specific files that changed are touched, and the
 * zip is kept in sync the same way.
 *
 * Actions (all via ?action=):
 *   GET  list             -> { pages: [...] }
 *   GET  get&id=<id>      -> { id, projectName, themeId, fields, images, previewLink }
 *   POST save (id, fields[, image files]) -> { success, previewLink }
 *   POST delete (id)      -> { success }
 * ---------------------------------------------------------
 */

header('Content-Type: application/json');

// ---- CORS: allow the web app (hosted elsewhere) to call this ----
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require __DIR__ . '/core/helpers.php';

$baseDir = __DIR__;
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

switch ($action) {
    case 'list':
        handleList($baseDir);
        break;
    case 'get':
        handleGet($baseDir);
        break;
    case 'save':
        handleSave($baseDir);
        break;
    case 'delete':
        handleDelete($baseDir);
        break;
    default:
        fail('Unknown or missing action.', 400);
}

// ---------------------------------------------------------
function handleList($baseDir) {
    sync_pages_registry_with_previews($baseDir);
    $pages = load_pages_registry($baseDir);
    usort($pages, function ($a, $b) {
        return strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? '');
    });
    echo json_encode(['pages' => $pages]);
}

// ---------------------------------------------------------
function handleGet($baseDir) {
    $id = trim($_GET['id'] ?? '');
    $page = find_page_record($baseDir, $id);
    if (!$page) {
        sync_pages_registry_with_previews($baseDir);
        $page = find_page_record($baseDir, $id);
    }
    if (!$page) {
        fail('Page not found.', 404);
    }

    $previewDir = $baseDir . '/output/previews/' . $id;
    if (!is_dir($previewDir)) {
        fail('Generated page files are missing for this id.', 404);
    }

    $fields = load_page_fields($previewDir);

    echo json_encode(array_merge($page, [
        'fields' => $fields,
        'images' => build_image_payload($previewDir, $page, $fields),
    ]));
}

// ---------------------------------------------------------
function build_image_payload($previewDir, $page, $fields) {
    $manifest = theme_image_manifest($page['themeId'] ?? '');
    $baseUrl  = !empty($page['previewLink']) ? rtrim(dirname($page['previewLink']), '/') : '';

    $single = [];
    foreach ($manifest['single'] as $img) {
        $exists = file_exists($previewDir . '/' . $img['path']);
        $single[] = [
            'field' => $img['field'],
            'label' => $img['label'],
            'url'   => ($exists && $baseUrl !== '') ? ($baseUrl . '/' . $img['path']) : null,
        ];
    }

    $repeated = [];
    foreach ($manifest['repeated'] as $rep) {
        $found  = scan_repeated_images($previewDir, $rep['template']);
        $labels = $rep['labelField'] ? (array) ($fields[$rep['labelField']] ?? []) : [];
        $labels = array_values($labels);

        $items = [];
        foreach ($found as $idx => $entry) {
            $items[] = [
                'n'     => $entry['n'],
                'url'   => $baseUrl !== '' ? ($baseUrl . '/' . $entry['path']) : null,
                'label' => $labels[$idx] ?? '',
            ];
        }

        $repeated[] = [
            'field'      => $rep['field'],
            'labelField' => $rep['labelField'],
            'label'      => $rep['label'],
            'items'      => $items,
        ];
    }

    return ['single' => $single, 'repeated' => $repeated];
}

// ---------------------------------------------------------
function handleSave($baseDir) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail('Method not allowed.', 405);
    }

    $id = trim($_POST['id'] ?? '');
    $newFields = json_decode($_POST['fields'] ?? '', true);
    if (!is_array($newFields)) {
        fail('Missing or invalid fields payload.', 400);
    }

    $page = find_page_record($baseDir, $id);
    if (!$page) {
        sync_pages_registry_with_previews($baseDir);
        $page = find_page_record($baseDir, $id);
    }
    if (!$page) {
        fail('Page not found.', 404);
    }

    $previewDir = $baseDir . '/output/previews/' . $id;
    $htmlPath = $previewDir . '/index.html';
    if (!is_dir($previewDir) || !file_exists($htmlPath)) {
        fail('Generated page files are missing for this id.', 404);
    }

    $oldFields = load_page_fields($previewDir);
    $html = file_get_contents($htmlPath);

    $crmPath = $previewDir . '/crm_connect.php';
    $crmContent = file_exists($crmPath) ? file_get_contents($crmPath) : null;

    // 1. Patch changed text/array fields directly into the already-
    //    generated files — no render_theme() call, no new folder/zip.
    $applied = apply_field_edits($html, $crmContent, $oldFields, $newFields);

    if (file_put_contents($htmlPath, $applied['html']) === false) {
        fail('Could not save changes to the preview file.', 500);
    }
    if ($applied['crm'] !== null) {
        file_put_contents($crmPath, $applied['crm']);
    }

    $zipPath = !empty($page['zipName']) ? ($baseDir . '/output/zips/' . $page['zipName']) : null;
    if ($zipPath) {
        update_zip_entry($zipPath, 'index.html', $applied['html']);
        if ($applied['crm'] !== null) {
            update_zip_entry($zipPath, 'crm_connect.php', $applied['crm']);
        }
    }

    // 2. Replace any images the user swapped out — written to the exact
    //    same path the original renderer used, so the HTML (already
    //    patched above) keeps pointing at the right file. This never
    //    changes how many images exist, only what's inside them.
    $manifest = theme_image_manifest($page['themeId'] ?? '');
    apply_image_replacements($previewDir, $zipPath, $manifest);

    // 3. Persist the merged field snapshot so future edits diff correctly.
    $mergedFields = array_merge($oldFields, $newFields);
    save_page_fields($previewDir, $mergedFields);

    upsert_page_record($baseDir, [
        'id'          => $id,
        'updatedAt'   => date('c'),
        'projectName' => $newFields['projectName'] ?? ($page['projectName'] ?? ''),
    ]);

    echo json_encode([
        'success'     => true,
        'previewLink' => $page['previewLink'] ?? null,
    ]);
}

// ---------------------------------------------------------
function handleDelete($baseDir) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail('Method not allowed.', 405);
    }

    $id = trim($_POST['id'] ?? '');
    if (!is_valid_page_id($id)) {
        fail('Invalid page id.', 400);
    }

    $page = find_page_record($baseDir, $id);
    if (!$page) {
        sync_pages_registry_with_previews($baseDir);
        $page = find_page_record($baseDir, $id);
    }
    if (!$page) {
        fail('Page not found.', 404);
    }

    $previewDir = $baseDir . '/output/previews/' . $id;
    if (is_dir($previewDir)) {
        deleteFolder($previewDir);
    }

    $zipName = $page['zipName'] ?? ($id . '.zip');
    $zipPath = $baseDir . '/output/zips/' . $zipName;
    if ($zipName && file_exists($zipPath)) {
        unlink($zipPath);
    }

    remove_page_record($baseDir, $id);

    echo json_encode(['success' => true]);
}

// ---------------------------------------------------------
function apply_image_replacements($previewDir, $zipPath, $manifest) {
    // Single fixed-slot images: uploaded under their own field name.
    foreach ($manifest['single'] as $img) {
        $file = $_FILES[$img['field']] ?? null;
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) continue;
        $destRel = $img['path'];
        $destAbs = $previewDir . '/' . $destRel;
        if (!is_dir(dirname($destAbs))) mkdir(dirname($destAbs), 0755, true);
        if (move_uploaded_file($file['tmp_name'], $destAbs) && $zipPath) {
            update_zip_entry_from_file($zipPath, $destRel, $destAbs);
        }
    }

    // Repeated slots: uploaded as img_slot__<field>__<n> so each
    // replacement maps to one exact existing slot — no add/remove,
    // only swapping what's already there.
    foreach ($_FILES as $key => $file) {
        if (strpos($key, 'img_slot__') !== 0) continue;
        $parts = explode('__', $key);
        if (count($parts) !== 3) continue;
        [, $field, $n] = $parts;
        $n = (int) $n;
        if ($n < 1 || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) continue;

        $rep = null;
        foreach ($manifest['repeated'] as $candidate) {
            if ($candidate['field'] === $field) { $rep = $candidate; break; }
        }
        if (!$rep) continue;

        $destRel = str_replace('{n}', $n, $rep['template']);
        $destAbs = $previewDir . '/' . $destRel;
        if (!file_exists($destAbs)) continue; // only replace an existing slot

        if (move_uploaded_file($file['tmp_name'], $destAbs) && $zipPath) {
            update_zip_entry_from_file($zipPath, $destRel, $destAbs);
        }
    }
}
