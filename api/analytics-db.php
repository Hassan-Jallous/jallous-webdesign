<?php
/**
 * Analytics Database Library
 * Local SQLite event logging for the analytics dashboard.
 *
 * Usage: require_once __DIR__ . '/analytics-db.php';
 */

/**
 * Returns a singleton PDO instance connected to the analytics SQLite database.
 * Creates the database, directory, and tables on first use.
 */
function getAnalyticsDB(): ?PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $dbPath = $dataDir . '/analytics.db';
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // WAL mode for better concurrent write performance
        $pdo->exec('PRAGMA journal_mode=WAL');
        // Wait up to 5 seconds if the database is locked
        $pdo->exec('PRAGMA busy_timeout=5000');

        // Create tables
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_name TEXT NOT NULL,
                event_time INTEGER NOT NULL,
                page TEXT,
                page_type TEXT,
                custom_data TEXT,
                ip_hash TEXT,
                user_agent TEXT,
                referrer TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS daily_stats (
                date TEXT NOT NULL,
                metric TEXT NOT NULL,
                value REAL NOT NULL,
                details TEXT,
                PRIMARY KEY (date, metric)
            )
        ');

        // CRM tables
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS leads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT,
                phone TEXT,
                branche TEXT,
                has_website TEXT,
                website_url TEXT,
                problem TEXT,
                umsatz TEXT,
                kunden TEXT,
                status TEXT DEFAULT "neu",
                engagement_score INTEGER DEFAULT 0,
                source TEXT,
                revenue REAL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS lead_contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL,
                contact_date DATETIME NOT NULL,
                type TEXT,
                note TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (lead_id) REFERENCES leads(id)
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS lead_notes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL,
                note TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (lead_id) REFERENCES leads(id)
            )
        ');

        // Auth tables
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS dashboard_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS dashboard_otps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                otp_code TEXT NOT NULL,
                expires_at INTEGER NOT NULL,
                used INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS pending_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                token TEXT NOT NULL UNIQUE,
                expires_at INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Ad performance cache
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS ad_daily_cache (
                date TEXT PRIMARY KEY,
                spend REAL DEFAULT 0,
                impressions INTEGER DEFAULT 0,
                ctr REAL DEFAULT 0,
                clicks INTEGER DEFAULT 0,
                link_clicks INTEGER DEFAULT 0,
                link_ctr REAL DEFAULT 0,
                form_views INTEGER DEFAULT 0,
                leads INTEGER DEFAULT 0,
                cost_per_link_click REAL DEFAULT 0,
                cost_per_lead REAL DEFAULT 0,
                raw_json TEXT,
                fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Seed default admin if no users exist
        $count = $pdo->query('SELECT COUNT(*) FROM dashboard_users')->fetchColumn();
        if ((int)$count === 0) {
            $hash = password_hash('jallous2026!', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO dashboard_users (email, password_hash) VALUES (?, ?)')->execute(['info@jallous-webdesign.de', $hash]);
        }

        // Migrate: add new CRM columns if missing
        $cols = $pdo->query("PRAGMA table_info(leads)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('ziele', $cols)) {
            $pdo->exec('ALTER TABLE leads ADD COLUMN ziele TEXT');
        }
        if (!in_array('investitionspotenzial', $cols)) {
            $pdo->exec('ALTER TABLE leads ADD COLUMN investitionspotenzial TEXT');
        }
        if (!in_array('closing_date', $cols)) {
            $pdo->exec('ALTER TABLE leads ADD COLUMN closing_date TEXT');
        }
        if (!in_array('followup_date', $cols)) {
            $pdo->exec('ALTER TABLE leads ADD COLUMN followup_date TEXT');
        }
        if (!in_array('call_type', $cols)) {
            $pdo->exec('ALTER TABLE leads ADD COLUMN call_type TEXT');
        }
        if (!in_array('count_in_stats', $cols)) {
            $pdo->exec('ALTER TABLE leads ADD COLUMN count_in_stats INTEGER DEFAULT 1');
        }

        // Indexes
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_events_event_time ON events (event_time)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_events_event_name ON events (event_name)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_events_page_type ON events (page_type)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_daily_stats_date ON daily_stats (date)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_leads_status ON leads (status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_leads_created ON leads (created_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_lead_contacts_lead ON lead_contacts (lead_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_dashboard_users_email ON dashboard_users (email)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_dashboard_otps_email ON dashboard_otps (email)');

        return $pdo;
    } catch (Exception $e) {
        $pdo = null;
        error_log('Analytics DB init failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Log a single event.
 */
function logEvent(
    string $eventName,
    ?string $page = null,
    ?string $pageType = null,
    ?string $customData = null,
    ?string $ipHash = null,
    ?string $userAgent = null,
    ?string $referrer = null
): bool {
    try {
        $db = getAnalyticsDB();
        if (!$db) return false;

        $stmt = $db->prepare('
            INSERT INTO events (event_name, event_time, page, page_type, custom_data, ip_hash, user_agent, referrer)
            VALUES (:event_name, :event_time, :page, :page_type, :custom_data, :ip_hash, :user_agent, :referrer)
        ');

        return $stmt->execute([
            ':event_name'  => $eventName,
            ':event_time'  => time(),
            ':page'        => $page,
            ':page_type'   => $pageType,
            ':custom_data' => $customData,
            ':ip_hash'     => $ipHash,
            ':user_agent'  => $userAgent,
            ':referrer'    => $referrer,
        ]);
    } catch (Exception $e) {
        error_log('Analytics logEvent failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Batch insert multiple events in a single transaction.
 * Each element in $eventsArray should be an associative array with keys:
 * event_name, page, page_type, custom_data, ip_hash, user_agent, referrer
 */
function logEvents(array $eventsArray): bool {
    try {
        $db = getAnalyticsDB();
        if (!$db) return false;

        $stmt = $db->prepare('
            INSERT INTO events (event_name, event_time, page, page_type, custom_data, ip_hash, user_agent, referrer)
            VALUES (:event_name, :event_time, :page, :page_type, :custom_data, :ip_hash, :user_agent, :referrer)
        ');

        $now = time();
        $db->beginTransaction();

        foreach ($eventsArray as $event) {
            $stmt->execute([
                ':event_name'  => $event['event_name'] ?? '',
                ':event_time'  => $event['event_time'] ?? $now,
                ':page'        => $event['page'] ?? null,
                ':page_type'   => $event['page_type'] ?? null,
                ':custom_data' => $event['custom_data'] ?? null,
                ':ip_hash'     => $event['ip_hash'] ?? null,
                ':user_agent'  => $event['user_agent'] ?? null,
                ':referrer'    => $event['referrer'] ?? null,
            ]);
        }

        return $db->commit();
    } catch (Exception $e) {
        try {
            $db = getAnalyticsDB();
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
        } catch (Exception $rollbackEx) {
            // Ignore rollback errors
        }
        error_log('Analytics logEvents failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Compute and store daily aggregate stats for a given date (YYYY-MM-DD).
 * Uses INSERT OR REPLACE to update existing rows.
 */
function aggregateDailyStats(string $date): bool {
    try {
        $db = getAnalyticsDB();
        if (!$db) return false;

        // Date boundaries as unix timestamps
        $startTs = strtotime($date . ' 00:00:00');
        $endTs = strtotime($date . ' 23:59:59');

        if ($startTs === false || $endTs === false) {
            return false;
        }

        $upsert = $db->prepare('
            INSERT OR REPLACE INTO daily_stats (date, metric, value, details)
            VALUES (:date, :metric, :value, :details)
        ');

        $db->beginTransaction();

        // Pageviews
        $stmt = $db->prepare('SELECT COUNT(*) FROM events WHERE event_name = "PageView" AND event_time BETWEEN :start AND :end');
        $stmt->execute([':start' => $startTs, ':end' => $endTs]);
        $pageviews = (int)$stmt->fetchColumn();
        $upsert->execute([':date' => $date, ':metric' => 'pageviews', ':value' => $pageviews, ':details' => null]);

        // Unique visitors (distinct ip_hash)
        $stmt = $db->prepare('SELECT COUNT(DISTINCT ip_hash) FROM events WHERE event_name = "PageView" AND ip_hash IS NOT NULL AND event_time BETWEEN :start AND :end');
        $stmt->execute([':start' => $startTs, ':end' => $endTs]);
        $uniqueVisitors = (int)$stmt->fetchColumn();
        $upsert->execute([':date' => $date, ':metric' => 'unique_visitors', ':value' => $uniqueVisitors, ':details' => null]);

        // Leads (form submissions)
        $stmt = $db->prepare('SELECT COUNT(*) FROM events WHERE event_name = "Lead" AND event_time BETWEEN :start AND :end');
        $stmt->execute([':start' => $startTs, ':end' => $endTs]);
        $leads = (int)$stmt->fetchColumn();
        $upsert->execute([':date' => $date, ':metric' => 'leads', ':value' => $leads, ':details' => null]);

        // Form starts
        $stmt = $db->prepare('SELECT COUNT(*) FROM events WHERE event_name = "FormStart" AND event_time BETWEEN :start AND :end');
        $stmt->execute([':start' => $startTs, ':end' => $endTs]);
        $formStarts = (int)$stmt->fetchColumn();
        $upsert->execute([':date' => $date, ':metric' => 'form_starts', ':value' => $formStarts, ':details' => null]);

        // Average engagement score (from custom_data JSON)
        $stmt = $db->prepare('SELECT custom_data FROM events WHERE event_name = "EngagementScore" AND custom_data IS NOT NULL AND event_time BETWEEN :start AND :end');
        $stmt->execute([':start' => $startTs, ':end' => $endTs]);
        $engagementScores = [];
        while ($row = $stmt->fetch()) {
            $data = json_decode($row['custom_data'], true);
            if (isset($data['score'])) {
                $engagementScores[] = (float)$data['score'];
            }
        }
        $avgEngagement = count($engagementScores) > 0 ? array_sum($engagementScores) / count($engagementScores) : 0;
        $upsert->execute([':date' => $date, ':metric' => 'avg_engagement_score', ':value' => round($avgEngagement, 2), ':details' => null]);

        // Average time on page (from custom_data JSON)
        $stmt = $db->prepare('SELECT custom_data FROM events WHERE event_name = "TimeOnPage" AND custom_data IS NOT NULL AND event_time BETWEEN :start AND :end');
        $stmt->execute([':start' => $startTs, ':end' => $endTs]);
        $times = [];
        while ($row = $stmt->fetch()) {
            $data = json_decode($row['custom_data'], true);
            if (isset($data['seconds'])) {
                $times[] = (float)$data['seconds'];
            }
        }
        $avgTime = count($times) > 0 ? array_sum($times) / count($times) : 0;
        $upsert->execute([':date' => $date, ':metric' => 'avg_time_on_page', ':value' => round($avgTime, 2), ':details' => null]);

        // Top pages (JSON breakdown)
        $stmt = $db->prepare('SELECT page, COUNT(*) as cnt FROM events WHERE event_name = "PageView" AND page IS NOT NULL AND event_time BETWEEN :start AND :end GROUP BY page ORDER BY cnt DESC LIMIT 10');
        $stmt->execute([':start' => $startTs, ':end' => $endTs]);
        $topPages = $stmt->fetchAll();
        $upsert->execute([':date' => $date, ':metric' => 'top_pages', ':value' => count($topPages), ':details' => json_encode($topPages)]);

        // Scroll depth distribution
        $scrollDist = ['25' => 0, '50' => 0, '75' => 0, '90' => 0, '100' => 0];
        $stmt = $db->prepare('SELECT custom_data FROM events WHERE event_name = "ScrollDepth" AND custom_data IS NOT NULL AND event_time BETWEEN :start AND :end');
        $stmt->execute([':start' => $startTs, ':end' => $endTs]);
        while ($row = $stmt->fetch()) {
            $data = json_decode($row['custom_data'], true);
            if (isset($data['depth_percent'])) {
                $d = (string)$data['depth_percent'];
                if (isset($scrollDist[$d])) $scrollDist[$d]++;
            }
        }
        $avgDepth = 0;
        $totalScrollEvents = array_sum($scrollDist);
        if ($totalScrollEvents > 0) {
            $avgDepth = round(($scrollDist['25']*25 + $scrollDist['50']*50 + $scrollDist['75']*75 + $scrollDist['90']*90 + $scrollDist['100']*100) / $totalScrollEvents, 2);
        }
        $upsert->execute([':date' => $date, ':metric' => 'scroll_depth_avg', ':value' => $avgDepth, ':details' => json_encode($scrollDist)]);

        // Bounce rate (visitors with only 1 pageview / total visitors)
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM (
                SELECT ip_hash, COUNT(*) as pv_count
                FROM events
                WHERE event_name = "PageView" AND ip_hash IS NOT NULL AND event_time BETWEEN :start AND :end
                GROUP BY ip_hash
                HAVING pv_count = 1
            )
        ');
        $stmt->execute([':start' => $startTs, ':end' => $endTs]);
        $bouncedVisitors = (int)$stmt->fetchColumn();
        $bounceRate = $uniqueVisitors > 0 ? round(($bouncedVisitors / $uniqueVisitors) * 100, 2) : 0;
        $upsert->execute([':date' => $date, ':metric' => 'bounce_rate', ':value' => $bounceRate, ':details' => null]);

        $commitResult = $db->commit();

        // Auto-cleanup: run once per request after commit
        static $cleanupDone = false;
        if (!$cleanupDone) {
            $cleanupDone = true;
            cleanupOldEvents(180);
        }

        return $commitResult;
    } catch (Exception $e) {
        try {
            $db = getAnalyticsDB();
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
        } catch (Exception $rollbackEx) {
            // Ignore rollback errors
        }
        error_log('Analytics aggregateDailyStats failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get daily_stats rows between two dates (inclusive, YYYY-MM-DD format).
 */
function getStats(string $startDate, string $endDate): array {
    try {
        $db = getAnalyticsDB();
        if (!$db) return [];

        $stmt = $db->prepare('
            SELECT date, metric, value, details
            FROM daily_stats
            WHERE date BETWEEN :start AND :end
            ORDER BY date ASC, metric ASC
        ');
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);

        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Analytics getStats failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get the last N events of a specific type, most recent first.
 */
function getRecentEvents(string $eventName, int $limit = 50): array {
    try {
        $db = getAnalyticsDB();
        if (!$db) return [];

        $stmt = $db->prepare('
            SELECT id, event_name, event_time, page, page_type, custom_data, ip_hash, user_agent, referrer, created_at
            FROM events
            WHERE event_name = :event_name
            ORDER BY event_time DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':event_name', $eventName, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Analytics getRecentEvents failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Delete events older than N days. Call periodically to prevent unbounded growth.
 */
function cleanupOldEvents(int $daysToKeep = 180): bool {
    try {
        $db = getAnalyticsDB();
        if (!$db) return false;

        $cutoff = time() - ($daysToKeep * 86400);
        $stmt = $db->prepare('DELETE FROM events WHERE event_time < :cutoff');
        $stmt->execute([':cutoff' => $cutoff]);

        // Also clean up old daily_stats
        $cutoffDate = date('Y-m-d', $cutoff);
        $stmt = $db->prepare('DELETE FROM daily_stats WHERE date < :cutoff');
        $stmt->execute([':cutoff' => $cutoffDate]);

        // Reclaim space
        $db->exec('PRAGMA optimize');

        return true;
    } catch (Exception $e) {
        error_log('Analytics cleanup failed: ' . $e->getMessage());
        return false;
    }
}
