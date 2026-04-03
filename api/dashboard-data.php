<?php
/**
 * Dashboard Data API
 * Returns analytics data for the dashboard frontend.
 * GET /api/dashboard-data.php?range=7
 */
header('Content-Type: application/json; charset=utf-8');

// Simple auth check — must be called from authenticated dashboard session
session_start();
if (!isset($_SESSION['dashboard_auth']) || !$_SESSION['dashboard_auth']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/analytics-db.php';

$range = (int)($_GET['range'] ?? 7);
if (!in_array($range, [1, 7, 30, 90])) $range = 7;

$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime("-{$range} days"));
$prevStartDate = date('Y-m-d', strtotime("-" . ($range * 2) . " days"));
$prevEndDate = date('Y-m-d', strtotime("-" . ($range + 1) . " days"));

// Aggregate stats for today and recent days that may not be aggregated yet
for ($i = 0; $i <= min($range, 3); $i++) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    aggregateDailyStats($d);
}

$db = getAnalyticsDB();
if (!$db) {
    echo json_encode(['error' => 'Database unavailable']);
    exit;
}

// Helper: sum a metric across date range
function sumMetric(PDO $db, string $metric, string $start, string $end): float {
    $stmt = $db->prepare('SELECT COALESCE(SUM(value), 0) FROM daily_stats WHERE metric = :metric AND date BETWEEN :start AND :end');
    $stmt->execute([':metric' => $metric, ':start' => $start, ':end' => $end]);
    return (float)$stmt->fetchColumn();
}

function avgMetric(PDO $db, string $metric, string $start, string $end): float {
    $stmt = $db->prepare('SELECT COALESCE(AVG(value), 0) FROM daily_stats WHERE metric = :metric AND date BETWEEN :start AND :end AND value > 0');
    $stmt->execute([':metric' => $metric, ':start' => $start, ':end' => $end]);
    return round((float)$stmt->fetchColumn(), 1);
}

function calcChange(float $current, float $previous): float {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

// KPIs
$visitors = sumMetric($db, 'unique_visitors', $startDate, $endDate);
$prevVisitors = sumMetric($db, 'unique_visitors', $prevStartDate, $prevEndDate);
$pageviews = sumMetric($db, 'pageviews', $startDate, $endDate);
$prevPageviews = sumMetric($db, 'pageviews', $prevStartDate, $prevEndDate);
$leads = sumMetric($db, 'leads', $startDate, $endDate);
$prevLeads = sumMetric($db, 'leads', $prevStartDate, $prevEndDate);
$engagement = avgMetric($db, 'avg_engagement_score', $startDate, $endDate);
$prevEngagement = avgMetric($db, 'avg_engagement_score', $prevStartDate, $prevEndDate);

// Chart data (daily visitors + pageviews)
$chartLabels = [];
$chartVisitors = [];
$chartPageviews = [];

for ($i = $range; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('d.m', strtotime($d));

    $stmt = $db->prepare('SELECT metric, value FROM daily_stats WHERE date = :date AND metric IN ("unique_visitors", "pageviews")');
    $stmt->execute([':date' => $d]);
    $dayData = [];
    while ($row = $stmt->fetch()) {
        $dayData[$row['metric']] = (int)$row['value'];
    }
    $chartVisitors[] = $dayData['unique_visitors'] ?? 0;
    $chartPageviews[] = $dayData['pageviews'] ?? 0;
}

// Funnel data (from raw events in date range)
$startTs = strtotime($startDate . ' 00:00:00');
$endTs = strtotime($endDate . ' 23:59:59');

$funnelEvents = ['FormVisible', 'FormStart', 'FormStep', 'Lead'];
$funnel = [];

foreach (['FormVisible', 'FormStart', 'Lead'] as $en) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM events WHERE event_name = :en AND event_time BETWEEN :start AND :end');
    $stmt->execute([':en' => $en, ':start' => $startTs, ':end' => $endTs]);
    $funnel[$en] = (int)$stmt->fetchColumn();
}

// FormSteps broken out by step number
for ($s = 1; $s <= 6; $s++) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM events WHERE event_name = "FormStep" AND CAST(json_extract(custom_data, "$.step_number") AS INTEGER) = :step AND event_time BETWEEN :start AND :end');
    $stmt->execute([':step' => $s, ':start' => $startTs, ':end' => $endTs]);
    $funnel['Step ' . $s] = (int)$stmt->fetchColumn();
}

$funnelOrdered = [
    'Formular sichtbar' => $funnel['FormVisible'] ?? 0,
    'Gestartet' => $funnel['FormStart'] ?? 0,
    'Branche' => $funnel['Step 1'] ?? 0,
    'Website' => $funnel['Step 2'] ?? 0,
    'Herausforderung' => $funnel['Step 3'] ?? 0,
    'Umsatz' => $funnel['Step 4'] ?? 0,
    'Kundengewinnung' => $funnel['Step 5'] ?? 0,
    'Lead' => $funnel['Lead'] ?? 0,
];

// Top pages
$stmt = $db->prepare('
    SELECT page, COUNT(*) as views
    FROM events
    WHERE event_name = "PageView" AND page IS NOT NULL AND event_time BETWEEN :start AND :end
    GROUP BY page ORDER BY views DESC LIMIT 10
');
$stmt->execute([':start' => $startTs, ':end' => $endTs]);
$topPagesRaw = $stmt->fetchAll();

// Get avg time per page
$topPages = [];
foreach ($topPagesRaw as $tp) {
    $stmt2 = $db->prepare('
        SELECT AVG(CAST(json_extract(custom_data, "$.seconds") AS INTEGER))
        FROM events
        WHERE event_name = "TimeOnPage" AND page = :page AND event_time BETWEEN :start AND :end
    ');
    $stmt2->execute([':page' => $tp['page'], ':start' => $startTs, ':end' => $endTs]);
    $avgTime = (int)$stmt2->fetchColumn();
    $topPages[] = ['page' => $tp['page'], 'views' => (int)$tp['views'], 'avg_time' => $avgTime];
}

// Traffic sources (from referrer or UTM)
$stmt = $db->prepare('
    SELECT
        CASE
            WHEN custom_data LIKE "%utm_source%" THEN json_extract(custom_data, "$.utm_source")
            WHEN referrer IS NULL OR referrer = "" THEN "Direkt"
            WHEN referrer LIKE "%google%" THEN "Google"
            WHEN referrer LIKE "%facebook%" OR referrer LIKE "%fb.%" THEN "Facebook"
            WHEN referrer LIKE "%instagram%" THEN "Instagram"
            WHEN referrer LIKE "%jallous-webdesign%" THEN "Intern"
            ELSE referrer
        END as source,
        COUNT(*) as visits
    FROM events
    WHERE event_name = "PageView" AND event_time BETWEEN :start AND :end
    GROUP BY source
    ORDER BY visits DESC
    LIMIT 10
');
$stmt->execute([':start' => $startTs, ':end' => $endTs]);
$sources = $stmt->fetchAll();

// Scroll depth distribution
$scrollDepth = ['25' => 0, '50' => 0, '75' => 0, '90' => 0, '100' => 0];
$totalPageviews = max($pageviews, 1);
$stmt = $db->prepare('SELECT details FROM daily_stats WHERE metric = "scroll_depth_avg" AND date BETWEEN :start AND :end AND details IS NOT NULL');
$stmt->execute([':start' => $startDate, ':end' => $endDate]);
while ($row = $stmt->fetch()) {
    $d = json_decode($row['details'], true);
    if ($d) {
        foreach ($scrollDepth as $k => &$v) {
            $v += ($d[$k] ?? 0);
        }
    }
}
// Convert to percentages
foreach ($scrollDepth as $k => &$v) {
    $v = $totalPageviews > 0 ? round(($v / $totalPageviews) * 100) : 0;
}

// Sections: views + average time
$stmt = $db->prepare('
    SELECT json_extract(custom_data, "$.section_name") as section_name, COUNT(*) as views
    FROM events
    WHERE event_name = "SectionView" AND custom_data IS NOT NULL AND event_time BETWEEN :start AND :end
    GROUP BY section_name
    ORDER BY views DESC
');
$stmt->execute([':start' => $startTs, ':end' => $endTs]);
$sectionViews = $stmt->fetchAll();

$sections = [];
foreach ($sectionViews as $sv) {
    $sName = $sv['section_name'];
    if (!$sName) continue;
    // Get avg time for this section
    $stmt2 = $db->prepare('
        SELECT AVG(CAST(json_extract(custom_data, "$.seconds_visible") AS INTEGER))
        FROM events
        WHERE event_name = "SectionTime" AND json_extract(custom_data, "$.section_name") = :sname AND event_time BETWEEN :start AND :end
    ');
    $stmt2->execute([':sname' => $sName, ':start' => $startTs, ':end' => $endTs]);
    $avgSectionTime = (int)$stmt2->fetchColumn();
    $sections[] = ['section' => $sName, 'views' => (int)$sv['views'], 'avg_time' => $avgSectionTime];
}

// Recent leads per day
$stmt = $db->prepare('
    SELECT date(event_time, "unixepoch") as lead_date, COUNT(*) as cnt
    FROM events
    WHERE event_name = "Lead" AND event_time BETWEEN :start AND :end
    GROUP BY lead_date
    ORDER BY lead_date DESC
    LIMIT 10
');
$stmt->execute([':start' => $startTs, ':end' => $endTs]);
$recentLeads = [];
while ($row = $stmt->fetch()) {
    $recentLeads[] = ['date' => date('d.m.Y', strtotime($row['lead_date'])), 'count' => (int)$row['cnt']];
}

// Build response
echo json_encode([
    'kpis' => [
        'visitors'   => ['current' => (int)$visitors, 'previous' => (int)$prevVisitors, 'change' => calcChange($visitors, $prevVisitors)],
        'pageviews'  => ['current' => (int)$pageviews, 'previous' => (int)$prevPageviews, 'change' => calcChange($pageviews, $prevPageviews)],
        'leads'      => ['current' => (int)$leads, 'previous' => (int)$prevLeads, 'change' => calcChange($leads, $prevLeads)],
        'engagement' => ['current' => $engagement, 'previous' => $prevEngagement, 'change' => calcChange($engagement, $prevEngagement)],
    ],
    'chart' => [
        'labels' => $chartLabels,
        'visitors' => $chartVisitors,
        'pageviews' => $chartPageviews,
    ],
    'funnel' => $funnelOrdered,
    'top_pages' => $topPages,
    'sources' => $sources,
    'scroll_depth' => $scrollDepth,
    'sections' => $sections,
    'recent_leads_per_day' => $recentLeads,
], JSON_UNESCAPED_UNICODE);
