<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('admin');

$db   = Database::getInstance();
$csrf = $auth->getCsrfToken();

// ── Helper: human-readable file size ─────────────────────────────────────────
function human_filesize(int $bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576,    2) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024,       2) . ' KB';
    return $bytes . ' B';
}

// ── Helper: recursive SQL dump via PDO (no exec/shell_exec) ──────────────────
function generateSqlDump(PDO $pdo, string $dbName): string
{
    $sql  = "-- mb Kommunikation + Events – Database Backup\n";
    $sql .= "-- Generated : " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database  : " . $dbName . "\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $createSQL = $createRow['Create Table'] ?? '';

        $sql .= "-- --------------------------------------------------------\n";
        $sql .= "-- Table: `{$table}`\n";
        $sql .= "-- --------------------------------------------------------\n\n";
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $createSQL . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            $sql .= "-- (no rows)\n\n";
            continue;
        }

        $columns = array_keys($rows[0]);
        $colList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));

        foreach ($rows as $row) {
            $values = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote((string) $v);
            }, array_values($row));
            $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(', ', $values) . ");\n";
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $sql;
}

// ── Helper: recursively add a directory to an open ZipArchive ────────────────
function addDirToZip(ZipArchive $zip, string $dir, string $prefix): void
{
    $dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) return;

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iter as $file) {
        if ($file->isFile()) {
            $localPath = $prefix . '/' . ltrim(substr($file->getPathname(), strlen($dir)), '/\\');
            $zip->addFile($file->getPathname(), $localPath);
        }
    }
}

// ── Download handler (GET ?download=<id>) ────────────────────────────────────
if (isset($_GET['download'])) {
    $bid    = (int) $_GET['download'];
    $backup = $db->find('backups', $bid);

    if ($backup) {
        $filePath = BACKUP_PATH . basename($backup['filename']);
        if (file_exists($filePath)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($backup['filename']) . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            readfile($filePath);
            exit;
        }
    }

    $auth->flash('Backup-Datei nicht gefunden.', 'error');
    header('Location: backup.php');
    exit;
}

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireCsrf();
    $action = $_POST['action'] ?? '';

    // ── backup_db ─────────────────────────────────────────────────────────────
    if ($action === 'backup_db') {

        if (!is_dir(BACKUP_PATH)) {
            @mkdir(BACKUP_PATH, 0755, true);
        }

        try {
            $timestamp = date('Ymd_His');
            $filename  = 'db_backup_' . $timestamp . '.sql.gz';
            $fullPath  = BACKUP_PATH . $filename;

            $sqlDump = generateSqlDump($db->getPdo(), DB_NAME);

            // Compress to .gz; fall back to plain .sql if gzencode unavailable
            if (function_exists('gzencode')) {
                $content = gzencode($sqlDump, 6);
                if ($content === false) throw new RuntimeException('gzencode fehlgeschlagen.');
                file_put_contents($fullPath, $content);
            } else {
                $filename = 'db_backup_' . $timestamp . '.sql';
                $fullPath = BACKUP_PATH . $filename;
                file_put_contents($fullPath, $sqlDump);
            }

            $fileSize = filesize($fullPath);

            $db->insert('backups', [
                'filename'    => $filename,
                'size_bytes'  => $fileSize,
                'backup_type' => 'database',
                'notes'       => '',
                'created_by'  => $auth->getUserId(),
            ]);

            $auth->flash(
                'Datenbank-Backup «' . $filename . '» (' . human_filesize((int) $fileSize) . ') wurde erstellt.',
                'success'
            );
        } catch (Throwable $e) {
            error_log('[Backup/db] ' . $e->getMessage());
            $auth->flash('Datenbank-Backup fehlgeschlagen: ' . $e->getMessage(), 'error');
        }

        header('Location: backup.php');
        exit;

    // ── backup_files ──────────────────────────────────────────────────────────
    } elseif ($action === 'backup_files') {

        if (!is_dir(BACKUP_PATH)) {
            @mkdir(BACKUP_PATH, 0755, true);
        }

        try {
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException('ZipArchive-Erweiterung ist nicht verfügbar.');
            }

            $timestamp = date('Ymd_His');
            $filename  = 'files_backup_' . $timestamp . '.zip';
            $fullPath  = BACKUP_PATH . $filename;

            $zip = new ZipArchive();
            if ($zip->open($fullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('ZIP-Datei konnte nicht erstellt werden: ' . $fullPath);
            }

            addDirToZip($zip, UPLOAD_PATH, 'uploads');
            $zip->close();

            $fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;

            $db->insert('backups', [
                'filename'    => $filename,
                'size_bytes'  => $fileSize,
                'backup_type' => 'files',
                'notes'       => '',
                'created_by'  => $auth->getUserId(),
            ]);

            $auth->flash(
                'Datei-Backup «' . $filename . '» (' . human_filesize((int) $fileSize) . ') wurde erstellt.',
                'success'
            );
        } catch (Throwable $e) {
            error_log('[Backup/files] ' . $e->getMessage());
            $auth->flash('Datei-Backup fehlgeschlagen: ' . $e->getMessage(), 'error');
        }

        header('Location: backup.php');
        exit;

    // ── backup_full ───────────────────────────────────────────────────────────
    } elseif ($action === 'backup_full') {

        if (!is_dir(BACKUP_PATH)) {
            @mkdir(BACKUP_PATH, 0755, true);
        }

        try {
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException('ZipArchive-Erweiterung ist nicht verfügbar.');
            }

            $timestamp = date('Ymd_His');
            $filename  = 'full_backup_' . $timestamp . '.zip';
            $fullPath  = BACKUP_PATH . $filename;

            $zip = new ZipArchive();
            if ($zip->open($fullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('ZIP-Datei konnte nicht erstellt werden: ' . $fullPath);
            }

            // 1. Embed SQL database dump directly into the ZIP
            $sqlDump = generateSqlDump($db->getPdo(), DB_NAME);
            $zip->addFromString('database/database_' . $timestamp . '.sql', $sqlDump);

            // 2. Add all uploaded files
            addDirToZip($zip, UPLOAD_PATH, 'uploads');

            $zip->close();

            $fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;

            $db->insert('backups', [
                'filename'    => $filename,
                'size_bytes'  => $fileSize,
                'backup_type' => 'full',
                'notes'       => '',
                'created_by'  => $auth->getUserId(),
            ]);

            $auth->flash(
                'Vollständiges Backup «' . $filename . '» (' . human_filesize((int) $fileSize) . ') wurde erstellt.',
                'success'
            );
        } catch (Throwable $e) {
            error_log('[Backup/full] ' . $e->getMessage());
            $auth->flash('Vollständiges Backup fehlgeschlagen: ' . $e->getMessage(), 'error');
        }

        header('Location: backup.php');
        exit;

    // ── delete_backup ─────────────────────────────────────────────────────────
    } elseif ($action === 'delete_backup') {

        $bid    = (int) ($_POST['backup_id'] ?? 0);
        $backup = $db->find('backups', $bid);

        if ($backup) {
            $filePath = BACKUP_PATH . basename($backup['filename']);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            $db->delete('backups', ['id' => $bid]);
            $auth->flash('Backup wurde gelöscht.', 'success');
        } else {
            $auth->flash('Backup nicht gefunden.', 'error');
        }

        header('Location: backup.php');
        exit;
    }
}

// ── Load backup list ──────────────────────────────────────────────────────────
$backups = $db->fetchAll(
    'SELECT b.*, u.username AS creator
     FROM backups b
     LEFT JOIN users u ON b.created_by = u.id
     ORDER BY b.created_at DESC'
);

// ── Storage stats ─────────────────────────────────────────────────────────────
$totalStorageBytes = 0;
$backupCount       = count($backups);
$lastBackupDate    = $backups[0]['created_at'] ?? null;

if (is_dir(BACKUP_PATH)) {
    foreach (new DirectoryIterator(BACKUP_PATH) as $f) {
        if ($f->isFile()) {
            $totalStorageBytes += $f->getSize();
        }
    }
}

// Storage limit for progress bar: warn above 500 MB
$storageLimit    = 500 * 1024 * 1024; // 500 MB
$storagePercent  = $storageLimit > 0
    ? min(100, round(($totalStorageBytes / $storageLimit) * 100))
    : 0;

$progressColor = $storagePercent >= 90 ? 'bg-red-500'
               : ($storagePercent >= 70 ? 'bg-yellow-400' : 'bg-primary');

// ── Type badge config ─────────────────────────────────────────────────────────
$typeLabels = [
    'database' => 'Datenbank',
    'files'    => 'Dateien',
    'full'     => 'Vollständig',
];
$typeColors = [
    'database' => 'bg-blue-100 text-blue-700',
    'files'    => 'bg-green-100 text-green-700',
    'full'     => 'bg-purple-100 text-purple-700',
];

$pageTitle   = 'Backup';
$currentPage = 'backup';
require_once 'layout.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ================================================================
         LEFT COLUMN: Create + Storage info
    ================================================================ -->
    <div class="space-y-5">

        <!-- SECTION 1: Backup erstellen -->
        <div class="card p-6">
            <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Backup erstellen
            </h3>

            <div class="space-y-3">

                <!-- Button 1: Datenbank-Backup -->
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action"     value="backup_db">
                    <button type="submit"
                            class="btn-primary w-full justify-center"
                            onclick="this.disabled=true; this.form.submit();">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7M9 11l3 3 3-3M12 3v11"/>
                        </svg>
                        Datenbank-Backup erstellen
                    </button>
                    <p class="text-xs text-gray-400 mt-1.5 pl-1">SQL-Export aller Tabellen (.sql.gz)</p>
                </form>

                <div class="border-t border-gray-100"></div>

                <!-- Button 2: Datei-Backup -->
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action"     value="backup_files">
                    <button type="submit"
                            class="btn-gray w-full justify-center"
                            onclick="this.disabled=true; this.form.submit();">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                        </svg>
                        Datei-Backup erstellen
                    </button>
                    <p class="text-xs text-gray-400 mt-1.5 pl-1">ZIP-Archiv des uploads/-Verzeichnisses</p>
                </form>

                <div class="border-t border-gray-100"></div>

                <!-- Button 3: Vollständiges Backup -->
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action"     value="backup_full">
                    <button type="submit"
                            class="btn-accent w-full justify-center"
                            onclick="this.disabled=true; this.form.submit();">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Vollständiges Backup
                    </button>
                    <p class="text-xs text-gray-400 mt-1.5 pl-1">Datenbank + Dateien in einem ZIP</p>
                </form>

            </div>

            <!-- ZipArchive availability notice -->
            <?php if (!class_exists('ZipArchive')): ?>
            <div class="mt-4 flex items-start gap-2 p-3 bg-red-50 border border-red-200 rounded-lg">
                <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.924-.833-2.694 0L3.34 16.5c-.77.833.193 2.5 1.732 2.5z"/>
                </svg>
                <p class="text-xs text-red-700">
                    ZipArchive-Erweiterung fehlt – Datei- und Vollbackup nicht möglich.
                    Nur Datenbank-Backup ist verfügbar.
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- SECTION 3: Storage-Anzeige -->
        <div class="card p-5">
            <h3 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wider">Speicherübersicht</h3>

            <div class="space-y-3 mb-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Backups gesamt</span>
                    <span class="font-semibold text-gray-800"><?= $backupCount ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Verwendeter Speicher</span>
                    <span class="font-semibold text-gray-800"><?= human_filesize($totalStorageBytes) ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Letzte Sicherung</span>
                    <span class="text-sm text-gray-600">
                        <?= $lastBackupDate ? format_date($lastBackupDate, 'd.m.Y H:i') : '–' ?>
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Backup-Ordner</span>
                    <code class="text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-600">/backup/files/</code>
                </div>
            </div>

            <!-- Progress bar -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-gray-500">Auslastung</span>
                    <span class="text-xs font-medium text-gray-600">
                        <?= human_filesize($totalStorageBytes) ?> / <?= human_filesize($storageLimit) ?>
                        (<?= $storagePercent ?>%)
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div class="<?= $progressColor ?> h-2 rounded-full transition-all duration-500"
                         style="width: <?= $storagePercent ?>%"></div>
                </div>
                <?php if ($storagePercent >= 90): ?>
                <p class="text-xs text-red-600 mt-1.5 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.924-.833-2.694 0L3.34 16.5c-.77.833.193 2.5 1.732 2.5z"/>
                    </svg>
                    Speicher fast voll – alte Backups löschen!
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Restore info -->
        <div class="card p-5 border-l-4 border-yellow-400">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h4 class="font-semibold text-sm text-gray-800 mb-1">Wiederherstellung</h4>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Backup herunterladen und die enthaltene <code class="bg-gray-100 px-1 rounded">.sql</code>-Datei
                        über phpMyAdmin oder die Hoststar-Datenbankverwaltung einspielen.
                        Upload-Dateien aus dem ZIP in das Verzeichnis <code class="bg-gray-100 px-1 rounded">uploads/</code> kopieren.
                    </p>
                </div>
            </div>
        </div>

    </div>

    <!-- ================================================================
         RIGHT COLUMN: SECTION 2 – Backup-Übersicht
    ================================================================ -->
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Backup-Verlauf</h3>
                <?php if ($backupCount > 0): ?>
                <span class="text-xs text-gray-400">
                    Gesamtgrösse: <strong class="text-gray-600"><?= human_filesize($totalStorageBytes) ?></strong>
                </span>
                <?php endif; ?>
            </div>

            <?php if (empty($backups)): ?>
            <div class="p-12 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                <p class="text-sm">Noch keine Backups vorhanden.</p>
                <p class="text-xs mt-1">Erstellen Sie Ihr erstes Backup mit den Schaltflächen links.</p>
            </div>
            <?php else: ?>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th">Dateiname</th>
                            <th class="table-th">Typ</th>
                            <th class="table-th">Größe</th>
                            <th class="table-th">Datum</th>
                            <th class="table-th">Erstellt von</th>
                            <th class="table-th text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup):
                            $filePath  = BACKUP_PATH . basename($backup['filename']);
                            $fileExists = file_exists($filePath);
                            $badgeClass = $typeColors[$backup['backup_type']] ?? 'bg-gray-100 text-gray-600';
                            $typeLabel  = $typeLabels[$backup['backup_type']] ?? $backup['backup_type'];
                        ?>
                        <tr class="table-tr">

                            <!-- Dateiname -->
                            <td class="table-td">
                                <div class="flex items-center gap-2">
                                    <?php if ($fileExists): ?>
                                    <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <?php else: ?>
                                    <svg class="w-4 h-4 text-red-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.924-.833-2.694 0L3.34 16.5c-.77.833.193 2.5 1.732 2.5z"/>
                                    </svg>
                                    <?php endif; ?>
                                    <span class="text-xs font-mono text-gray-600 break-all">
                                        <?= htmlspecialchars($backup['filename']) ?>
                                    </span>
                                </div>
                                <?php if (!$fileExists): ?>
                                <p class="text-xs text-red-400 mt-0.5 ml-6">Datei fehlt auf Disk</p>
                                <?php endif; ?>
                            </td>

                            <!-- Typ -->
                            <td class="table-td">
                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($typeLabel) ?>
                                </span>
                            </td>

                            <!-- Größe -->
                            <td class="table-td text-gray-500 whitespace-nowrap">
                                <?= $backup['size_bytes'] ? human_filesize((int) $backup['size_bytes']) : '–' ?>
                            </td>

                            <!-- Datum -->
                            <td class="table-td text-gray-500 text-xs whitespace-nowrap">
                                <?= format_date($backup['created_at'], 'd.m.Y H:i') ?>
                            </td>

                            <!-- Erstellt von -->
                            <td class="table-td text-gray-500 text-xs">
                                <?= $backup['creator']
                                    ? htmlspecialchars('@' . $backup['creator'])
                                    : '–' ?>
                            </td>

                            <!-- Aktionen -->
                            <td class="table-td">
                                <div class="flex items-center justify-end gap-1">

                                    <!-- Download -->
                                    <?php if ($fileExists): ?>
                                    <a href="backup.php?download=<?= (int) $backup['id'] ?>"
                                       class="p-1.5 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors"
                                       title="Herunterladen">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                    </a>
                                    <?php else: ?>
                                    <span class="p-1.5 text-gray-200 cursor-not-allowed" title="Datei nicht vorhanden">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                    </span>
                                    <?php endif; ?>

                                    <!-- Delete -->
                                    <form method="post" class="inline"
                                          onsubmit="return confirm('Backup «<?= htmlspecialchars(addslashes($backup['filename'])) ?>» wirklich löschen?')">
                                        <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf) ?>">
                                        <input type="hidden" name="action"      value="delete_backup">
                                        <input type="hidden" name="backup_id"   value="<?= (int) $backup['id'] ?>">
                                        <button type="submit"
                                                class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Löschen">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>

                    <!-- Footer: total size -->
                    <tfoot class="bg-gray-50 border-t border-gray-100">
                        <tr>
                            <td class="table-td text-xs text-gray-400" colspan="2">
                                <?= $backupCount ?> Backup(s)
                            </td>
                            <td class="table-td text-xs font-semibold text-gray-600" colspan="4">
                                Gesamt: <?= human_filesize($totalStorageBytes) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php endif; ?>
        </div>
    </div>

</div><!-- /grid -->

<?php require_once 'layout_end.php'; ?>
