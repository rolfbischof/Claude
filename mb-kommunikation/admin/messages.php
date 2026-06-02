<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
$auth->requireRole('viewer'); // minimum role to access admin

$auth->requireRole('editor');

$db        = Database::getInstance();
$csrfToken = $auth->getCsrfToken();

// ── Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $mid = (int) ($_POST['msg_id'] ?? 0);
        $db->update('contact_messages', ['is_read' => 1], ['id' => $mid]);
        header('Location: messages.php');
        exit;

    } elseif ($action === 'mark_unread') {
        $mid = (int) ($_POST['msg_id'] ?? 0);
        $db->update('contact_messages', ['is_read' => 0], ['id' => $mid]);
        header('Location: messages.php');
        exit;

    } elseif ($action === 'delete_msg') {
        $mid = (int) ($_POST['msg_id'] ?? 0);
        $db->delete('contact_messages', ['id' => $mid]);
        $auth->flash('Nachricht wurde gelöscht.', 'success');
        header('Location: messages.php');
        exit;

    } elseif ($action === 'mark_all_read') {
        $db->query('UPDATE contact_messages SET is_read = 1 WHERE is_read = 0');
        $auth->flash('Alle Nachrichten als gelesen markiert.', 'success');
        header('Location: messages.php');
        exit;
    }
}

// ── View single message ───────────────────────────────────────────────────
$viewMsg = null;
if (isset($_GET['view'])) {
    $viewMsg = $db->find('contact_messages', (int) $_GET['view']);
    if ($viewMsg && !$viewMsg['is_read']) {
        $db->update('contact_messages', ['is_read' => 1], ['id' => $viewMsg['id']]);
        $viewMsg['is_read'] = 1;
    }
}

// ── Load messages ─────────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$whereSQL = '1=1';
$params   = [];
if ($filter === 'unread') {
    $whereSQL .= ' AND is_read = 0';
} elseif ($filter === 'read') {
    $whereSQL .= ' AND is_read = 1';
}

$total      = (int) $db->fetchColumn("SELECT COUNT(*) FROM contact_messages WHERE $whereSQL", $params);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$messages = $db->fetchAll(
    "SELECT * FROM contact_messages WHERE $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

$unreadTotal = (int) $db->fetchColumn('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');

$pageTitle   = 'Nachrichten';
$currentPage = 'messages';
require_once 'layout.php';
?>

<?php if ($viewMsg): ?>
<!-- ── SINGLE MESSAGE VIEW ─────────────────────────────────────────────── -->
<div class="mb-5 flex items-center justify-between flex-wrap gap-3">
    <a href="messages.php" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Zurück zur Übersicht
    </a>
    <div class="flex gap-2">
        <a href="mailto:<?= htmlspecialchars($viewMsg['email']) ?>?subject=Re: <?= rawurlencode($viewMsg['subject'] ?: 'Ihre Anfrage') ?>"
           class="btn-primary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
            Antworten
        </a>
        <form method="post" class="inline" onsubmit="return confirm('Nachricht wirklich löschen?')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action"  value="delete_msg">
            <input type="hidden" name="msg_id"  value="<?= $viewMsg['id'] ?>">
            <button type="submit" class="btn-danger">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Löschen
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Message -->
    <div class="lg:col-span-2">
        <div class="card p-6">
            <div class="flex items-start gap-4 mb-6 pb-6 border-b border-gray-100">
                <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                    <span class="text-primary font-semibold text-lg"><?= htmlspecialchars(mb_substr($viewMsg['name'], 0, 1)) ?></span>
                </div>
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($viewMsg['subject'] ?: '(kein Betreff)') ?></h2>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1">
                        <span class="text-sm text-gray-500">
                            Von: <strong class="text-gray-700"><?= htmlspecialchars($viewMsg['name']) ?></strong>
                        </span>
                        <span class="text-sm text-gray-500">
                            <a href="mailto:<?= htmlspecialchars($viewMsg['email']) ?>" class="text-primary hover:underline"><?= htmlspecialchars($viewMsg['email']) ?></a>
                        </span>
                        <?php if ($viewMsg['phone']): ?>
                        <span class="text-sm text-gray-500">Tel: <?= htmlspecialchars($viewMsg['phone']) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-gray-400 mt-1"><?= format_date($viewMsg['created_at'], 'd.m.Y H:i') ?> Uhr</p>
                </div>
            </div>
            <div class="prose max-w-none text-gray-700 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($viewMsg['message']) ?></div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-4">
        <div class="card p-5">
            <h3 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wider">Absender</h3>
            <div class="space-y-2 text-sm">
                <div><span class="text-gray-400">Name:</span> <span class="text-gray-700 font-medium"><?= htmlspecialchars($viewMsg['name']) ?></span></div>
                <div><span class="text-gray-400">E-Mail:</span> <a href="mailto:<?= htmlspecialchars($viewMsg['email']) ?>" class="text-primary hover:underline"><?= htmlspecialchars($viewMsg['email']) ?></a></div>
                <?php if ($viewMsg['phone']): ?>
                <div><span class="text-gray-400">Tel:</span> <a href="tel:<?= htmlspecialchars($viewMsg['phone']) ?>" class="text-primary hover:underline"><?= htmlspecialchars($viewMsg['phone']) ?></a></div>
                <?php endif; ?>
                <div><span class="text-gray-400">Datum:</span> <span class="text-gray-700"><?= format_date($viewMsg['created_at'], 'd.m.Y H:i') ?></span></div>
                <div><span class="text-gray-400">Status:</span>
                    <span class="badge <?= $viewMsg['is_read'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-accent' ?>">
                        <?= $viewMsg['is_read'] ? 'Gelesen' : 'Ungelesen' ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="card p-5">
            <h3 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wider">Aktionen</h3>
            <div class="space-y-2">
                <a href="mailto:<?= htmlspecialchars($viewMsg['email']) ?>?subject=Re: <?= rawurlencode($viewMsg['subject'] ?: 'Ihre Anfrage') ?>"
                   class="btn-primary w-full justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    Per E-Mail antworten
                </a>
                <form method="post" class="inline w-full block">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action"  value="<?= $viewMsg['is_read'] ? 'mark_unread' : 'mark_read' ?>">
                    <input type="hidden" name="msg_id"  value="<?= $viewMsg['id'] ?>">
                    <button type="submit" class="btn-gray w-full justify-center">
                        <?= $viewMsg['is_read'] ? 'Als ungelesen markieren' : 'Als gelesen markieren' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ── LIST VIEW ──────────────────────────────────────────────────────────── -->

<!-- Filter tabs + actions -->
<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="flex gap-1 bg-white rounded-xl p-1 shadow-sm border border-gray-100">
        <?php
        $filterTabs = [
            'all'    => 'Alle (' . $total . ')',
            'unread' => 'Ungelesen (' . $unreadTotal . ')',
            'read'   => 'Gelesen',
        ];
        foreach ($filterTabs as $fval => $flabel):
        ?>
        <a href="messages.php?filter=<?= $fval ?>"
           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $filter === $fval ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
            <?= $flabel ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php if ($unreadTotal > 0): ?>
    <form method="post" class="inline">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn-gray text-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Alle als gelesen
        </button>
    </form>
    <?php endif; ?>
</div>

<div class="card overflow-hidden" x-data="{ modalMsg: null, showModal: false }">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="table-th">Absender</th>
                    <th class="table-th">Betreff</th>
                    <th class="table-th">Datum</th>
                    <th class="table-th">Status</th>
                    <th class="table-th text-right">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                <tr><td colspan="5" class="table-td text-center py-8 text-gray-400">Keine Nachrichten gefunden.</td></tr>
                <?php endif; ?>
                <?php foreach ($messages as $msg): ?>
                <tr class="table-tr <?= !$msg['is_read'] ? 'bg-blue-50/30' : '' ?>">
                    <td class="table-td">
                        <div class="flex items-center gap-3">
                            <?php if (!$msg['is_read']): ?>
                            <span class="w-2 h-2 rounded-full bg-accent flex-shrink-0"></span>
                            <?php else: ?>
                            <span class="w-2 h-2 flex-shrink-0"></span>
                            <?php endif; ?>
                            <div>
                                <p class="font-medium text-gray-800 <?= !$msg['is_read'] ? 'font-semibold' : '' ?>"><?= htmlspecialchars($msg['name']) ?></p>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars($msg['email']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="table-td">
                        <button @click="modalMsg=<?= htmlspecialchars(json_encode([
                            'id'      => (int)$msg['id'],
                            'name'    => $msg['name'],
                            'email'   => $msg['email'],
                            'phone'   => $msg['phone'] ?? '',
                            'subject' => $msg['subject'] ?? '',
                            'message' => $msg['message'],
                            'is_read' => (bool)$msg['is_read'],
                            'date'    => date('d.m.Y H:i', strtotime($msg['created_at'])),
                        ]), ENT_QUOTES) ?>; showModal=true"
                                class="text-left text-sm <?= !$msg['is_read'] ? 'font-semibold text-gray-800' : 'text-gray-700' ?> hover:text-primary transition-colors max-w-xs truncate block">
                            <?= htmlspecialchars($msg['subject'] ?: '(kein Betreff)') ?>
                        </button>
                        <p class="text-xs text-gray-400 truncate max-w-xs"><?= htmlspecialchars(truncate($msg['message'], 60)) ?></p>
                    </td>
                    <td class="table-td text-gray-500 text-xs whitespace-nowrap">
                        <?= format_date($msg['created_at'], 'd.m.Y H:i') ?>
                    </td>
                    <td class="table-td">
                        <span class="badge <?= $msg['is_read'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-accent' ?>">
                            <?= $msg['is_read'] ? 'Gelesen' : 'Neu' ?>
                        </span>
                    </td>
                    <td class="table-td">
                        <div class="flex items-center justify-end gap-1">
                            <!-- View full -->
                            <a href="messages.php?view=<?= $msg['id'] ?>"
                               class="p-1.5 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Ansehen">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <!-- Toggle read -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action"  value="<?= $msg['is_read'] ? 'mark_unread' : 'mark_read' ?>">
                                <input type="hidden" name="msg_id"  value="<?= $msg['id'] ?>">
                                <button type="submit"
                                        class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                        title="<?= $msg['is_read'] ? 'Als ungelesen' : 'Als gelesen' ?> markieren">
                                    <?php if ($msg['is_read']): ?>
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8"/></svg>
                                    <?php else: ?>
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <?php endif; ?>
                                </button>
                            </form>
                            <!-- Reply via mailto -->
                            <a href="mailto:<?= htmlspecialchars($msg['email']) ?>?subject=Re: <?= rawurlencode($msg['subject'] ?: 'Ihre Anfrage') ?>"
                               class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Antworten">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                            </a>
                            <!-- Delete -->
                            <form method="post" class="inline" onsubmit="return confirm('Nachricht löschen?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action"  value="delete_msg">
                                <input type="hidden" name="msg_id"  value="<?= $msg['id'] ?>">
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

    <?php if ($totalPages > 1): ?>
    <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-sm text-gray-500">Seite <?= $page ?> von <?= $totalPages ?></p>
        <div class="flex gap-1">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?= $p ?>&filter=<?= urlencode($filter) ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm <?= $p === $page ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick-view modal -->
    <div x-show="showModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="showModal=false">
        <div class="absolute inset-0 bg-gray-900/60" @click="showModal=false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-y-auto max-h-[85vh]" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800" x-text="modalMsg?.subject || '(kein Betreff)'"></h2>
                <button @click="showModal=false" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4 text-sm">
                    <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                        <span class="text-primary text-sm font-semibold" x-text="modalMsg?.name?.charAt(0)"></span>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800" x-text="modalMsg?.name"></p>
                        <p class="text-gray-400 text-xs" x-text="modalMsg?.email"></p>
                    </div>
                    <span class="ml-auto text-xs text-gray-400" x-text="modalMsg?.date"></span>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap" x-text="modalMsg?.message"></div>
                <div class="flex gap-3 mt-4">
                    <a :href="'mailto:' + modalMsg?.email + '?subject=Re: ' + encodeURIComponent(modalMsg?.subject || 'Ihre Anfrage')"
                       class="btn-primary flex-1 justify-center">Antworten</a>
                    <a :href="'messages.php?view=' + modalMsg?.id"
                       class="btn-gray flex-1 justify-center">Vollansicht</a>
                </div>
            </div>
        </div>
    </div>
</div><!-- /card -->

<?php endif; ?>

<?php require_once 'layout_end.php'; ?>
