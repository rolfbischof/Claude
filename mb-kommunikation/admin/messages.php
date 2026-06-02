<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('editor');

$db        = Database::getInstance();
$csrfToken = $auth->getCsrfToken();

// ── Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireCsrf();
    $action = $_POST['action'] ?? '';
    $mid    = (int) ($_POST['msg_id'] ?? 0);

    if ($action === 'mark_read' && $mid > 0) {
        $db->update('contact_messages', ['is_read' => 1], ['id' => $mid]);
        $auth->flash('Nachricht als gelesen markiert.', 'success');
        $qs = http_build_query(array_filter(['filter' => $_POST['filter'] ?? '', 'page' => $_POST['page'] ?? '']));
        header('Location: messages.php' . ($qs ? '?' . $qs : ''));
        exit;

    } elseif ($action === 'mark_unread' && $mid > 0) {
        $db->update('contact_messages', ['is_read' => 0], ['id' => $mid]);
        $auth->flash('Nachricht als ungelesen markiert.', 'success');
        $qs = http_build_query(array_filter(['filter' => $_POST['filter'] ?? '', 'page' => $_POST['page'] ?? '']));
        header('Location: messages.php' . ($qs ? '?' . $qs : ''));
        exit;

    } elseif ($action === 'delete_msg' && $mid > 0) {
        $db->delete('contact_messages', ['id' => $mid]);
        $auth->flash('Nachricht wurde gelöscht.', 'success');
        $qs = http_build_query(array_filter(['filter' => $_POST['filter'] ?? '', 'page' => $_POST['page'] ?? '']));
        header('Location: messages.php' . ($qs ? '?' . $qs : ''));
        exit;

    } elseif ($action === 'mark_all_read') {
        $db->query('UPDATE contact_messages SET is_read = 1 WHERE is_read = 0');
        $auth->flash('Alle Nachrichten als gelesen markiert.', 'success');
        header('Location: messages.php');
        exit;
    }
}

// ── Auto-mark-as-read when viewing a single message via ?view=ID ──────────
$viewMsg = null;
if (isset($_GET['view']) && (int) $_GET['view'] > 0) {
    $viewMsg = $db->fetchOne('SELECT * FROM contact_messages WHERE id = ?', [(int) $_GET['view']]);
    if ($viewMsg && !$viewMsg['is_read']) {
        $db->update('contact_messages', ['is_read' => 1], ['id' => $viewMsg['id']]);
        $viewMsg['is_read'] = 1;
    }
}

// ── Filter / pagination ───────────────────────────────────────────────────
$filter  = in_array($_GET['filter'] ?? '', ['unread', 'read', 'all']) ? $_GET['filter'] : 'all';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = ADMIN_ITEMS_PER_PAGE; // 20

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

// Counts for tabs
$unreadTotal = (int) $db->fetchColumn('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
$readTotal   = (int) $db->fetchColumn('SELECT COUNT(*) FROM contact_messages WHERE is_read = 1');
$allTotal    = (int) $db->fetchColumn('SELECT COUNT(*) FROM contact_messages');

$pageTitle   = 'Nachrichten';
$currentPage = 'messages';
require_once 'layout.php';
?>

<?php if ($viewMsg): ?>
<!-- ── SINGLE MESSAGE VIEW ────────────────────────────────────────────────── -->
<div class="mb-5 flex items-center justify-between flex-wrap gap-3">
    <a href="messages.php?filter=<?= urlencode($filter) ?>"
       class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zur Übersicht
    </a>
    <div class="flex gap-2 flex-wrap">
        <a href="mailto:<?= htmlspecialchars($viewMsg['email']) ?>?subject=Re:+<?= rawurlencode($viewMsg['subject'] ?: 'Ihre Anfrage') ?>"
           class="btn-primary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
            </svg>
            Antworten
        </a>
        <form method="post" class="inline">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action"   value="<?= $viewMsg['is_read'] ? 'mark_unread' : 'mark_read' ?>">
            <input type="hidden" name="msg_id"   value="<?= (int) $viewMsg['id'] ?>">
            <input type="hidden" name="filter"   value="<?= htmlspecialchars($filter) ?>">
            <button type="submit" class="btn-gray">
                <?php if ($viewMsg['is_read']): ?>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8"/>
                </svg>
                Als ungelesen markieren
                <?php else: ?>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Als gelesen markieren
                <?php endif; ?>
            </button>
        </form>
        <form method="post" class="inline"
              onsubmit="return confirm('Nachricht wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action"   value="delete_msg">
            <input type="hidden" name="msg_id"   value="<?= (int) $viewMsg['id'] ?>">
            <input type="hidden" name="filter"   value="<?= htmlspecialchars($filter) ?>">
            <button type="submit" class="btn-danger">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Löschen
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Message body -->
    <div class="lg:col-span-2">
        <div class="card p-6">
            <!-- Header -->
            <div class="flex items-start gap-4 pb-5 mb-5 border-b border-gray-100">
                <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                    <span class="text-primary font-bold text-lg"><?= htmlspecialchars(mb_strtoupper(mb_substr($viewMsg['name'], 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start gap-2 flex-wrap">
                        <h2 class="text-lg font-semibold text-gray-800 leading-tight">
                            <?= htmlspecialchars($viewMsg['subject'] ?: '(kein Betreff)') ?>
                        </h2>
                        <?php if (!$viewMsg['is_read']): ?>
                        <span class="badge bg-accent text-white flex-shrink-0">Neu</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1.5">
                        <span class="text-sm text-gray-500">
                            Von: <strong class="text-gray-700"><?= htmlspecialchars($viewMsg['name']) ?></strong>
                        </span>
                        <span class="text-sm">
                            <a href="mailto:<?= htmlspecialchars($viewMsg['email']) ?>"
                               class="text-primary hover:underline"><?= htmlspecialchars($viewMsg['email']) ?></a>
                        </span>
                        <?php if (!empty($viewMsg['phone'])): ?>
                        <span class="text-sm text-gray-500">
                            Tel:
                            <a href="tel:<?= htmlspecialchars($viewMsg['phone']) ?>"
                               class="text-primary hover:underline"><?= htmlspecialchars($viewMsg['phone']) ?></a>
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        <?= format_date($viewMsg['created_at'], 'd.m.Y H:i') ?> Uhr
                        &nbsp;&middot;&nbsp; ID #<?= (int) $viewMsg['id'] ?>
                    </p>
                </div>
            </div>

            <!-- Message text -->
            <div class="text-gray-700 text-sm leading-relaxed whitespace-pre-wrap">
                <?= htmlspecialchars($viewMsg['message']) ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-4">

        <!-- Sender info -->
        <div class="card p-5">
            <h3 class="font-semibold text-gray-700 mb-3 text-xs uppercase tracking-wider">Absender</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex gap-2">
                    <dt class="text-gray-400 w-16 flex-shrink-0">Name</dt>
                    <dd class="text-gray-700 font-medium"><?= htmlspecialchars($viewMsg['name']) ?></dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-gray-400 w-16 flex-shrink-0">E-Mail</dt>
                    <dd class="min-w-0">
                        <a href="mailto:<?= htmlspecialchars($viewMsg['email']) ?>"
                           class="text-primary hover:underline text-sm break-all"><?= htmlspecialchars($viewMsg['email']) ?></a>
                    </dd>
                </div>
                <?php if (!empty($viewMsg['phone'])): ?>
                <div class="flex gap-2">
                    <dt class="text-gray-400 w-16 flex-shrink-0">Tel</dt>
                    <dd>
                        <a href="tel:<?= htmlspecialchars($viewMsg['phone']) ?>"
                           class="text-primary hover:underline"><?= htmlspecialchars($viewMsg['phone']) ?></a>
                    </dd>
                </div>
                <?php endif; ?>
                <div class="flex gap-2">
                    <dt class="text-gray-400 w-16 flex-shrink-0">Datum</dt>
                    <dd class="text-gray-700"><?= format_date($viewMsg['created_at'], 'd.m.Y H:i') ?></dd>
                </div>
                <div class="flex gap-2">
                    <dt class="text-gray-400 w-16 flex-shrink-0">Status</dt>
                    <dd>
                        <span class="badge <?= $viewMsg['is_read'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-accent' ?>">
                            <?= $viewMsg['is_read'] ? 'Gelesen' : 'Ungelesen' ?>
                        </span>
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Quick actions -->
        <div class="card p-5">
            <h3 class="font-semibold text-gray-700 mb-3 text-xs uppercase tracking-wider">Aktionen</h3>
            <div class="space-y-2">
                <a href="mailto:<?= htmlspecialchars($viewMsg['email']) ?>?subject=Re:+<?= rawurlencode($viewMsg['subject'] ?: 'Ihre Anfrage') ?>"
                   class="btn-primary w-full justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                    Per E-Mail antworten
                </a>
                <form method="post" class="block">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action"   value="<?= $viewMsg['is_read'] ? 'mark_unread' : 'mark_read' ?>">
                    <input type="hidden" name="msg_id"   value="<?= (int) $viewMsg['id'] ?>">
                    <input type="hidden" name="filter"   value="<?= htmlspecialchars($filter) ?>">
                    <button type="submit" class="btn-gray w-full justify-center">
                        <?= $viewMsg['is_read'] ? 'Als ungelesen markieren' : 'Als gelesen markieren' ?>
                    </button>
                </form>
                <form method="post" class="block"
                      onsubmit="return confirm('Nachricht wirklich löschen?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action"   value="delete_msg">
                    <input type="hidden" name="msg_id"   value="<?= (int) $viewMsg['id'] ?>">
                    <input type="hidden" name="filter"   value="<?= htmlspecialchars($filter) ?>">
                    <button type="submit" class="btn-danger w-full justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Nachricht löschen
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<?php else: ?>
<!-- ── LIST VIEW ──────────────────────────────────────────────────────────── -->

<!-- Filter tabs + bulk action -->
<div class="flex items-center justify-between mb-5 flex-wrap gap-3">

    <!-- Filter tabs -->
    <div class="flex gap-1 bg-white rounded-xl p-1 shadow-sm border border-gray-100 flex-wrap">
        <a href="messages.php?filter=all"
           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors inline-flex items-center gap-1.5
                  <?= $filter === 'all' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
            Alle
            <span class="<?= $filter === 'all' ? 'bg-white/20' : 'bg-gray-100' ?> text-xs rounded-full px-1.5 py-0.5 font-semibold leading-none">
                <?= $allTotal ?>
            </span>
        </a>
        <a href="messages.php?filter=unread"
           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors inline-flex items-center gap-1.5
                  <?= $filter === 'unread' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
            Ungelesen
            <?php if ($unreadTotal > 0): ?>
            <span class="<?= $filter === 'unread' ? 'bg-white/20' : 'bg-accent' ?> text-white text-xs rounded-full px-1.5 py-0.5 font-bold leading-none">
                <?= $unreadTotal ?>
            </span>
            <?php endif; ?>
        </a>
        <a href="messages.php?filter=read"
           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors inline-flex items-center gap-1.5
                  <?= $filter === 'read' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
            Gelesen
            <span class="<?= $filter === 'read' ? 'bg-white/20' : 'bg-gray-100' ?> text-xs rounded-full px-1.5 py-0.5 font-semibold leading-none">
                <?= $readTotal ?>
            </span>
        </a>
    </div>

    <!-- Mark-all-read -->
    <?php if ($unreadTotal > 0): ?>
    <form method="post" class="inline"
          onsubmit="return confirm('Alle <?= $unreadTotal ?> ungelesene Nachrichten als gelesen markieren?')">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn-gray text-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Alle als gelesen
        </button>
    </form>
    <?php endif; ?>
</div>

<!-- Messages table with Alpine.js modal -->
<div class="card overflow-hidden"
     x-data="{
         showModal: false,
         msg: {},
         openModal(data) {
             this.msg = data;
             this.showModal = true;
         }
     }">

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="table-th w-12">#</th>
                    <th class="table-th">Absender</th>
                    <th class="table-th">Betreff / Vorschau</th>
                    <th class="table-th whitespace-nowrap">Datum</th>
                    <th class="table-th">Status</th>
                    <th class="table-th text-right">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                <tr>
                    <td colspan="6" class="table-td text-center py-12">
                        <div class="flex flex-col items-center gap-3 text-gray-400">
                            <svg class="w-10 h-10 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-sm">Keine Nachrichten gefunden.</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($messages as $msg): ?>
                <?php
                $isUnread  = !(bool) $msg['is_read'];
                $rowBg     = $isUnread ? 'bg-blue-50/40' : '';
                $msgData   = json_encode([
                    'id'      => (int) $msg['id'],
                    'name'    => $msg['name'],
                    'email'   => $msg['email'],
                    'phone'   => $msg['phone'] ?? '',
                    'subject' => $msg['subject'] ?? '',
                    'message' => $msg['message'],
                    'is_read' => (bool) $msg['is_read'],
                    'date'    => format_date($msg['created_at'], 'd.m.Y H:i'),
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                ?>
                <tr class="table-tr <?= $rowBg ?> cursor-pointer"
                    @click="openModal(<?= htmlspecialchars($msgData, ENT_QUOTES) ?>)">

                    <!-- ID -->
                    <td class="table-td text-gray-400 text-xs" @click.stop>
                        <div class="flex items-center gap-1.5">
                            <?php if ($isUnread): ?>
                            <span class="w-2 h-2 rounded-full bg-accent flex-shrink-0" title="Ungelesen"></span>
                            <?php else: ?>
                            <span class="w-2 h-2 flex-shrink-0"></span>
                            <?php endif; ?>
                            <a href="messages.php?view=<?= (int) $msg['id'] ?>&filter=<?= urlencode($filter) ?>"
                               class="hover:text-primary transition-colors">#<?= (int) $msg['id'] ?></a>
                        </div>
                    </td>

                    <!-- Name / Email -->
                    <td class="table-td">
                        <p class="font-medium text-gray-800 <?= $isUnread ? 'font-semibold' : '' ?>">
                            <?= htmlspecialchars($msg['name']) ?>
                        </p>
                        <p class="text-xs text-gray-400"><?= htmlspecialchars($msg['email']) ?></p>
                    </td>

                    <!-- Subject / Preview -->
                    <td class="table-td max-w-xs">
                        <p class="text-sm <?= $isUnread ? 'font-semibold text-gray-800' : 'text-gray-700' ?> truncate">
                            <?= htmlspecialchars($msg['subject'] ?: '(kein Betreff)') ?>
                        </p>
                        <p class="text-xs text-gray-400 truncate">
                            <?= htmlspecialchars(truncate($msg['message'], 65)) ?>
                        </p>
                    </td>

                    <!-- Date -->
                    <td class="table-td text-gray-500 text-xs whitespace-nowrap">
                        <?= format_date($msg['created_at'], 'd.m.Y') ?><br>
                        <span class="text-gray-400"><?= format_date($msg['created_at'], 'H:i') ?></span>
                    </td>

                    <!-- Status badge -->
                    <td class="table-td" @click.stop>
                        <form method="post" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action"   value="<?= $isUnread ? 'mark_read' : 'mark_unread' ?>">
                            <input type="hidden" name="msg_id"   value="<?= (int) $msg['id'] ?>">
                            <input type="hidden" name="filter"   value="<?= htmlspecialchars($filter) ?>">
                            <input type="hidden" name="page"     value="<?= $page ?>">
                            <button type="submit" title="Klicken zum <?= $isUnread ? 'als gelesen' : 'als ungelesen' ?> markieren"
                                    class="badge cursor-pointer transition-colors
                                           <?= $isUnread
                                               ? 'bg-red-100 text-accent hover:bg-red-200'
                                               : 'bg-green-100 text-green-700 hover:bg-green-200' ?>">
                                <?= $isUnread ? 'Neu' : 'Gelesen' ?>
                            </button>
                        </form>
                    </td>

                    <!-- Actions -->
                    <td class="table-td" @click.stop>
                        <div class="flex items-center justify-end gap-1">

                            <!-- Full view -->
                            <a href="messages.php?view=<?= (int) $msg['id'] ?>&filter=<?= urlencode($filter) ?>"
                               title="Vollansicht"
                               class="p-1.5 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>

                            <!-- Reply -->
                            <a href="mailto:<?= htmlspecialchars($msg['email']) ?>?subject=Re:+<?= rawurlencode($msg['subject'] ?: 'Ihre Anfrage') ?>"
                               title="Per E-Mail antworten"
                               class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                </svg>
                            </a>

                            <!-- Delete -->
                            <form method="post" class="inline"
                                  onsubmit="return confirm('Nachricht #<?= (int) $msg['id'] ?> wirklich löschen?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action"   value="delete_msg">
                                <input type="hidden" name="msg_id"   value="<?= (int) $msg['id'] ?>">
                                <input type="hidden" name="filter"   value="<?= htmlspecialchars($filter) ?>">
                                <input type="hidden" name="page"     value="<?= $page ?>">
                                <button type="submit" title="Löschen"
                                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>

                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-500">
            Seite <?= $page ?> von <?= $totalPages ?>
            <span class="text-gray-400">(<?= $total ?> Nachrichten)</span>
        </p>
        <div class="flex gap-1 flex-wrap">
            <?php if ($page > 1): ?>
            <a href="?filter=<?= urlencode($filter) ?>&page=<?= $page - 1 ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm text-gray-600 hover:bg-gray-100">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <?php endif; ?>

            <?php
            // Show up to 7 page links with ellipsis
            $window = 2;
            $start  = max(1, $page - $window);
            $end    = min($totalPages, $page + $window);
            if ($start > 1): ?>
            <a href="?filter=<?= urlencode($filter) ?>&page=1"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm text-gray-600 hover:bg-gray-100">1</a>
            <?php if ($start > 2): ?>
            <span class="w-8 h-8 flex items-center justify-center text-gray-400 text-sm">&hellip;</span>
            <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $start; $p <= $end; $p++): ?>
            <a href="?filter=<?= urlencode($filter) ?>&page=<?= $p ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm
                      <?= $p === $page ? 'bg-primary text-white font-semibold' : 'text-gray-600 hover:bg-gray-100' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
            <span class="w-8 h-8 flex items-center justify-center text-gray-400 text-sm">&hellip;</span>
            <?php endif; ?>
            <a href="?filter=<?= urlencode($filter) ?>&page=<?= $totalPages ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm text-gray-600 hover:bg-gray-100">
                <?= $totalPages ?>
            </a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
            <a href="?filter=<?= urlencode($filter) ?>&page=<?= $page + 1 ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm text-gray-600 hover:bg-gray-100">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Alpine.js Quick-View Modal ──────────────────────────────────────── -->
    <div x-show="showModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="showModal = false">

        <!-- Backdrop -->
        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"
             @click="showModal = false"></div>

        <!-- Modal panel -->
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-y-auto max-h-[88vh]"
             @click.stop>

            <!-- Modal header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-wrap gap-2">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                        <span class="text-primary text-sm font-bold"
                              x-text="msg?.name?.charAt(0)?.toUpperCase()"></span>
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-800 truncate" x-text="msg?.subject || '(kein Betreff)'"></p>
                        <p class="text-xs text-gray-400" x-text="msg?.name + ' · ' + msg?.date"></p>
                    </div>
                </div>
                <button @click="showModal = false"
                        class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Modal body -->
            <div class="p-6">

                <!-- Contact info bar -->
                <div class="flex flex-wrap gap-x-5 gap-y-1.5 mb-4 text-sm">
                    <div class="flex items-center gap-1.5 text-gray-500">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <a :href="'mailto:' + msg?.email" class="text-primary hover:underline" x-text="msg?.email"></a>
                    </div>
                    <template x-if="msg?.phone">
                        <div class="flex items-center gap-1.5 text-gray-500">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <span x-text="msg?.phone"></span>
                        </div>
                    </template>
                </div>

                <!-- Message text -->
                <div class="bg-gray-50 rounded-xl p-4 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap"
                     x-text="msg?.message"></div>

                <!-- Modal actions -->
                <div class="flex gap-3 mt-5 flex-wrap">
                    <a :href="'mailto:' + msg?.email + '?subject=Re:+' + encodeURIComponent(msg?.subject || 'Ihre Anfrage')"
                       class="btn-primary flex-1 justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                        </svg>
                        Antworten
                    </a>
                    <a :href="'messages.php?view=' + msg?.id + '&filter=<?= urlencode($filter) ?>'"
                       class="btn-gray flex-1 justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Vollansicht
                    </a>
                    <button @click="showModal = false" class="btn-gray">
                        Schliessen
                    </button>
                </div>
            </div>
        </div>
    </div>

</div><!-- /card -->
<?php endif; ?>

<?php require_once 'layout_end.php'; ?>
