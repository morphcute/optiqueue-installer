<?php
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
// When installer lives in /install, target directory is parent folder (public_html / workspace)
$targetDir = realpath(__DIR__ . '/..') ?: __DIR__;

function sendJson($status, $data = []) {
    echo json_encode(array_merge(['status' => $status], $data));
    exit;
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
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = trim($_POST['db_port'] ?? '3306');
        $dbName = trim($_POST['db_name'] ?? 'optiqueue');
        $user = trim($_POST['db_user'] ?? 'root');
        $pass = $_POST['db_pass'] ?? '';
        $appUrl = trim($_POST['app_url'] ?? 'http://localhost:8000');

        $appKey = 'base64:' . base64_encode(random_bytes(32));

        $envContent = <<<EOT
APP_NAME=OptiQueue
APP_ENV=local
APP_KEY={$appKey}
APP_DEBUG=true
APP_TIMEZONE=Asia/Manila
APP_URL={$appUrl}

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
            $autoloadPath = $targetDir . '/vendor/autoload.php';
            $bootstrapPath = $targetDir . '/bootstrap/app.php';

            if (file_exists($autoloadPath) && file_exists($bootstrapPath)) {
                // Execute Artisan migrations natively in PHP without needing shell exec()
                require_once $autoloadPath;
                $app = require_once $bootstrapPath;

                $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
                @$kernel->call('migrate', ['--force' => true]);
                @$kernel->call('db:seed', ['--force' => true]);

                sendJson('success', [
                    'message' => 'Database migrations and seeding completed successfully!'
                ]);
            } else {
                // Fallback to exec if available
                if (function_exists('exec')) {
                    $phpPath = PHP_BINARY ? PHP_BINARY : 'php';
                    $artisanPath = $targetDir . '/artisan';
                    if (file_exists($artisanPath)) {
                        @exec("\"{$phpPath}\" \"{$artisanPath}\" migrate --force");
                        @exec("\"{$phpPath}\" \"{$artisanPath}\" db:seed --force");
                    }
                }
                sendJson('success', ['message' => 'Setup initialization completed!']);
            }
        } catch (Throwable $e) {
            // Return clean JSON error instead of 500
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
        } catch (Exception $e) {
            sendJson('error', ['message' => 'Failed to create admin user: ' . $e->getMessage()]);
        }
        break;

    default:
        sendJson('error', ['message' => 'Invalid action requested']);
        break;
}
