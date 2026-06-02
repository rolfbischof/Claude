<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
$auth->requireRole('viewer'); // minimum role to access admin

$db = Database::getInstance();

// ── Stats ──────────────────────────────────────────────────────────────────
$totalUsers    = (int) $db->fetchColumn('SELECT COUNT(*) FROM users WHERE active = 1');
$publishedPages = (int) $db->fetchColumn('SELECT COUNT(*) FROM pages WHERE published = 1');
$totalEvents   = (int) $db->fetchColumn('SELECT COUNT(*) FROM events_portfolio');
$unreadMessages = (int) $db->fetchColumn('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
$totalMessages  = (int) $db->fetchColumn('SELECT COUNT(*) FROM contact_messages');

// ── Recent messages ───────────────────────────────────────────────────────
$recentMessages = $db->fetchAll(
    'SELECT id, name, email, subject, is_read, created_at
     FROM contact_messages
     ORDER BY created_at DESC
     LIMIT 5'
);

// ── Recent events ─────────────────────────────────────────────────────────
$recentEvents = $db->fetchAll(
    'SELECT id, title, event_date, location, published
     FROM events_portfolio
     ORDER BY created_at DESC
     LIMIT 5'
);

// ── Quick stats for roles ─────────────────────────────────────────────────
$activeEditors = (int) $db->fetchColumn(
    "SELECT COUNT(*) FROM users WHERE active = 1 AND role IN ('editor','admin','superadmin')"
);

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';
require_once 'layout.php';
?>

<!-- Welcome banner -->
<div class="rounded-2xl bg-gradient-to-r from-primary to-primary-light p-6 mb-6 text-white">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-xl font-bold mb-1">
                Willkommen zurück, <?= htmlspecialchars($auth->getUserDisplayName()) ?>!
            </h2>
            <p class="text-blue-200 text-sm">
                <?= date('l, d. F Y') ?> &nbsp;·&nbsp;
                <span class="inline-flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span>
                    System aktiv
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <?php if ($auth->hasRole('editor')): ?>
            <a href="/admin/events.php?action=new" class="btn-accent text-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Neuer Event
            </a>
            <a href="/admin/messages.php" class="bg-white/20 hover:bg-white/30 text-white inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Nachrichten
                <?php if ($unreadMessages > 0): ?>
                <span class="bg-accent text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold"><?= $unreadMessages ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stats grid -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    <!-- Total Users -->
    <?php if ($auth->hasRole('admin')): ?>
    <a href="/admin/users.php" class="card p-5 hover:shadow-md transition-shadow group">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <svg class="w-4 h-4 text-gray-300 group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?= $totalUsers ?></p>
        <p class="text-xs text-gray-500 mt-0.5">Aktive Benutzer</p>
    </a>
    <?php else: ?>
    <div class="card p-5">
        <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center mb-3">
            <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?= $totalUsers ?></p>
        <p class="text-xs text-gray-500 mt-0.5">Aktive Benutzer</p>
    </div>
    <?php endif; ?>

    <!-- Published Pages -->
    <?php if ($auth->hasRole('editor')): ?>
    <a href="/admin/content.php" class="card p-5 hover:shadow-md transition-shadow group">
    <?php else: ?>
    <div class="card p-5">
    <?php endif; ?>
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-gold/10 flex items-center justify-center">
                <svg class="w-5 h-5 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <?php if ($auth->hasRole('editor')): ?>
            <svg class="w-4 h-4 text-gray-300 group-hover:text-gold transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <?php endif; ?>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?= $publishedPages ?></p>
        <p class="text-xs text-gray-500 mt-0.5">Veröffentlichte Seiten</p>
    <?php if ($auth->hasRole('editor')): ?></a><?php else: ?></div><?php endif; ?>

    <!-- Total Events -->
    <?php if ($auth->hasRole('editor')): ?>
    <a href="/admin/events.php" class="card p-5 hover:shadow-md transition-shadow group">
    <?php else: ?>
    <div class="card p-5">
    <?php endif; ?>
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl bg-accent/10 flex items-center justify-center">
                <svg class="w-5 h-5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <?php if ($auth->hasRole('editor')): ?>
            <svg class="w-4 h-4 text-gray-300 group-hover:text-accent transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <?php endif; ?>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?= $totalEvents ?></p>
        <p class="text-xs text-gray-500 mt-0.5">Events total</p>
    <?php if ($auth->hasRole('editor')): ?></a><?php else: ?></div><?php endif; ?>

    <!-- Unread Messages -->
    <?php if ($auth->hasRole('editor')): ?>
    <a href="/admin/messages.php?filter=unread" class="card p-5 hover:shadow-md transition-shadow group">
    <?php else: ?>
    <div class="card p-5">
    <?php endif; ?>
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl <?= $unreadMessages > 0 ? 'bg-red-100' : 'bg-gray-100' ?> flex items-center justify-center">
                <svg class="w-5 h-5 <?= $unreadMessages > 0 ? 'text-accent' : 'text-gray-400' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <?php if ($auth->hasRole('editor')): ?>
            <svg class="w-4 h-4 text-gray-300 group-hover:text-accent transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <?php endif; ?>
        </div>
        <p class="text-2xl font-bold <?= $unreadMessages > 0 ? 'text-accent' : 'text-gray-800' ?>"><?= $unreadMessages ?></p>
        <p class="text-xs text-gray-500 mt-0.5">Ungelesene Nachrichten</p>
    <?php if ($auth->hasRole('editor')): ?></a><?php else: ?></div><?php endif; ?>

</div>

<!-- Two-column: recent messages + recent events -->
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">

    <!-- Recent contact messages -->
    <?php if ($auth->hasRole('editor')): ?>
    <div class="card">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Letzte Nachrichten
                <?php if ($unreadMessages > 0): ?>
                <span class="badge bg-red-100 text-accent"><?= $unreadMessages ?> ungelesen</span>
                <?php endif; ?>
            </h3>
            <a href="/admin/messages.php" class="text-xs text-primary hover:underline">Alle anzeigen</a>
        </div>
        <?php if (empty($recentMessages)): ?>
        <div class="px-5 py-8 text-center text-gray-400 text-sm">Noch keine Nachrichten vorhanden.</div>
        <?php else: ?>
        <div class="divide-y divide-gray-50">
            <?php foreach ($recentMessages as $msg): ?>
            <a href="/admin/messages.php?view=<?= $msg['id'] ?>" class="flex items-start gap-3 px-5 py-3.5 hover:bg-gray-50 transition-colors">
                <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <span class="text-primary text-xs font-semibold"><?= htmlspecialchars(mb_substr($msg['name'], 0, 1)) ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($msg['name']) ?></span>
                        <?php if (!$msg['is_read']): ?>
                        <span class="w-2 h-2 rounded-full bg-accent flex-shrink-0"></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($msg['subject'] ?: '(kein Betreff)') ?></p>
                </div>
                <span class="text-xs text-gray-400 flex-shrink-0"><?= date('d.m', strtotime($msg['created_at'])) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Recent events -->
    <?php if ($auth->hasRole('editor')): ?>
    <div class="card">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Letzte Events
            </h3>
            <a href="/admin/events.php" class="text-xs text-primary hover:underline">Alle anzeigen</a>
        </div>
        <?php if (empty($recentEvents)): ?>
        <div class="px-5 py-8 text-center text-gray-400 text-sm">Noch keine Events vorhanden.</div>
        <?php else: ?>
        <div class="divide-y divide-gray-50">
            <?php foreach ($recentEvents as $ev): ?>
            <a href="/admin/events.php?action=edit&id=<?= $ev['id'] ?>" class="flex items-center gap-3 px-5 py-3.5 hover:bg-gray-50 transition-colors">
                <div class="w-8 h-8 rounded-lg bg-accent/10 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($ev['title']) ?></p>
                    <p class="text-xs text-gray-500"><?= $ev['event_date'] ? format_date($ev['event_date']) : '–' ?> <?= $ev['location'] ? '· ' . htmlspecialchars($ev['location']) : '' ?></p>
                </div>
                <span class="badge <?= $ev['published'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                    <?= $ev['published'] ? 'Aktiv' : 'Entwurf' ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<!-- Quick actions -->
<?php if ($auth->hasRole('editor')): ?>
<div class="card p-5">
    <h3 class="font-semibold text-gray-800 mb-4">Schnellzugriff</h3>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <a href="/admin/events.php?action=new" class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-dashed border-gray-200 hover:border-primary hover:bg-primary/5 transition-all group text-center">
            <div class="w-10 h-10 rounded-xl bg-accent/10 group-hover:bg-accent/20 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
            <span class="text-xs font-medium text-gray-600 group-hover:text-primary">Neuer Event</span>
        </a>
        <a href="/admin/references.php?action=new" class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-dashed border-gray-200 hover:border-primary hover:bg-primary/5 transition-all group text-center">
            <div class="w-10 h-10 rounded-xl bg-gold/10 group-hover:bg-gold/20 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
            <span class="text-xs font-medium text-gray-600 group-hover:text-primary">Neue Referenz</span>
        </a>
        <a href="/admin/content.php" class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-dashed border-gray-200 hover:border-primary hover:bg-primary/5 transition-all group text-center">
            <div class="w-10 h-10 rounded-xl bg-primary/10 group-hover:bg-primary/20 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </div>
            <span class="text-xs font-medium text-gray-600 group-hover:text-primary">Seiten bearbeiten</span>
        </a>
        <a href="/admin/messages.php" class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-dashed border-gray-200 hover:border-primary hover:bg-primary/5 transition-all group text-center">
            <div class="w-10 h-10 rounded-xl bg-red-50 group-hover:bg-red-100 flex items-center justify-center transition-colors relative">
                <svg class="w-5 h-5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <?php if ($unreadMessages > 0): ?>
                <span class="absolute -top-1 -right-1 w-4 h-4 bg-accent text-white text-xs rounded-full flex items-center justify-center font-bold"><?= min($unreadMessages, 9) ?></span>
                <?php endif; ?>
            </div>
            <span class="text-xs font-medium text-gray-600 group-hover:text-primary">Nachrichten</span>
        </a>
        <?php if ($auth->hasRole('admin')): ?>
        <a href="/admin/users.php?action=new" class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-dashed border-gray-200 hover:border-primary hover:bg-primary/5 transition-all group text-center">
            <div class="w-10 h-10 rounded-xl bg-primary/10 group-hover:bg-primary/20 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            </div>
            <span class="text-xs font-medium text-gray-600 group-hover:text-primary">Benutzer hinzufügen</span>
        </a>
        <a href="/admin/backup.php" class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-dashed border-gray-200 hover:border-primary hover:bg-primary/5 transition-all group text-center">
            <div class="w-10 h-10 rounded-xl bg-green-50 group-hover:bg-green-100 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            </div>
            <span class="text-xs font-medium text-gray-600 group-hover:text-primary">Backup erstellen</span>
        </a>
        <a href="/admin/settings.php" class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-dashed border-gray-200 hover:border-primary hover:bg-primary/5 transition-all group text-center">
            <div class="w-10 h-10 rounded-xl bg-gray-100 group-hover:bg-gray-200 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <span class="text-xs font-medium text-gray-600 group-hover:text-primary">Einstellungen</span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once 'layout_end.php'; ?>
