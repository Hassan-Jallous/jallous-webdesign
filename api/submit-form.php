<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://jallous-webdesign.de');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/smtp.php';

// --- Rate limiting ---
session_start();
$now = time();
if (!isset($_SESSION['form_submissions'])) {
    $_SESSION['form_submissions'] = [];
}
$_SESSION['form_submissions'] = array_filter($_SESSION['form_submissions'], function ($ts) use ($now) {
    return ($now - $ts) < 3600;
});
if (count($_SESSION['form_submissions']) >= 3) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Zu viele Anfragen. Bitte versuche es später erneut.']);
    exit;
}

// --- Parse & validate ---
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
    exit;
}

$name  = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');

if ($name === '' || $email === '' || $phone === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Name, E-Mail und Telefon sind Pflichtfelder.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige E-Mail-Adresse.']);
    exit;
}

// --- Collect fields ---
$branche    = htmlspecialchars($data['branche'] ?? '', ENT_QUOTES, 'UTF-8');
$hasWebsite = htmlspecialchars($data['hasWebsite'] ?? '', ENT_QUOTES, 'UTF-8');
$websiteUrl = htmlspecialchars($data['websiteUrl'] ?? '', ENT_QUOTES, 'UTF-8');
$problem    = htmlspecialchars($data['problem'] ?? '', ENT_QUOTES, 'UTF-8');
$umsatz     = htmlspecialchars($data['umsatz'] ?? '', ENT_QUOTES, 'UTF-8');
$kunden     = htmlspecialchars($data['kunden'] ?? '', ENT_QUOTES, 'UTF-8');
$nameSafe   = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$emailSafe  = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$phoneSafe  = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$firstName  = htmlspecialchars(explode(' ', $name)[0], ENT_QUOTES, 'UTF-8');

$websiteDisplay = ($hasWebsite === 'ja' && $websiteUrl !== '')
    ? "Ja — {$websiteUrl}"
    : ($hasWebsite === 'nein' ? 'Nein' : $hasWebsite);

$dateTime = date('d.m.Y H:i', time());

// ═══════════════════════════════════════════════
// EMAIL 1: Benachrichtigung an Hassan
// ═══════════════════════════════════════════════
$notifySubject = "Neue Anfrage von {$name}";

$notifyBody = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin: 0; padding: 0; background: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background: #f4f4f4; padding: 40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">

  <!-- Header -->
  <tr>
    <td style="background: #000; padding: 32px 40px; text-align: center;">
      <div style="font-size: 20px; font-weight: 700; color: #fff; letter-spacing: 6px;">JALLOUS</div>
      <div style="font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 4px; letter-spacing: 1px;">WEBDESIGN</div>
    </td>
  </tr>

  <!-- Title -->
  <tr>
    <td style="padding: 36px 40px 20px;">
      <div style="font-size: 13px; color: #999; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 8px;">Neue Anfrage</div>
      <div style="font-size: 24px; font-weight: 700; color: #111;">{$nameSafe}</div>
      <div style="font-size: 14px; color: #888; margin-top: 4px;">{$dateTime}</div>
    </td>
  </tr>

  <tr><td style="padding: 0 40px;"><div style="border-top: 1px solid #eee;"></div></td></tr>

  <!-- Data -->
  <tr>
    <td style="padding: 24px 40px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f5; width: 140px; vertical-align: top;">
            <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 1px;">Branche</div>
          </td>
          <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top;">
            <div style="font-size: 15px; color: #333;">{$branche}</div>
          </td>
        </tr>
        <tr>
          <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top;">
            <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 1px;">Website</div>
          </td>
          <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top;">
            <div style="font-size: 15px; color: #333;">{$websiteDisplay}</div>
          </td>
        </tr>
        <tr>
          <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top;">
            <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 1px;">Herausforderung</div>
          </td>
          <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top;">
            <div style="font-size: 15px; color: #333;">{$problem}</div>
          </td>
        </tr>
        <tr>
          <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top;">
            <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 1px;">Umsatz</div>
          </td>
          <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top;">
            <div style="font-size: 15px; color: #333;">{$umsatz}</div>
          </td>
        </tr>
        <tr>
          <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top;">
            <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 1px;">Kundengewinnung</div>
          </td>
          <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top;">
            <div style="font-size: 15px; color: #333;">{$kunden}</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <tr><td style="padding: 0 40px;"><div style="border-top: 1px solid #eee;"></div></td></tr>

  <!-- Contact Actions -->
  <tr>
    <td style="padding: 24px 40px 32px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td width="50%" style="padding-right: 8px;">
            <a href="mailto:{$emailSafe}" style="display: block; padding: 14px 20px; background: #000; color: #fff; text-decoration: none; border-radius: 10px; text-align: center; font-size: 14px; font-weight: 600;">{$emailSafe}</a>
          </td>
          <td width="50%" style="padding-left: 8px;">
            <a href="tel:{$phoneSafe}" style="display: block; padding: 14px 20px; background: #f5f5f5; color: #333; text-decoration: none; border-radius: 10px; text-align: center; font-size: 14px; font-weight: 600;">{$phoneSafe}</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body></html>
HTML;

// ═══════════════════════════════════════════════
// EMAIL 2: Bestätigung an den Kunden
// ═══════════════════════════════════════════════
$confirmSubject = "{$firstName}, starke Entscheidung.";

$confirmBody = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin: 0; padding: 0; background: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background: #f4f4f4; padding: 40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">

  <!-- Header -->
  <tr>
    <td style="background: #000; padding: 32px 40px; text-align: center;">
      <div style="font-size: 20px; font-weight: 700; color: #fff; letter-spacing: 6px;">JALLOUS</div>
      <div style="font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 4px; letter-spacing: 1px;">WEBDESIGN</div>
    </td>
  </tr>

  <!-- Content -->
  <tr>
    <td style="padding: 40px 40px 16px;">
      <div style="font-size: 26px; font-weight: 700; color: #111; line-height: 1.3;">
        {$firstName}, du hast genau das Richtige getan.
      </div>
    </td>
  </tr>

  <tr>
    <td style="padding: 0 40px;">
      <p style="font-size: 16px; color: #444; line-height: 1.7; margin: 16px 0;">
        Die meisten Unternehmer wissen, dass ihre Website mehr bringen sollte. Aber nur wenige handeln. Du gehst jetzt den Schritt, der dein Business nach vorne bringt.
      </p>
      <p style="font-size: 16px; color: #444; line-height: 1.7; margin: 16px 0;">
        Deine Anfrage ist eingegangen und ich schaue sie mir persönlich an. Innerhalb von 24 Stunden melde ich mich bei dir, um einen Termin für dein kostenloses Erstgespräch zu vereinbaren.
      </p>
    </td>
  </tr>

  <!-- What happens next -->
  <tr>
    <td style="padding: 28px 40px 8px;">
      <div style="font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 16px;">So geht es weiter</div>
    </td>
  </tr>

  <tr>
    <td style="padding: 0 40px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="padding: 16px 0; border-bottom: 1px solid #f0f0f0;">
            <table cellpadding="0" cellspacing="0"><tr>
              <td style="width: 40px; vertical-align: top;">
                <div style="width: 28px; height: 28px; background: #000; color: #fff; border-radius: 50%; text-align: center; line-height: 28px; font-size: 13px; font-weight: 700;">1</div>
              </td>
              <td style="vertical-align: top;">
                <div style="font-size: 15px; font-weight: 600; color: #222;">Ich melde mich bei dir</div>
                <div style="font-size: 13px; color: #888; margin-top: 2px;">Innerhalb von 24 Stunden, per Telefon oder E-Mail</div>
              </td>
            </tr></table>
          </td>
        </tr>
        <tr>
          <td style="padding: 16px 0; border-bottom: 1px solid #f0f0f0;">
            <table cellpadding="0" cellspacing="0"><tr>
              <td style="width: 40px; vertical-align: top;">
                <div style="width: 28px; height: 28px; background: #000; color: #fff; border-radius: 50%; text-align: center; line-height: 28px; font-size: 13px; font-weight: 700;">2</div>
              </td>
              <td style="vertical-align: top;">
                <div style="font-size: 15px; font-weight: 600; color: #222;">Kostenloses Erstgespräch</div>
                <div style="font-size: 13px; color: #888; margin-top: 2px;">Wir besprechen deine Ziele und ich zeige dir, was möglich ist</div>
              </td>
            </tr></table>
          </td>
        </tr>
        <tr>
          <td style="padding: 16px 0;">
            <table cellpadding="0" cellspacing="0"><tr>
              <td style="width: 40px; vertical-align: top;">
                <div style="width: 28px; height: 28px; background: #000; color: #fff; border-radius: 50%; text-align: center; line-height: 28px; font-size: 13px; font-weight: 700;">3</div>
              </td>
              <td style="vertical-align: top;">
                <div style="font-size: 15px; font-weight: 600; color: #222;">Du siehst, was möglich ist</div>
                <div style="font-size: 13px; color: #888; margin-top: 2px;">Ich zeige dir, wie Unternehmen wie deins online Kunden gewinnen</div>
              </td>
            </tr></table>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Closing -->
  <tr>
    <td style="padding: 32px 40px 12px;">
      <p style="font-size: 16px; color: #444; line-height: 1.7; margin: 0;">
        Bis bald,
      </p>
      <p style="font-size: 16px; font-weight: 700; color: #111; margin: 4px 0 0;">
        Hassan Jallous
      </p>
      <p style="font-size: 13px; color: #999; margin: 2px 0 0;">
        Gründer, Jallous Webdesign
      </p>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="padding: 24px 40px 32px;">
      <div style="border-top: 1px solid #eee; padding-top: 20px;">
        <table cellpadding="0" cellspacing="0"><tr>
          <td style="padding-right: 16px;">
            <a href="https://jallous-webdesign.de" style="font-size: 13px; color: #999; text-decoration: none;">jallous-webdesign.de</a>
          </td>
          <td style="padding-right: 16px; color: #ddd;">|</td>
          <td>
            <a href="mailto:info@jallous-webdesign.de" style="font-size: 13px; color: #999; text-decoration: none;">info@jallous-webdesign.de</a>
          </td>
        </tr></table>
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body></html>
HTML;

// --- Send both emails via SMTP ---
$sent1 = smtpSend(
    'info@jallous-webdesign.de',
    $notifySubject,
    $notifyBody,
    'Jallous Webdesign',
    'info@jallous-webdesign.de',
    $email
);

$sent2 = smtpSend(
    $email,
    $confirmSubject,
    $confirmBody,
    'Hassan Jallous',
    'info@jallous-webdesign.de',
    'info@jallous-webdesign.de'
);

if ($sent1) {
    $_SESSION['form_submissions'][] = $now;

    // Auto-create lead in CRM
    try {
        require_once __DIR__ . '/analytics-db.php';
        $db = getAnalyticsDB();
        if ($db) {
            $ref = $_SERVER['HTTP_REFERER'] ?? '';
            $source = 'Direkt';
            if (strpos($ref, 'google') !== false) $source = 'Google';
            elseif (strpos($ref, 'facebook') !== false || strpos($ref, 'fb.') !== false) $source = 'Facebook';
            elseif (strpos($ref, 'instagram') !== false) $source = 'Instagram';

            $stmt = $db->prepare('INSERT INTO leads (name, email, phone, branche, has_website, website_url, problem, umsatz, kunden, source) VALUES (:name, :email, :phone, :branche, :hw, :wu, :problem, :umsatz, :kunden, :source)');
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':branche' => $data['branche'] ?? '',
                ':hw' => $data['hasWebsite'] ?? '',
                ':wu' => $data['websiteUrl'] ?? '',
                ':problem' => $data['problem'] ?? '',
                ':umsatz' => $data['umsatz'] ?? '',
                ':kunden' => $data['kunden'] ?? '',
                ':source' => $source,
            ]);
        }
    } catch (Exception $e) {
        // CRM insert must never affect form submission
    }

    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'E-Mail konnte nicht gesendet werden.']);
}
