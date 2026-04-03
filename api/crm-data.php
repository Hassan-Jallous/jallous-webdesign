<?php
/**
 * CRM API — Leads Management (Setter-Closer Pipeline)
 * Handles all CRUD operations for the CRM dashboard.
 */

header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['dashboard_auth']) || !$_SESSION['dashboard_auth']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/analytics-db.php';

$db = getAnalyticsDB();
if (!$db) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {

        // ── LIST ALL LEADS ──────────────────────────────────────────
        case 'list':
            if ($method !== 'GET') { methodNotAllowed(); }

            $stmt = $db->query('
                SELECT l.*,
                       COUNT(lc.id) AS contact_count,
                       MAX(lc.contact_date) AS last_contact
                FROM leads l
                LEFT JOIN lead_contacts lc ON lc.lead_id = l.id
                GROUP BY l.id
                ORDER BY l.created_at DESC
            ');
            $leads = $stmt->fetchAll();

            $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));

            foreach ($leads as &$lead) {
                $ref = $lead['last_contact'] ?? $lead['created_at'];
                if ($ref) {
                    $refDate = new DateTimeImmutable($ref, new DateTimeZone('Europe/Berlin'));
                    $lead['days_since_contact'] = (int) $now->diff($refDate)->days;
                } else {
                    $lead['days_since_contact'] = null;
                }
                $lead['contact_count'] = (int) $lead['contact_count'];
                $lead['engagement_score'] = (int) $lead['engagement_score'];
                $lead['revenue'] = (float) $lead['revenue'];
            }
            unset($lead);

            // Stats with new pipeline stages (only count_in_stats leads)
            $statsStmt = $db->query('SELECT status, COUNT(*) AS cnt FROM leads WHERE count_in_stats = 1 GROUP BY status');
            $statusCounts = [];
            while ($row = $statsStmt->fetch()) {
                $statusCounts[$row['status']] = (int) $row['cnt'];
            }

            $revenueStmt = $db->query('SELECT COALESCE(SUM(revenue), 0) AS total_revenue FROM leads WHERE status != "verloren" AND count_in_stats = 1');
            $totalRevenue = (float) $revenueStmt->fetch()['total_revenue'];

            $total = array_sum($statusCounts);

            $stats = [
                'total'              => $total,
                'neu'                => $statusCounts['neu'] ?? 0,
                'kontaktiert'        => $statusCounts['kontaktiert'] ?? 0,
                'setting_gebucht'    => $statusCounts['setting_gebucht'] ?? 0,
                'qualifiziert'       => $statusCounts['qualifiziert'] ?? 0,
                'nachgespraech'      => $statusCounts['nachgespraech'] ?? 0,
                'gewonnen'           => $statusCounts['gewonnen'] ?? 0,
                'followup'           => $statusCounts['followup'] ?? 0,
                'verloren'           => $statusCounts['verloren'] ?? 0,
                'total_revenue'      => $totalRevenue,
                // Legacy compat
                'termin'             => $statusCounts['termin'] ?? 0,
                'angebot'            => $statusCounts['angebot'] ?? 0,
            ];

            echo json_encode(['leads' => $leads, 'stats' => $stats]);
            break;

        // ── LEAD DETAIL ─────────────────────────────────────────────
        case 'detail':
            if ($method !== 'GET') { methodNotAllowed(); }

            $id = intval($_GET['id'] ?? 0);
            if (!$id) { badRequest('Missing or invalid id'); }

            $stmt = $db->prepare('SELECT * FROM leads WHERE id = ?');
            $stmt->execute([$id]);
            $lead = $stmt->fetch();
            if (!$lead) { notFound('Lead not found'); }

            $lead['engagement_score'] = (int) $lead['engagement_score'];
            $lead['revenue'] = (float) $lead['revenue'];

            $contactsStmt = $db->prepare('SELECT * FROM lead_contacts WHERE lead_id = ? ORDER BY contact_date DESC');
            $contactsStmt->execute([$id]);
            $contacts = $contactsStmt->fetchAll();

            $notesStmt = $db->prepare('SELECT * FROM lead_notes WHERE lead_id = ? ORDER BY created_at DESC');
            $notesStmt->execute([$id]);
            $notes = $notesStmt->fetchAll();

            echo json_encode(['lead' => $lead, 'contacts' => $contacts, 'notes' => $notes]);
            break;

        // ── UPDATE STATUS ───────────────────────────────────────────
        case 'update_status':
            if ($method !== 'POST') { methodNotAllowed(); }

            $data = getJsonBody();
            $id = intval($data['id'] ?? 0);
            $status = $data['status'] ?? '';

            if (!$id) { badRequest('Missing or invalid id'); }

            $validStatuses = ['neu', 'kontaktiert', 'setting_gebucht', 'qualifiziert', 'nachgespraech', 'gewonnen', 'followup', 'verloren'];
            if (!in_array($status, $validStatuses, true)) {
                badRequest('Invalid status. Valid: ' . implode(', ', $validStatuses));
            }

            $stmt = $db->prepare('UPDATE leads SET status = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$status, now(), $id]);

            if ($stmt->rowCount() === 0) { notFound('Lead not found'); }

            echo json_encode(['success' => true]);
            break;

        // ── UPDATE LEAD (generic field update) ──────────────────────
        case 'update_lead':
            if ($method !== 'POST') { methodNotAllowed(); }

            $data = getJsonBody();
            $id = intval($data['id'] ?? 0);
            if (!$id) { badRequest('Missing or invalid id'); }

            $allowedFields = ['name', 'email', 'phone', 'branche', 'website_url', 'source', 'problem', 'ziele', 'investitionspotenzial', 'closing_date', 'followup_date', 'call_type', 'revenue', 'count_in_stats', 'umsatz', 'kunden'];
            $sets = [];
            $params = [];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $sets[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($sets)) { badRequest('No valid fields to update'); }

            $sets[] = "updated_at = ?";
            $params[] = now();
            $params[] = $id;

            $sql = 'UPDATE leads SET ' . implode(', ', $sets) . ' WHERE id = ?';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) { notFound('Lead not found'); }

            echo json_encode(['success' => true]);
            break;

        // ── CREATE LEAD (manual) ────────────────────────────────────
        case 'create_lead':
            if ($method !== 'POST') { methodNotAllowed(); }

            $data = getJsonBody();
            $name = trim($data['name'] ?? '');
            if ($name === '') { badRequest('Name ist erforderlich'); }

            $email = trim($data['email'] ?? '');
            $phone = trim($data['phone'] ?? '');
            $branche = trim($data['branche'] ?? '');
            $source = trim($data['source'] ?? 'Manuell');
            $ziele = trim($data['ziele'] ?? '');
            $problem = trim($data['problem'] ?? '');
            $countInStats = isset($data['count_in_stats']) ? (int) $data['count_in_stats'] : 1;

            $stmt = $db->prepare('INSERT INTO leads (name, email, phone, branche, source, ziele, problem, count_in_stats, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "neu", ?, ?)');
            $nowStr = now();
            $stmt->execute([$name, $email ?: null, $phone ?: null, $branche ?: null, $source, $ziele ?: null, $problem ?: null, $countInStats, $nowStr, $nowStr]);

            $newId = $db->lastInsertId();
            echo json_encode(['success' => true, 'id' => (int) $newId]);
            break;

        // ── UPDATE REVENUE ──────────────────────────────────────────
        case 'update_revenue':
            if ($method !== 'POST') { methodNotAllowed(); }

            $data = getJsonBody();
            $id = intval($data['id'] ?? 0);
            $revenue = floatval($data['revenue'] ?? 0);

            if (!$id) { badRequest('Missing or invalid id'); }

            $stmt = $db->prepare('UPDATE leads SET revenue = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$revenue, now(), $id]);

            if ($stmt->rowCount() === 0) { notFound('Lead not found'); }

            echo json_encode(['success' => true]);
            break;

        // ── ADD CONTACT ─────────────────────────────────────────────
        case 'add_contact':
            if ($method !== 'POST') { methodNotAllowed(); }

            $data = getJsonBody();
            $leadId = intval($data['lead_id'] ?? 0);
            $contactDate = $data['contact_date'] ?? '';
            $type = $data['type'] ?? '';
            $note = trim($data['note'] ?? '');

            if (!$leadId) { badRequest('Missing or invalid lead_id'); }
            if (!$contactDate) { badRequest('Missing contact_date'); }

            $validTypes = ['setting-call', 'closing-call', 'nachgespraech', 'anruf', 'email', 'meeting', 'sonstiges'];
            if (!in_array($type, $validTypes, true)) {
                badRequest('Invalid type. Valid: ' . implode(', ', $validTypes));
            }

            // Validate contact_date is after lead's created_at
            $leadStmt = $db->prepare('SELECT created_at FROM leads WHERE id = ?');
            $leadStmt->execute([$leadId]);
            $lead = $leadStmt->fetch();
            if (!$lead) { notFound('Lead not found'); }

            $leadCreated = new DateTimeImmutable($lead['created_at'], new DateTimeZone('Europe/Berlin'));
            $contactDt = new DateTimeImmutable($contactDate, new DateTimeZone('Europe/Berlin'));

            if ($contactDt <= $leadCreated) {
                badRequest('contact_date must be after the lead was created (' . $lead['created_at'] . ')');
            }

            $formattedDate = $contactDt->format('Y-m-d H:i:s');

            $stmt = $db->prepare('INSERT INTO lead_contacts (lead_id, contact_date, type, note, created_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$leadId, $formattedDate, $type, $note, now()]);

            $newId = $db->lastInsertId();

            $fetchStmt = $db->prepare('SELECT * FROM lead_contacts WHERE id = ?');
            $fetchStmt->execute([$newId]);
            $contact = $fetchStmt->fetch();

            echo json_encode(['success' => true, 'contact' => $contact]);
            break;

        // ── ADD NOTE ────────────────────────────────────────────────
        case 'add_note':
            if ($method !== 'POST') { methodNotAllowed(); }

            $data = getJsonBody();
            $leadId = intval($data['lead_id'] ?? 0);
            $noteText = trim($data['note'] ?? '');

            if (!$leadId) { badRequest('Missing or invalid lead_id'); }
            if ($noteText === '') { badRequest('Missing note'); }

            // Check lead exists
            $leadStmt = $db->prepare('SELECT id FROM leads WHERE id = ?');
            $leadStmt->execute([$leadId]);
            if (!$leadStmt->fetch()) { notFound('Lead not found'); }

            $stmt = $db->prepare('INSERT INTO lead_notes (lead_id, note, created_at) VALUES (?, ?, ?)');
            $stmt->execute([$leadId, $noteText, now()]);

            $newId = $db->lastInsertId();

            $fetchStmt = $db->prepare('SELECT * FROM lead_notes WHERE id = ?');
            $fetchStmt->execute([$newId]);
            $note = $fetchStmt->fetch();

            echo json_encode(['success' => true, 'note' => $note]);
            break;

        // ── DELETE CONTACT ──────────────────────────────────────────
        case 'delete_contact':
            if ($method !== 'POST') { methodNotAllowed(); }

            $data = getJsonBody();
            $id = intval($data['id'] ?? 0);
            if (!$id) { badRequest('Missing or invalid id'); }

            $stmt = $db->prepare('DELETE FROM lead_contacts WHERE id = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) { notFound('Contact not found'); }

            echo json_encode(['success' => true]);
            break;

        // ── DELETE NOTE ─────────────────────────────────────────────
        case 'delete_note':
            if ($method !== 'POST') { methodNotAllowed(); }

            $data = getJsonBody();
            $id = intval($data['id'] ?? 0);
            if (!$id) { badRequest('Missing or invalid id'); }

            $stmt = $db->prepare('DELETE FROM lead_notes WHERE id = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) { notFound('Note not found'); }

            echo json_encode(['success' => true]);
            break;

        // ── CRM KPIs (Schlagzahl) ──────────────────────────────────
        case 'crm_kpis':
            if ($method !== 'GET') { methodNotAllowed(); }

            $tz = new DateTimeZone('Europe/Berlin');
            $now = new DateTimeImmutable('now', $tz);

            // Calls diese Woche (contact types: setting-call, closing-call, nachgespraech, anruf)
            $weekStart = $now->modify('monday this week')->format('Y-m-d 00:00:00');
            $weekEnd = $now->format('Y-m-d 23:59:59');
            $callStmt = $db->prepare("SELECT COUNT(*) FROM lead_contacts WHERE type IN ('setting-call','closing-call','nachgespraech','anruf') AND contact_date BETWEEN ? AND ?");
            $callStmt->execute([$weekStart, $weekEnd]);
            $callsThisWeek = (int) $callStmt->fetchColumn();

            // Termine diese Woche (leads that moved to setting_gebucht or qualifiziert this week)
            $appointStmt = $db->prepare("SELECT COUNT(*) FROM leads WHERE status IN ('setting_gebucht','qualifiziert') AND updated_at BETWEEN ? AND ? AND count_in_stats = 1");
            $appointStmt->execute([$weekStart, $weekEnd]);
            $appointmentsThisWeek = (int) $appointStmt->fetchColumn();

            // Abschlüsse diesen Monat
            $monthStart = $now->modify('first day of this month')->format('Y-m-d 00:00:00');
            $closedStmt = $db->prepare("SELECT COUNT(*) FROM leads WHERE status = 'gewonnen' AND updated_at BETWEEN ? AND ? AND count_in_stats = 1");
            $closedStmt->execute([$monthStart, $weekEnd]);
            $closedThisMonth = (int) $closedStmt->fetchColumn();

            // Conversion Rate: gewonnen / (gewonnen + verloren)
            $wonStmt = $db->query("SELECT COUNT(*) FROM leads WHERE status = 'gewonnen' AND count_in_stats = 1");
            $won = (int) $wonStmt->fetchColumn();
            $lostStmt = $db->query("SELECT COUNT(*) FROM leads WHERE status = 'verloren' AND count_in_stats = 1");
            $lost = (int) $lostStmt->fetchColumn();
            $conversionRate = ($won + $lost) > 0 ? round(($won / ($won + $lost)) * 100, 1) : 0;

            // Pipeline-Wert (revenue aller nicht-verloren)
            $pipeStmt = $db->query("SELECT COALESCE(SUM(revenue), 0) FROM leads WHERE status != 'verloren' AND count_in_stats = 1");
            $pipelineValue = (float) $pipeStmt->fetchColumn();

            // Ø Tage bis Abschluss
            $avgStmt = $db->query("SELECT AVG(julianday(updated_at) - julianday(created_at)) FROM leads WHERE status = 'gewonnen' AND count_in_stats = 1");
            $avgDays = $avgStmt->fetchColumn();
            $avgDaysToClose = $avgDays !== null ? round((float) $avgDays, 1) : 0;

            echo json_encode([
                'calls_this_week'       => $callsThisWeek,
                'appointments_this_week' => $appointmentsThisWeek,
                'closed_this_month'     => $closedThisMonth,
                'conversion_rate'       => $conversionRate,
                'pipeline_value'        => $pipelineValue,
                'avg_days_to_close'     => $avgDaysToClose,
            ]);
            break;

        // ── UNKNOWN ACTION ──────────────────────────────────────────
        default:
            badRequest('Unknown action: ' . $action);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

// ── HELPERS ─────────────────────────────────────────────────────────

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        badRequest('Invalid JSON body');
    }
    return $data;
}

function now(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d H:i:s');
}

function badRequest(string $msg): void {
    http_response_code(400);
    echo json_encode(['error' => $msg]);
    exit;
}

function notFound(string $msg): void {
    http_response_code(404);
    echo json_encode(['error' => $msg]);
    exit;
}

function methodNotAllowed(): void {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
