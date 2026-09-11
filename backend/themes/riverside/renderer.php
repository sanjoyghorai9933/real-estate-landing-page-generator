<?php
/**
 * themes/riverside/renderer.php
 * ---------------------------------------------------------
 * Renderer for the "riverside" theme — converted from an
 * uploaded static design (rich, content-heavy luxury residences
 * microsite). Fully dynamic sections (highlights, amenities,
 * price cards, floor plan, gallery, location advantages), same
 * pattern the "default" theme uses. Four lead forms (hero,
 * contact section, enquiry modal, on-load popup), each wired to
 * the shared crm_connect.php + a per-form math captcha.
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

$metaDescription = trim($_POST['metaDescription'] ?? '');
$metaKeywords    = trim($_POST['metaKeywords'] ?? '');

$overviewText = trim($_POST['overviewText'] ?? '');
if ($overviewText === '') {
    $overviewText = "{$projectName} is a thoughtfully designed residential development offering spacious layouts, elegant interiors, and a full suite of modern lifestyle amenities.\nEvery residence is planned for comfort, connectivity, and long-term value in one of the city's most sought-after addresses.";
}

$aboutDeveloperText = trim($_POST['aboutDeveloperText'] ?? '');
if ($aboutDeveloperText === '') {
    $aboutDeveloperText = "We believe great living is defined by thoughtful design, quality construction, and an unmatched lifestyle experience.\n{$projectName} reflects that vision — combining contemporary architecture with world-class amenities to create homes for modern families.";
}

$footerDisclaimerText = trim($_POST['footerDisclaimerText'] ?? '');
if ($footerDisclaimerText === '') {
    $footerDisclaimerText = 'The contents, information, images, visuals or sketches, computer generated images including landscaping on the website are merely representative images or artistic renderings for general informational purposes only, unless specifically claimed to be actual photograph. The maps, floor plans and layouts are not drawn to any particular scale, and are subject to change without notice.';
}

$whatsappLink = trim($_POST['whatsappLink'] ?? '');
if ($whatsappLink === '') $whatsappLink = 'https://wa.me/';

$virtualTourEmbedUrl = trim($_POST['virtualTourEmbedUrl'] ?? '');

$gtagId           = trim($_POST['gtagId'] ?? '');
$conversionSendTo = trim($_POST['conversionSendTo'] ?? '');

$heroBadges         = json_decode($_POST['heroBadges'] ?? '[]', true) ?: [];
$highlights         = json_decode($_POST['highlights'] ?? '[]', true) ?: [];
$priceRows          = json_decode($_POST['priceRows'] ?? '[]', true) ?: [];
$locationAdvantages = json_decode($_POST['locationAdvantages'] ?? '[]', true) ?: [];

// Section toggles — default to included when not sent
$includeHighlights  = ($_POST['includeHighlights'] ?? '1') === '1';
$includeAmenities   = ($_POST['includeAmenities'] ?? '1') === '1';
$includePrice       = ($_POST['includePrice'] ?? '1') === '1';
$includeFloorplan   = ($_POST['includeFloorplan'] ?? '1') === '1';
$includeMasterplan  = ($_POST['includeMasterplan'] ?? '1') === '1';
$includeGallery     = ($_POST['includeGallery'] ?? '1') === '1';
$includeLocation    = ($_POST['includeLocation'] ?? '1') === '1';
$includeVirtualTour = ($virtualTourEmbedUrl !== '') && (($_POST['includeVirtualTour'] ?? '1') === '1');

$colorPrimary   = sanitizeColor($_POST['colorPrimary'] ?? '', '#956543');
$colorSecondary = sanitizeColor($_POST['colorSecondary'] ?? '', '#1a1a1a');

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
if (empty($_FILES['heroImage']['tmp_name'][0]) && empty($_FILES['heroImage']['tmp_name'])) {
    // heroImage is posted as an array field (heroImage[]) for the unlimited slider —
    // require at least one valid file below; this early check just guards totally-empty submits.
}

// ---------------------------------------------------------
// 2. Prepare a working folder for this submission
// ---------------------------------------------------------
$slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($projectName));
$slug = trim($slug, '-') ?: 'project';
$folderName = $slug . '-' . date('Ymd-His');

$workDir   = $baseDir . '/output/' . $folderName;
$outputDir = $baseDir . '/output/zips';

if (!mkdir($workDir . '/assets/img', 0755, true) && !is_dir($workDir . '/assets/img')) {
    fail('Could not create working directory.', 500);
}

// ---------------------------------------------------------
// 3. Copy static files + this theme's default assets (fallback
//    images live here — used whenever the visitor doesn't upload
//    their own for that slot)
// ---------------------------------------------------------
copy($templatesDir . '/SMTPMailer.php', $workDir . '/SMTPMailer.php');
copy($templatesDir . '/config_smtp-template.php', $workDir . '/config_smtp.php');
copyDirRecursive($staticAssetsDir, $workDir . '/assets');

// ---------------------------------------------------------
// 4. Save uploaded images (falling back to the theme's default
//    asset filename when nothing was uploaded for that slot)
// ---------------------------------------------------------
$allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

function riversideSaveSingle($field, $destPath, $allowedExt) {
    if (!empty($_FILES[$field]['tmp_name']) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) return false;
        move_uploaded_file($_FILES[$field]['tmp_name'], $destPath);
        return true;
    }
    return false;
}

// -- Unlimited image list (hero slider) --
function riversideSaveMulti($field, $workDir, $prefix, $allowedExt) {
    $saved = [];
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['tmp_name'])) return $saved;
    $files = $_FILES[$field];
    foreach ($files['tmp_name'] as $i => $tmp) {
        if (empty($tmp) || !is_uploaded_file($tmp) || ($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) continue;
        $n = count($saved) + 1;
        $destName = "{$prefix}{$n}.jpg";
        move_uploaded_file($tmp, $workDir . '/assets/img/' . $destName);
        $saved[] = $destName;
    }
    return $saved;
}

// -- Unlimited image list paired with a text label (amenities / floor plan / gallery) --
function riversideSaveLabeled($fileField, $labelField, $workDir, $prefix, $allowedExt) {
    $items = [];
    if (empty($_FILES[$fileField]) || !is_array($_FILES[$fileField]['tmp_name'])) return $items;
    $files  = $_FILES[$fileField];
    $labels = $_POST[$labelField] ?? [];
    foreach ($files['tmp_name'] as $i => $tmp) {
        if (empty($tmp) || !is_uploaded_file($tmp) || ($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) continue;
        $n = count($items) + 1;
        $destName = "{$prefix}{$n}.jpg";
        move_uploaded_file($tmp, $workDir . '/assets/img/' . $destName);
        $items[] = [
            'file'  => $destName,
            'label' => trim($labels[$i] ?? ''),
        ];
    }
    return $items;
}

// Logo (fallback to the theme's own default logo, already copied above)
riversideSaveSingle('logo', $workDir . '/assets/img/logo.png', $allowedExt);

// Hero slider — unlimited uploads, falls back to the theme's 2 default banners
$heroFiles = riversideSaveMulti('heroImage', $workDir, 'hero', $allowedExt);
if (empty($heroFiles)) {
    $heroFiles = array_values(array_filter(
        ['banner1.webp', 'banner2.webp'],
        function ($f) use ($workDir) { return file_exists($workDir . '/assets/img/' . $f); }
    ));
}
if (empty($heroFiles)) {
    fail('A hero banner image is required.');
}

// Single-slot images with theme defaults
$overviewImageUploaded  = riversideSaveSingle('overviewImage', $workDir . '/assets/img/overview.webp', $allowedExt);
$highlightImageUploaded = riversideSaveSingle('highlightImage', $workDir . '/assets/img/banner2.webp', $allowedExt);
$masterplanUploaded     = riversideSaveSingle('masterplanImage', $workDir . '/assets/img/floor-plan/master-plan.png', $allowedExt);
$locationImageUploaded  = riversideSaveSingle('locationImage', $workDir . '/assets/img/location.webp', $allowedExt);

$overviewImage  = 'overview.webp';
$highlightImage = 'banner2.webp';
$masterplanImage = 'floor-plan/master-plan.png';
$locationImage  = 'location.webp';

// Amenities — unlimited image+label uploads, falls back to the theme's default 6
$amenityEntries = riversideSaveLabeled('amenityImage', 'amenityLabel', $workDir, 'amenities/am', $allowedExt);
if (empty($amenityEntries)) {
    $defaultAmenities = [
        ['file' => 'amenities/am1.webp', 'label' => 'Clubhouse & Gymnasium'],
        ['file' => 'amenities/am2.webp', 'label' => 'Swimming Pool'],
        ['file' => 'amenities/am3.webp', 'label' => 'Parking Services'],
        ['file' => 'amenities/kidsplay.jpg', 'label' => 'Kids Play Area'],
        ['file' => 'amenities/am5.webp', 'label' => 'Jogging Track'],
        ['file' => 'amenities/am6.webp', 'label' => '24×7 Security'],
    ];
    $amenityEntries = array_values(array_filter($defaultAmenities, function ($e) use ($workDir) {
        return file_exists($workDir . '/assets/img/' . $e['file']);
    }));
}

// Floor plan — unlimited image+label uploads, falls back to the theme's default 4
$floorplanEntries = riversideSaveLabeled('floorplanImage', 'floorplanLabel', $workDir, 'floor-plan/plan', $allowedExt);
if (empty($floorplanEntries)) {
    $defaultFloorplans = [
        ['file' => 'floor-plan/floor-plan-1.png', 'label' => 'Type A'],
        ['file' => 'floor-plan/floor-plan-2.png', 'label' => 'Type B'],
        ['file' => 'floor-plan/floor-plan-3.png', 'label' => 'Type C'],
        ['file' => 'floor-plan/floor-plan-4.png', 'label' => 'Type D'],
    ];
    $floorplanEntries = array_values(array_filter($defaultFloorplans, function ($e) use ($workDir) {
        return file_exists($workDir . '/assets/img/' . $e['file']);
    }));
}

// Gallery — unlimited image+caption uploads, falls back to the theme's default 6
$galleryEntries = riversideSaveLabeled('galleryImage', 'galleryCaption', $workDir, 'gallery/photo', $allowedExt);
if (empty($galleryEntries)) {
    $defaultGallery = [
        ['file' => 'gallery/gallery01.png', 'label' => 'Gallery'],
        ['file' => 'gallery/gallery02.webp', 'label' => 'Lifestyle'],
        ['file' => 'gallery/gallery03.webp', 'label' => 'Amenities'],
        ['file' => 'gallery/gallery04.webp', 'label' => 'Architecture'],
        ['file' => 'gallery/gallery05.jpg', 'label' => 'Interiors'],
        ['file' => 'gallery/gallery06.webp', 'label' => 'Landscape'],
    ];
    $galleryEntries = array_values(array_filter($defaultGallery, function ($e) use ($workDir) {
        return file_exists($workDir . '/assets/img/' . $e['file']);
    }));
}

$includeAmenities  = $includeAmenities && count($amenityEntries) > 0;
$includeFloorplan  = $includeFloorplan && count($floorplanEntries) > 0;
$includeGallery    = $includeGallery && count($galleryEntries) > 0;
$includeMasterplan = $includeMasterplan && ($masterplanUploaded || file_exists($workDir . '/assets/img/' . $masterplanImage));
$includeLocation   = $includeLocation && ($locationImageUploaded || file_exists($workDir . '/assets/img/' . $locationImage));

// ---------------------------------------------------------
// 5. Build the dynamic HTML fragments
// ---------------------------------------------------------

// Hero slider items + indicators
$heroSliderItems = '';
$heroSliderIndicators = '';
foreach ($heroFiles as $i => $file) {
    $activeClass = $i === 0 ? ' active' : '';
    $heroSliderItems .= "      <div class=\"carousel-item{$activeClass}\">\n        <img src=\"assets/img/{$file}\" class=\"banner-img d-block w-100\" alt=\"" . e($projectName) . "\">\n      </div>\n";
    $heroSliderIndicators .= "      <li data-target=\"#carouselExampleCaptions\" data-slide-to=\"{$i}\"" . ($i === 0 ? ' class="active"' : '') . "></li>\n";
}

// Hero badges (label/value pairs, up to 3, matches source design's layout)
$heroBadgesHtml = '';
foreach (array_slice($heroBadges, 0, 3) as $badge) {
    $label = trim($badge['label'] ?? '');
    $value = trim($badge['value'] ?? '');
    if ($label === '' && $value === '') continue;
    $heroBadgesHtml .= "                <li class=\"hero-banner__badge-line\">\n";
    $heroBadgesHtml .= "                  <span class=\"hero-banner__badge-label\">" . e($label) . "</span>\n";
    $heroBadgesHtml .= "                  <span class=\"hero-banner__badge-value\">" . e($value) . "</span>\n";
    $heroBadgesHtml .= "                </li>\n";
}

// Overview / About Developer text — textarea, blank-line-separated paragraphs
function riversideParagraphs($text) {
    $paras = preg_split('/\r?\n\s*\r?\n|\r?\n/', trim($text));
    $html = '';
    foreach ($paras as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $html .= '<p>' . e($p) . "</p>\n";
    }
    return $html;
}
$overviewTextHtml      = riversideParagraphs($overviewText);
$aboutDeveloperTextHtml = riversideParagraphs($aboutDeveloperText);

// Highlights -> numbered grid
$highlightsHtml = '';
$hlItems = array_values(array_filter(array_map('trim', $highlights)));
foreach ($hlItems as $i => $item) {
    $num = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
    $highlightsHtml .= "              <li>\n                <span class=\"hl-split__num\">{$num}</span>\n                <p>" . e($item) . "</p>\n              </li>\n";
}
$includeHighlights = $includeHighlights && count($hlItems) > 0;

// Amenities grid (tall tile every 3rd item, matches source design's masonry pattern)
$amenitiesHtml = '';
foreach ($amenityEntries as $i => $entry) {
    $tallClass = ($i % 3 === 0) ? ' tall' : '';
    $img   = $entry['file'];
    $label = $entry['label'] !== '' ? $entry['label'] : 'Amenity';
    $amenitiesHtml .= "        <figure class=\"imgbox{$tallClass}\">\n          <img src=\"assets/img/{$img}\" class=\"am-images\" alt=\"" . e($label) . "\">\n          <figcaption class=\"fig-cap\">" . e($label) . "</figcaption>\n        </figure>\n\n";
}

// Price cards
$priceCardsHtml = '';
$priceRowItems = array_values(array_filter($priceRows, function ($r) {
    return trim($r['type'] ?? '') !== '';
}));
if (empty($priceRowItems)) {
    $includePrice = false;
} else {
    foreach ($priceRowItems as $row) {
        $type  = e(trim($row['type'] ?? ''));
        $size  = e(trim($row['size'] ?? ''));
        $price = e(trim($row['price'] ?? '') ?: 'On Request');
        $priceCardsHtml .= <<<HTML
        <article class="price-card">
          <div class="price-card__block">
            <span class="price-card__label">Unit Type</span>
            <span class="price-card__detail">{$type}</span>
          </div>
          <div class="price-card__block">
            <span class="price-card__label">Unit Sizes</span>
            <span class="price-card__detail">{$size}</span>
          </div>
          <div class="price-card__block price-card__block--price">
            <span class="price-card__label">Price</span>
            <span class="price-card__detail price-card__detail--price">{$price}</span>
          </div>
          <div class="price-card__cta">
            <button type="button" data-toggle="modal" data-target="#exampleModal1">Know More</button>
          </div>
        </article>

HTML;
    }
}

// Floor plan cards
$floorplanCardsHtml = '';
foreach ($floorplanEntries as $entry) {
    $img   = $entry['file'];
    $label = e($entry['label'] !== '' ? $entry['label'] : 'Floor Plan');
    $floorplanCardsHtml .= <<<HTML
        <article class="floor-card">
          <div class="floor-card__thumb">
            <img src="assets/img/{$img}" alt="{$label}" loading="lazy" decoding="async">
          </div>
          <div class="floor-card__block">
            <span class="floor-card__label">Unit Type</span>
            <span class="floor-card__detail">{$label}</span>
          </div>
          <div class="floor-card__cta">
            <button type="button" data-toggle="modal" data-target="#exampleModal1">Know More</button>
          </div>
        </article>

HTML;
}

// Gallery grid (magnific popup lightbox)
$galleryHtml = '';
foreach ($galleryEntries as $i => $entry) {
    $img     = $entry['file'];
    $label   = e($entry['label'] !== '' ? $entry['label'] : $projectName);
    $feature = $i === 0 ? ' gallery-tile--feature' : '';
    $galleryHtml .= <<<HTML
        <figure class="gallery-tile{$feature}">
          <a href="assets/img/{$img}" class="with-caption image-link" title="{$label}">
            <img src="assets/img/{$img}" alt="{$label}">
            <span class="gallery-tile__overlay">
              <span class="gallery-tile__icon" aria-hidden="true"></span>
              <span class="gallery-tile__label">View</span>
            </span>
          </a>
        </figure>

HTML;
}

// Location advantages — bulleted list (entries may contain simple inline HTML like <strong>)
$locationAdvantagesHtml = '';
$locAdvItems = array_values(array_filter(array_map('trim', $locationAdvantages)));
foreach ($locAdvItems as $adv) {
    $locationAdvantagesHtml .= "            <li>\n              <span class=\"loc-connect__icon\" aria-hidden=\"true\"></span>\n              <span class=\"loc-connect__text\">" . $adv . "</span>\n            </li>\n";
}
if (empty($locAdvItems)) {
    $includeLocation = false;
}

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
    '{{PAGE_TITLE}}'               => e("{$projectName} - {$locationTagline}"),
    '{{META_DESCRIPTION}}'         => e($metaDescription !== '' ? $metaDescription : "{$projectName} at {$locationTagline}. {$priceText}. Enquire for price list, floor plans & site visit."),
    '{{META_KEYWORDS}}'            => e($metaKeywords),
    '{{PROJECT_NAME}}'             => e($projectName),
    '{{LOCATION_TAGLINE}}'         => e($locationTagline),
    '{{HERO_SUBTITLE}}'            => e($heroSubtitle),
    '{{PRICE_TEXT}}'               => e($priceText),
    '{{HERO_FIRST_IMAGE}}'         => e($heroFiles[0]),
    '{{HERO_SLIDER_ITEMS}}'        => $heroSliderItems,
    '{{HERO_SLIDER_INDICATORS}}'   => $heroSliderIndicators,
    '{{HERO_BADGES_HTML}}'         => $heroBadgesHtml,
    '{{OVERVIEW_IMAGE}}'           => e($overviewImage),
    '{{OVERVIEW_TEXT_HTML}}'       => $overviewTextHtml,
    '{{HIGHLIGHT_IMAGE}}'          => e($highlightImage),
    '{{HIGHLIGHTS_HTML}}'          => $highlightsHtml,
    '{{AMENITIES_HTML}}'           => $amenitiesHtml,
    '{{PRICE_CARDS_HTML}}'         => $priceCardsHtml,
    '{{FLOORPLAN_CARDS_HTML}}'     => $floorplanCardsHtml,
    '{{MASTERPLAN_IMAGE}}'         => e($masterplanImage),
    '{{GALLERY_HTML}}'             => $galleryHtml,
    '{{VIRTUAL_TOUR_EMBED_URL}}'   => e($virtualTourEmbedUrl),
    '{{LOCATION_IMAGE}}'           => e($locationImage),
    '{{LOCATION_ADVANTAGES_HTML}}' => $locationAdvantagesHtml,
    '{{ABOUT_DEVELOPER_TEXT_HTML}}' => $aboutDeveloperTextHtml,
    '{{FOOTER_DISCLAIMER_TEXT}}'   => e($footerDisclaimerText),
    '{{CURRENT_YEAR}}'             => date('Y'),
    '{{WHATSAPP_LINK}}'            => e($whatsappLink),
    '{{PHONE_TEL}}'                => e($phoneTel),
    '{{PHONE_DISPLAY}}'            => e($phone),
    '{{COLOR_PRIMARY}}'            => $colorPrimary,
    '{{COLOR_SECONDARY}}'          => $colorSecondary,
    '{{GTAG_HEAD_SNIPPET}}'        => $gtagHeadSnippet,
];
$indexHtml = strtr($indexTemplate, $tokens);

// ---------------------------------------------------------
// 6b. Strip out any page sections not included (uploaded content
//     missing for that slot, or explicitly deselected)
// ---------------------------------------------------------
function riversideApplySectionToggle($html, $marker, $include) {
    $start = "<!--SECTION:{$marker}_START-->";
    $end   = "<!--SECTION:{$marker}_END-->";
    if ($include) {
        return str_replace([$start, $end], '', $html);
    }
    $pattern = '/' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '/s';
    return preg_replace($pattern, '', $html);
}
$sectionToggles = [
    'HIGHLIGHT'       => $includeHighlights,
    'AMENITIES'       => $includeAmenities,
    'PRICE'           => $includePrice,
    'FLOORPLAN'       => $includeFloorplan,
    'MASTERPLAN'      => $includeMasterplan,
    'GALLERY'         => $includeGallery,
    'VIRTUALTOUR'     => $includeVirtualTour,
    'LOCATION'        => $includeLocation,
    'ABOUTDEVELOPER'  => true, // always has fallback copy, like the default theme's About Builder
];
foreach ($sectionToggles as $marker => $include) {
    $indexHtml = riversideApplySectionToggle($indexHtml, $marker, $include);
}

file_put_contents($workDir . '/index.html', $indexHtml);

// ---------------------------------------------------------
// 7. Fill crm_connect.php (mail-only lead capture, same as palm-estate)
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
