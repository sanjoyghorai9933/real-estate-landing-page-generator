<?php
/**
 * themes/palm-estate/renderer.php
 * ---------------------------------------------------------
 * Renderer for the "palm-estate" theme — converted from an
 * uploaded static design. Own section set, own visual identity,
 * own unified enquiry modal (replacing the original third-party
 * form embed with the same self-hosted lead flow every theme uses).
 *
 * Contract: same as every other theme's renderer.php —
 *   function render_theme(array $ctx): array
 * ---------------------------------------------------------
 */

function render_theme(array $ctx) {
$baseDir         = $ctx['baseDir'];
$templatesDir    = $ctx['templatesDir'];
$staticAssetsDir = $ctx['staticAssetsDir'];

// ---------------------------------------------------------
// 1. Read + validate incoming fields
// ---------------------------------------------------------
$projectName     = trim($_POST['projectName'] ?? '');
$locationTagline = trim($_POST['locationTagline'] ?? '');
$heroSubtitle    = trim($_POST['heroSubtitle'] ?? '');
$priceText       = trim($_POST['priceText'] ?? '');
$phone           = trim($_POST['phone'] ?? '');
$toEmail         = trim($_POST['toEmail'] ?? '');
$ccEmail         = trim($_POST['ccEmail'] ?? '');
$bccEmail        = trim($_POST['bccEmail'] ?? '');
$refNumber       = trim($_POST['refNumber'] ?? ('REQ-' . time()));

$aboutText       = trim($_POST['aboutText'] ?? '');
if ($aboutText === '') $aboutText = 'A landmark address offering spacious, thoughtfully designed homes with premium amenities and lush green surroundings.';

$featureType        = trim($_POST['featureType'] ?? '') ?: 'Residential';
$featureLocation     = trim($_POST['featureLocation'] ?? '') ?: $locationTagline;
$featurePossession   = trim($_POST['featurePossession'] ?? '') ?: 'Ready to Move';

$builderHeading = trim($_POST['builderHeading'] ?? '') ?: 'About the Builder';
$builderText    = trim($_POST['builderText'] ?? '');
if ($builderText === '') $builderText = 'A trusted name in real estate, delivering landmark developments defined by quality construction, thoughtful design, and timely delivery.';

$addressBlock = trim($_POST['addressBlock'] ?? '');
if ($addressBlock === '') $addressBlock = 'CORPORATE OFFICE: ' . $projectName;

$gtagId           = trim($_POST['gtagId'] ?? '');
$conversionSendTo = trim($_POST['conversionSendTo'] ?? '');

$amenities = json_decode($_POST['amenities'] ?? '[]', true) ?: [];
$amenities = array_values(array_filter(array_map('trim', $amenities)));
if (empty($amenities)) {
    $amenities = [
        '100% power backup', 'Provision of LPG gas pipelines', 'Squash court',
        'Perimeter security', 'Provision for cable TV', 'WiFi community',
        'Modern elevator', 'Burglar alarm system', 'Gardens and parks',
        'Visitor parking', 'Driveway and carport', 'Landscaped gardens',
    ];
}

$colorPrimary   = sanitizeColor($_POST['colorPrimary'] ?? '', '#0c5848');
$colorSecondary = sanitizeColor($_POST['colorSecondary'] ?? '', '#000000');

if ($projectName === '' || $locationTagline === '' || $phone === '' || $toEmail === '' || $priceText === '') {
    fail('Missing required fields.');
}
if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    fail('Lead email address is not valid.');
}
if ($ccEmail !== '' && !filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
    fail('CC email address is not valid.');
}
if ($bccEmail !== '' && !filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
    fail('BCC email address is not valid.');
}
if (empty($_FILES['heroImage']['tmp_name'])) {
    fail('A hero banner image is required.');
}

// ---------------------------------------------------------
// 2. Prepare a working folder for this submission
// ---------------------------------------------------------
$slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($projectName));
$slug = trim($slug, '-') ?: 'project';
$folderName = $slug . '-' . date('Ymd-His');

$workDir   = $baseDir . '/output/' . $folderName;
$outputDir = $baseDir . '/output/zips';

if (!mkdir($workDir . '/assets/img/media', 0755, true) && !is_dir($workDir . '/assets/img/media')) {
    fail('Could not create working directory.', 500);
}

// ---------------------------------------------------------
// 3. Copy static files + this theme's default assets (fallback
//    images live here — e.g. media/gallery1.jpg — used whenever
//    the visitor doesn't upload their own for that slot)
// ---------------------------------------------------------
copy($templatesDir . '/SMTPMailer.php', $workDir . '/SMTPMailer.php');
copy($templatesDir . '/config_smtp-template.php', $workDir . '/config_smtp.php');
copyDirRecursive($staticAssetsDir, $workDir . '/assets');

// ---------------------------------------------------------
// 4. Save uploaded images (falling back to the theme's default
//    asset filename when nothing was uploaded for that slot)
// ---------------------------------------------------------
$allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

function savePalmImage($field, $destPath, $allowedExt) {
    if (!empty($_FILES[$field]['tmp_name']) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) return false;
        move_uploaded_file($_FILES[$field]['tmp_name'], $destPath);
        return true;
    }
    return false;
}
savePalmImage('heroImage', $workDir . '/assets/img/banner.jpg', $allowedExt);
savePalmImage('logo', $workDir . '/assets/img/logo.png', $allowedExt);
$aboutImageUploaded    = savePalmImage('aboutImage', $workDir . '/assets/img/side-building.jpg', $allowedExt);
$locationImageUploaded = savePalmImage('locationImage', $workDir . '/assets/img/media/location.jpg', $allowedExt);
$builderImageUploaded  = savePalmImage('builderImage', $workDir . '/assets/img/media/emaar-group.jpg', $allowedExt);

$aboutImage    = 'side-building.jpg';
$locationImage = 'media/location.jpg';
$builderImage  = 'media/emaar-group.jpg';

// Gallery: unlimited uploads, falls back to the theme's default 6 photos
// when nothing was uploaded (same convention as every other theme here).
function savePalmGallery($field, $workDir, $allowedExt) {
    $saved = [];
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['tmp_name'])) return $saved;
    $files = $_FILES[$field];
    foreach ($files['tmp_name'] as $i => $tmp) {
        if (empty($tmp) || !is_uploaded_file($tmp) || ($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) continue;
        $n = count($saved) + 1;
        $destName = "gallery{$n}.jpg";
        move_uploaded_file($tmp, $workDir . '/assets/img/media/' . $destName);
        $saved[] = $destName;
    }
    return $saved;
}
$galleryFiles = savePalmGallery('gallery', $workDir, $allowedExt);
if (empty($galleryFiles)) {
    $galleryFiles = array_values(array_filter(
        ['gallery1.jpg', 'gallery2.jpg', 'gallery3.jpg', 'gallery4.jpg', 'gallery5.jpg', 'gallery6.jpg'],
        function ($f) use ($workDir) { return file_exists($workDir . '/assets/img/media/' . $f); }
    ));
}

// ---------------------------------------------------------
// 5. Build the dynamic HTML fragments
// ---------------------------------------------------------
// Amenities split into 3 columns (5-ish items each), same layout as the source design
$columns = [[], [], []];
foreach ($amenities as $i => $item) {
    $columns[$i % 3][] = $item;
}
function buildAmenityColumn($items) {
    $html = '';
    foreach ($items as $item) {
        $html .= '<li>' . e($item) . "</li>\n";
    }
    return $html;
}
$amenitiesCol1 = buildAmenityColumn($columns[0]);
$amenitiesCol2 = buildAmenityColumn($columns[1]);
$amenitiesCol3 = buildAmenityColumn($columns[2]);
$includeAmenities = count($amenities) > 0 && ($_POST['includeAmenities'] ?? '1') === '1';

// Gallery grid
$galleryHtml = '';
foreach ($galleryFiles as $file) {
    $galleryHtml .= <<<HTML
                    <div class="col-sm-6 col-md-6 col-lg-4 col-xs-12 item"><a href="assets/img/media/{$file}" class="js-lightbox" data-lightbox="photos"><img class="img-fluid" style="height:164px" src="assets/img/media/{$file}"></a></div>

HTML;
}
$includeGallery = count($galleryFiles) > 0 && ($_POST['includeGallery'] ?? '1') === '1';
$includeLocation = true; // location image always present (uploaded or fallback)
$includeBuilder = ($builderText !== '') && ($_POST['includeBuilder'] ?? '1') === '1';

$gtagHeadSnippet   = buildGtagSnippet($gtagId, $conversionSendTo, false);
$thanksGtagSnippet = buildGtagSnippet($gtagId, $conversionSendTo, true);

$thanksTemplate = file_get_contents($templatesDir . '/thanks.html');
$thanksHtml = strtr($thanksTemplate, [
    '{{GTAG_HEAD_SNIPPET}}' => $thanksGtagSnippet,
    '{{COLOR_PRIMARY}}'     => $colorPrimary,
    '{{COLOR_SECONDARY}}'   => $colorSecondary,
]);
file_put_contents($workDir . '/thanks.html', $thanksHtml);

$phoneDigits = preg_replace('/[^0-9+]/', '', $phone);
$phoneTel = 'tel:' . $phoneDigits;

// ---------------------------------------------------------
// 6. Fill index.html
// ---------------------------------------------------------
$indexTemplate = file_get_contents($templatesDir . '/index-template.html');

$tokens = [
    '{{PAGE_TITLE}}'          => e("{$projectName} - {$locationTagline}"),
    '{{PROJECT_NAME}}'        => e($projectName),
    '{{LOCATION_TAGLINE}}'    => e($locationTagline),
    '{{HERO_SUBTITLE}}'       => e($heroSubtitle),
    '{{PRICE_TEXT}}'          => e($priceText),
    '{{HERO_IMAGE}}'          => 'banner.jpg',
    '{{ABOUT_IMAGE}}'         => e($aboutImage),
    '{{ABOUT_TEXT}}'          => e($aboutText),
    '{{FEATURE_TYPE}}'        => e($featureType),
    '{{FEATURE_LOCATION}}'    => e($featureLocation),
    '{{FEATURE_POSSESSION}}'  => e($featurePossession),
    '{{AMENITIES_COL1}}'      => $amenitiesCol1,
    '{{AMENITIES_COL2}}'      => $amenitiesCol2,
    '{{AMENITIES_COL3}}'      => $amenitiesCol3,
    '{{LOCATION_IMAGE}}'      => e($locationImage),
    '{{GALLERY_ITEMS}}'       => $galleryHtml,
    '{{BUILDER_IMAGE}}'       => e($builderImage),
    '{{BUILDER_HEADING}}'     => e($builderHeading),
    '{{BUILDER_TEXT}}'        => e($builderText),
    '{{ADDRESS_BLOCK}}'       => nl2br(e($addressBlock)),
    '{{PHONE_TEL}}'           => e($phoneTel),
    '{{PHONE_DISPLAY}}'       => e($phone),
    '{{COLOR_PRIMARY}}'       => $colorPrimary,
    '{{COLOR_SECONDARY}}'     => $colorSecondary,
    '{{GTAG_HEAD_SNIPPET}}'   => $gtagHeadSnippet,
];
$indexHtml = strtr($indexTemplate, $tokens);

foreach (['AMENITIES' => $includeAmenities, 'LOCATION' => $includeLocation, 'GALLERY' => $includeGallery, 'BUILDER' => $includeBuilder] as $marker => $include) {
    $start = "<!--SECTION:{$marker}_START-->";
    $end   = "<!--SECTION:{$marker}_END-->";
    if ($include) {
        $indexHtml = str_replace([$start, $end], '', $indexHtml);
    } else {
        $pattern = '/' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '/s';
        $indexHtml = preg_replace($pattern, '', $indexHtml);
    }
}

file_put_contents($workDir . '/index.html', $indexHtml);

// ---------------------------------------------------------
// 7. Fill crm_connect.php (mail-only lead capture)
// ---------------------------------------------------------
$crmTemplate = file_get_contents($templatesDir . '/crm_connect-template.php');
$crmTokens = [
    '{{PROJECT_NAME}}' => addslashes($projectName),
    '{{TO_EMAIL}}'     => addslashes($toEmail),
    '{{CC_EMAIL}}'     => addslashes($ccEmail),
    '{{BCC_EMAIL}}'    => addslashes($bccEmail),
    '{{CRM_BLOCK}}'    => '',
];
$crmPhp = strtr($crmTemplate, $crmTokens);
file_put_contents($workDir . '/crm_connect.php', $crmPhp);

// ---------------------------------------------------------
// 8. Zip everything up
// ---------------------------------------------------------
$zipName = $folderName . '.zip';
$zipPath = $outputDir . '/' . $zipName;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fail('Could not create zip file.', 500);
}
addFolderToZip($zip, $workDir);
$zip->close();

// ---------------------------------------------------------
// 8b. Public preview copy
// ---------------------------------------------------------
$previewsDir = $baseDir . '/output/previews';
$previewDir = $previewsDir . '/' . $folderName;
mkdir($previewDir, 0755, true);
copy($workDir . '/index.html', $previewDir . '/index.html');
if (is_dir($workDir . '/assets')) {
    copyFolder($workDir . '/assets', $previewDir . '/assets');
}
// Carry the lead-capture files too, so the enquiry form(s) on the
// preview page don't 404 when submitted — same files ship in the zip.
foreach (['crm_connect.php', 'config_smtp.php', 'SMTPMailer.php', 'thanks.html'] as $leadFile) {
    if (file_exists($workDir . '/' . $leadFile)) {
        copy($workDir . '/' . $leadFile, $previewDir . '/' . $leadFile);
    }
}

// ---------------------------------------------------------
// 9. Clean up + log + respond
// ---------------------------------------------------------
deleteFolder($workDir);
log_submission($baseDir, [date('Y-m-d H:i:s'), $refNumber, $projectName, $toEmail, $phone, $zipName]);
$links = build_links($baseDir, $zipName, $folderName);

return [
    'success'      => true,
    'refNumber'    => $refNumber,
    'downloadLink' => $links['downloadLink'],
    'previewLink'  => $links['previewLink'],
];

} // end render_theme()
