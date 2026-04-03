<?php
/* ═══════════════════════════════════════════════════════════════
   META CONVERSIONS API — Server-Side Event Tracking
   Receives events from meta-pixel.js and forwards to Meta CAPI
   ═══════════════════════════════════════════════════════════════ */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://jallous-webdesign.de');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Load credentials and analytics
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/analytics-db.php';

// Parse request body
$raw = file_get_contents('php://input');
if (strlen($raw) > 50000) {
    http_response_code(413);
    echo json_encode(['error' => 'Payload too large']);
    exit;
}
$input = json_decode($raw, true);
if (!$input || !isset($input['events']) || !is_array($input['events'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit;
}

// Build CAPI events
$allowedEvents = ['PageView', 'ViewContent', 'Contact', 'FormVisible', 'FormStart', 'FormStep', 'Lead', 'SectionView', 'SectionTime', 'ScrollDepth', 'EngagementScore', 'TimeOnPage', 'ProjectCTA'];
$events = [];
foreach ($input['events'] as $event) {
    $eventName = $event['event_name'] ?? '';
    $eventId = $event['event_id'] ?? '';
    if (!$eventName || !$eventId) continue;
    if (!in_array($eventName, $allowedEvents)) continue;

    $capiEvent = [
        'event_name'  => $eventName,
        'event_time'  => time(),
        'event_id'    => $eventId, // Deduplication with pixel
        'action_source' => 'website',
        'event_source_url' => $event['source_url'] ?? '',
        'user_data'   => [
            'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'fbc' => $_COOKIE['_fbc'] ?? $event['fbc'] ?? null,
            'fbp' => $_COOKIE['_fbp'] ?? $event['fbp'] ?? null,
        ],
    ];

    // Add hashed user data if provided (email, phone)
    if (!empty($event['user_data'])) {
        if (!empty($event['user_data']['em'])) {
            $capiEvent['user_data']['em'] = [hash('sha256', strtolower(trim($event['user_data']['em'])))];
        }
        if (!empty($event['user_data']['ph'])) {
            $phone = preg_replace('/[^0-9]/', '', $event['user_data']['ph']);
            $capiEvent['user_data']['ph'] = [hash('sha256', $phone)];
        }
        if (!empty($event['user_data']['fn'])) {
            $capiEvent['user_data']['fn'] = [hash('sha256', strtolower(trim($event['user_data']['fn'])))];
        }
    }

    // Remove null values from user_data
    $capiEvent['user_data'] = array_filter($capiEvent['user_data'], function($v) {
        return $v !== null && $v !== '';
    });

    // Add custom data if present
    if (!empty($event['custom_data'])) {
        $capiEvent['custom_data'] = $event['custom_data'];
    }

    $events[] = $capiEvent;
}

if (empty($events)) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid events']);
    exit;
}

// Log events locally for dashboard (non-blocking, never crashes main flow)
try {
    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d'));
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $localEvents = [];
    foreach ($input['events'] as $ev) {
        $cd = $ev['custom_data'] ?? [];
        $localEvents[] = [
            'event_name'  => $ev['event_name'] ?? '',
            'page'        => $cd['page'] ?? $cd['content_name'] ?? null,
            'page_type'   => $cd['page_type'] ?? null,
            'custom_data' => !empty($cd) ? json_encode($cd) : null,
            'ip_hash'     => $ipHash,
            'user_agent'  => $ua,
            'referrer'    => $ref,
        ];
    }
    if (!empty($localEvents)) {
        logEvents($localEvents);
    }
} catch (Exception $e) {
    // Silently fail — analytics logging must never affect tracking
}

// Send to Meta CAPI
$payload = json_encode([
    'data'         => $events,
    'access_token' => META_ACCESS_TOKEN,
]);

$url = 'https://graph.facebook.com/v21.0/' . META_PIXEL_ID . '/events';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_CONNECTTIMEOUT => 3,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log('CAPI request failed: ' . $curlError);
    http_response_code(502);
    echo json_encode(['error' => 'Request failed']);
    exit;
}

http_response_code($httpCode >= 200 && $httpCode < 300 ? 200 : $httpCode);
echo $response;
