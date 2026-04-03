<?php
/**
 * Ad Performance API
 * Fetches Meta Ads insights via Graph API, caches in SQLite, merges with CRM data.
 * GET /api/ad-performance.php?month=2026-04&refresh=1
 */
header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['dashboard_auth']) || !$_SESSION['dashboard_auth']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/analytics-db.php';

$db = getAnalyticsDB();
if (!$db) {
    http_response_code(500);
    echo json_encode(['error' => 'Database unavailable']);
    exit;
}

// --- Parameters ---
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$refresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';

// Date range for the month
$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));
$today = date('Y-m-d');
if ($endDate > $today) {
    $endDate = $today;
}

// --- Determine which dates need fetching ---
$datesToFetch = [];
$cachedData = [];

$stmt = $db->prepare('SELECT * FROM ad_daily_cache WHERE date BETWEEN ? AND ?');
$stmt->execute([$startDate, $endDate]);
$rows = $stmt->fetchAll();
foreach ($rows as $row) {
    $cachedData[$row['date']] = $row;
}

$current = $startDate;
while ($current <= $endDate) {
    if ($refresh) {
        $datesToFetch[] = $current;
    } elseif (!isset($cachedData[$current])) {
        $datesToFetch[] = $current;
    } elseif ($current === $today) {
        // Today's data: only use cache if less than 4 hours old
        $fetchedAt = strtotime($cachedData[$current]['fetched_at']);
        if (time() - $fetchedAt > 14400) {
            $datesToFetch[] = $current;
        }
    }
    $current = date('Y-m-d', strtotime($current . ' +1 day'));
}

// --- Fetch from Meta Graph API if needed ---
$apiError = null;
if (!empty($datesToFetch)) {
    $fetchStart = min($datesToFetch);
    $fetchEnd = max($datesToFetch);

    $fields = 'spend,impressions,ctr,clicks,inline_link_clicks,inline_link_click_ctr,cost_per_inline_link_click,actions,cost_per_action_type';
    $timeRange = json_encode(['since' => $fetchStart, 'until' => $fetchEnd]);

    $url = 'https://graph.facebook.com/v21.0/' . META_AD_ACCOUNT_ID . '/insights'
        . '?fields=' . urlencode($fields)
        . '&time_range=' . urlencode($timeRange)
        . '&time_increment=1'
        . '&level=account'
        . '&limit=100'
        . '&access_token=' . META_ACCESS_TOKEN;

    $allApiRows = [];
    $fetchUrl = $url;

    // Paginate through all results
    while ($fetchUrl) {
        $ch = curl_init($fetchUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            $decoded = json_decode($response, true);
            $errorMsg = $decoded['error']['message'] ?? 'Meta API request failed';
            $errorCode = $decoded['error']['code'] ?? 0;

            if ($errorCode == 190) {
                $apiError = 'Access Token abgelaufen. Bitte in config.php erneuern.';
            } elseif ($errorCode == 17 || $errorCode == 32) {
                $apiError = 'API Rate Limit erreicht. Cache-Daten werden angezeigt.';
            } else {
                $apiError = 'Meta API Fehler: ' . $errorMsg;
            }
            break;
        }

        $decoded = json_decode($response, true);
        if (isset($decoded['data'])) {
            $allApiRows = array_merge($allApiRows, $decoded['data']);
        }

        // Follow pagination
        $fetchUrl = $decoded['paging']['next'] ?? null;
    }

    // Parse and cache API data
    $insertStmt = $db->prepare('
        INSERT OR REPLACE INTO ad_daily_cache
        (date, spend, impressions, ctr, clicks, link_clicks, link_ctr, form_views, leads, cost_per_link_click, cost_per_lead, raw_json, fetched_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime("now"))
    ');

    foreach ($allApiRows as $row) {
        $date = $row['date_start'] ?? null;
        if (!$date) continue;

        $spend = (float)($row['spend'] ?? 0);
        $impressions = (int)($row['impressions'] ?? 0);
        $ctr = (float)($row['ctr'] ?? 0);
        $clicks = (int)($row['clicks'] ?? 0);
        $linkClicks = (int)($row['inline_link_clicks'] ?? 0);
        $linkCtr = (float)($row['inline_link_click_ctr'] ?? 0);
        $costPerLinkClick = (float)($row['cost_per_inline_link_click'] ?? 0);

        // Parse actions array for form_views and leads
        $formViews = 0;
        $apiLeads = 0;
        if (isset($row['actions']) && is_array($row['actions'])) {
            foreach ($row['actions'] as $action) {
                $type = $action['action_type'] ?? '';
                $val = (int)($action['value'] ?? 0);
                if ($type === 'landing_page_view') {
                    $formViews = $val;
                }
                if ($type === 'lead' || $type === 'offsite_conversion.fb_pixel_lead') {
                    $apiLeads += $val;
                }
            }
        }

        // Parse cost_per_action_type for cost_per_lead
        $costPerLead = 0;
        if (isset($row['cost_per_action_type']) && is_array($row['cost_per_action_type'])) {
            foreach ($row['cost_per_action_type'] as $cpa) {
                $type = $cpa['action_type'] ?? '';
                if ($type === 'lead' || $type === 'offsite_conversion.fb_pixel_lead') {
                    $costPerLead = (float)($cpa['value'] ?? 0);
                    break;
                }
            }
        }

        $insertStmt->execute([
            $date, $spend, $impressions, $ctr, $clicks, $linkClicks, $linkCtr,
            $formViews, $apiLeads, $costPerLinkClick, $costPerLead,
            json_encode($row)
        ]);

        // Update local cache
        $cachedData[$date] = [
            'date' => $date,
            'spend' => $spend,
            'impressions' => $impressions,
            'ctr' => $ctr,
            'clicks' => $clicks,
            'link_clicks' => $linkClicks,
            'link_ctr' => $linkCtr,
            'form_views' => $formViews,
            'leads' => $apiLeads,
            'cost_per_link_click' => $costPerLinkClick,
            'cost_per_lead' => $costPerLead,
        ];
    }
}

// --- Get CRM data (Gespräche + Abschlüsse per day) ---
$gespraecheByDay = [];
$stmt = $db->prepare("
    SELECT DATE(contact_date) as d, COUNT(*) as cnt
    FROM lead_contacts
    WHERE type IN ('setting-call', 'closing-call')
    AND DATE(contact_date) BETWEEN ? AND ?
    GROUP BY DATE(contact_date)
");
$stmt->execute([$startDate, $endDate]);
foreach ($stmt->fetchAll() as $row) {
    $gespraecheByDay[$row['d']] = (int)$row['cnt'];
}

$abschluesseByDay = [];
$stmt = $db->prepare("
    SELECT DATE(updated_at) as d, COUNT(*) as cnt
    FROM leads
    WHERE status = 'gewonnen' AND count_in_stats = 1
    AND DATE(updated_at) BETWEEN ? AND ?
    GROUP BY DATE(updated_at)
");
$stmt->execute([$startDate, $endDate]);
foreach ($stmt->fetchAll() as $row) {
    $abschluesseByDay[$row['d']] = (int)$row['cnt'];
}

// --- Build response ---
$germanDays = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
$days = [];
$weeklyBuckets = [];

$current = $startDate;
$lastDayOfMonth = date('Y-m-t', strtotime($startDate));
$loopEnd = min($lastDayOfMonth, $today);

while ($current <= $lastDayOfMonth) {
    $ts = strtotime($current);
    $kw = (int)date('W', $ts);
    $dayOfWeek = (int)date('N', $ts) - 1; // 0=Mo, 6=So
    $weekday = $germanDays[$dayOfWeek];
    $isFuture = $current > $today;

    $ad = $cachedData[$current] ?? null;
    $gespraeche = $gespraecheByDay[$current] ?? 0;
    $abschluesse = $abschluesseByDay[$current] ?? 0;

    $spend = $ad ? (float)$ad['spend'] : 0;
    $impressions = $ad ? (int)$ad['impressions'] : 0;
    $ctrVal = $ad ? (float)$ad['ctr'] : 0;
    $linkClicks = $ad ? (int)$ad['link_clicks'] : 0;
    $costPerLinkClick = $ad ? (float)$ad['cost_per_link_click'] : 0;
    $linkCtr = $ad ? (float)$ad['link_ctr'] : 0;
    $formViews = $ad ? (int)$ad['form_views'] : 0;
    $leads = $ad ? (int)$ad['leads'] : 0;
    $costPerLead = $ad ? (float)$ad['cost_per_lead'] : 0;

    // Calculated fields
    $costPerFormView = ($formViews > 0) ? $spend / $formViews : null;
    $formViewRate = ($linkClicks > 0) ? ($formViews / $linkClicks) * 100 : null;
    $leadRate = ($formViews > 0) ? ($leads / $formViews) * 100 : null;
    $costPerGespraech = ($gespraeche > 0) ? $spend / $gespraeche : null;
    $costPerAbschluss = ($abschluesse > 0) ? $spend / $abschluesse : null;
    $abschlussRate = ($gespraeche > 0) ? ($abschluesse / $gespraeche) * 100 : null;

    $dayData = [
        'date' => $current,
        'weekday' => $weekday,
        'kw' => $kw,
        'is_future' => $isFuture,
        'has_data' => $ad !== null && $spend > 0,
        'spend' => round($spend, 2),
        'impressions' => $impressions,
        'ctr' => round($ctrVal, 2),
        'link_clicks' => $linkClicks,
        'cost_per_link_click' => round($costPerLinkClick, 2),
        'link_ctr' => round($linkCtr, 2),
        'form_views' => $formViews,
        'cost_per_form_view' => $costPerFormView !== null ? round($costPerFormView, 2) : null,
        'form_view_rate' => $formViewRate !== null ? round($formViewRate, 2) : null,
        'leads' => $leads,
        'cost_per_lead' => round($costPerLead, 2),
        'lead_rate' => $leadRate !== null ? round($leadRate, 2) : null,
        'gespraeche' => $gespraeche,
        'cost_per_gespraech' => $costPerGespraech !== null ? round($costPerGespraech, 2) : null,
        'abschluesse' => $abschluesse,
        'cost_per_abschluss' => $costPerAbschluss !== null ? round($costPerAbschluss, 2) : null,
        'abschluss_rate' => $abschlussRate !== null ? round($abschlussRate, 2) : null,
    ];

    $days[] = $dayData;

    // Bucket for weekly totals
    if (!isset($weeklyBuckets[$kw])) {
        $weeklyBuckets[$kw] = ['days' => [], 'kw' => $kw];
    }
    $weeklyBuckets[$kw]['days'][] = $dayData;

    $current = date('Y-m-d', strtotime($current . ' +1 day'));
}

// --- Compute weekly totals ---
$weeklyTotals = [];
foreach ($weeklyBuckets as $kw => $bucket) {
    $weeklyTotals[$kw] = computeAggregate($bucket['days'], $kw);
}

// --- Compute monthly totals ---
$monthlyTotal = computeAggregate($days, null);
$daysWithData = array_filter($days, function($d) { return $d['has_data']; });
$daysCount = count($daysWithData);
$monthlyAvg = [
    'spend' => $daysCount > 0 ? round($monthlyTotal['spend'] / $daysCount, 2) : 0,
    'impressions' => $daysCount > 0 ? round($monthlyTotal['impressions'] / $daysCount) : 0,
    'ctr' => $daysCount > 0 ? round($monthlyTotal['ctr_sum'] / $daysCount, 2) : 0,
    'link_clicks' => $daysCount > 0 ? round($monthlyTotal['link_clicks'] / $daysCount) : 0,
    'form_views' => $daysCount > 0 ? round($monthlyTotal['form_views'] / $daysCount) : 0,
    'leads' => $daysCount > 0 ? round($monthlyTotal['leads'] / $daysCount, 1) : 0,
    'gespraeche' => $daysCount > 0 ? round($monthlyTotal['gespraeche'] / $daysCount, 1) : 0,
    'abschluesse' => $daysCount > 0 ? round($monthlyTotal['abschluesse'] / $daysCount, 1) : 0,
    'cost_per_link_click' => $monthlyTotal['cost_per_link_click'],
    'cost_per_form_view' => $monthlyTotal['cost_per_form_view'],
    'cost_per_lead' => $monthlyTotal['cost_per_lead'],
    'form_view_rate' => $monthlyTotal['form_view_rate'],
    'lead_rate' => $monthlyTotal['lead_rate'],
    'cost_per_gespraech' => $monthlyTotal['cost_per_gespraech'],
    'cost_per_abschluss' => $monthlyTotal['cost_per_abschluss'],
    'abschluss_rate' => $monthlyTotal['abschluss_rate'],
];

$result = [
    'month' => $month,
    'days' => $days,
    'weekly_totals' => $weeklyTotals,
    'monthly_total' => $monthlyTotal,
    'monthly_avg' => $monthlyAvg,
    'last_fetched' => date('Y-m-d H:i:s'),
];

if ($apiError) {
    $result['warning'] = $apiError;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);

// --- Helper: compute aggregate from array of day data ---
function computeAggregate(array $days, ?int $kw): array {
    $spend = 0; $impressions = 0; $clicks = 0; $linkClicks = 0;
    $formViews = 0; $leads = 0; $gespraeche = 0; $abschluesse = 0;
    $ctrSum = 0; $dataCount = 0;

    foreach ($days as $d) {
        if (!$d['has_data'] && !$d['gespraeche'] && !$d['abschluesse']) continue;
        $spend += $d['spend'];
        $impressions += $d['impressions'];
        $linkClicks += $d['link_clicks'];
        $formViews += $d['form_views'];
        $leads += $d['leads'];
        $gespraeche += $d['gespraeche'];
        $abschluesse += $d['abschluesse'];
        if ($d['has_data']) {
            $ctrSum += $d['ctr'];
            $dataCount++;
        }
    }

    return [
        'kw' => $kw,
        'days_count' => count($days),
        'data_days' => $dataCount,
        'spend' => round($spend, 2),
        'impressions' => $impressions,
        'ctr_sum' => $ctrSum,
        'avg_ctr' => $dataCount > 0 ? round($ctrSum / $dataCount, 2) : 0,
        'link_clicks' => $linkClicks,
        'cost_per_link_click' => $linkClicks > 0 ? round($spend / $linkClicks, 2) : null,
        'link_ctr' => $impressions > 0 ? round(($linkClicks / $impressions) * 100, 2) : null,
        'form_views' => $formViews,
        'cost_per_form_view' => $formViews > 0 ? round($spend / $formViews, 2) : null,
        'form_view_rate' => $linkClicks > 0 ? round(($formViews / $linkClicks) * 100, 2) : null,
        'leads' => $leads,
        'cost_per_lead' => $leads > 0 ? round($spend / $leads, 2) : null,
        'lead_rate' => $formViews > 0 ? round(($leads / $formViews) * 100, 2) : null,
        'gespraeche' => $gespraeche,
        'cost_per_gespraech' => $gespraeche > 0 ? round($spend / $gespraeche, 2) : null,
        'abschluesse' => $abschluesse,
        'cost_per_abschluss' => $abschluesse > 0 ? round($spend / $abschluesse, 2) : null,
        'abschluss_rate' => $gespraeche > 0 ? round(($abschluesse / $gespraeche) * 100, 2) : null,
    ];
}
