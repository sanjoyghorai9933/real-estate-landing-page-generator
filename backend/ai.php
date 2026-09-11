<?php
/**
 * backend/ai.php — server-side AI content assistant
 *
 * Keeps the OpenAI API key on the server. The browser sends only the
 * project brief; this endpoint returns structured landing-page copy.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require __DIR__ . '/core/helpers.php';

$apiKey = trim((string) getenv('OPENAI_API_KEY'));
if ($apiKey === '') {
    fail('AI is not configured. Set OPENAI_API_KEY on the server.', 503);
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 24000) {
    fail('Request is empty or too large.', 413);
}

$input = json_decode($raw, true);
if (!is_array($input)) {
    fail('Invalid JSON request.', 400);
}

$brief = trim((string) ($input['brief'] ?? ''));
$themeId = trim((string) ($input['themeId'] ?? 'default'));
if ($brief === '' || mb_strlen($brief) < 10) {
    fail('Please provide a little more project information.', 400);
}
if (mb_strlen($brief) > 10000) {
    fail('Project brief is too long. Keep it under 10,000 characters.', 400);
}
if (!preg_match('/^[a-z0-9_-]+$/', $themeId)) {
    fail('Invalid theme.', 400);
}

$schema = [
    'type' => 'object',
    'additionalProperties' => false,
    'properties' => [
        'projectName' => ['type' => 'string'],
        'statusBadge' => ['type' => 'string'],
        'priceRange' => ['type' => 'string'],
        'address' => ['type' => 'string'],
        'landArea' => ['type' => 'string'],
        'totalUnits' => ['type' => 'string'],
        'floors' => ['type' => 'string'],
        'highlights' => ['type' => 'array', 'items' => ['type' => 'string']],
        'configHeading' => ['type' => 'string'],
        'locationAdvantages' => ['type' => 'array', 'items' => ['type' => 'string']],
        'aboutBuilderHeading' => ['type' => 'string'],
        'aboutBuilderText' => ['type' => 'string'],
        'priceRows' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'type' => ['type' => 'string'],
                    'area' => ['type' => 'string'],
                    'price' => ['type' => 'string'],
                ],
                'required' => ['type', 'area', 'price'],
            ],
        ],
    ],
    'required' => [
        'projectName', 'statusBadge', 'priceRange', 'address', 'landArea',
        'totalUnits', 'floors', 'highlights', 'configHeading',
        'locationAdvantages', 'aboutBuilderHeading', 'aboutBuilderText', 'priceRows',
    ],
];

$payload = [
    'model' => getenv('OPENAI_MODEL') ?: 'gpt-5.5',
    'store' => false,
    'instructions' => "You are a real-estate landing-page copy assistant. Turn the supplied project brief into polished, factual marketing copy. Never invent exact prices, addresses, unit counts, approvals, RERA numbers, distances, amenities, developer claims, or other factual details. If a detail is missing, return an empty string or omit it from arrays. Keep copy concise and suitable for a premium property landing page. The selected theme is {$themeId}.",
    'input' => $brief,
    'text' => [
        'format' => [
            'type' => 'json_schema',
            'name' => 'real_estate_landing_content',
            'strict' => true,
            'schema' => $schema,
        ],
    ],
    'max_output_tokens' => 1800,
];

$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($responseBody === false) {
    fail('AI request failed: ' . ($curlError ?: 'network error'), 502);
}

$response = json_decode($responseBody, true);
if (!is_array($response)) {
    fail('AI returned an invalid response.', 502);
}
if ($status < 200 || $status >= 300) {
    $message = $response['error']['message'] ?? 'OpenAI request failed.';
    fail($message, 502);
}

$text = $response['output_text'] ?? '';
if ($text === '' && !empty($response['output']) && is_array($response['output'])) {
    foreach ($response['output'] as $item) {
        if (($item['type'] ?? '') !== 'message') continue;
        foreach (($item['content'] ?? []) as $content) {
            if (($content['type'] ?? '') === 'output_text') {
                $text = (string) ($content['text'] ?? '');
                break 2;
            }
        }
    }
}

$data = json_decode($text, true);
if (!is_array($data)) {
    fail('AI returned content in an unexpected format.', 502);
}

// Server-side output limits: keep the browser and generated pages bounded.
$data['highlights'] = array_slice(array_values(array_filter((array) ($data['highlights'] ?? []), 'is_string')), 0, 8);
$data['locationAdvantages'] = array_slice(array_values(array_filter((array) ($data['locationAdvantages'] ?? []), 'is_string')), 0, 8);
$data['priceRows'] = array_slice(array_values(array_filter((array) ($data['priceRows'] ?? []), 'is_array')), 0, 8);

echo json_encode(['success' => true, 'content' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
