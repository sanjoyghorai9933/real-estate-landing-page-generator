<?php
/**
 * request_validation.php
 * Theme-agnostic request hardening for the page-generation endpoint.
 */

if (!function_exists('apply_property_matrimony_delivery_defaults')) {
    function apply_property_matrimony_delivery_defaults() {
        // Lead delivery is client-specific. If deployment variables are set,
        // never trust editable browser fields for the actual recipient values.
        $fixedTo = trim((string) getenv('PROPERTY_MATRIMONY_TO_EMAIL'));
        $fixedCc = trim((string) getenv('PROPERTY_MATRIMONY_CC_EMAIL'));
        $fixedBcc = trim((string) getenv('PROPERTY_MATRIMONY_BCC_EMAIL'));
        if ($fixedTo !== '') $_POST['toEmail'] = $fixedTo;
        if ($fixedCc !== '') $_POST['ccEmail'] = $fixedCc;
        if ($fixedBcc !== '') $_POST['bccEmail'] = $fixedBcc;
        $_POST['crmOption'] = 'crm';
        $_POST['crmApiKey'] = '';
    }
}

if (!function_exists('validate_generation_request')) {
    function validate_generation_request() {
        apply_property_matrimony_delivery_defaults();

        $maxTextLengths = [
            'projectName' => 180,
            'statusBadge' => 120,
            'priceRange' => 180,
            'address' => 500,
            'landArea' => 120,
            'totalUnits' => 80,
            'floors' => 80,
            'phone' => 50,
            'toEmail' => 254,
            'ccEmail' => 254,
            'bccEmail' => 254,
            'mapLink' => 2048,
            'refNumber' => 120,
            'configHeading' => 180,
            'disclaimerText' => 8000,
            'aboutBuilderHeading' => 180,
            'aboutBuilderText' => 5000,
            'gtagId' => 120,
            'conversionSendTo' => 180,
            'crmApiKey' => 1,
        ];

        foreach ($maxTextLengths as $key => $max) {
            if (!isset($_POST[$key])) continue;
            if (is_array($_POST[$key])) fail("Invalid value for {$key}.", 400);
            if (strlen((string) $_POST[$key]) > $max) fail("The {$key} field is too long (maximum {$max} characters).", 400);
        }

        $jsonFields = ['highlights', 'locationAdvantages', 'priceRows'];
        foreach ($jsonFields as $key) {
            if (!isset($_POST[$key]) || $_POST[$key] === '') continue;
            if (is_array($_POST[$key]) || strlen((string) $_POST[$key]) > 100000) fail("Invalid {$key} payload.", 400);
            $decoded = json_decode((string) $_POST[$key], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) fail("Invalid {$key} JSON.", 400);
            if (count($decoded) > 100) fail("Too many {$key} entries (maximum 100).", 400);
        }

        validate_generation_uploads();
    }
}

if (!function_exists('validate_generation_uploads')) {
    function validate_generation_uploads() {
        $allowed = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ];
        $maxBytes = 8 * 1024 * 1024;
        $maxImages = 30;
        $imageCount = 0;

        foreach ($_FILES as $field => $payload) {
            if (!is_array($payload) || !isset($payload['tmp_name'])) continue;
            $tmpNames = is_array($payload['tmp_name']) ? $payload['tmp_name'] : [$payload['tmp_name']];
            $names = is_array($payload['name'] ?? null) ? $payload['name'] : [$payload['name'] ?? ''];
            $errors = is_array($payload['error'] ?? null) ? $payload['error'] : [$payload['error'] ?? UPLOAD_ERR_NO_FILE];
            $sizes = is_array($payload['size'] ?? null) ? $payload['size'] : [$payload['size'] ?? 0];

            foreach ($tmpNames as $i => $tmp) {
                $error = $errors[$i] ?? UPLOAD_ERR_NO_FILE;
                if ($error === UPLOAD_ERR_NO_FILE) continue;
                if ($error !== UPLOAD_ERR_OK) fail("Upload failed for {$field}.", 400);
                if (!is_string($tmp) || !is_uploaded_file($tmp)) fail("Invalid upload for {$field}.", 400);

                $size = (int) ($sizes[$i] ?? 0);
                if ($size <= 0 || $size > $maxBytes) fail('Each image must be between 1 byte and 8 MB.', 400);

                $originalName = (string) ($names[$i] ?? '');
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) fail("Unsupported image type for {$field}. Use JPG, PNG, or WebP.", 400);

                $info = @getimagesize($tmp);
                if ($info === false || empty($info['mime']) || !isset($allowed[$info['mime']])) fail("The uploaded file for {$field} is not a valid supported image.", 400);
                if (!in_array($ext, $allowed[$info['mime']], true)) fail("The file extension does not match the image type for {$field}.", 400);

                $width = (int) ($info[0] ?? 0);
                $height = (int) ($info[1] ?? 0);
                if ($width < 100 || $height < 100 || $width > 10000 || $height > 10000) fail("Image dimensions for {$field} must be between 100 and 10,000 pixels.", 400);

                $imageCount++;
                if ($imageCount > $maxImages) fail('Too many images. Maximum 30 images per generated page.', 400);
            }
        }

        if (empty($_FILES['slider']['tmp_name'])) fail('At least one slider image is required.', 400);
    }
}
