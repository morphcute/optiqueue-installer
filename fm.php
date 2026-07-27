<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$password = 'admin123';

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Login
if (isset($_POST['pass'])) {
    if ($_POST['pass'] === $password) {
        $_SESSION['auth'] = true;
    } else {
        $error = "Incorrect password access!";
    }
}

if (!isset($_SESSION['auth'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Pro File Manager</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://unpkg.com/lucide@latest"></script>
    </head>
    <body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl backdrop-blur-xl">
            <div class="flex justify-center mb-6">
                <div class="p-3 bg-indigo-500/10 rounded-xl border border-indigo-500/20 text-indigo-400">
                    <i data-lucide="shield-lock" class="w-8 h-8"></i>
                </div>
            </div>
            <h2 class="text-2xl font-bold text-center text-white mb-2">Web File Manager</h2>
            <p class="text-sm text-slate-400 text-center mb-6">Enter system key to authenticate session</p>
            
            <?php if (isset($error)): ?>
                <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-sm flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">Access Password</label>
                    <input type="password" name="pass" placeholder="••••••••" required class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white transition">
                </div>
                <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20">
                    <i data-lucide="log-in" class="w-4 h-4"></i> Authenticate
                </button>
            </form>
        </div>
        <script>lucide.createIcons();</script>
    </body>
    </html>
    <?php
    exit;
}

$dir = isset($_GET['dir']) ? realpath($_GET['dir']) : realpath(__DIR__);
$msg = '';
$err_msg = '';

// Format File Size Helper
function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// Helper function to zip folders for download
function zipFolder($source, $destination) {
    if (!extension_loaded('zip') || !file_exists($source)) return false;
    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE)) return false;

    $source = realpath($source);
    if (is_dir($source)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($files as $file) {
            $file = realpath($file);
            if (is_dir($file)) {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            } else if (is_file($file)) {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    } else if (is_file($source)) {
        $zip->addFromString(basename($source), file_get_contents($source));
    }
    return $zip->close();
}

// HANDLE DOWNLOAD
if (isset($_GET['download'])) {
    $targetName = $_GET['download'];
    $targetPath = $dir . DIRECTORY_SEPARATOR . $targetName;

    if (file_exists($targetPath)) {
        if (is_dir($targetPath)) {
            $zipName = $targetName . '.zip';
            $tmpZip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipName;
            if (zipFolder($targetPath, $tmpZip)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipName . '"');
                header('Content-Length: ' . filesize($tmpZip));
                readfile($tmpZip);
                unlink($tmpZip);
                exit;
            } else {
                $err_msg = "Failed to create ZIP package for download.";
            }
        } else {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($targetPath) . '"');
            header('Content-Length: ' . filesize($targetPath));
            readfile($targetPath);
            exit;
        }
    }
}

// FAST SYSTEM DELETION
if (isset($_GET['delete'])) {
    $targetName = $_GET['delete'];
    $targetPath = $dir . DIRECTORY_SEPARATOR . $targetName;

    if (file_exists($targetPath) && $targetName !== '.' && $targetName !== '..') {
        $escapedPath = escapeshellarg($targetPath);
        if (function_exists('exec')) {
            exec("rm -rf $escapedPath 2>&1", $output, $returnVar);
        }

        if (file_exists($targetPath)) {
            function fastPhpDelete($path) {
                @chmod($path, 0777);
                if (is_dir($path)) {
                    foreach (array_diff(scandir($path), array('.', '..')) as $file) {
                        fastPhpDelete($path . DIRECTORY_SEPARATOR . $file);
                    }
                    return @rmdir($path);
                }
                return @unlink($path);
            }
            fastPhpDelete($targetPath);
        }

        if (!file_exists($targetPath)) {
            $msg = "Successfully purged " . htmlspecialchars($targetName);
        } else {
            $err_msg = "Deletion blocked by server permissions (777 required).";
        }
    }
}

// HANDLE FILE UPLOAD
if (isset($_FILES['upload_file'])) {
    $target = $dir . DIRECTORY_SEPARATOR . basename($_FILES['upload_file']['name']);
    if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $target)) {
        $msg = "File uploaded successfully!";
    } else {
        $err_msg = "Upload failed. Check permissions.";
    }
}

// HANDLE ZIP EXTRACTION
if (isset($_POST['unzip']) && !empty($_FILES['zip_file']['tmp_name'])) {
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($_FILES['zip_file']['tmp_name']) === TRUE) {
            $zip->extractTo($dir);
            $zip->close();
            $msg = "Archive extracted successfully!";
        } else {
            $err_msg = "Failed to open ZIP package.";
        }
    } else {
        $err_msg = "PHP ZipArchive module is missing.";
    }
}

$files = scandir($dir);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pro File Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #020617; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #334155; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans">

    <!-- Header Navigation -->
    <header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-xl sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-600/10 rounded-lg border border-indigo-500/20 text-indigo-400">
                    <i data-lucide="folder-tree" class="w-6 h-6"></i>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white leading-tight">Pro File Manager</h1>
                    <p class="text-xs text-slate-400">Hostinger Fast Server Console</p>
                </div>
            </div>
            <a href="?logout=1" class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-red-500/20 bg-red-500/10 text-red-400 hover:bg-red-500/20 transition text-xs font-semibold">
                <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Logout
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <!-- Status Alerts -->
        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center gap-3 shadow-lg shadow-emerald-500/5">
                <i data-lucide="check-circle-2" class="w-5 h-5 flex-shrink-0"></i> <?= $msg ?>
            </div>
        <?php endif; ?>
        <?php if ($err_msg): ?>
            <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm flex items-center gap-3 shadow-lg shadow-red-500/5">
                <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0"></i> <?= $err_msg ?>
            </div>
        <?php endif; ?>

        <!-- Control Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <!-- Upload Box -->
            <form method="post" enctype="multipart/form-data" class="bg-slate-900 border border-slate-800 rounded-xl p-4 flex flex-col justify-between">
                <label class="block text-xs font-semibold uppercase text-slate-400 mb-2 flex items-center gap-2">
                    <i data-lucide="upload-cloud" class="w-4 h-4 text-indigo-400"></i> Direct File Upload
                </label>
                <div class="flex gap-2">
                    <input type="file" name="upload_file" required class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-indigo-300 hover:file:bg-slate-700 bg-slate-950 border border-slate-800 rounded-lg">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-xs rounded-lg transition flex items-center gap-1.5 flex-shrink-0">
                        Upload
                    </button>
                </div>
            </form>

            <!-- Unzip Box -->
            <form method="post" enctype="multipart/form-data" class="bg-slate-900 border border-slate-800 rounded-xl p-4 flex flex-col justify-between">
                <label class="block text-xs font-semibold uppercase text-slate-400 mb-2 flex items-center gap-2">
                    <i data-lucide="file-archive" class="w-4 h-4 text-emerald-400"></i> Deploy & Unzip Package
                </label>
                <div class="flex gap-2">
                    <input type="file" name="zip_file" accept=".zip" required class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-emerald-300 hover:file:bg-slate-700 bg-slate-950 border border-slate-800 rounded-lg">
                    <input type="hidden" name="unzip" value="1">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-xs rounded-lg transition flex items-center gap-1.5 flex-shrink-0">
                        Extract
                    </button>
                </div>
            </form>
        </div>

        <!-- Directory Navigation & Breadcrumbs -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-2xl">
            <div class="p-4 border-b border-slate-800 bg-slate-900/50 flex flex-wrap items-center justify-between gap-4">
                
                <!-- Path Breadcrumbs -->
                <div class="flex items-center gap-1 text-xs font-medium text-slate-300 overflow-x-auto max-w-full">
                    <i data-lucide="hard-drive" class="w-4 h-4 text-slate-400 mr-1 flex-shrink-0"></i>
                    <?php
                    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $dir));
                    $buildPath = '';
                    echo '<a href="?dir=' . urlencode(DIRECTORY_SEPARATOR) . '" class="hover:text-indigo-400 transition">root</a>';
                    foreach ($parts as $part) {
                        $buildPath .= DIRECTORY_SEPARATOR . $part;
                        echo '<span class="text-slate-600">/</span>';
                        echo '<a href="?dir=' . urlencode($buildPath) . '" class="hover:text-indigo-400 transition">' . htmlspecialchars($part) . '</a>';
                    }
                    ?>
                </div>

                <!-- Active Folder Actions -->
                <?php if ($dir !== realpath('/')): ?>
                    <a href="?dir=<?= urlencode($dir) ?>&download=." class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-sky-500/20 bg-sky-500/10 text-sky-400 hover:bg-sky-500/20 transition text-xs font-semibold">
                        <i data-lucide="archive" class="w-3.5 h-3.5"></i> Zip & Download Directory
                    </a>
                <?php endif; ?>
            </div>

            <!-- File Explorer Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/60 text-slate-400 uppercase font-semibold">
                            <th class="py-3.5 px-4">Name</th>
                            <th class="py-3.5 px-4">Type</th>
                            <th class="py-3.5 px-4">Size</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50 text-slate-300">
                        
                        <!-- Parent Level Navigate -->
                        <?php if ($dir !== realpath('/')): ?>
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="py-3 px-4 font-medium" colspan="4">
                                    <a href="?dir=<?= urlencode(dirname($dir)) ?>" class="flex items-center gap-2.5 text-indigo-400 hover:text-indigo-300">
                                        <i data-lucide="corner-left-up" class="w-4 h-4"></i> .. (Parent Directory)
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <!-- File Listing -->
                        <?php 
                        foreach ($files as $f): 
                            if ($f === '.' || $f === '..') continue; 
                            $path = $dir . DIRECTORY_SEPARATOR . $f;
                            $isFolder = is_dir($path);
                            $size = $isFolder ? '-' : formatSize(filesize($path));
                        ?>
                            <tr class="hover:bg-slate-800/40 transition group">
                                <td class="py-3 px-4 font-medium">
                                    <?php if ($isFolder): ?>
                                        <a href="?dir=<?= urlencode($path) ?>" class="flex items-center gap-2.5 text-slate-200 group-hover:text-indigo-400 transition">
                                            <i data-lucide="folder" class="w-4 h-4 text-amber-400 fill-amber-400/20"></i>
                                            <?= htmlspecialchars($f) ?>
                                        </a>
                                    <?php else: ?>
                                        <div class="flex items-center gap-2.5 text-slate-300">
                                            <i data-lucide="file-text" class="w-4 h-4 text-slate-400"></i>
                                            <?= htmlspecialchars($f) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold tracking-wide <?= $isFolder ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-slate-800 text-slate-400 border border-slate-700' ?>">
                                        <?= $isFolder ? 'Folder' : 'File' ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-400 font-mono text-[11px]"><?= $size ?></td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="?dir=<?= urlencode($dir) ?>&download=<?= urlencode($f) ?>" title="Download" class="p-1.5 rounded-lg border border-sky-500/20 bg-sky-500/10 text-sky-400 hover:bg-sky-500/20 transition">
                                            <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                        </a>
                                        <a href="?dir=<?= urlencode($dir) ?>&delete=<?= urlencode($f) ?>" onclick="return confirm('Purge <?= htmlspecialchars($f) ?> immediately?')" title="Delete" class="p-1.5 rounded-lg border border-rose-500/20 bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 transition">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>