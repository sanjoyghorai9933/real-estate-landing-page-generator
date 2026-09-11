<?php
/** Generated lead handler. Delivery credentials and recipients are server-side only. */
require __DIR__ . '/SMTPMailer.php';

function lead_fail_redirect($message = 'Unable to submit your enquiry.') {
    error_log('[lead] ' . $message);
    header('Location: thanks.html?status=error', true, 303);
    exit;
}
function lead_post_string($key, $max = 2000) {
    $value = trim((string) ($_POST[$key] ?? ''));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

$encToken = (string) ($_POST['token'] ?? '');
$decodedToken = base64_decode($encToken, true);
$parts = $decodedToken === false ? [] : explode('__s__', $decodedToken);
if (count($parts) !== 3 || !is_numeric($parts[0]) || !is_numeric($parts[1]) || !is_numeric($parts[2])
    || ((int) $parts[0] + (int) $parts[1]) !== (int) $parts[2]) {
    lead_fail_redirect('Invalid captcha.');
}

$name = lead_post_string('name', 120);
$email = lead_post_string('email', 254);
$phone = lead_post_string('phone', 50);
$message = lead_post_string('message', 3000);
$project = lead_post_string('project', 200);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// The generated public form historically did not post the project name.
// Recover it from the generated page title so the CRM lead remains project-specific.
if ($project === '' && file_exists(__DIR__ . '/index.html')) {
    $indexHtml = file_get_contents(__DIR__ . '/index.html');
    if ($indexHtml !== false && preg_match('/<title>\s*BOOK NOW\s*-\s*(.*?)\s*-\s*[^<]*<\/title>/is', $indexHtml, $m)) {
        $project = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    }
}

if ($name === '' || $phone === '' || $project === '') lead_fail_redirect('Missing required lead fields.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) lead_fail_redirect('Invalid lead email.');

$to = trim((string) getenv('PROPERTY_MATRIMONY_TO_EMAIL'));
$cc = trim((string) getenv('PROPERTY_MATRIMONY_CC_EMAIL'));
$bcc = trim((string) getenv('PROPERTY_MATRIMONY_BCC_EMAIL'));
$crmEndpoint = trim((string) getenv('PROPERTY_MATRIMONY_CRM_ENDPOINT'));
$crmApiKey = trim((string) getenv('PROPERTY_MATRIMONY_CRM_API_KEY'));

if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) lead_fail_redirect('Lead email recipient is not configured.');

$crmOk = true;
if ($crmEndpoint !== '' && $crmApiKey !== '') {
    $payload = json_encode([[
        'name' => $name,
        'mobile' => $phone,
        'project' => $project,
        'notes' => $message,
        'email' => $email,
    ]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $curl = curl_init($crmEndpoint);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['API-Key: ' . $crmApiKey, 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $crmResponse = curl_exec($curl);
    $crmHttpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $crmError = curl_error($curl);
    curl_close($curl);

    if ($crmResponse === false || $crmHttpCode < 200 || $crmHttpCode >= 300) {
        $crmOk = false;
        error_log('[lead] CRM delivery failed. HTTP=' . $crmHttpCode . ' error=' . $crmError);
    }
} else {
    $crmOk = false;
    error_log('[lead] CRM delivery is not configured.');
}

$mail = new SMTPMailer();
$mail->addTo($to);
if ($cc !== '' && filter_var($cc, FILTER_VALIDATE_EMAIL)) $mail->addCc($cc);
if ($bcc !== '' && filter_var($bcc, FILTER_VALIDATE_EMAIL)) $mail->addBcc($bcc);
$mail->Subject('Response : By ' . $project);
$mail->Body(
    '<html><body><table border="1" cellpadding="6" cellspacing="0">'
    . '<tr><th>Name</th><td>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><th>Email</th><td>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><th>Phone</th><td>' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><th>Project</th><td>' . htmlspecialchars($project, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '<tr><th>Message</th><td>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</td></tr>'
    . '<tr><th>IP</th><td>' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</td></tr>'
    . '</table></body></html>'
);

if (!$mail->Send()) {
    error_log('[lead] SMTP delivery failed. CRM status=' . ($crmOk ? 'ok' : 'failed'));
    lead_fail_redirect('SMTP delivery failed.');
}
if (!$crmOk) error_log('[lead] Lead email delivered but CRM delivery was unsuccessful.');
header('Location: thanks.html?status=success', true, 303);
exit;
