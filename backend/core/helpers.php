<?php
/**
 * core/helpers.php
 * ---------------------------------------------------------
 * Theme-agnostic utility functions shared by generate.php (the
 * core dispatcher) and every theme's renderer.php. Nothing in
 * this file knows about any specific theme's fields, sections,
 * or template tokens — that logic belongs in each theme's own
 * renderer.php.
 * ---------------------------------------------------------
 */

if (!function_exists('fail')) {
    function fail($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit;
    }
}

if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitizeColor')) {
    function sanitizeColor($val, $default) {
        $val = trim((string) $val);
        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $val) ? $val : $default;
    }
}

if (!function_exists('copyDirRecursive')) {
    function copyDirRecursive($src, $dst) {
        if (!is_dir($src)) return;
        if (!is_dir($dst)) mkdir($dst, 0755, true);
        foreach (scandir($src) as $item) {
            if ($item === '.' || $item === '..') continue;
            if ($item === 'PUT_YOUR_ASSETS_HERE.txt') continue;
            $s = $src . '/' . $item;
            $d = $dst . '/' . $item;
            if (is_dir($s)) {
                copyDirRecursive($s, $d);
            } else {
                copy($s, $d);
            }
        }
    }
}

if (!function_exists('addFolderToZip')) {
    function addFolderToZip($zip, $folder, $zipRoot = '') {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            if ($file->isDir()) continue;
            $filePath = $file->getRealPath();
            $relativePath = $zipRoot . substr($filePath, strlen($folder) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
}

if (!function_exists('copyFolder')) {
    function copyFolder($source, $dest) {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            $target = $dest . '/' . substr($item->getRealPath(), strlen($source) + 1);
            if ($item->isDir()) {
                if (!is_dir($target)) mkdir($target, 0755, true);
            } else {
                copy($item->getRealPath(), $target);
            }
        }
    }
}

if (!function_exists('deleteFolder')) {
    function deleteFolder($folder) {
        if (!is_dir($folder)) return;
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }
        rmdir($folder);
    }
}

if (!function_exists('ensure_output_dirs')) {
    /**
     * Creates backend/output/, backend/output/zips/ and backend/output/previews/
     * with the correct .htaccess lockdown, shared by every theme since all
     * generated zips/previews land in the same output tree regardless of theme.
     */
    function ensure_output_dirs($baseDir) {
        $outputDir = $baseDir . '/output';
        $zipsDir = $outputDir . '/zips';
        $previewsDir = $outputDir . '/previews';

        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
        if (!is_dir($zipsDir)) mkdir($zipsDir, 0755, true);
        if (!is_dir($previewsDir)) mkdir($previewsDir, 0755, true);

        $outputRootHtaccess = $outputDir . '/.htaccess';
        if (!file_exists($outputRootHtaccess)) {
            file_put_contents($outputRootHtaccess, "Require all denied\n\n# Apache 2.2 fallback\nOrder deny,allow\nDeny from all\n");
        }
        $zipsHtaccess = $zipsDir . '/.htaccess';
        if (!file_exists($zipsHtaccess)) {
            file_put_contents($zipsHtaccess, "Require all granted\n\n# Apache 2.2 fallback\nOrder allow,deny\nAllow from all\n");
        }
        $previewsHtaccess = $previewsDir . '/.htaccess';
        $previewsHtaccessContent = "Require all granted\n\n# Apache 2.2 fallback\nOrder allow,deny\nAllow from all\n\n<FilesMatch \"\\.php$\">\n    Require all denied\n    Order deny,allow\n    Deny from all\n</FilesMatch>\n";
        if (!file_exists($previewsHtaccess) || strpos((string) file_get_contents($previewsHtaccess), 'Apache 2.2 fallback') === false) {
            file_put_contents($previewsHtaccess, $previewsHtaccessContent);
        }

        return ['outputDir' => $outputDir, 'zipsDir' => $zipsDir, 'previewsDir' => $previewsDir];
    }
}

if (!function_exists('buildGtagSnippet')) {
    /**
     * Google gtag.js + Google Ads conversion tracking snippet builder.
     * Generic across themes: takes a gtag ID and/or a conversion "send to"
     * ID and returns the <script> block to inject, or '' if neither is set.
     */
    function buildGtagSnippet($gtagId, $conversionSendTo, $fireConversion) {
        $loadId = $gtagId;
        if ($loadId === '' && $conversionSendTo !== '') {
            $parts = explode('/', $conversionSendTo);
            $loadId = $parts[0] ?? '';
        }
        if ($loadId === '') return '';
        $loadIdJs = addslashes($loadId);
        $snippet  = "<!-- Google tag (gtag.js) -->\n";
        $snippet .= "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$loadIdJs}\"></script>\n";
        $snippet .= "<script>\n";
        $snippet .= "  window.dataLayer = window.dataLayer || [];\n";
        $snippet .= "  function gtag(){dataLayer.push(arguments);}\n";
        $snippet .= "  gtag('js', new Date());\n";
        $snippet .= "  gtag('config', '{$loadIdJs}');\n";
        if ($fireConversion && $conversionSendTo !== '') {
            $sendToJs = addslashes($conversionSendTo);
            $snippet .= "  gtag('event', 'conversion', {'send_to': '{$sendToJs}'});\n";
        }
        $snippet .= "</script>\n";
        return $snippet;
    }
}

if (!function_exists('log_submission')) {
    function log_submission($baseDir, array $row) {
        $logLine = implode(',', array_map(function ($v) {
            return '"' . str_replace('"', '""', $v) . '"';
        }, $row));
        file_put_contents($baseDir . '/output/submissions.csv', $logLine . "\n", FILE_APPEND);
    }
}

if (!function_exists('build_links')) {
    function build_links($baseDir, $zipName, $folderName) {
        $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host       = $_SERVER['HTTP_HOST'];
        $scriptDir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        return [
            'downloadLink' => "{$protocol}://{$host}{$scriptDir}/output/zips/{$zipName}",
            'previewLink'  => "{$protocol}://{$host}{$scriptDir}/output/previews/{$folderName}/index.html",
        ];
    }
}

// ---------------------------------------------------------
// Generated-page registry (theme-agnostic)
// ---------------------------------------------------------
// Every generated page (any theme) is recorded here so it can be
// listed and edited later WITHOUT re-running render_theme(). Editing
// only ever touches the already-generated index.html (in output/previews/
// and inside the zip) — the registry just keeps track of which pages
// exist and where they live.

if (!function_exists('pages_registry_path')) {
    function pages_registry_path($baseDir) {
        return $baseDir . '/output/pages.json';
    }
}

if (!function_exists('load_pages_registry')) {
    function load_pages_registry($baseDir) {
        $path = pages_registry_path($baseDir);
        if (!file_exists($path)) return [];
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('save_pages_registry')) {
    function save_pages_registry($baseDir, array $pages) {
        file_put_contents(
            pages_registry_path($baseDir),
            json_encode(array_values($pages), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}

if (!function_exists('is_valid_page_id')) {
    // Page ids are the generator's own folder-name slugs (e.g. "my-project-20240101-120000")
    function is_valid_page_id($id) {
        return is_string($id) && $id !== '' && preg_match('/^[a-z0-9-]+$/', $id);
    }
}

if (!function_exists('find_page_record')) {
    function find_page_record($baseDir, $id) {
        if (!is_valid_page_id($id)) return null;
        foreach (load_pages_registry($baseDir) as $page) {
            if (($page['id'] ?? null) === $id) return $page;
        }
        return null;
    }
}

if (!function_exists('upsert_page_record')) {
    function upsert_page_record($baseDir, array $record) {
        if (!is_valid_page_id($record['id'] ?? null)) return;
        $pages = load_pages_registry($baseDir);
        $found = false;
        foreach ($pages as &$page) {
            if (($page['id'] ?? null) === $record['id']) {
                $page = array_merge($page, $record);
                $found = true;
                break;
            }
        }
        unset($page);
        if (!$found) $pages[] = $record;
        save_pages_registry($baseDir, $pages);
    }
}

if (!function_exists('remove_page_record')) {
    function remove_page_record($baseDir, $id) {
        if (!is_valid_page_id($id)) return;
        $pages = load_pages_registry($baseDir);
        $pages = array_values(array_filter($pages, function ($page) use ($id) {
            return ($page['id'] ?? null) !== $id;
        }));
        save_pages_registry($baseDir, $pages);
    }
}

if (!function_exists('update_zip_entry')) {
    /**
     * Replaces a single file's contents inside an already-built zip
     * (used when saving edits so the download zip stays in sync with
     * the live preview, without rebuilding the whole archive).
     */
    function update_zip_entry($zipPath, $entryName, $content) {
        if (!file_exists($zipPath)) return false;
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) return false;
        $zip->deleteName($entryName);
        $zip->addFromString($entryName, $content);
        $zip->close();
        return true;
    }
}

if (!function_exists('page_fields_path')) {
    function page_fields_path($previewDir) {
        return $previewDir . '/data.json';
    }
}

if (!function_exists('load_page_fields')) {
    // The full snapshot of submitted form fields (all scalar $_POST values,
    // themeId/refNumber included) saved at generation time, used both to
    // show the edit form pre-filled and to diff against on save.
    function load_page_fields($previewDir) {
        $path = page_fields_path($previewDir);
        if (!file_exists($path)) return [];
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('save_page_fields')) {
    function save_page_fields($previewDir, array $fields) {
        file_put_contents(
            page_fields_path($previewDir),
            json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}

if (!function_exists('apply_scalar_replacement')) {
    /**
     * Replaces one field's old rendered text with its new value inside
     * already-generated content. Values are matched the same way the
     * renderer wrote them (htmlspecialchars via e()) since that's how
     * every theme's renderer.php inserts user text into the template.
     * Very short values are skipped since they're too ambiguous to
     * safely find-and-replace across a whole page.
     */
    function apply_scalar_replacement($content, $oldVal, $newVal) {
        $oldVal = (string) $oldVal;
        $newVal = (string) $newVal;
        if ($oldVal === $newVal) return $content;

        $oldEsc = e($oldVal);
        $newEsc = e($newVal);
        if (mb_strlen($oldEsc) < 2) return $content;
        if (strpos($content, $oldEsc) === false) return $content;

        return str_replace($oldEsc, $newEsc, $content);
    }
}

if (!function_exists('decode_maybe_array')) {
    // Some fields (highlights, priceRows...) are posted as a single JSON
    // string; others (floorplanLabel[], galleryCaption[]...) are posted
    // as real PHP arrays via name="field[]". Both need to be walked
    // item-by-item when patching edits, so this normalizes either shape
    // to a PHP array, or returns null if the value isn't array-shaped.
    function decode_maybe_array($val) {
        if (is_array($val)) return $val;
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (is_array($decoded)) return $decoded;
        }
        return null;
    }
}

if (!function_exists('apply_field_edits')) {
    /**
     * Theme-agnostic patcher: walks every changed field from the edit
     * form and finds-and-replaces its old rendered value with the new
     * one directly inside the already-generated index.html (and, for a
     * few lead-delivery fields, crm_connect.php). Array-shaped fields
     * (highlights, priceRows, floorplanLabel, etc.) are walked item by
     * item; each item's own text is replaced the same way. This never
     * re-runs a theme's render_theme().
     */
    function apply_field_edits($html, $crmContent, array $oldFields, array $newFields) {
        $skipKeys = ['themeId', 'refNumber'];
        $crmRelevantKeys = ['toEmail', 'ccEmail', 'bccEmail', 'projectName', 'crmApiKey'];

        foreach ($newFields as $key => $newVal) {
            if (in_array($key, $skipKeys, true)) continue;
            if (!array_key_exists($key, $oldFields)) continue;
            $oldVal = $oldFields[$key];

            $oldArr = decode_maybe_array($oldVal);
            $newArr = decode_maybe_array($newVal);

            if ($oldArr !== null && $newArr !== null) {
                $count = min(count($oldArr), count($newArr));
                $oldArrList = array_values($oldArr);
                $newArrList = array_values($newArr);
                for ($i = 0; $i < $count; $i++) {
                    $oldItem = $oldArrList[$i];
                    $newItem = $newArrList[$i];
                    if (is_array($oldItem) && is_array($newItem)) {
                        foreach ($oldItem as $subKey => $oldSubVal) {
                            if (!array_key_exists($subKey, $newItem)) continue;
                            $html = apply_scalar_replacement($html, $oldSubVal, $newItem[$subKey]);
                        }
                    } else {
                        $html = apply_scalar_replacement($html, $oldItem, $newItem);
                    }
                }
                continue;
            }

            $html = apply_scalar_replacement($html, $oldVal, $newVal);

            if ($crmContent !== null && in_array($key, $crmRelevantKeys, true) && (string) $oldVal !== (string) $newVal) {
                $oldSlash = addslashes((string) $oldVal);
                $newSlash = addslashes((string) $newVal);
                if ($oldSlash !== '' && strpos($crmContent, $oldSlash) !== false) {
                    $crmContent = str_replace($oldSlash, $newSlash, $crmContent);
                }
            }
        }

        return ['html' => $html, 'crm' => $crmContent];
    }
}

if (!function_exists('theme_image_manifest')) {
    /**
     * Where each theme's uploaded images live on disk, keyed by the same
     * field names used in the generate form. Purely descriptive — the
     * actual paths/filenames already come straight from each renderer.php
     * (never changed here); this just lets the editor know where to find
     * and where to overwrite an existing image without regenerating
     * anything. "template" uses {n} as the 1-based slot placeholder for
     * repeated (multi-image) fields.
     */
    function theme_image_manifest($themeId) {
        $manifests = [
            'default' => [
                'single' => [
                    ['field' => 'logo',            'path' => 'assets/logo.png',                      'label' => 'Logo'],
                    ['field' => 'masterplan',       'path' => 'assets/img/masterplan.jpg',            'label' => 'Master Plan Image'],
                    ['field' => 'authPartnerLogo',  'path' => 'assets/img/comman/logo-footer.jpg',     'label' => 'Authorized Partner Logo'],
                ],
                'repeated' => [
                    ['field' => 'slider',         'labelField' => null,             'template' => 'assets/img/slider{n}.jpg',    'label' => 'Slider Images'],
                    ['field' => 'floorplanImage', 'labelField' => 'floorplanLabel', 'template' => 'assets/img/floorplan{n}.jpg', 'label' => 'Floor Plan Images'],
                    ['field' => 'galleryImage',    'labelField' => 'galleryCaption', 'template' => 'assets/img/gallery{n}.jpg',   'label' => 'Gallery Images'],
                    ['field' => 'amenityImage',    'labelField' => 'amenityLabel',   'template' => 'assets/img/amenity{n}.jpg',   'label' => 'Amenity Images'],
                ],
            ],
            'palm-estate' => [
                'single' => [
                    ['field' => 'heroImage',     'path' => 'assets/img/banner.jpg',            'label' => 'Hero Banner Image'],
                    ['field' => 'logo',          'path' => 'assets/img/logo.png',               'label' => 'Logo'],
                    ['field' => 'aboutImage',    'path' => 'assets/img/side-building.jpg',       'label' => 'About Section Image'],
                    ['field' => 'locationImage', 'path' => 'assets/img/media/location.jpg',      'label' => 'Location Image'],
                    ['field' => 'builderImage',  'path' => 'assets/img/media/emaar-group.jpg',   'label' => 'Builder Image'],
                ],
                'repeated' => [
                    ['field' => 'gallery', 'labelField' => null, 'template' => 'assets/img/media/gallery{n}.jpg', 'label' => 'Gallery Images'],
                ],
            ],
            'riverside' => [
                'single' => [
                    ['field' => 'logo',             'path' => 'assets/img/logo.png',                    'label' => 'Logo'],
                    ['field' => 'overviewImage',    'path' => 'assets/img/overview.webp',               'label' => 'Overview Image'],
                    ['field' => 'highlightImage',   'path' => 'assets/img/banner2.webp',                'label' => 'Highlight Banner Image'],
                    ['field' => 'masterplanImage',  'path' => 'assets/img/floor-plan/master-plan.png',  'label' => 'Master Plan Image'],
                    ['field' => 'locationImage',    'path' => 'assets/img/location.webp',               'label' => 'Location Image'],
                ],
                'repeated' => [
                    ['field' => 'heroImage',       'labelField' => null,             'template' => 'assets/img/hero{n}.jpg',              'label' => 'Hero Slider Images'],
                    ['field' => 'amenityImage',    'labelField' => 'amenityLabel',   'template' => 'assets/img/amenities/am{n}.jpg',      'label' => 'Amenity Images'],
                    ['field' => 'floorplanImage',  'labelField' => 'floorplanLabel', 'template' => 'assets/img/floor-plan/plan{n}.jpg',   'label' => 'Floor Plan Images'],
                    ['field' => 'galleryImage',    'labelField' => 'galleryCaption', 'template' => 'assets/img/gallery/photo{n}.jpg',     'label' => 'Gallery Images'],
                ],
            ],
        ];
        return $manifests[$themeId] ?? ['single' => [], 'repeated' => []];
    }
}

if (!function_exists('scan_repeated_images')) {
    // Repeated image slots are always saved sequentially with no gaps
    // (slider1.jpg, slider2.jpg, ...), so the first missing index marks
    // the end of the list.
    function scan_repeated_images($previewDir, $template, $max = 60) {
        $found = [];
        for ($n = 1; $n <= $max; $n++) {
            $rel = str_replace('{n}', $n, $template);
            if (!file_exists($previewDir . '/' . $rel)) break;
            $found[] = ['n' => $n, 'path' => $rel];
        }
        return $found;
    }
}

if (!function_exists('update_zip_entry_from_file')) {
    function update_zip_entry_from_file($zipPath, $entryName, $filePath) {
        if (!file_exists($filePath)) return false;
        return update_zip_entry($zipPath, $entryName, file_get_contents($filePath));
    }
}



if (!function_exists('sync_pages_registry_with_previews')) {
    /**
     * Backfills the registry with any generated page folders that already
     * exist under output/previews/ but aren't tracked yet — e.g. pages
     * generated before this "My Landing Pages" feature existed. Without
     * this, those older pages would never show up to be edited even
     * though their files are sitting right there.
     */
    function sync_pages_registry_with_previews($baseDir) {
        $previewsDir = $baseDir . '/output/previews';
        if (!is_dir($previewsDir)) return;

        $pages = load_pages_registry($baseDir);
        $known = [];
        foreach ($pages as $p) {
            $known[$p['id'] ?? ''] = true;
        }

        $changed = false;
        foreach (scandir($previewsDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (isset($known[$entry]) || !is_valid_page_id($entry)) continue;

            $folder = $previewsDir . '/' . $entry;
            if (!is_dir($folder) || !file_exists($folder . '/index.html')) continue;

            $zipName = $entry . '.zip';
            $zipExists = file_exists($baseDir . '/output/zips/' . $zipName);
            $links = build_links($baseDir, $zipName, $entry);
            $mtime = @filemtime($folder . '/index.html') ?: time();

            // Folder names are "<slug>-<Ymd>-<His>" — strip the timestamp
            // to get a readable fallback project name.
            $slug = preg_replace('/-\d{8}-\d{6}$/', '', $entry);
            $projectName = trim(ucwords(str_replace('-', ' ', $slug))) ?: $entry;

            $pages[] = [
                'id'           => $entry,
                'themeId'      => '',
                'projectName'  => $projectName,
                'refNumber'    => '',
                'zipName'      => $zipExists ? $zipName : '',
                'previewLink'  => $links['previewLink'],
                'downloadLink' => $zipExists ? $links['downloadLink'] : '',
                'createdAt'    => date('c', $mtime),
                'updatedAt'    => date('c', $mtime),
            ];
            $changed = true;
        }

        if ($changed) save_pages_registry($baseDir, $pages);
    }
}
