<?php
/**
 * Idempotent database auto-migrator (mysqli version).
 *
 * Runs pending PHP migrations from database/auto-migrations/ when an admin loads any admin page.
 *
 * Add a new file under database/auto-migrations/ named like:
 *   2026_09_03_120000_short_name.php
 * Returning:
 *   ['id' => '...', 'description' => '...', 'up' => function(mysqli $conn) { ... }]
 */

class DatabaseAutoMigrate {
    private static $ranThisRequest = false;
    private $conn;
    private $dir;

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
        $this->dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'auto-migrations';
    }

    /**
     * Run once per request. Returns summary array.
     */
    public function run($appliedBy = null) {
        if (self::$ranThisRequest) {
            return $_SESSION['auto_migration_last_result'] ?? [
                'ran' => false,
                'applied' => [],
                'failed' => [],
                'skipped' => 0,
                'errors' => [],
            ];
        }
        self::$ranThisRequest = true;

        $result = [
            'ran' => true,
            'applied' => [],
            'failed' => [],
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            $this->ensureTrackingTable();
            $migrations = $this->discoverMigrations();
            $appliedIds = $this->getSuccessfullyAppliedIds();

            foreach ($migrations as $migration) {
                $id = (string) $migration['id'];
                if (isset($appliedIds[$id])) {
                    $result['skipped']++;
                    continue;
                }

                try {
                    $up = $migration['up'];
                    if (!is_callable($up)) {
                        throw new Exception('Migration up() is not callable');
                    }
                    $up($this->conn);
                    $this->recordSuccess($id, $migration['description'] ?? $id, $appliedBy);
                    $result['applied'][] = [
                        'id' => $id,
                        'description' => $migration['description'] ?? $id,
                    ];
                } catch (Throwable $e) {
                    $msg = $e->getMessage();
                    $this->recordFailure($id, $migration['description'] ?? $id, $msg, $appliedBy);
                    $result['failed'][] = [
                        'id' => $id,
                        'description' => $migration['description'] ?? $id,
                        'error' => $msg,
                    ];
                    $result['errors'][] = "{$id}: {$msg}";
                    error_log("Auto-migration failed [{$id}]: {$msg}");
                }
            }
        } catch (Throwable $e) {
            $result['errors'][] = 'Migrator bootstrap failed: ' . $e->getMessage();
            error_log('DatabaseAutoMigrate bootstrap error: ' . $e->getMessage());
        }

        $_SESSION['auto_migration_last_result'] = $result;

        if (!empty($result['failed']) || !empty($result['errors'])) {
            $_SESSION['auto_migration_errors'] = $result['errors'];
        } else {
            unset($_SESSION['auto_migration_errors']);
        }

        if (!empty($result['applied'])) {
            $_SESSION['auto_migration_success'] = array_map(static function ($row) {
                return ($row['description'] ?? $row['id']) . ' (' . $row['id'] . ')';
            }, $result['applied']);
        }

        return $result;
    }

    private function ensureTrackingTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `auto_migrations` (
            `id` varchar(191) NOT NULL,
            `description` varchar(255) DEFAULT NULL,
            `status` enum('success','failed') NOT NULL DEFAULT 'success',
            `error_message` text DEFAULT NULL,
            `applied_by` int(11) DEFAULT NULL,
            `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_status` (`status`),
            KEY `idx_applied_at` (`applied_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$this->conn->query($sql)) {
            throw new Exception('Failed creating auto_migrations tracking table: ' . $this->conn->error);
        }
    }

    private function getSuccessfullyAppliedIds() {
        $ids = [];
        $result = $this->conn->query("SELECT id FROM auto_migrations WHERE status = 'success'");
        if ($result === false) {
            throw new Exception('Failed reading auto_migrations: ' . $this->conn->error);
        }
        while ($row = $result->fetch_assoc()) {
            $ids[$row['id']] = true;
        }
        $result->free();
        return $ids;
    }

    private function discoverMigrations() {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }

        $files = glob($this->dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);

        $migrations = [];
        foreach ($files as $file) {
            if (basename($file) === 'index.php') {
                continue;
            }
            $data = include $file;
            if (!is_array($data) || empty($data['id']) || !isset($data['up'])) {
                throw new Exception('Invalid migration file: ' . basename($file));
            }
            $migrations[] = $data;
        }
        return $migrations;
    }

    private function recordSuccess($id, $description, $appliedBy) {
        $stmt = $this->conn->prepare(
            "INSERT INTO auto_migrations (id, description, status, error_message, applied_by, applied_at)
             VALUES (?, ?, 'success', NULL, ?, NOW())
             ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                status = 'success',
                error_message = NULL,
                applied_by = VALUES(applied_by),
                applied_at = NOW()"
        );
        $stmt->bind_param('ssi', $id, $description, $appliedBy);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("Failed recording success for migration {$id}");
        }
        $stmt->close();
    }

    private function recordFailure($id, $description, $error, $appliedBy) {
        $stmt = $this->conn->prepare(
            "INSERT INTO auto_migrations (id, description, status, error_message, applied_by, applied_at)
             VALUES (?, ?, 'failed', ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                status = 'failed',
                error_message = VALUES(error_message),
                applied_by = VALUES(applied_by),
                updated_at = NOW()"
        );
        $stmt->bind_param('sssi', $id, $description, $error, $appliedBy);
        $stmt->execute();
        $stmt->close();
    }

    // ---- Static helpers for use inside migration up() closures ----

    /**
     * Add a column if it doesn't exist. Returns true if added.
     */
    public static function ensureColumn(mysqli $conn, $table, $column, $definitionSql) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?"
        );
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($row['cnt'])) {
            return false;
        }

        if (!$conn->query("ALTER TABLE `{$table}` ADD COLUMN {$definitionSql}")) {
            throw new Exception("Failed adding column {$table}.{$column}: " . $conn->error);
        }
        return true;
    }

    /**
     * Ensure a settings row exists. Returns true if inserted.
     */
    public static function ensureSetting(mysqli $conn, $key, $value) {
        $stmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            return false;
        }

        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->bind_param('ss', $key, $value);
        if (!$stmt->execute()) {
            throw new Exception("Failed inserting setting '{$key}': " . $conn->error);
        }
        $stmt->close();
        return true;
    }

    /**
     * Run a query and throw on failure.
     */
    public static function execOrFail(mysqli $conn, $sql, $label = null) {
        if (!$conn->query($sql)) {
            throw new Exception(($label ?: 'Query failed') . ': ' . $conn->error);
        }
    }

    /**
     * Check if a table exists.
     */
    public static function tableExists(mysqli $conn, $table) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?"
        );
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return !empty($row['cnt']);
    }

    /**
     * Check if a column exists.
     */
    public static function columnExists(mysqli $conn, $table, $column) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?"
        );
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return !empty($row['cnt']);
    }

    /**
     * Modify a column type if different. Idempotent.
     */
    public static function modifyColumn(mysqli $conn, $table, $column, $newDefinition) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if (!$conn->query("ALTER TABLE `{$table}` MODIFY COLUMN {$newDefinition}")) {
            throw new Exception("Failed modifying column {$table}.{$column}: " . $conn->error);
        }
    }
}

/**
 * Run auto-migrations for the current admin session.
 */
function runAdminDatabaseAutoMigrations($adminUserId = null) {
    global $conn;
    if (!$conn || !($conn instanceof mysqli)) {
        return null;
    }
    $migrator = new DatabaseAutoMigrate($conn);
    return $migrator->run($adminUserId);
}
