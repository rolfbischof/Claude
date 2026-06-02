<?php
/**
 * mb Kommunikation + Events – Kommunikation
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$page_title  = 'Kommunikation';
$active_page = 'kommunikation';

$settings = get_settings();

// Page data from DB (optional)
$page_data = null;
try {
    $db = Database::getInstance();
    $page_data = $db->find('pages', 'kommunikation', 'slug');
} catch (Throwable $e) {
    // fallback to defaults
}

$hero_title    = $page_data['title']    ?? 'Kommunikation';
$hero_subtitle = $page_data['subtitle'] ?? 'Ihre Botschaft – professionell vermittelt';

require_once __DIR__ . '/includes/header.php';
?>

<!-- ================================================================
     HERO SECTION
     ================================================================ -->
<section class="relative bg-primary overflow-hidden py-24 lg:py-32">
    <!-- Background decoration -->
    <div class="absolute inset-0 opacity-5"
         style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:28px 28px;"></div>
    <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-accent/10 blur-3xl"></div>
    <div class="absolute -bottom-12 -left-12 w-72 h-72 rounded-full bg-gold/10 blur-3xl"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-blue-300 text-sm mb-8">
                <a href="/" class="hover:text-white transition-colors">Home</a>
                <i data-lucide="chevron-right" class="w-4 h-4 flex-shrink-0"></i>
                <span class="text-white font-medium">Kommunikation</span>
            </nav>

            <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-4">Unsere Leistungen</p>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight mb-6">
                <?= e($hero_title) ?>
            </h1>
            <p class="text-blue-200 text-xl leading-relaxed mb-10 max-w-2xl">
                <?= e($hero_subtitle) ?>
            </p>
            <div class="flex flex-wrap gap-4">
                <a href="/kontakt.php"
                   class="inline-flex items-center gap-2 bg-accent text-white font-bold px-8 py-4 rounded-xl shadow-lg hover:bg-accent-dark transition-all duration-200">
                    <i data-lucide="send" class="w-5 h-5"></i>
                    Beratungsgespräch anfragen
                </a>
                <a href="#leistungen"
                   class="inline-flex items-center gap-2 bg-white/10 border border-white/20 text-white font-semibold px-8 py-4 rounded-xl hover:bg-white/20 transition-all duration-200">
                    <i data-lucide="arrow-down" class="w-5 h-5"></i>
                    Leistungen entdecken
                </a>
            </div>
        </div>
    </div>

    <!-- Bottom wave -->
    <div class="absolute bottom-0 left-0 right-0 leading-none">
        <svg viewBox="0 0 1440 60" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="w-full h-12">
            <path d="M0,60 L0,30 Q360,0 720,30 Q1080,60 1440,30 L1440,60 Z" fill="#ffffff"/>
        </svg>
    </div>
</section>


<!-- ================================================================
     SERVICES GRID
     ================================================================ -->
<section id="leistungen" class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Section header -->
        <div class="text-center mb-16">
            <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-3">Was wir anbieten</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-primary mb-4">Kommunikationsleistungen</h2>
            <p class="text-gray-500 text-lg max-w-2xl mx-auto">
                Massgeschneiderte Kommunikationslösungen für KMU, Vereine und Privatpersonen in der Zentralschweiz.
            </p>
        </div>

        <!-- Services detailed cards -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">

            <!-- 1. Kommunikationsstrategie -->
            <div id="strategie" class="group bg-white border-2 border-gray-100 rounded-2xl p-8 hover:border-primary/20 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-primary/5 mb-6 group-hover:bg-primary group-hover:scale-110 transition-all duration-300">
                    <i data-lucide="target" class="w-7 h-7 text-primary group-hover:text-white transition-colors"></i>
                </div>
                <h3 class="text-primary font-bold text-xl mb-3">Kommunikationsstrategie</h3>
                <p class="text-gray-500 leading-relaxed mb-5">
                    Wir analysieren Ihre aktuelle Kommunikation und entwickeln eine massgeschneiderte Strategie für eine starke und kohärente Markenkommunikation nach innen und aussen.
                </p>
                <ul class="space-y-2 mb-6">
                    <?php
                    $items = ['Kommunikations-Audit', 'Zielgruppenanalyse', 'Strategie-Entwicklung', 'Tonalität & Messaging'];
                    foreach ($items as $item): ?>
                    <li class="flex items-center gap-2.5 text-sm text-gray-600">
                        <span class="w-1.5 h-1.5 rounded-full bg-accent flex-shrink-0"></span>
                        <?= e($item) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="/kontakt.php" class="inline-flex items-center gap-2 text-sm font-semibold text-accent hover:text-accent-dark transition-colors">
                    Anfragen <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>

            <!-- 2. Drucksachen -->
            <div id="drucksachen" class="group bg-white border-2 border-gray-100 rounded-2xl p-8 hover:border-accent/20 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-accent/5 mb-6 group-hover:bg-accent group-hover:scale-110 transition-all duration-300">
                    <i data-lucide="printer" class="w-7 h-7 text-accent group-hover:text-white transition-colors"></i>
                </div>
                <h3 class="text-primary font-bold text-xl mb-3">Drucksachen</h3>
                <p class="text-gray-500 leading-relaxed mb-5">
                    Von der professionellen Visitenkarte bis zur aufwändigen Broschüre – wir gestalten und koordinieren alle Ihre Drucksachen mit Liebe zum Detail und Schweizer Qualitätsanspruch.
                </p>
                <ul class="space-y-2 mb-6">
                    <?php
                    $items = ['Visitenkarten', 'Flyer & Folder', 'Broschüren & Kataloge', 'Newsletter (Print & Digital)', 'Plakate & Banner', 'Einladungen'];
                    foreach ($items as $item): ?>
                    <li class="flex items-center gap-2.5 text-sm text-gray-600">
                        <span class="w-1.5 h-1.5 rounded-full bg-accent flex-shrink-0"></span>
                        <?= e($item) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="/kontakt.php" class="inline-flex items-center gap-2 text-sm font-semibold text-accent hover:text-accent-dark transition-colors">
                    Offerte anfragen <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>

            <!-- 3. Websites & Digital -->
            <div id="websites" class="group bg-white border-2 border-gray-100 rounded-2xl p-8 hover:border-gold/30 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-gold/10 mb-6 group-hover:bg-gold group-hover:scale-110 transition-all duration-300">
                    <i data-lucide="monitor" class="w-7 h-7 text-yellow-600 group-hover:text-white transition-colors"></i>
                </div>
                <h3 class="text-primary font-bold text-xl mb-3">Websites & Digital</h3>
                <p class="text-gray-500 leading-relaxed mb-5">
                    Website-Konzepte und digitale Kommunikationsstrategien, die Ihre Zielgruppe ansprechen und Ihre Online-Präsenz stärken.
                </p>
                <ul class="space-y-2 mb-6">
                    <?php
                    $items = ['Website-Konzept & Briefing', 'Texterstellung', 'Social-Media-Konzept', 'E-Mail-Marketing', 'Online-Newsletter'];
                    foreach ($items as $item): ?>
                    <li class="flex items-center gap-2.5 text-sm text-gray-600">
                        <span class="w-1.5 h-1.5 rounded-full bg-gold flex-shrink-0"></span>
                        <?= e($item) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="/kontakt.php" class="inline-flex items-center gap-2 text-sm font-semibold text-accent hover:text-accent-dark transition-colors">
                    Anfragen <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>

            <!-- 4. Medienarbeit -->
            <div id="medien" class="group bg-white border-2 border-gray-100 rounded-2xl p-8 hover:border-primary/20 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-primary/5 mb-6 group-hover:bg-primary group-hover:scale-110 transition-all duration-300">
                    <i data-lucide="newspaper" class="w-7 h-7 text-primary group-hover:text-white transition-colors"></i>
                </div>
                <h3 class="text-primary font-bold text-xl mb-3">Medienarbeit</h3>
                <p class="text-gray-500 leading-relaxed mb-5">
                    Professionelle Medienkommunikation für Ihr Unternehmen: Von der Medienmitteilung bis zur vollständigen Medienbetreuung.
                </p>
                <ul class="space-y-2 mb-6">
                    <?php
                    $items = ['Medienmitteilungen', 'Pressemappen', 'Medienmonitoring', 'Journalist:innen-Kontakte'];
                    foreach ($items as $item): ?>
                    <li class="flex items-center gap-2.5 text-sm text-gray-600">
                        <span class="w-1.5 h-1.5 rounded-full bg-accent flex-shrink-0"></span>
                        <?= e($item) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="/kontakt.php" class="inline-flex items-center gap-2 text-sm font-semibold text-accent hover:text-accent-dark transition-colors">
                    Anfragen <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>

            <!-- 5. Werbetechnik -->
            <div id="werbetechnik" class="group bg-white border-2 border-gray-100 rounded-2xl p-8 hover:border-accent/20 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-accent/5 mb-6 group-hover:bg-accent group-hover:scale-110 transition-all duration-300">
                    <i data-lucide="megaphone" class="w-7 h-7 text-accent group-hover:text-white transition-colors"></i>
                </div>
                <h3 class="text-primary font-bold text-xl mb-3">Werbetechnik</h3>
                <p class="text-gray-500 leading-relaxed mb-5">
                    Beschriftungen, Roll-ups, Messestände und mehr – wir koordinieren Ihre Werbemittelproduktion vom Entwurf bis zur Montage.
                </p>
                <ul class="space-y-2 mb-6">
                    <?php
                    $items = ['Fahrzeugbeschriftung', 'Schilder & Beschriftungen', 'Messe-Materialien', 'Roll-ups & Displays', 'Werbeartikel'];
                    foreach ($items as $item): ?>
                    <li class="flex items-center gap-2.5 text-sm text-gray-600">
                        <span class="w-1.5 h-1.5 rounded-full bg-accent flex-shrink-0"></span>
                        <?= e($item) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="/kontakt.php" class="inline-flex items-center gap-2 text-sm font-semibold text-accent hover:text-accent-dark transition-colors">
                    Anfragen <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>

            <!-- 6. Corporate Identity -->
            <div id="ci" class="group bg-white border-2 border-gray-100 rounded-2xl p-8 hover:border-gold/30 hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-gold/10 mb-6 group-hover:bg-gold group-hover:scale-110 transition-all duration-300">
                    <i data-lucide="palette" class="w-7 h-7 text-yellow-600 group-hover:text-white transition-colors"></i>
                </div>
                <h3 class="text-primary font-bold text-xl mb-3">Corporate Identity</h3>
                <p class="text-gray-500 leading-relaxed mb-5">
                    Ein starker und konsistenter Markenauftritt – wir entwickeln oder überarbeiten Ihre Corporate Identity für einen professionellen Gesamtauftritt.
                </p>
                <ul class="space-y-2 mb-6">
                    <?php
                    $items = ['Logo & Bildmarke', 'CI-Manual & Guidelines', 'Geschäftspapiere', 'Schrift- & Farbkonzept', 'Bildsprache'];
                    foreach ($items as $item): ?>
                    <li class="flex items-center gap-2.5 text-sm text-gray-600">
                        <span class="w-1.5 h-1.5 rounded-full bg-gold flex-shrink-0"></span>
                        <?= e($item) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="/kontakt.php" class="inline-flex items-center gap-2 text-sm font-semibold text-accent hover:text-accent-dark transition-colors">
                    Anfragen <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </div>
</section>


<!-- ================================================================
     PROCESS SECTION: WIE WIR ARBEITEN
     ================================================================ -->
<section class="py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-16">
            <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-3">Unser Prozess</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-primary mb-4">Wie wir arbeiten</h2>
            <p class="text-gray-500 text-lg max-w-2xl mx-auto">
                Einfach, transparent und ergebnisorientiert – so begleiten wir Sie durch Ihr Kommunikationsprojekt.
            </p>
        </div>

        <!-- Steps -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8 relative">
            <!-- Connecting line (desktop) -->
            <div class="hidden lg:block absolute top-10 left-[calc(12.5%+1rem)] right-[calc(12.5%+1rem)] h-0.5 bg-gradient-to-r from-primary/20 via-accent/40 to-primary/20"></div>

            <?php
            $steps = [
                [
                    'num'   => '01',
                    'icon'  => 'message-circle',
                    'title' => 'Erstgespräch',
                    'desc'  => 'Wir lernen uns kennen, hören Ihre Ziele und Wünsche und definieren gemeinsam den Rahmen Ihres Projekts.',
                    'color' => 'bg-primary text-white',
                ],
                [
                    'num'   => '02',
                    'icon'  => 'lightbulb',
                    'title' => 'Analyse & Konzept',
                    'desc'  => 'Analyse Ihrer Ausgangslage, Ihrer Zielgruppen und Entwicklung eines massgeschneiderten Konzepts.',
                    'color' => 'bg-accent text-white',
                ],
                [
                    'num'   => '03',
                    'icon'  => 'pen-tool',
                    'title' => 'Umsetzung',
                    'desc'  => 'Professionelle Umsetzung der definierten Massnahmen mit klaren Meilensteinen und regelmässigen Updates.',
                    'color' => 'bg-gold text-white',
                ],
                [
                    'num'   => '04',
                    'icon'  => 'check-circle',
                    'title' => 'Abschluss & Erfolg',
                    'desc'  => 'Übergabe der Ergebnisse, finale Qualitätskontrolle und auf Wunsch fortlaufende Betreuung.',
                    'color' => 'bg-primary text-white',
                ],
            ];
            foreach ($steps as $step): ?>
            <div class="flex flex-col items-center text-center">
                <!-- Step circle -->
                <div class="relative flex items-center justify-center w-20 h-20 rounded-full <?= $step['color'] ?> shadow-lg mb-6 z-10">
                    <i data-lucide="<?= $step['icon'] ?>" class="w-8 h-8"></i>
                    <!-- Number badge -->
                    <span class="absolute -top-2 -right-2 w-7 h-7 rounded-full bg-white border-2 border-gray-100 flex items-center justify-center text-xs font-black text-primary shadow-sm">
                        <?= $step['num'] ?>
                    </span>
                </div>
                <h3 class="text-primary font-bold text-lg mb-3"><?= e($step['title']) ?></h3>
                <p class="text-gray-500 text-sm leading-relaxed"><?= e($step['desc']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- ================================================================
     USP BANNER
     ================================================================ -->
<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-br from-primary to-primary-light rounded-3xl p-10 sm:p-14 relative overflow-hidden">
            <!-- Decorative -->
            <div class="absolute -top-12 -right-12 w-56 h-56 rounded-full bg-accent/20 blur-2xl"></div>
            <div class="absolute -bottom-8 -left-8 w-48 h-48 rounded-full bg-gold/15 blur-2xl"></div>

            <div class="relative grid lg:grid-cols-2 gap-10 items-center">
                <div>
                    <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-4">Warum mb?</p>
                    <h2 class="text-3xl font-extrabold text-white mb-4">Kommunikation aus einer Hand</h2>
                    <p class="text-blue-200 leading-relaxed">
                        Als Kommunikations-Generalistin begleite ich Sie ganzheitlich – von der Strategie über die Gestaltung bis zur Produktion. Kein Jonglieren mit verschiedenen Agenturen, kein Informationsverlust. Alles aus einer Hand.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <?php
                    $usps = [
                        ['icon' => 'shield-check', 'text' => 'Persönliche Betreuung'],
                        ['icon' => 'clock',        'text' => 'Termingerecht & verlässlich'],
                        ['icon' => 'map-pin',      'text' => 'Regional verwurzelt'],
                        ['icon' => 'award',        'text' => 'Jahrelange Erfahrung'],
                    ];
                    foreach ($usps as $usp): ?>
                    <div class="flex items-center gap-3 bg-white/10 rounded-xl p-4">
                        <div class="w-10 h-10 rounded-lg bg-accent/30 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="<?= $usp['icon'] ?>" class="w-5 h-5 text-white"></i>
                        </div>
                        <p class="text-white text-sm font-medium leading-tight"><?= e($usp['text']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- ================================================================
     CTA SECTION
     ================================================================ -->
<section class="py-24 bg-gray-50">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-accent/10 mb-8">
            <i data-lucide="message-square-plus" class="w-10 h-10 text-accent"></i>
        </div>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-primary mb-4">
            Bereit für starke Kommunikation?
        </h2>
        <p class="text-gray-500 text-lg mb-10">
            Nehmen Sie jetzt Kontakt auf und vereinbaren Sie ein unverbindliches Erstgespräch. Wir freuen uns auf Ihr Projekt!
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/kontakt.php"
               class="inline-flex items-center justify-center gap-2 bg-accent text-white font-bold px-10 py-4 rounded-xl shadow-lg hover:bg-accent-dark hover:shadow-xl transition-all duration-200">
                <i data-lucide="send" class="w-5 h-5"></i>
                Jetzt anfragen
            </a>
            <a href="/referenzen.php"
               class="inline-flex items-center justify-center gap-2 bg-white text-primary font-bold px-10 py-4 rounded-xl border-2 border-primary/10 hover:border-primary hover:bg-primary hover:text-white transition-all duration-200">
                <i data-lucide="star" class="w-5 h-5"></i>
                Referenzen ansehen
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
