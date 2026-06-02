<?php
// includes/header.php
// Requires: $page_title (string), $active_page (string)
// Depends on: includes/auth.php (provides $is_logged_in, $current_user)

if (!isset($page_title))  $page_title  = 'mb Kommunikation + Events';
if (!isset($active_page)) $active_page = 'home';

// Gather flash messages
$flash_message = '';
$flash_type    = '';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type    = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$is_logged_in = isset($is_logged_in) ? $is_logged_in : (isset($_SESSION['user_id']) && !empty($_SESSION['user_id']));
$current_user = isset($current_user) ? $current_user : ($_SESSION['user_name'] ?? '');

$nav_links = [
    ['href' => '/',               'label' => 'Home',          'key' => 'home'],
    ['href' => '/kommunikation.php', 'label' => 'Kommunikation', 'key' => 'kommunikation'],
    ['href' => '/events.php',     'label' => 'Events',        'key' => 'events'],
    ['href' => '/referenzen.php', 'label' => 'Referenzen',    'key' => 'referenzen'],
    ['href' => '/kontakt.php',    'label' => 'Kontakt',       'key' => 'kontakt'],
];
?>
<!DOCTYPE html>
<html lang="de" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="mb Kommunikation + Events – Professionelle Kommunikationsberatung und Eventmanagement in Hochdorf, Schweiz. Manuela Bischof.">
    <title><?= htmlspecialchars($page_title) ?> | mb Kommunikation + Events</title>

    <!-- Favicon placeholder -->
    <link rel="icon" href="/assets/favicon.ico" type="image/x-icon">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary:  { DEFAULT: '#1e3a5f', light: '#2a4f80', dark: '#142844' },
                        accent:   { DEFAULT: '#e94560', light: '#ef6b82', dark: '#c73550' },
                        gold:     { DEFAULT: '#f5a623', light: '#f7bc55', dark: '#d4891a' },
                    },
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    backdropBlur: { xs: '2px' },
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }

        /* Smooth scroll offset for sticky header */
        html { scroll-padding-top: 80px; }

        /* Header scroll effect handled via Alpine */
        .header-scrolled {
            box-shadow: 0 2px 20px rgba(30,58,95,0.15);
        }

        /* Active nav underline */
        .nav-active {
            position: relative;
        }
        .nav-active::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e94560;
            border-radius: 2px;
        }

        /* Flash animations */
        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        .flash-animate { animation: slideDown 0.4s ease forwards; }

        /* Mobile menu transition */
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-white text-gray-800 antialiased">

<!-- ===================== HEADER ===================== -->
<header
    x-data="{
        open: false,
        scrolled: false,
        adminOpen: false,
        init() {
            window.addEventListener('scroll', () => {
                this.scrolled = window.scrollY > 20;
            });
        }
    }"
    :class="scrolled ? 'header-scrolled bg-white/95 backdrop-blur-sm' : 'bg-white'"
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">

            <!-- Logo -->
            <a href="/" class="flex items-center gap-3 group flex-shrink-0">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary text-white font-bold text-lg shadow-md group-hover:bg-accent transition-colors duration-200">
                    mb
                </div>
                <div class="flex flex-col leading-tight">
                    <span class="text-primary font-bold text-base sm:text-lg tracking-tight">
                        mb <span class="text-accent">|</span> Kommunikation
                    </span>
                    <span class="text-gray-500 text-xs font-medium tracking-widest uppercase">+ Events · Hochdorf</span>
                </div>
            </a>

            <!-- Desktop Navigation -->
            <nav class="hidden lg:flex items-center gap-8">
                <?php foreach ($nav_links as $link): ?>
                    <a
                        href="<?= htmlspecialchars($link['href']) ?>"
                        class="relative text-sm font-medium transition-colors duration-200
                            <?= $active_page === $link['key']
                                ? 'text-accent nav-active'
                                : 'text-gray-600 hover:text-primary' ?>"
                    >
                        <?= htmlspecialchars($link['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Right: Auth + Mobile toggle -->
            <div class="flex items-center gap-3">

                <?php if ($is_logged_in): ?>
                    <!-- Admin Dropdown -->
                    <div class="relative hidden lg:block" x-data="{ open: false }" @click.outside="open = false">
                        <button
                            @click="open = !open"
                            class="flex items-center gap-2 bg-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-primary-light transition-colors duration-200"
                        >
                            <i data-lucide="user-circle" class="w-4 h-4"></i>
                            <span class="max-w-[120px] truncate"><?= htmlspecialchars($current_user ?: 'Admin') ?></span>
                            <i data-lucide="chevron-down" class="w-3 h-3 transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            x-cloak
                            class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-50"
                        >
                            <a href="/admin/" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary transition-colors">
                                <i data-lucide="layout-dashboard" class="w-4 h-4 text-primary"></i> Dashboard
                            </a>
                            <a href="/admin/events.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary transition-colors">
                                <i data-lucide="calendar" class="w-4 h-4 text-primary"></i> Events verwalten
                            </a>
                            <a href="/admin/referenzen.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary transition-colors">
                                <i data-lucide="star" class="w-4 h-4 text-primary"></i> Referenzen
                            </a>
                            <a href="/admin/nachrichten.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary transition-colors">
                                <i data-lucide="mail" class="w-4 h-4 text-primary"></i> Nachrichten
                            </a>
                            <a href="/admin/einstellungen.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary transition-colors">
                                <i data-lucide="settings" class="w-4 h-4 text-primary"></i> Einstellungen
                            </a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="/logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                <i data-lucide="log-out" class="w-4 h-4"></i> Abmelden
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Login Button (desktop) -->
                    <a
                        href="/login.php"
                        class="hidden lg:flex items-center gap-2 border border-primary text-primary text-sm font-medium px-4 py-2 rounded-lg hover:bg-primary hover:text-white transition-all duration-200"
                    >
                        <i data-lucide="log-in" class="w-4 h-4"></i>
                        Login
                    </a>
                <?php endif; ?>

                <!-- CTA Button (desktop) -->
                <a
                    href="/kontakt.php"
                    class="hidden lg:inline-flex items-center gap-2 bg-accent text-white text-sm font-semibold px-5 py-2 rounded-lg shadow-sm hover:bg-accent-dark transition-all duration-200 hover:shadow-md"
                >
                    <i data-lucide="send" class="w-4 h-4"></i>
                    Kontakt
                </a>

                <!-- Mobile Hamburger -->
                <button
                    @click="open = !open"
                    class="lg:hidden flex items-center justify-center w-10 h-10 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors"
                    :aria-expanded="open.toString()"
                    aria-label="Navigation öffnen"
                >
                    <i x-show="!open" data-lucide="menu" class="w-5 h-5"></i>
                    <i x-show="open"  data-lucide="x"    class="w-5 h-5" x-cloak></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation Drawer -->
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        x-cloak
        class="lg:hidden bg-white border-t border-gray-100 shadow-xl"
        @click.outside="open = false"
    >
        <nav class="max-w-7xl mx-auto px-4 py-4 flex flex-col gap-1">
            <?php foreach ($nav_links as $link): ?>
                <a
                    href="<?= htmlspecialchars($link['href']) ?>"
                    @click="open = false"
                    class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors duration-150
                        <?= $active_page === $link['key']
                            ? 'bg-primary/5 text-accent font-semibold'
                            : 'text-gray-700 hover:bg-gray-50 hover:text-primary' ?>"
                >
                    <?= htmlspecialchars($link['label']) ?>
                    <?php if ($active_page === $link['key']): ?>
                        <span class="ml-auto w-1.5 h-1.5 rounded-full bg-accent"></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <div class="border-t border-gray-100 my-2"></div>

            <?php if ($is_logged_in): ?>
                <a href="/admin/" @click="open = false" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-primary hover:bg-primary/5">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Admin Dashboard
                </a>
                <a href="/logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50">
                    <i data-lucide="log-out" class="w-4 h-4"></i> Abmelden
                </a>
            <?php else: ?>
                <a href="/login.php" @click="open = false" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i data-lucide="log-in" class="w-4 h-4"></i> Login
                </a>
            <?php endif; ?>

            <a
                href="/kontakt.php"
                @click="open = false"
                class="mt-2 flex items-center justify-center gap-2 bg-accent text-white text-sm font-semibold px-5 py-3 rounded-lg shadow-sm hover:bg-accent-dark transition-colors"
            >
                <i data-lucide="send" class="w-4 h-4"></i>
                Jetzt Kontakt aufnehmen
            </a>
        </nav>
    </div>
</header>

<!-- Spacer for fixed header -->
<div class="h-20"></div>

<!-- ===================== FLASH MESSAGE ===================== -->
<?php if ($flash_message): ?>
<div
    x-data="{ show: true }"
    x-show="show"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-init="setTimeout(() => show = false, 5000)"
    class="flash-animate fixed top-24 left-1/2 -translate-x-1/2 z-40 w-full max-w-xl px-4"
>
    <div class="flex items-start gap-3 p-4 rounded-xl shadow-xl border
        <?php
        switch ($flash_type) {
            case 'success': echo 'bg-green-50 border-green-200 text-green-800'; break;
            case 'error':   echo 'bg-red-50 border-red-200 text-red-800';       break;
            case 'warning': echo 'bg-yellow-50 border-yellow-200 text-yellow-800'; break;
            default:        echo 'bg-blue-50 border-blue-200 text-blue-800';    break;
        }
        ?>">
        <i data-lucide="<?php
            switch ($flash_type) {
                case 'success': echo 'check-circle'; break;
                case 'error':   echo 'alert-circle'; break;
                case 'warning': echo 'alert-triangle'; break;
                default:        echo 'info';          break;
            }
        ?>" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
        <p class="text-sm font-medium flex-1"><?= htmlspecialchars($flash_message) ?></p>
        <button @click="show = false" class="flex-shrink-0 hover:opacity-70 transition-opacity">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
</div>
<?php endif; ?>

<script>
    // Initialise Lucide icons after DOM is ready
    document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
</script>
