<?php
session_start();
date_default_timezone_set('Europe/Berlin');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/analytics-db.php';

// Handle GET confirm_user (link from email) — must be before JSON headers
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'confirm_user') {
    header('Content-Type: text/html; charset=utf-8');
    $token = $_GET['token'] ?? '';
    if ($token === '') {
        http_response_code(400);
        echo '<h1>Ung&uuml;ltiger Link</h1>';
        exit;
    }

    try {
        $db = getAnalyticsDB();
        $stmt = $db->prepare('SELECT email, password_hash, expires_at FROM pending_users WHERE token = :token');
        $stmt->execute([':token' => $token]);
        $pending = $stmt->fetch();

        if (!$pending) {
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:sans-serif;text-align:center;padding:80px 20px;"><h1>Link ung&uuml;ltig oder bereits verwendet.</h1><p><a href="/dashboard/">Zum Dashboard</a></p></body></html>';
            exit;
        }

        if ($pending['expires_at'] < time()) {
            $db->prepare('DELETE FROM pending_users WHERE token = :token')->execute([':token' => $token]);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:sans-serif;text-align:center;padding:80px 20px;"><h1>Link ist abgelaufen.</h1><p>Bitte fordere einen neuen Zugang an.</p><p><a href="/dashboard/">Zum Dashboard</a></p></body></html>';
            exit;
        }

        // Check not already registered
        $stmt = $db->prepare('SELECT id FROM dashboard_users WHERE email = :email');
        $stmt->execute([':email' => $pending['email']]);
        if (!$stmt->fetch()) {
            $stmt = $db->prepare('INSERT INTO dashboard_users (email, password_hash) VALUES (:email, :hash)');
            $stmt->execute([':email' => $pending['email'], ':hash' => $pending['password_hash']]);
        }

        // Delete pending entry
        $db->prepare('DELETE FROM pending_users WHERE token = :token')->execute([':token' => $token]);

        $safeEmail = htmlspecialchars($pending['email'], ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:-apple-system,sans-serif;background:#0a0a0a;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}.card{background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:48px 40px;text-align:center;max-width:420px}h1{font-size:24px;margin-bottom:12px}p{color:rgba(255,255,255,0.5);line-height:1.6}a{display:inline-block;margin-top:24px;padding:14px 32px;background:#fff;color:#000;text-decoration:none;border-radius:10px;font-weight:600}</style></head><body><div class="card"><div style="font-size:20px;letter-spacing:6px;font-weight:700;margin-bottom:32px;">JALLOUS</div><h1>Zugang best&auml;tigt</h1><p><strong>' . $safeEmail . '</strong> kann sich jetzt im Dashboard einloggen.</p><a href="/dashboard/">Zum Dashboard</a></div></body></html>';
    } catch (Exception $e) {
        error_log('Confirm user failed: ' . $e->getMessage());
        echo '<h1>Fehler bei der Best&auml;tigung.</h1>';
    }
    exit;
}

// JSON API headers for all non-confirm routes
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://jallous-webdesign.de');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/smtp.php';

// --- Helpers ---

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function requireAuth(): void {
    if (empty($_SESSION['dashboard_auth'])) {
        jsonResponse(['error' => 'Nicht authentifiziert'], 401);
    }
}

// --- Parse input ---
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input || empty($input['action'])) {
    jsonResponse(['error' => 'Ungültige Anfrage'], 400);
}

$action = $input['action'];

try {
    $db = getAnalyticsDB();
    if (!$db) {
        jsonResponse(['error' => 'Datenbankfehler'], 500);
    }

    // ═══════════════════════════════════════════════
    // LOGIN (email + password)
    // ═══════════════════════════════════════════════
    if ($action === 'login') {
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            jsonResponse(['error' => 'E-Mail und Passwort erforderlich'], 400);
        }

        $stmt = $db->prepare('SELECT id, email, password_hash FROM dashboard_users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonResponse(['error' => 'Ungültige Anmeldedaten'], 401);
        }

        $_SESSION['dashboard_auth'] = true;
        $_SESSION['dashboard_email'] = $user['email'];

        jsonResponse(['success' => true, 'email' => $user['email']]);
    }

    // ═══════════════════════════════════════════════
    // REQUEST OTP
    // ═══════════════════════════════════════════════
    if ($action === 'request_otp') {
        $email = trim($input['email'] ?? '');

        if ($email === '') {
            jsonResponse(['error' => 'E-Mail erforderlich'], 400);
        }

        // Always return success (don't leak whether email exists)
        $stmt = $db->prepare('SELECT id FROM dashboard_users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Invalidate previous unused OTPs
            $stmt = $db->prepare('UPDATE dashboard_otps SET used = 1 WHERE email = :email AND used = 0');
            $stmt->execute([':email' => $email]);

            // Generate 6-digit code
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = time() + 600;

            $stmt = $db->prepare('INSERT INTO dashboard_otps (email, otp_code, expires_at) VALUES (:email, :otp, :expires)');
            $stmt->execute([':email' => $email, ':otp' => $otp, ':expires' => $expiresAt]);

            // Send OTP email
            $otpHtml = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin: 0; padding: 0; background: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background: #f4f4f4; padding: 40px 20px;">
<tr><td align="center">
<table width="480" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">

  <!-- Header -->
  <tr>
    <td style="background: #000; padding: 28px 40px; text-align: center;">
      <div style="font-size: 18px; font-weight: 700; color: #fff; letter-spacing: 6px;">JALLOUS</div>
      <div style="font-size: 10px; color: rgba(255,255,255,0.4); margin-top: 3px; letter-spacing: 1px;">WEBDESIGN</div>
    </td>
  </tr>

  <!-- Content -->
  <tr>
    <td style="padding: 36px 40px 16px; text-align: center;">
      <div style="font-size: 14px; color: #888; margin-bottom: 8px;">Dein Login-Code</div>
      <div style="font-size: 42px; font-weight: 800; color: #111; letter-spacing: 10px; font-family: 'SF Mono', 'Fira Code', monospace;">{$otp}</div>
    </td>
  </tr>

  <tr>
    <td style="padding: 16px 40px 32px; text-align: center;">
      <div style="font-size: 14px; color: #999; line-height: 1.6;">
        Gib diesen Code im Dashboard ein, um dich anzumelden.<br>
        Dieser Code ist <strong style="color: #555;">10 Minuten</strong> gültig.
      </div>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="padding: 20px 40px 28px; text-align: center; border-top: 1px solid #f0f0f0;">
      <div style="font-size: 12px; color: #bbb;">
        Wenn du diesen Code nicht angefordert hast, kannst du diese E-Mail ignorieren.
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body></html>
HTML;

            smtpSend(
                $email,
                'Dein Login-Code',
                $otpHtml,
                'Jallous Webdesign',
                'info@jallous-webdesign.de',
                'info@jallous-webdesign.de'
            );
        }

        jsonResponse(['success' => true]);
    }

    // ═══════════════════════════════════════════════
    // VERIFY OTP
    // ═══════════════════════════════════════════════
    if ($action === 'verify_otp') {
        $email = trim($input['email'] ?? '');
        $otp = trim($input['otp'] ?? '');

        if ($email === '' || $otp === '') {
            jsonResponse(['error' => 'E-Mail und Code erforderlich'], 400);
        }

        // Rate limit: max 5 attempts per email per hour
        $oneHourAgo = time() - 3600;
        $stmt = $db->prepare('SELECT COUNT(*) FROM dashboard_otps WHERE email = :email AND created_at > datetime(:ts, \'unixepoch\')');
        $stmt->execute([':email' => $email, ':ts' => $oneHourAgo]);
        $attempts = (int)$stmt->fetchColumn();

        if ($attempts > 5) {
            jsonResponse(['error' => 'Zu viele Versuche. Bitte warte eine Stunde.'], 429);
        }

        // Find valid OTP
        $now = time();
        $stmt = $db->prepare('SELECT id FROM dashboard_otps WHERE email = :email AND otp_code = :otp AND used = 0 AND expires_at > :now ORDER BY id DESC LIMIT 1');
        $stmt->execute([':email' => $email, ':otp' => $otp, ':now' => $now]);
        $otpRow = $stmt->fetch();

        if (!$otpRow) {
            jsonResponse(['error' => 'Ungültiger oder abgelaufener Code'], 401);
        }

        // Mark as used
        $stmt = $db->prepare('UPDATE dashboard_otps SET used = 1 WHERE id = :id');
        $stmt->execute([':id' => $otpRow['id']]);

        // Set session
        $_SESSION['dashboard_auth'] = true;
        $_SESSION['dashboard_email'] = $email;

        jsonResponse(['success' => true, 'email' => $email]);
    }

    // ═══════════════════════════════════════════════
    // CHANGE PASSWORD (requires auth)
    // ═══════════════════════════════════════════════
    if ($action === 'change_password') {
        requireAuth();

        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '') {
            jsonResponse(['error' => 'Aktuelles und neues Passwort erforderlich'], 400);
        }

        if (strlen($newPassword) < 8) {
            jsonResponse(['error' => 'Neues Passwort muss mindestens 8 Zeichen lang sein'], 400);
        }

        $email = $_SESSION['dashboard_email'];
        $stmt = $db->prepare('SELECT password_hash FROM dashboard_users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            jsonResponse(['error' => 'Aktuelles Passwort ist falsch'], 401);
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE dashboard_users SET password_hash = :hash WHERE email = :email');
        $stmt->execute([':hash' => $newHash, ':email' => $email]);

        jsonResponse(['success' => true]);
    }

    // ═══════════════════════════════════════════════
    // ADD USER (requires auth, sends confirmation to admin)
    // ═══════════════════════════════════════════════
    if ($action === 'add_user') {
        requireAuth();

        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            jsonResponse(['error' => 'E-Mail und Passwort erforderlich'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Ungültige E-Mail-Adresse'], 400);
        }

        if (strlen($password) < 8) {
            jsonResponse(['error' => 'Passwort muss mindestens 8 Zeichen lang sein'], 400);
        }

        // Check if email already taken
        $stmt = $db->prepare('SELECT id FROM dashboard_users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'E-Mail ist bereits registriert'], 409);
        }

        // Check if already pending
        $stmt = $db->prepare('SELECT id FROM pending_users WHERE email = :email AND expires_at > :now');
        $stmt->execute([':email' => $email, ':now' => time()]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Bestätigungs-E-Mail wurde bereits gesendet. Bitte prüfe dein Postfach.'], 409);
        }

        // Create pending user with confirmation token
        $token = bin2hex(random_bytes(32));
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $expiresAt = time() + 86400; // 24 hours

        // Clean up old expired pending users
        $db->prepare('DELETE FROM pending_users WHERE expires_at < :now')->execute([':now' => time()]);

        $stmt = $db->prepare('INSERT INTO pending_users (email, password_hash, token, expires_at) VALUES (:email, :hash, :token, :expires)');
        $stmt->execute([':email' => $email, ':hash' => $hash, ':token' => $token, ':expires' => $expiresAt]);

        // Send confirmation email to admin (info@jallous-webdesign.de)
        $confirmUrl = 'https://jallous-webdesign.de/api/auth.php?action=confirm_user&token=' . $token;
        $adminEmail = 'info@jallous-webdesign.de';
        $subject = 'Neuen Dashboard-Zugang bestätigen';
        $body = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
<tr><td style="background:#000;padding:32px 40px;text-align:center;">
<div style="font-size:20px;font-weight:700;color:#fff;letter-spacing:6px;">JALLOUS</div>
<div style="font-size:11px;color:rgba(255,255,255,0.5);margin-top:4px;letter-spacing:1px;">DASHBOARD</div>
</td></tr>
<tr><td style="padding:36px 40px 20px;">
<div style="font-size:22px;font-weight:700;color:#111;margin-bottom:16px;">Neuer Zugang angefragt</div>
<p style="font-size:16px;color:#444;line-height:1.7;">Folgender Account möchte Zugang zum Dashboard:</p>
<div style="background:#f8f8f8;border-radius:10px;padding:16px 20px;margin:16px 0;">
<div style="font-size:15px;font-weight:600;color:#222;">{$email}</div>
</div>
<p style="font-size:14px;color:#888;line-height:1.6;">Klicke auf den Button um den Zugang zu bestätigen. Der Link ist 24 Stunden gültig.</p>
<a href="{$confirmUrl}" style="display:inline-block;margin-top:16px;padding:14px 32px;background:#000;color:#fff;text-decoration:none;border-radius:10px;font-size:15px;font-weight:600;">Zugang bestätigen</a>
<p style="font-size:12px;color:#bbb;margin-top:24px;">Wenn du diesen Zugang nicht angelegt hast, ignoriere diese E-Mail.</p>
</td></tr>
</table>
</td></tr></table>
</body></html>
HTML;

        require_once __DIR__ . '/smtp.php';
        smtpSend($adminEmail, $subject, $body, 'Jallous Dashboard', 'info@jallous-webdesign.de', 'info@jallous-webdesign.de');

        jsonResponse(['success' => true, 'message' => 'Bestätigungs-E-Mail wurde an info@jallous-webdesign.de gesendet.']);
    }

    // ═══════════════════════════════════════════════
    // LIST USERS (requires auth)
    // ═══════════════════════════════════════════════
    if ($action === 'list_users') {
        requireAuth();

        $stmt = $db->query('SELECT id, email, created_at FROM dashboard_users ORDER BY created_at ASC');
        $users = $stmt->fetchAll();

        jsonResponse(['users' => $users]);
    }

    // ═══════════════════════════════════════════════
    // DELETE USER (requires auth)
    // ═══════════════════════════════════════════════
    if ($action === 'delete_user') {
        requireAuth();

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['error' => 'Ungültige User-ID'], 400);
        }

        // Check user exists and get email
        $stmt = $db->prepare('SELECT email FROM dashboard_users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $target = $stmt->fetch();

        if (!$target) {
            jsonResponse(['error' => 'User nicht gefunden'], 404);
        }

        // Cannot delete yourself
        if ($target['email'] === $_SESSION['dashboard_email']) {
            jsonResponse(['error' => 'Du kannst dich nicht selbst löschen'], 400);
        }

        // Must keep at least 1 user
        $count = (int)$db->query('SELECT COUNT(*) FROM dashboard_users')->fetchColumn();
        if ($count <= 1) {
            jsonResponse(['error' => 'Es muss mindestens ein User existieren'], 400);
        }

        $stmt = $db->prepare('DELETE FROM dashboard_users WHERE id = :id');
        $stmt->execute([':id' => $id]);

        jsonResponse(['success' => true]);
    }

    // ═══════════════════════════════════════════════
    // RESET USER PASSWORD (requires auth, for any user)
    // ═══════════════════════════════════════════════
    if ($action === 'reset_user_password') {
        requireAuth();

        $id = (int)($input['id'] ?? 0);
        $newPassword = $input['new_password'] ?? '';

        if ($id <= 0) {
            jsonResponse(['error' => 'Ungültige User-ID'], 400);
        }

        if (strlen($newPassword) < 8) {
            jsonResponse(['error' => 'Passwort muss mindestens 8 Zeichen lang sein'], 400);
        }

        $stmt = $db->prepare('SELECT id FROM dashboard_users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'User nicht gefunden'], 404);
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE dashboard_users SET password_hash = :hash WHERE id = :id');
        $stmt->execute([':hash' => $newHash, ':id' => $id]);

        jsonResponse(['success' => true]);
    }

    // ═══════════════════════════════════════════════
    // LOGOUT
    // ═══════════════════════════════════════════════
    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();

        jsonResponse(['success' => true]);
    }

    // Unknown action
    jsonResponse(['error' => 'Unbekannte Aktion'], 400);

} catch (Exception $e) {
    error_log('Auth API error: ' . $e->getMessage());
    jsonResponse(['error' => 'Interner Serverfehler'], 500);
}
