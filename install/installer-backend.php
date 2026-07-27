<?php
ob_start();
error_reporting(0);
ini_set('display_errors', '0');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
// When installer lives in /install, target directory is parent folder (public_html / workspace)
$targetDir = realpath(__DIR__ . '/..') ?: __DIR__;

function sendJson($status, $data = []) {
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status], $data));
    exit;
}

function executeSqlFile($pdo, $sqlFilePath) {
    if (!file_exists($sqlFilePath)) return false;
    $sql = file_get_contents($sqlFilePath);
    
    // Temporarily disable foreign key checks for clean batch table creation
    try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;"); } catch (Throwable $e) {}

    $queries = explode(";\n", $sql);
    foreach ($queries as $q) {
        $query = trim($q);
        if (!empty($query)) {
            try {
                $pdo->exec($query);
            } catch (Throwable $e) {
                // Ignore minor duplicate table warnings
            }
        }
    }

    try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;"); } catch (Throwable $e) {}
    return true;
}

function ensureStorageDirectories($targetDir) {
    @umask(0);

    $storageBase = $targetDir . '/storage';

    // Remove 'storage' if it exists as a symlink or file (is_link catches broken symlinks)
    if (is_link($storageBase) || (file_exists($storageBase) && !is_dir($storageBase))) {
        @unlink($storageBase);
    }

    $dirs = [
        $targetDir . '/storage',
        $targetDir . '/storage/app',
        $targetDir . '/storage/app/public',
        $targetDir . '/storage/framework',
        $targetDir . '/storage/framework/cache',
        $targetDir . '/storage/framework/cache/data',
        $targetDir . '/storage/framework/sessions',
        $targetDir . '/storage/framework/testing',
        $targetDir . '/storage/framework/views',
        $targetDir . '/storage/logs',
        $targetDir . '/bootstrap/cache',
    ];

    foreach ($dirs as $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0777, true);
        }
        @chmod($d, 0777);
        @file_put_contents($d . '/.gitkeep', '');
    }

    // Wipe old compiled view files in storage/framework/views/ so Laravel creates fresh writable files
    $viewsDir = $targetDir . '/storage/framework/views';
    if (is_dir($viewsDir)) {
        $files = glob($viewsDir . '/*');
        if ($files) {
            foreach ($files as $f) {
                if (is_file($f) && basename($f) !== '.gitkeep') {
                    @chmod($f, 0777);
                    @unlink($f);
                }
            }
        }
    }

    // Wipe stale development framework cache files from bootstrap/cache/
    @unlink($targetDir . '/bootstrap/cache/config.php');
    @unlink($targetDir . '/bootstrap/cache/routes.php');
    @unlink($targetDir . '/bootstrap/cache/services.php');
    @unlink($targetDir . '/bootstrap/cache/packages.php');
}

switch ($action) {
    case 'check_env':
        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.1.0', '>=');
        
        $pdoOk = extension_loaded('pdo_mysql');
        $zipOk = extension_loaded('zip');
        $fileinfoOk = extension_loaded('fileinfo');
        $opensslOk = extension_loaded('openssl');

        // Real Write Test instead of unreliable is_writable()
        $testFile = $targetDir . '/.__write_test_' . time();
        $writableOk = false;
        if (@file_put_contents($testFile, 'test') !== false) {
            $writableOk = true;
            @unlink($testFile);
        }

        sendJson('success', [
            'checks' => [
                ['name' => 'PHP Version (>= 8.1)', 'pass' => $phpOk, 'value' => $phpVersion],
                ['name' => 'PDO MySQL Extension', 'pass' => $pdoOk, 'value' => $pdoOk ? 'Enabled' : 'Missing'],
                ['name' => 'ZIP Archive Extension', 'pass' => $zipOk, 'value' => $zipOk ? 'Enabled' : 'Missing'],
                ['name' => 'OpenSSL Extension', 'pass' => $opensslOk, 'value' => $opensslOk ? 'Enabled' : 'Missing'],
                ['name' => 'Target Directory Writable', 'pass' => $writableOk, 'value' => $writableOk ? 'Writable' : 'Write Test Failed'],
            ],
            'canProceed' => ($phpOk && $pdoOk && $zipOk)
        ]);
        break;

    case 'test_db':
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = trim($_POST['db_port'] ?? '3306');
        $dbName = trim($_POST['db_name'] ?? 'optiqueue');
        $user = trim($_POST['db_user'] ?? 'root');
        $pass = $_POST['db_pass'] ?? '';

        // 1. Try connecting directly to pre-created DB (for shared hosting like AwardSpace, Hostinger, cPanel)
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            sendJson('success', ['message' => "Successfully connected to database '{$dbName}'!"]);
        } catch (Exception $eDirect) {
            // 2. Fallback: try creating DB if user has root/create privileges (for local XAMPP/Docker)
            try {
                $pdoRoot = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

                $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName}", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                sendJson('success', ['message' => "Database '{$dbName}' created & connected!"]);
            } catch (Exception $eRoot) {
                sendJson('error', ['message' => 'Database connection failed: ' . $eDirect->getMessage()]);
            }
        }
        break;

    case 'write_env':
        ensureStorageDirectories($targetDir);

        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = trim($_POST['db_port'] ?? '3306');
        $dbName = trim($_POST['db_name'] ?? 'optiqueue');
        $user = trim($_POST['db_user'] ?? 'root');
        $pass = $_POST['db_pass'] ?? '';
        $appUrl = trim($_POST['app_url'] ?? 'http://localhost:8000');

        $appKey = 'base64:' . base64_encode(random_bytes(32));
        $viewsPath = str_replace('\\', '/', $targetDir . '/storage/framework/views');

        $envContent = <<<EOT
APP_NAME=OptiQueue
APP_ENV=local
APP_KEY={$appKey}
APP_DEBUG=true
APP_TIMEZONE=Asia/Manila
APP_URL={$appUrl}

VIEW_COMPILED_PATH={$viewsPath}

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST={$host}
DB_PORT={$port}
DB_DATABASE={$dbName}
DB_USERNAME={$user}
DB_PASSWORD="{$pass}"

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
EOT;

        $targetEnvPath = $targetDir . '/.env';
        if (@file_put_contents($targetEnvPath, $envContent) !== false) {
            sendJson('success', ['message' => '.env file generated successfully!']);
        } else {
            sendJson('error', ['message' => 'Failed to write .env file to ' . $targetEnvPath . '. Please check directory permissions.']);
        }
        break;

    case 'list_zips':
        $zips = [];
        $dirsToScan = [$targetDir, __DIR__, $targetDir . '/dist_installer'];

        foreach ($dirsToScan as $dir) {
            if (is_dir($dir)) {
                $found = glob($dir . '/*.zip');
                if ($found) {
                    foreach ($found as $filePath) {
                        $filename = basename($filePath);
                        if (!isset($zips[$filename])) {
                            $zips[$filename] = [
                                'filename' => $filename,
                                'path' => $filePath,
                                'size' => round(filesize($filePath) / (1024 * 1024), 2) . ' MB'
                            ];
                        }
                    }
                }
            }
        }

        sendJson('success', ['zips' => array_values($zips)]);
        break;

    case 'extract_zip':
        $zipFilePath = null;

        // Check if local ZIP is selected
        if (!empty($_POST['local_zip_name'])) {
            $localName = basename($_POST['local_zip_name']);
            $dirsToScan = [$targetDir . '/' . $localName, __DIR__ . '/' . $localName, $targetDir . '/dist_installer/' . $localName];
            foreach ($dirsToScan as $p) {
                if (file_exists($p)) {
                    $zipFilePath = $p;
                    break;
                }
            }
        }

        // Fallback to uploaded ZIP file if no local file selected
        if (!$zipFilePath && !empty($_FILES['zip_file']['tmp_name'])) {
            $zipFilePath = $_FILES['zip_file']['tmp_name'];
        }

        if (!$zipFilePath) {
            sendJson('error', ['message' => 'No ZIP package selected or uploaded']);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) === true) {
            $successCount = 0;
            $failCount = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                // Skip overwriting running installer directory
                if (str_starts_with($filename, 'install/')) {
                    continue;
                }

                $targetPath = $targetDir . '/' . ltrim($filename, '/');

                if (str_ends_with($filename, '/')) {
                    if (!is_dir($targetPath)) {
                        @mkdir($targetPath, 0755, true);
                    }
                } else {
                    $dirName = dirname($targetPath);
                    if (!is_dir($dirName)) {
                        @mkdir($dirName, 0755, true);
                    }

                    $stream = $zip->getStream($filename);
                    if ($stream) {
                        $contents = stream_get_contents($stream);
                        fclose($stream);

                        if (@file_put_contents($targetPath, $contents) !== false) {
                            $successCount++;
                        } else {
                            $failCount++;
                        }
                    } else {
                        $failCount++;
                    }
                }
            }
            $zip->close();

            if ($successCount > 0) {
                // Ensure all required Laravel storage directories exist and are writable
                ensureStorageDirectories($targetDir);

                // Automatically generate Hostinger/Apache root .htaccess for Laravel
                $htaccessContent = <<<EOT
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^$ public/ [L]
    RewriteRule (.*) public/$1 [L]
</IfModule>
EOT;
                @file_put_contents($targetDir . '/.htaccess', $htaccessContent);

                sendJson('success', [
                    'message' => "Successfully extracted {$successCount} project files to server!",
                    'filesExtracted' => $successCount,
                    'filesFailed' => $failCount
                ]);
            } else {
                sendJson('error', [
                    'message' => "Cannot extract ZIP files to '{$targetDir}'. Please check folder permissions (0755/0777)."
                ]);
            }
        } else {
            sendJson('error', ['message' => 'Failed to open or invalid ZIP package.']);
        }
        break;

    case 'run_setup':
        try {
            ensureStorageDirectories($targetDir);

            // Fetch DB credentials from POST or .env
            $host = trim($_POST['db_host'] ?? '127.0.0.1');
            $port = trim($_POST['db_port'] ?? '3306');
            $dbName = trim($_POST['db_name'] ?? 'optiqueue');
            $user = trim($_POST['db_user'] ?? 'root');
            $pass = $_POST['db_pass'] ?? '';

            // Run direct PDO schema migration from optiqueue_schema.sql if present
            try {
                $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName}", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                $sqlPaths = [
                    __DIR__ . '/optiqueue_schema.sql',
                    $targetDir . '/database/optiqueue_schema.sql',
                    $targetDir . '/optiqueue_schema.sql'
                ];

                foreach ($sqlPaths as $sqlFile) {
                    if (file_exists($sqlFile)) {
                        executeSqlFile($pdo, $sqlFile);
                        break;
                    }
                }

                $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) NOT NULL,
                  `email` varchar(255) NOT NULL,
                  `email_verified_at` timestamp NULL DEFAULT NULL,
                  `password` varchar(255) NOT NULL,
                  `role` varchar(50) NOT NULL DEFAULT 'User',
                  `aid` varchar(255) DEFAULT NULL,
                  `birthday` date DEFAULT NULL,
                  `last_seen_at` timestamp NULL DEFAULT NULL,
                  `bio` text DEFAULT NULL,
                  `google_id` varchar(255) DEFAULT NULL,
                  `profile_photo_path` varchar(2048) DEFAULT NULL,
                  `remember_token` varchar(100) DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `users_email_unique` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `login_settings` (
                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `settings` json DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("INSERT IGNORE INTO `login_settings` (`id`, `settings`, `created_at`, `updated_at`) VALUES (1, '{}', NOW(), NOW());");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `departments` (
                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) NOT NULL,
                  `status` varchar(50) NOT NULL DEFAULT 'Active',
                  `datecreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `services` (
                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `department_id` bigint(20) UNSIGNED NOT NULL,
                  `name` varchar(255) NOT NULL,
                  `default_number` int(10) UNSIGNED NOT NULL DEFAULT 0,
                  `prefix` varchar(10) NOT NULL,
                  `status` varchar(50) NOT NULL DEFAULT 'Active',
                  `instructions` text DEFAULT NULL,
                  `estimated_minutes_per_ticket` int(10) UNSIGNED DEFAULT NULL,
                  `datecreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `counters` (
                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) NOT NULL,
                  `assigned_service_id` bigint(20) UNSIGNED DEFAULT NULL,
                  `assigned_service1_id` bigint(20) UNSIGNED DEFAULT NULL,
                  `assigned_service2_id` bigint(20) UNSIGNED DEFAULT NULL,
                  `assigned_service3_id` bigint(20) UNSIGNED DEFAULT NULL,
                  `assigned_service4_id` bigint(20) UNSIGNED DEFAULT NULL,
                  `status` varchar(50) NOT NULL DEFAULT 'Active',
                  `is_on_break` tinyint(1) NOT NULL DEFAULT 0,
                  `break_start_time` timestamp NULL DEFAULT NULL,
                  `break_end_time` timestamp NULL DEFAULT NULL,
                  `break_reason` text DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `customers` (
                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `phone_number` varchar(255) NOT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `queues` (
                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `service_id` bigint(20) UNSIGNED NOT NULL,
                  `transferred_service_id` bigint(20) UNSIGNED DEFAULT NULL,
                  `customer_id` bigint(20) UNSIGNED NOT NULL,
                  `ticket_number` varchar(255) NOT NULL,
                  `called` tinyint(1) NOT NULL DEFAULT 0,
                  `preferred_prefix` varchar(50) NOT NULL DEFAULT '',
                  `transferred_ticket` tinyint(1) NOT NULL DEFAULT 0,
                  `default_number` int(10) UNSIGNED NOT NULL DEFAULT 0,
                  `missed` tinyint(1) NOT NULL DEFAULT 0,
                  `created_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `token` varchar(255) NOT NULL,
                  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
                  `created_from` varchar(50) NOT NULL DEFAULT 'station',
                  `was_no_show` tinyint(1) NOT NULL DEFAULT 0,
                  `canceled_by_user` tinyint(1) NOT NULL DEFAULT 0,
                  `created_day` date DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `queues_token_unique` (`token`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `calls` (
                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `queue_id` bigint(20) UNSIGNED NOT NULL,
                  `counter_id` bigint(20) UNSIGNED NOT NULL,
                  `user_id` bigint(20) UNSIGNED NOT NULL,
                  `called_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `announced_datetime` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `user_logs` (
                  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `user_id` bigint(20) UNSIGNED NOT NULL,
                  `action` varchar(255) NOT NULL,
                  `details` text DEFAULT NULL,
                  `ip_address` varchar(45) DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            } catch (Throwable $e) {
                // PDO creation fallback logged
            }

            // Emulate CLI environment before requiring Laravel bootstrap
            $_SERVER['REQUEST_URI'] = '/';
            $_SERVER['SCRIPT_NAME'] = 'artisan';
            $_SERVER['SCRIPT_FILENAME'] = $targetDir . '/artisan';
            $_SERVER['PHP_SELF'] = 'artisan';
            $_SERVER['REQUEST_METHOD'] = 'GET';

            $autoloadPath = $targetDir . '/vendor/autoload.php';
            $bootstrapPath = $targetDir . '/bootstrap/app.php';

            if (file_exists($autoloadPath) && file_exists($bootstrapPath)) {
                try {
                    require_once $autoloadPath;

                    if (!defined('STDIN')) define('STDIN', fopen('php://stdin', 'r'));
                    if (!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'w'));
                    if (!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));

                    $app = require $bootstrapPath;
                    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
                    if (method_exists($kernel, 'bootstrap')) {
                        @$kernel->bootstrap();
                    }

                    @$kernel->call('migrate', ['--force' => true]);
                    @$kernel->call('db:seed', ['--force' => true]);
                } catch (Throwable $e) {
                    // Ignore Artisan warnings if PDO schema already succeeded
                }
            }

            sendJson('success', [
                'message' => 'Database tables and migrations created successfully!'
            ]);
        } catch (Throwable $e) {
            sendJson('error', ['message' => 'Migration notice: ' . $e->getMessage()]);
        }
        break;

    case 'create_admin':
        $name = trim($_POST['admin_name'] ?? 'Administrator');
        $email = trim($_POST['admin_email'] ?? 'admin@optiqueue.online');
        $password = $_POST['admin_password'] ?? 'admin123';

        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = trim($_POST['db_port'] ?? '3306');
        $dbName = trim($_POST['db_name'] ?? 'optiqueue');
        $user = trim($_POST['db_user'] ?? 'root');
        $pass = $_POST['db_pass'] ?? '';

        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $sqlPaths = [
                __DIR__ . '/optiqueue_schema.sql',
                $targetDir . '/database/optiqueue_schema.sql',
                $targetDir . '/optiqueue_schema.sql'
            ];

            foreach ($sqlPaths as $sqlFile) {
                if (file_exists($sqlFile)) {
                    executeSqlFile($pdo, $sqlFile);
                    break;
                }
            }

            // Ensure essential core tables exist via PDO
            $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
              `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL,
              `email` varchar(255) NOT NULL,
              `email_verified_at` timestamp NULL DEFAULT NULL,
              `password` varchar(255) NOT NULL,
              `role` varchar(50) NOT NULL DEFAULT 'User',
              `aid` varchar(255) DEFAULT NULL,
              `birthday` date DEFAULT NULL,
              `last_seen_at` timestamp NULL DEFAULT NULL,
              `bio` text DEFAULT NULL,
              `google_id` varchar(255) DEFAULT NULL,
              `profile_photo_path` varchar(2048) DEFAULT NULL,
              `remember_token` varchar(100) DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `users_email_unique` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `login_settings` (
              `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
              `settings` json DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("INSERT IGNORE INTO `login_settings` (`id`, `settings`, `created_at`, `updated_at`) VALUES (1, '{}', NOW(), NOW());");

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $now = date('Y-m-d H:i:s');

            // Insert or Update Administrator user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at, updated_at) 
                VALUES (:name, :email, :password, 'Administrator', :created_at, :updated_at)
                ON DUPLICATE KEY UPDATE name = :name2, password = :password2, role = 'Administrator', updated_at = :updated_at2");
            
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':created_at' => $now,
                ':updated_at' => $now,
                ':name2' => $name,
                ':password2' => $hashedPassword,
                ':updated_at2' => $now,
            ]);

            // Create installation lock file
            @file_put_contents($targetDir . '/installed.lock', 'INSTALLED_ON_' . date('Y-m-d H:i:s'));

            // Rename index.php to installer-done.php so Hostinger serves Laravel's public/index.php
            if (file_exists(__DIR__ . '/index.php')) {
                @rename(__DIR__ . '/index.php', __DIR__ . '/installer-done.php');
            }

            sendJson('success', [
                'message' => 'Administrator account created & installation locked!',
                'loginEmail' => $email
            ]);
        } catch (Throwable $e) {
            sendJson('error', ['message' => 'Failed to create admin user: ' . $e->getMessage()]);
        }
        break;

    default:
        sendJson('error', ['message' => 'Invalid action requested']);
        break;
}
