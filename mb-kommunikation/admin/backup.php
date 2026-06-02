<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
$auth->requireRole('viewer'); // minimum role to access admin

$auth->requireRole('admin');

$db        = Database::getInstance();
$csrfToken = $auth->getCsrfToken();
$backupDir = BACKUP_PATH;

// ── Pure-PHP database export (no exec/shell_exec) ─────────────────────────
function generateSqlDump(PDO $pdo, string $dbName): string
{
    $sql  = "-- mb Kommunikation + Events – Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: " . $dbName . "\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    // Fetch all table names
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // DROP + CREATE TABLE
        $createRow = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $createSQL = $createRow['Create Table'] ?? '';
        $sql .= "-- Table: $table\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $createSQL . ";\n\n";

        // Data rows
        $rowStmt = $pdo->query("SELECT * FROM `$table`");
        $rows    = $rowStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            $sql .= "-- (no data)\n\n";
            continue;
        }

        $columns = array_keys($rows[0]);
        $colList = implode(', ', array_map(fn($c) => "`$c`", $columns));

        foreach ($rows as $row) {
            $values = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote((string) $v);
            }, array_values($row));
            $sql .= "INSERT INTO `$table` ($colList) VALUES (" . implode(', ', $values) . ");\n";
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $sql;
}

function createZip(array $filesToAdd, string $zipPath): bool
{
    if (!class_exists('ZipArchive')) return false;
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;
    foreach ($filesToAdd as $localName => $filePath) {
        if (is_file($filePath)) $zip->addFile($filePath, $localName);
    }
    $zip->close();
    return true;
}

function addDirectoryToZip(ZipArchive $zip, string $dir, string $prefix): void
{
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if ($file->isFile()) {
            $localPath = $prefix . substr($file->getPathname(), strlen($dir));
            $zip->addFile($file->getPathname(), $localPath);
        }
    }
}

// ── Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireCsrf();
    $action = $_POST['action'] ?? '';

    // ── Create backup ──────────────────────────────────────────────────────
    if ($action === 'create_backup') {
        $backupType = $_POST['backup_type'] ?? 'database';
        $notes      = trim($_POST['notes'] ?? '');

        if (!in_array($backupType, ['full','database','files'])) {
            $auth->flash('Ungültiger Backup-Typ.', 'error');
            header('Location: backup.php');
            exit;
        }

        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $filename  = 'backup_' . $backupType . '_' . $timestamp . '.zip';
        $zipPath   = $backupDir . $filename;
        $rootPath  = dirname(__DIR__);

        try {
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException('ZipArchive-Erweiterung ist nicht verfügbar.');
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('ZIP-Datei konnte nicht erstellt werden.');
            }

            // Always add database dump
            if (in_array($backupType, ['full','database'])) {
                $pdo     = $db->getPdo();
                $dbName  = DB_NAME;
                $sqlDump = generateSqlDump($pdo, $dbName);
                $zip->addFromString('database_' . $timestamp . '.sql', $sqlDump);
            }

            // Add files
            if (in_array($backupType, ['full','files'])) {
                $foldersToBackup = [
                    'uploads' => $rootPath . '/uploads',
                    'config'  => $rootPath . '/config',
                ];
                foreach ($foldersToBackup as $prefix => $dir) {
                    if (is_dir($dir)) addDirectoryToZip($zip, $dir, $prefix);
                }
            }

            $zip->close();
            $fileSize = file_exists($zipPath) ? filesize($zipPath) : 0;

            $db->insert('backups', [
                'filename'    => $filename,
                'size_bytes'  => $fileSize,
                'backup_type' => $backupType,
                'notes'       => $notes,
                'created_by'  => $auth->getUserId(),
            ]);

            $auth->flash('Backup «'.$filename.'» ('.formatBytes($fileSize).') wurde erstellt.', 'success');
        } catch (Throwable $e) {
            error_log('[Backup] Error: ' . $e->getMessage());
            $auth->flash('Backup-Fehler: ' . $e->getMessage(), 'error');
        }

        header('Location: backup.php');
        exit;

    // ── Delete backup ──────────────────────────────────────────────────────
    } elseif ($action === 'delete_backup') {
        $bid    = (int) ($_POST['backup_id'] ?? 0);
        $backup = $db->find('backups', $bid);
        if ($backup) {
            $file = $backupDir . basename($backup['filename']);
            if (file_exists($file)) @unlink($file);
            $db->delete('backups', ['id' => $bid]);
            $auth->flash('Backup wurde gelöscht.', 'success');
        }
        header('Location: backup.php');
        exit;
    }
}

// ── Download ───────────────────────────────────────────────────────────────
if (isset($_GET['download'])) {
    $bid    = (int) $_GET['download'];
    $backup = $db->find('backups', $bid);
    if ($backup) {
        $file = $backupDir . basename($backup['filename']);
        if (file_exists($file)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($backup['filename']) . '"');
            header('Content-Length: ' . filesize($file));
            header('Cache-Control: no-cache, must-revalidate');
            readfile($file);
            exit;
        }
    }
    $auth->flash('Backup-Datei nicht gefunden.', 'error');
    header('Location: backup.php');
    exit;
}

// ── Load backups ───────────────────────────────────────────────────────────
$backups = $db->fetchAll(
    'SELECT b.*, u.username AS creator
     FROM backups b
     LEFT JOIN users u ON b.created_by = u.id
     ORDER BY b.created_at DESC'
);

// Storage usage
function formatBytes(int $bytes, int $precision = 2): string
{
    if ($bytes === 0) return '0 B';
    $units = ['B','KB','MB','GB'];
    $exp   = (int) floor(log($bytes, 1024));
    return round($bytes / pow(1024, $exp), $precision) . ' ' . $units[$exp];
}

$totalStorageBytes = 0;
if (is_dir($backupDir)) {
    foreach (new DirectoryIterator($backupDir) as $f) {
        if ($f->isFile()) $totalStorageBytes += $f->getSize();
    }
}

$typeLabels = ['full' => 'Vollständig', 'database' => 'Datenbank', 'files' => 'Dateien'];
$typeColors = ['full' => 'bg-purple-100 text-purple-700', 'database' => 'bg-blue-100 text-blue-700', 'files' => 'bg-green-100 text-green-700'];

$pageTitle   = 'Backup';
$currentPage = 'backup';
require_once 'layout.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Left: Create backup + restore -->
    <div class="space-y-5">

        <!-- Create backup -->
        <div class="card p-6">
            <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Neues Backup
            </h3>
            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action"     value="create_backup">
                <div>
                    <label class="form-label">Backup-Typ</label>
                    <div class="space-y-2">
                        <label class="flex items-start gap-3 p-3 rounded-xl border-2 border-primary bg-primary/5 cursor-pointer hover:bg-primary/10 transition-colors">
                            <input type="radio" name="backup_type" value="database" checked class="mt-0.5 accent-primary">
                            <div>
                                <p class="font-medium text-sm text-gray-800">Nur Datenbank</p>
                                <p class="text-xs text-gray-500">SQL-Export aller Tabellen</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 p-3 rounded-xl border-2 border-gray-200 cursor-pointer hover:border-primary hover:bg-primary/5 transition-colors">
                            <input type="radio" name="backup_type" value="files" class="mt-0.5 accent-primary">
                            <div>
                                <p class="font-medium text-sm text-gray-800">Nur Dateien</p>
                                <p class="text-xs text-gray-500">uploads/ und config/</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 p-3 rounded-xl border-2 border-gray-200 cursor-pointer hover:border-primary hover:bg-primary/5 transition-colors">
                            <input type="radio" name="backup_type" value="full" class="mt-0.5 accent-primary">
                            <div>
                                <p class="font-medium text-sm text-gray-800">Vollständig</p>
                                <p class="text-xs text-gray-500">Datenbank + Dateien</p>
                            </div>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="form-label">Notiz (optional)</label>
                    <input type="text" name="notes" class="form-input" placeholder="z.B. Vor Update">
                </div>
                <button type="submit" class="btn-primary w-full justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Backup erstellen
                </button>
            </form>
        </div>

        <!-- Storage stats -->
        <div class="card p-5">
            <h3 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wider">Speicher</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Backups gesamt</span>
                    <span class="font-semibold text-gray-800"><?= count($backups) ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Verwendeter Speicher</span>
                    <span class="font-semibold text-gray-800"><?= formatBytes($totalStorageBytes) ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Backup-Ordner</span>
                    <code class="text-xs bg-gray-100 px-2 py-0.5 rounded">/backup/files/</code>
                </div>
                <?php if (class_exists('ZipArchive')): ?>
                <div class="flex items-center gap-1 text-xs text-green-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    ZipArchive verfügbar
                </div>
                <?php else: ?>
                <div class="flex items-center gap-1 text-xs text-red-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.924-.833-2.694 0L3.34 16.5c-.77.833.193 2.5 1.732 2.5z"/></svg>
                    ZipArchive fehlt – Backup nicht möglich
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Restore notice -->
        <div class="card p-5 border-l-4 border-yellow-400">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.924-.833-2.694 0L3.34 16.5c-.77.833.193 2.5 1.732 2.5z"/></svg>
                <div>
                    <h4 class="font-semibold text-sm text-gray-800 mb-1">Wiederherstellung</h4>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Um eine Sicherung wiederherzustellen, laden Sie die ZIP-Datei herunter und stellen Sie die Datenbank-SQL-Datei über phpMyAdmin oder die Hoststar-Datenbankverwaltung wieder her.
                    </p>
                </div>
            </div>
        </div>

    </div>

    <!-- Right: Backup list -->
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Backup-Verlauf</h3>
            </div>
            <?php if (empty($backups)): ?>
            <div class="p-12 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <p class="text-sm">Noch keine Backups vorhanden.</p>
                <p class="text-xs mt-1">Erstellen Sie Ihr erstes Backup mit dem Formular links.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th">Dateiname</th>
                            <th class="table-th">Typ</th>
                            <th class="table-th">Grösse</th>
                            <th class="table-th">Erstellt</th>
                            <th class="table-th">Notiz</th>
                            <th class="table-th text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup):
                        $fileExists = file_exists($backupDir . basename($backup['filename']));
                        ?>
                        <tr class="table-tr">
                            <td class="table-td">
                                <div class="flex items-center gap-2">
                                    <?php if (!$fileExists): ?>
                                    <svg class="w-4 h-4 text-red-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.924-.833-2.694 0L3.34 16.5c-.77.833.193 2.5 1.732 2.5z"/></svg>
                                    <?php else: ?>
                                    <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <?php endif; ?>
                                    <span class="text-xs text-gray-600 font-mono"><?= htmlspecialchars($backup['filename']) ?></span>
                                </div>
                                <?php if ($backup['creator']): ?>
                                <p class="text-xs text-gray-400 mt-0.5 ml-6">von @<?= htmlspecialchars($backup['creator']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="table-td">
                                <span class="badge <?= $typeColors[$backup['backup_type']] ?? 'bg-gray-100 text-gray-600' ?>">
                                    <?= htmlspecialchars($typeLabels[$backup['backup_type']] ?? $backup['backup_type']) ?>
                                </span>
                            </td>
                            <td class="table-td text-gray-500">
                                <?= $backup['size_bytes'] ? formatBytes((int)$backup['size_bytes']) : '–' ?>
                            </td>
                            <td class="table-td text-gray-500 text-xs whitespace-nowrap">
                                <?= format_date($backup['created_at'], 'd.m.Y H:i') ?>
                            </td>
                            <td class="table-td text-gray-500 text-xs max-w-[160px] truncate">
                                <?= htmlspecialchars($backup['notes'] ?: '–') ?>
                            </td>
                            <td class="table-td">
                                <div class="flex items-center justify-end gap-1">
                                    <?php if ($fileExists): ?>
                                    <a href="backup.php?download=<?= $backup['id'] ?>"
                                       class="p-1.5 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Herunterladen">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </a>
                                    <?php endif; ?>
                                    <form method="post" class="inline" onsubmit="return confirm('Backup wirklich löschen?')">
                                        <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action"     value="delete_backup">
                                        <input type="hidden" name="backup_id"  value="<?= $backup['id'] ?>">
                                        <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Löschen">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'layout_end.php'; ?>
