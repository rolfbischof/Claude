<?php
/**
 * mb Kommunikation + Events – Admin Layout
 * Shared header/sidebar/footer for all admin pages.
 *
 * Usage: include this file AFTER setting $pageTitle and $currentPage.
 * Call layout_end() (or include layout_end.php) to close the wrapper.
 */

declare(strict_types=1);

// $auth, $db must already be available (required by caller)
$db       = Database::getInstance();
$userRole = $auth->getUserRole();
$userName = $auth->getUserDisplayName();
$userId   = $auth->getUserId();
$csrfToken = $auth->getCsrfToken();

// Unread messages count (for badge)
$unreadCount = 0;
try {
    $unreadCount = (int) $db->fetchColumn('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
} catch (Throwable) {}

// Flash message
$flashMsg  = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type']    ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Role helpers
$isAdmin      = $auth->hasRole('admin');
$isEditor     = $auth->hasRole('editor');
$isSuperAdmin = $auth->hasRole('superadmin');

// Role badge config
$roleBadgeColors = [
    'superadmin' => 'bg-red-600 text-white',
    'admin'      => 'bg-orange-500 text-white',
    'editor'     => 'bg-blue-600 text-white',
    'viewer'     => 'bg-gray-400 text-white',
];
$roleBadgeClass = $roleBadgeColors[$userRole] ?? 'bg-gray-400 text-white';

$roleLabels = [
    'superadmin' => 'Superadmin',
    'admin'      => 'Admin',
    'editor'     => 'Editor',
    'viewer'     => 'Viewer',
];
$roleLabel = $roleLabels[$userRole] ?? $userRole;

$pageTitle   = $pageTitle   ?? 'Admin';
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="de" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle) ?> – mb Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e3a5f',
                        'primary-light': '#2a4f82',
                        'primary-dark': '#152b47',
                        accent: '#e94560',
                        'accent-light': '#f06b81',
                        gold: '#f5a623',
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link { @apply flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-all duration-150; }
        .sidebar-link:hover { @apply bg-white/10 text-white; }
        .sidebar-link.active { @apply bg-white/20 text-white font-semibold; }
        .sidebar-link:not(.active) { @apply text-blue-100; }
        [x-cloak] { display: none !important; }
        .card { @apply bg-white rounded-xl shadow-sm border border-gray-100; }
        .btn-primary { @apply inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-light transition-colors; }
        .btn-accent { @apply inline-flex items-center gap-2 px-4 py-2 bg-accent text-white rounded-lg text-sm font-medium hover:bg-accent-light transition-colors; }
        .btn-gray { @apply inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors; }
        .btn-danger { @apply inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors; }
        .form-input { @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary; }
        .form-label { @apply block text-sm font-medium text-gray-700 mb-1; }
        .badge { @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium; }
        .table-th { @apply px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider; }
        .table-td { @apply px-4 py-3 text-sm text-gray-700; }
        .table-tr { @apply border-b border-gray-100 hover:bg-gray-50 transition-colors; }
    </style>
</head>
<body class="h-full" x-data="{ sidebarOpen: false }">

<div class="flex h-screen overflow-hidden">

    <!-- ============================================================
         SIDEBAR
    ============================================================ -->
    <!-- Mobile overlay -->
    <div x-show="sidebarOpen"
         x-cloak
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-gray-900/60 lg:hidden"></div>

    <!-- Sidebar panel -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 w-64 bg-primary flex flex-col transform transition-transform duration-200 ease-in-out lg:relative lg:translate-x-0 lg:flex lg:flex-shrink-0">

        <!-- Logo -->
        <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
            <div class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center flex-shrink-0">
                <span class="text-white font-bold text-sm">mb</span>
            </div>
            <div class="overflow-hidden">
                <p class="text-white font-semibold text-sm leading-tight truncate">mb Kommunikation</p>
                <p class="text-blue-300 text-xs truncate">+ Events</p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">

            <!-- Dashboard - all roles -->
            <a href="/admin/" class="sidebar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                Dashboard
            </a>

            <?php if ($isEditor): ?>
            <!-- Seiten/Content - editor+ -->
            <a href="/admin/content.php" class="sidebar-link <?= $currentPage === 'content' ? 'active' : '' ?>">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Seiten / Content
            </a>

            <!-- Events - editor+ -->
            <a href="/admin/events.php" class="sidebar-link <?= $currentPage === 'events' ? 'active' : '' ?>">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Events
            </a>

            <!-- Referenzen - editor+ -->
            <a href="/admin/references.php" class="sidebar-link <?= $currentPage === 'references' ? 'active' : '' ?>">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Referenzen
            </a>

            <!-- Nachrichten - editor+ -->
            <a href="/admin/messages.php" class="sidebar-link <?= $currentPage === 'messages' ? 'active' : '' ?>">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Nachrichten
                <?php if ($unreadCount > 0): ?>
                <span class="ml-auto bg-accent text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center"><?= min($unreadCount, 99) ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
            <div class="pt-3 pb-1 px-1">
                <p class="text-blue-300/60 text-xs uppercase tracking-widest font-semibold">Administration</p>
            </div>

            <!-- Benutzer - admin+ -->
            <a href="/admin/users.php" class="sidebar-link <?= $currentPage === 'users' ? 'active' : '' ?>">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Benutzer
            </a>

            <!-- Backup - admin+ -->
            <a href="/admin/backup.php" class="sidebar-link <?= $currentPage === 'backup' ? 'active' : '' ?>">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Backup
            </a>

            <!-- Einstellungen - admin+ -->
            <a href="/admin/settings.php" class="sidebar-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Einstellungen
            </a>
            <?php endif; ?>

        </nav>

        <!-- User section at bottom -->
        <div class="border-t border-white/10 px-4 py-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                    <span class="text-white text-sm font-semibold"><?= htmlspecialchars(mb_substr($userName, 0, 1)) ?></span>
                </div>
                <div class="overflow-hidden flex-1 min-w-0">
                    <p class="text-white text-sm font-medium truncate"><?= htmlspecialchars($userName) ?></p>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium <?= $roleBadgeClass ?>"><?= $roleLabel ?></span>
                </div>
            </div>
            <a href="/admin/logout.php"
               onclick="return confirm('Wirklich abmelden?')"
               class="flex items-center gap-2 w-full px-3 py-2 text-blue-200 hover:text-white hover:bg-white/10 rounded-lg text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Abmelden
            </a>
        </div>
    </aside>

    <!-- ============================================================
         MAIN CONTENT AREA
    ============================================================ -->
    <div class="flex-1 flex flex-col overflow-hidden">

        <!-- Top bar -->
        <header class="bg-white border-b border-gray-200 px-4 sm:px-6 py-3 flex items-center gap-4 flex-shrink-0">
            <!-- Mobile hamburger -->
            <button @click="sidebarOpen = !sidebarOpen"
                    class="lg:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <div class="flex-1">
                <h1 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($pageTitle) ?></h1>
            </div>

            <!-- Quick links -->
            <a href="/" target="_blank" class="hidden sm:flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                Website
            </a>
        </header>

        <!-- Flash message -->
        <?php if (!empty($flashMsg)): ?>
        <?php
        $flashClasses = [
            'success' => 'bg-green-50 border-green-200 text-green-800',
            'error'   => 'bg-red-50 border-red-200 text-red-800',
            'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-800',
            'info'    => 'bg-blue-50 border-blue-200 text-blue-800',
        ];
        $fc = $flashClasses[$flashType] ?? $flashClasses['info'];
        ?>
        <div x-data="{ show: true }" x-show="show" x-cloak
             class="mx-4 sm:mx-6 mt-4 flex items-start gap-3 p-4 rounded-xl border <?= $fc ?> text-sm">
            <span class="flex-1"><?= htmlspecialchars($flashMsg) ?></span>
            <button @click="show=false" class="opacity-60 hover:opacity-100 flex-shrink-0 mt-0.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <?php endif; ?>

        <!-- Page content wrapper (caller fills this) -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6">
