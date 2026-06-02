<?php
// includes/footer.php
// Reads contact settings from $settings array (populated by includes/functions.php)
// Falls back to hardcoded defaults if $settings not available.

$s_email    = $settings['contact_email']   ?? 'info@mb-kommunikation.ch';
$s_phone    = $settings['contact_phone']   ?? '+41 79 123 45 67';
$s_address  = $settings['contact_address'] ?? 'Hochdorf, Luzern, Schweiz';
$s_instagram = $settings['instagram_url'] ?? 'https://www.instagram.com/mb_kommunikation/';
$s_linkedin  = $settings['linkedin_url']  ?? 'https://www.linkedin.com/company/mb-kommunikation/';
$s_facebook  = $settings['facebook_url']  ?? '';
?>

<!-- ===================== FOOTER ===================== -->
<footer class="bg-primary text-white">

    <!-- Top wave separator -->
    <div class="bg-gray-50 leading-none">
        <svg viewBox="0 0 1440 60" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="w-full h-12 sm:h-16">
            <path d="M0,60 L0,30 Q360,0 720,30 Q1080,60 1440,30 L1440,60 Z" fill="#1e3a5f"/>
        </svg>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-12">

            <!-- Col 1: Company Info -->
            <div class="lg:col-span-1">
                <!-- Logo -->
                <a href="/" class="inline-flex items-center gap-3 group mb-5">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-accent font-bold text-lg shadow-md group-hover:bg-gold transition-colors duration-200 text-white">
                        mb
                    </div>
                    <div class="flex flex-col leading-tight">
                        <span class="text-white font-bold text-base">mb <span class="text-accent">|</span> Kommunikation</span>
                        <span class="text-blue-200 text-xs font-medium tracking-widest uppercase">+ Events</span>
                    </div>
                </a>
                <p class="text-blue-200 text-sm leading-relaxed mb-5">
                    Professionelle Kommunikationsberatung und Eventmanagement in der Zentralschweiz. Wir bringen Ihre Botschaft ans Ziel.
                </p>
                <!-- Social Links -->
                <div class="flex items-center gap-3">
                    <?php if ($s_instagram): ?>
                    <a href="<?= htmlspecialchars($s_instagram) ?>" target="_blank" rel="noopener noreferrer"
                       class="flex items-center justify-center w-9 h-9 rounded-lg bg-white/10 text-blue-200 hover:bg-accent hover:text-white transition-all duration-200"
                       aria-label="Instagram">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                    <?php if ($s_linkedin): ?>
                    <a href="<?= htmlspecialchars($s_linkedin) ?>" target="_blank" rel="noopener noreferrer"
                       class="flex items-center justify-center w-9 h-9 rounded-lg bg-white/10 text-blue-200 hover:bg-accent hover:text-white transition-all duration-200"
                       aria-label="LinkedIn">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                    <?php if ($s_facebook): ?>
                    <a href="<?= htmlspecialchars($s_facebook) ?>" target="_blank" rel="noopener noreferrer"
                       class="flex items-center justify-center w-9 h-9 rounded-lg bg-white/10 text-blue-200 hover:bg-accent hover:text-white transition-all duration-200"
                       aria-label="Facebook">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Col 2: Quick Links -->
            <div>
                <h3 class="text-white font-semibold text-sm uppercase tracking-widest mb-5 flex items-center gap-2">
                    <span class="block w-4 h-0.5 bg-accent rounded"></span>
                    Navigation
                </h3>
                <ul class="space-y-3">
                    <?php
                    $quick_links = [
                        ['href' => '/',                  'label' => 'Startseite'],
                        ['href' => '/kommunikation.php', 'label' => 'Kommunikation'],
                        ['href' => '/events.php',        'label' => 'Events'],
                        ['href' => '/referenzen.php',    'label' => 'Referenzen'],
                        ['href' => '/kontakt.php',       'label' => 'Kontakt'],
                        ['href' => '/impressum.php',     'label' => 'Impressum'],
                        ['href' => '/datenschutz.php',   'label' => 'Datenschutz'],
                    ];
                    foreach ($quick_links as $lnk):
                    ?>
                    <li>
                        <a href="<?= htmlspecialchars($lnk['href']) ?>"
                           class="text-blue-200 text-sm hover:text-white transition-colors duration-150 flex items-center gap-2 group">
                            <i data-lucide="chevron-right" class="w-3 h-3 text-accent group-hover:translate-x-0.5 transition-transform duration-150"></i>
                            <?= htmlspecialchars($lnk['label']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Col 3: Services -->
            <div>
                <h3 class="text-white font-semibold text-sm uppercase tracking-widest mb-5 flex items-center gap-2">
                    <span class="block w-4 h-0.5 bg-accent rounded"></span>
                    Leistungen
                </h3>
                <ul class="space-y-3">
                    <?php
                    $services = [
                        'Kommunikationsberatung',
                        'Drucksachen & Medien',
                        'Websites & Digital',
                        'Werbetechnik',
                        'Eventberatung',
                        'Eventkonzept & Planung',
                        'Eventdurchführung',
                        'Firmen- & Teamevents',
                        'Jubiläen & Feiern',
                    ];
                    foreach ($services as $svc):
                    ?>
                    <li class="flex items-center gap-2 text-blue-200 text-sm">
                        <span class="block w-1.5 h-1.5 rounded-full bg-accent flex-shrink-0"></span>
                        <?= htmlspecialchars($svc) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Col 4: Contact -->
            <div>
                <h3 class="text-white font-semibold text-sm uppercase tracking-widest mb-5 flex items-center gap-2">
                    <span class="block w-4 h-0.5 bg-accent rounded"></span>
                    Kontakt
                </h3>
                <ul class="space-y-4">
                    <li class="flex items-start gap-3">
                        <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-white/10 mt-0.5">
                            <i data-lucide="user" class="w-4 h-4 text-accent"></i>
                        </span>
                        <div>
                            <p class="text-white text-sm font-medium">Manuela Bischof</p>
                            <p class="text-blue-200 text-xs">Inhaberin</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-white/10 mt-0.5">
                            <i data-lucide="map-pin" class="w-4 h-4 text-accent"></i>
                        </span>
                        <div>
                            <p class="text-blue-200 text-sm"><?= htmlspecialchars($s_address) ?></p>
                        </div>
                    </li>
                    <li>
                        <a href="tel:<?= preg_replace('/[^+\d]/', '', $s_phone) ?>"
                           class="flex items-start gap-3 group">
                            <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-white/10 mt-0.5 group-hover:bg-accent transition-colors">
                                <i data-lucide="phone" class="w-4 h-4 text-accent group-hover:text-white transition-colors"></i>
                            </span>
                            <span class="text-blue-200 group-hover:text-white text-sm transition-colors self-center"><?= htmlspecialchars($s_phone) ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="mailto:<?= htmlspecialchars($s_email) ?>"
                           class="flex items-start gap-3 group">
                            <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-white/10 mt-0.5 group-hover:bg-accent transition-colors">
                                <i data-lucide="mail" class="w-4 h-4 text-accent group-hover:text-white transition-colors"></i>
                            </span>
                            <span class="text-blue-200 group-hover:text-white text-sm transition-colors self-center break-all"><?= htmlspecialchars($s_email) ?></span>
                        </a>
                    </li>
                </ul>

                <!-- CTA -->
                <a
                    href="/kontakt.php"
                    class="mt-6 inline-flex items-center gap-2 bg-accent text-white text-sm font-semibold px-5 py-2.5 rounded-lg hover:bg-accent-dark transition-all duration-200 shadow-md hover:shadow-lg"
                >
                    <i data-lucide="send" class="w-4 h-4"></i>
                    Nachricht senden
                </a>
            </div>
        </div>

        <!-- Divider -->
        <div class="border-t border-white/10 mt-12 pt-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-blue-300 text-sm text-center sm:text-left">
                    &copy; <?= date('Y') ?> mb Kommunikation + Events · Manuela Bischof · Hochdorf, Schweiz
                </p>
                <p class="text-blue-300 text-sm flex items-center gap-1.5">
                    Made with <span class="text-accent text-base">♥</span> in der Schweiz
                    <span class="text-lg" title="Schweizer Qualität">🇨🇭</span>
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Re-init icons for any footer icons rendered after initial DOMContentLoaded -->
<script>
    if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
