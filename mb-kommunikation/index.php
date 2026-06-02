<?php
/**
 * mb Kommunikation + Events – Startseite
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$page_title  = 'Startseite';
$active_page = 'home';

// Load settings
$settings = get_settings();

// Load 3 featured events
$featured_events = [];
try {
    $db = Database::getInstance();
    $featured_events = $db->fetchAll(
        "SELECT * FROM `events_portfolio`
          WHERE `published` = 1 AND `featured` = 1
          ORDER BY `sort_order` ASC, `event_date` DESC
          LIMIT 3"
    );
} catch (Throwable $e) {
    // silent – show empty state
}

// Load featured references
$featured_refs = [];
try {
    $db = Database::getInstance();
    $featured_refs = $db->fetchAll(
        "SELECT * FROM `references_portfolio`
          WHERE `published` = 1 AND `featured` = 1
          ORDER BY `sort_order` ASC
          LIMIT 6"
    );
} catch (Throwable $e) {
    // silent
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ================================================================
     HERO SECTION
     ================================================================ -->
<section class="relative overflow-hidden bg-primary min-h-[92vh] flex items-center">
    <!-- Background gradient layers -->
    <div class="absolute inset-0">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-dark via-primary to-[#1a4980]"></div>
        <!-- Decorative circles -->
        <div class="absolute -top-32 -right-32 w-[600px] h-[600px] rounded-full bg-accent/10 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-[500px] h-[500px] rounded-full bg-gold/10 blur-3xl"></div>
        <!-- Grid pattern overlay -->
        <div class="absolute inset-0 opacity-5"
             style="background-image:radial-gradient(#ffffff 1px,transparent 1px);background-size:32px 32px;"></div>
    </div>

    <!-- Animated floating shapes -->
    <div class="absolute top-20 right-1/4 w-16 h-16 rounded-2xl bg-accent/20 rotate-12 animate-pulse hidden lg:block"></div>
    <div class="absolute bottom-1/3 right-16 w-10 h-10 rounded-xl bg-gold/30 -rotate-6 animate-bounce hidden lg:block" style="animation-duration:3s;"></div>
    <div class="absolute top-1/3 left-16 w-8 h-8 rounded-lg bg-white/10 rotate-45 animate-pulse hidden lg:block" style="animation-duration:4s;"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 lg:py-32">
        <div class="grid lg:grid-cols-2 gap-12 items-center">

            <!-- Left: Text content -->
            <div class="text-center lg:text-left">
                <!-- Badge -->
                <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-4 py-1.5 mb-8">
                    <span class="block w-2 h-2 rounded-full bg-gold animate-pulse"></span>
                    <span class="text-white/90 text-sm font-medium">Hochdorf, Schweiz · Seit 2010</span>
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight tracking-tight mb-6">
                    Kommunikation
                    <span class="block text-accent">&amp; Events</span>
                    <span class="block text-3xl sm:text-4xl lg:text-5xl font-bold text-white/90">die begeistern.</span>
                </h1>

                <p class="text-blue-200 text-lg sm:text-xl leading-relaxed mb-10 max-w-xl mx-auto lg:mx-0">
                    <?= e($settings['hero_subtitle'] ?? 'Professionelle Kommunikationsberatung und unvergessliche Events – alles aus einer Hand in der Zentralschweiz.') ?>
                </p>

                <!-- CTAs -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                    <a href="/kommunikation.php"
                       class="inline-flex items-center justify-center gap-2 bg-accent text-white font-bold px-8 py-4 rounded-xl shadow-lg hover:bg-accent-dark hover:shadow-xl transition-all duration-200 text-base">
                        <i data-lucide="layers" class="w-5 h-5"></i>
                        Unsere Leistungen
                    </a>
                    <a href="/kontakt.php"
                       class="inline-flex items-center justify-center gap-2 bg-white/10 border border-white/30 text-white font-semibold px-8 py-4 rounded-xl hover:bg-white/20 transition-all duration-200 text-base backdrop-blur-sm">
                        <i data-lucide="send" class="w-5 h-5"></i>
                        Jetzt Kontakt aufnehmen
                    </a>
                </div>

                <!-- Trust signals -->
                <div class="mt-12 flex flex-wrap gap-6 justify-center lg:justify-start">
                    <?php
                    $trust = [
                        ['num' => '10+',  'label' => 'Jahre Erfahrung'],
                        ['num' => '100+', 'label' => 'Zufriedene Kunden'],
                        ['num' => '50+',  'label' => 'Events geplant'],
                    ];
                    foreach ($trust as $t): ?>
                    <div class="text-center lg:text-left">
                        <p class="text-2xl font-extrabold text-white"><?= e($t['num']) ?></p>
                        <p class="text-blue-300 text-xs font-medium uppercase tracking-wider"><?= e($t['label']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right: Visual card stack -->
            <div class="hidden lg:flex justify-center items-center relative">
                <!-- Main card -->
                <div class="relative bg-white/10 backdrop-blur-md border border-white/20 rounded-3xl p-8 shadow-2xl w-80">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-16 h-16 rounded-2xl bg-accent flex items-center justify-center shadow-lg">
                            <i data-lucide="calendar-heart" class="w-8 h-8 text-white"></i>
                        </div>
                        <div>
                            <p class="text-white font-bold text-lg">Ihr nächstes Event</p>
                            <p class="text-blue-300 text-sm">Professionell geplant</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <?php
                        $card_items = [
                            ['icon' => 'check-circle', 'text' => 'Konzept & Planung'],
                            ['icon' => 'check-circle', 'text' => 'Durchführung & Leitung'],
                            ['icon' => 'check-circle', 'text' => 'Nachbearbeitung'],
                        ];
                        foreach ($card_items as $item): ?>
                        <div class="flex items-center gap-3 bg-white/10 rounded-lg px-3 py-2.5">
                            <i data-lucide="<?= $item['icon'] ?>" class="w-4 h-4 text-gold flex-shrink-0"></i>
                            <span class="text-white/90 text-sm"><?= e($item['text']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Floating badge -->
                    <div class="absolute -top-4 -right-4 bg-gold text-primary-dark text-xs font-bold px-3 py-1.5 rounded-full shadow-lg">
                        Schweizer Qualität
                    </div>
                </div>

                <!-- Background decorative card -->
                <div class="absolute -bottom-6 -left-8 w-64 h-40 bg-accent/20 rounded-2xl border border-accent/20 -z-10 rotate-6"></div>
                <div class="absolute -top-8 right-4 w-48 h-32 bg-white/5 rounded-2xl border border-white/10 -z-10 -rotate-3"></div>
            </div>
        </div>
    </div>

    <!-- Bottom wave -->
    <div class="absolute bottom-0 left-0 right-0 leading-none">
        <svg viewBox="0 0 1440 80" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="w-full h-16">
            <path d="M0,80 L0,40 Q360,80 720,40 Q1080,0 1440,40 L1440,80 Z" fill="#ffffff"/>
        </svg>
    </div>
</section>


<!-- ================================================================
     SERVICES SECTION
     ================================================================ -->
<section id="leistungen" class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-16">
            <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-3">Was wir bieten</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-primary mb-4">Unsere Leistungen</h2>
            <p class="text-gray-500 text-lg max-w-2xl mx-auto">
                Von der ersten Idee bis zur erfolgreichen Umsetzung – wir begleiten Sie mit Expertise und Leidenschaft.
            </p>
        </div>

        <!-- Services Grid -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php
            $services = [
                [
                    'icon'     => 'message-circle',
                    'title'    => 'Kommunikationsberatung',
                    'desc'     => 'Strategische Beratung für Ihre interne und externe Kommunikation. Wir entwickeln massgeschneiderte Konzepte für Ihr Unternehmen.',
                    'color'    => 'bg-primary/5 text-primary',
                    'href'     => '/kommunikation.php',
                    'category' => 'Kommunikation',
                ],
                [
                    'icon'     => 'printer',
                    'title'    => 'Drucksachen',
                    'desc'     => 'Visitenkarten, Flyer, Broschüren und Newsletter – professionell gestaltet und produziert für Ihren perfekten Auftritt.',
                    'color'    => 'bg-accent/5 text-accent',
                    'href'     => '/kommunikation.php#drucksachen',
                    'category' => 'Kommunikation',
                ],
                [
                    'icon'     => 'monitor',
                    'title'    => 'Websites & Medien',
                    'desc'     => 'Website-Konzepte, Medienarbeit und digitale Präsenz – damit Ihre Botschaft auch online ankommt.',
                    'color'    => 'bg-gold/10 text-gold-dark',
                    'href'     => '/kommunikation.php#websites',
                    'category' => 'Kommunikation',
                ],
                [
                    'icon'     => 'lightbulb',
                    'title'    => 'Eventberatung',
                    'desc'     => 'Kompetente Beratung für Ihr nächstes Event. Wir analysieren Ihre Bedürfnisse und entwickeln das passende Konzept.',
                    'color'    => 'bg-primary/5 text-primary',
                    'href'     => '/events.php',
                    'category' => 'Events',
                ],
                [
                    'icon'     => 'file-text',
                    'title'    => 'Eventkonzept',
                    'desc'     => 'Durchdachte Eventkonzepte von der Ideenfindung über die Planung bis zum detaillierten Ablaufplan.',
                    'color'    => 'bg-accent/5 text-accent',
                    'href'     => '/events.php#konzept',
                    'category' => 'Events',
                ],
                [
                    'icon'     => 'star',
                    'title'    => 'Eventdurchführung',
                    'desc'     => 'Professionelle Durchführung von Firmenevents, Teamevents, Jubiläen und Feiern – wir kümmern uns um alles.',
                    'color'    => 'bg-gold/10 text-gold-dark',
                    'href'     => '/events.php#durchfuehrung',
                    'category' => 'Events',
                ],
            ];
            foreach ($services as $svc): ?>
            <div class="group bg-white border border-gray-100 rounded-2xl p-8 hover:shadow-xl hover:border-primary/20 transition-all duration-300 hover:-translate-y-1 flex flex-col">
                <!-- Icon -->
                <div class="flex items-center gap-4 mb-5">
                    <div class="flex items-center justify-center w-14 h-14 rounded-xl <?= $svc['color'] ?> bg-opacity-100 group-hover:scale-110 transition-transform duration-300">
                        <i data-lucide="<?= $svc['icon'] ?>" class="w-6 h-6"></i>
                    </div>
                    <span class="text-xs font-semibold uppercase tracking-widest text-gray-400"><?= e($svc['category']) ?></span>
                </div>
                <h3 class="text-primary font-bold text-xl mb-3"><?= e($svc['title']) ?></h3>
                <p class="text-gray-500 text-sm leading-relaxed flex-1"><?= e($svc['desc']) ?></p>
                <a href="<?= e($svc['href']) ?>"
                   class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-accent hover:text-accent-dark transition-colors">
                    Mehr erfahren
                    <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- ================================================================
     ABOUT SECTION
     ================================================================ -->
<section id="ueber-uns" class="py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-16 items-center">

            <!-- Left: Image + floating stats -->
            <div class="relative">
                <!-- Photo placeholder -->
                <div class="relative rounded-3xl overflow-hidden shadow-2xl aspect-[4/5] bg-gradient-to-br from-primary to-primary-light flex items-center justify-center">
                    <div class="text-center text-white p-8">
                        <div class="w-32 h-32 rounded-full bg-white/10 border-4 border-white/20 mx-auto mb-6 flex items-center justify-center">
                            <i data-lucide="user" class="w-16 h-16 text-white/50"></i>
                        </div>
                        <p class="text-white font-bold text-xl">Manuela Bischof</p>
                        <p class="text-blue-200 text-sm mt-1">mb Kommunikation + Events</p>
                        <p class="text-blue-300 text-xs mt-1">Hochdorf, Schweiz</p>
                    </div>
                    <!-- Decorative overlay -->
                    <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-primary-dark/60 to-transparent"></div>
                </div>

                <!-- Floating stat cards -->
                <div class="absolute -bottom-6 -right-6 bg-white rounded-2xl shadow-xl p-5 border border-gray-100">
                    <p class="text-3xl font-extrabold text-primary">10+</p>
                    <p class="text-gray-500 text-sm font-medium">Jahre Erfahrung</p>
                </div>
                <div class="absolute -top-6 -left-6 bg-accent text-white rounded-2xl shadow-xl p-5">
                    <p class="text-3xl font-extrabold">50+</p>
                    <p class="text-white/80 text-sm font-medium">Events</p>
                </div>
            </div>

            <!-- Right: Text -->
            <div>
                <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-3">Über mich</p>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-primary mb-6 leading-tight">
                    Leidenschaft für<br>
                    <span class="text-accent">Kommunikation & Events</span>
                </h2>
                <p class="text-gray-600 text-lg leading-relaxed mb-6">
                    <?= e($settings['about_text'] ?? 'Manuela Bischof ist die Inhaberin von mb Kommunikation + Events. Mit Leidenschaft und Professionalität berät sie Unternehmen in Kommunikationsfragen und organisiert unvergessliche Events.') ?>
                </p>
                <p class="text-gray-600 leading-relaxed mb-8">
                    Als erfahrene Kommunikationsexpertin und Eventmanagerin begleite ich Sie von der ersten Idee bis zur erfolgreichen Umsetzung. Ob massgeschneiderte Drucksachen, strategische Kommunikationskonzepte oder unvergessliche Firmenevents – ich bringe Ihr Unternehmen zum Strahlen.
                </p>

                <!-- Stats row -->
                <div class="grid grid-cols-3 gap-6 mb-10">
                    <?php
                    $stats = [
                        ['num' => '100+', 'label' => 'Zufriedene Kunden'],
                        ['num' => '50+',  'label' => 'Erfolgreiche Events'],
                        ['num' => '10+',  'label' => 'Jahre Erfahrung'],
                    ];
                    foreach ($stats as $stat): ?>
                    <div class="text-center bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                        <p class="text-2xl font-extrabold text-primary"><?= e($stat['num']) ?></p>
                        <p class="text-gray-500 text-xs font-medium mt-1 leading-tight"><?= e($stat['label']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Values -->
                <div class="space-y-4 mb-10">
                    <?php
                    $values = [
                        ['icon' => 'shield-check', 'title' => 'Schweizer Qualität',   'desc' => 'Höchste Standards und Zuverlässigkeit in jedem Projekt.'],
                        ['icon' => 'heart',         'title' => 'Persönlicher Service', 'desc' => 'Individuelle Betreuung und echte Partnerschaft.'],
                        ['icon' => 'zap',           'title' => 'Kreativität',          'desc' => 'Frische Ideen und innovative Lösungsansätze.'],
                    ];
                    foreach ($values as $v): ?>
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-lg bg-primary/5">
                            <i data-lucide="<?= $v['icon'] ?>" class="w-5 h-5 text-primary"></i>
                        </div>
                        <div>
                            <p class="text-primary font-semibold text-sm"><?= e($v['title']) ?></p>
                            <p class="text-gray-500 text-sm"><?= e($v['desc']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <a href="/kontakt.php"
                   class="inline-flex items-center gap-2 bg-primary text-white font-bold px-8 py-4 rounded-xl hover:bg-primary-dark transition-colors shadow-md hover:shadow-lg">
                    <i data-lucide="send" class="w-5 h-5"></i>
                    Jetzt Kontakt aufnehmen
                </a>
            </div>
        </div>
    </div>
</section>


<!-- ================================================================
     EVENTS PREVIEW SECTION
     ================================================================ -->
<section id="events" class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-12">
            <div>
                <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-3">Portfolio</p>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-primary">Aktuelle Events<br>&amp; Referenzen</h2>
            </div>
            <a href="/events.php"
               class="inline-flex items-center gap-2 text-primary font-semibold border-2 border-primary px-6 py-3 rounded-xl hover:bg-primary hover:text-white transition-all duration-200 self-start sm:self-auto">
                Alle Events
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>

        <?php if (!empty($featured_events)): ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($featured_events as $event): ?>
            <article class="group bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                <!-- Image area -->
                <div class="relative bg-gradient-to-br from-primary to-primary-light h-52 overflow-hidden">
                    <?php if (!empty($event['image'])): ?>
                        <img src="<?= e(upload_url($event['image'])) ?>"
                             alt="<?= e($event['title']) ?>"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <?php else: ?>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i data-lucide="calendar" class="w-16 h-16 text-white/20"></i>
                        </div>
                    <?php endif; ?>
                    <!-- Category badge -->
                    <?php if (!empty($event['category'])): ?>
                    <span class="absolute top-4 left-4 bg-accent text-white text-xs font-bold px-3 py-1.5 rounded-full">
                        <?= e($event['category']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <!-- Content -->
                <div class="p-6">
                    <div class="flex items-center gap-4 text-xs text-gray-400 mb-3">
                        <?php if (!empty($event['event_date'])): ?>
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                            <?= format_date($event['event_date']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($event['location'])): ?>
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                            <?= e($event['location']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-primary font-bold text-lg mb-2 group-hover:text-accent transition-colors">
                        <?= e($event['title']) ?>
                    </h3>
                    <p class="text-gray-500 text-sm leading-relaxed">
                        <?= e(truncate($event['description'] ?? '', 120)) ?>
                    </p>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Empty state -->
        <div class="text-center py-16 bg-gray-50 rounded-2xl">
            <i data-lucide="calendar" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
            <p class="text-gray-500 font-medium">Demnächst neue Events – bleiben Sie gespannt!</p>
            <a href="/kontakt.php" class="mt-4 inline-flex items-center gap-2 text-accent text-sm font-semibold hover:underline">
                Event anfragen <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>


<!-- ================================================================
     REFERENCES SECTION
     ================================================================ -->
<section id="referenzen" class="py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-14">
            <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-3">Unsere Kunden</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-primary mb-4">Ausgewählte Referenzen</h2>
            <p class="text-gray-500 text-lg max-w-xl mx-auto">
                Projekte, die wir mit Herzblut umgesetzt haben – von KMU bis Verein, von Kommunikation bis Event.
            </p>
        </div>

        <?php if (!empty($featured_refs)): ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($featured_refs as $ref):
                $cat_colors = [
                    'kommunikation' => 'bg-primary/5 text-primary',
                    'events'        => 'bg-accent/5 text-accent',
                    'design'        => 'bg-gold/10 text-yellow-700',
                    'alle'          => 'bg-gray-100 text-gray-600',
                ];
                $cat_class = $cat_colors[$ref['category']] ?? 'bg-gray-100 text-gray-600';
                $cat_labels = ['kommunikation' => 'Kommunikation', 'events' => 'Events', 'design' => 'Design', 'alle' => 'Alle'];
                $cat_label = $cat_labels[$ref['category']] ?? ucfirst($ref['category'] ?? '');
            ?>
            <div class="group bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-lg hover:border-primary/20 transition-all duration-300 hover:-translate-y-1">
                <!-- Category -->
                <div class="flex items-center justify-between mb-4">
                    <span class="inline-block text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-full <?= $cat_class ?>">
                        <?= e($cat_label) ?>
                    </span>
                    <?php if (!empty($ref['url'])): ?>
                    <a href="<?= e($ref['url']) ?>" target="_blank" rel="noopener"
                       class="text-gray-300 hover:text-primary transition-colors">
                        <i data-lucide="external-link" class="w-4 h-4"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <!-- Image -->
                <div class="h-32 bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl mb-4 overflow-hidden flex items-center justify-center">
                    <?php if (!empty($ref['image'])): ?>
                        <img src="<?= e(upload_url($ref['image'])) ?>"
                             alt="<?= e($ref['title']) ?>"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <?php else: ?>
                        <i data-lucide="briefcase" class="w-10 h-10 text-gray-300"></i>
                    <?php endif; ?>
                </div>
                <h3 class="text-primary font-bold text-lg mb-1 group-hover:text-accent transition-colors">
                    <?= e($ref['title']) ?>
                </h3>
                <?php if (!empty($ref['client'])): ?>
                <p class="text-gray-400 text-xs font-medium mb-2"><?= e($ref['client']) ?></p>
                <?php endif; ?>
                <p class="text-gray-500 text-sm leading-relaxed"><?= e(truncate($ref['description'] ?? '', 100)) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-16 bg-white rounded-2xl border border-gray-100">
            <i data-lucide="briefcase" class="w-12 h-12 text-gray-300 mx-auto mb-4"></i>
            <p class="text-gray-500">Referenzen folgen in Kürze.</p>
        </div>
        <?php endif; ?>

        <div class="text-center mt-10">
            <a href="/referenzen.php"
               class="inline-flex items-center gap-2 text-primary font-semibold border-2 border-primary px-8 py-3 rounded-xl hover:bg-primary hover:text-white transition-all duration-200">
                Alle Referenzen ansehen
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
</section>


<!-- ================================================================
     TESTIMONIAL SECTION
     ================================================================ -->
<section class="py-24 bg-primary relative overflow-hidden">
    <!-- Decorative elements -->
    <div class="absolute top-0 left-0 w-72 h-72 rounded-full bg-accent/10 blur-3xl -translate-x-1/2 -translate-y-1/2"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 rounded-full bg-gold/10 blur-3xl translate-x-1/3 translate-y-1/3"></div>
    <!-- Grid pattern -->
    <div class="absolute inset-0 opacity-5"
         style="background-image:radial-gradient(#ffffff 1px,transparent 1px);background-size:32px 32px;"></div>

    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <!-- Large quote mark -->
        <div class="text-8xl font-serif text-accent/30 leading-none mb-4 select-none">&ldquo;</div>

        <blockquote class="text-white text-xl sm:text-2xl lg:text-3xl font-light leading-relaxed mb-8 -mt-8">
            Manuela Bischof hat unseren Firmenjubiläum perfekt geplant und durchgeführt. Die Professionalität,
            Kreativität und persönliche Betreuung haben uns begeistert. Wir können mb Kommunikation + Events
            uneingeschränkt empfehlen!
        </blockquote>

        <div class="flex items-center justify-center gap-4">
            <div class="w-12 h-12 rounded-full bg-accent/30 border-2 border-accent/50 flex items-center justify-center">
                <i data-lucide="user" class="w-6 h-6 text-white/60"></i>
            </div>
            <div class="text-left">
                <p class="text-white font-semibold">Kundin aus Luzern</p>
                <p class="text-blue-300 text-sm">Geschäftsführerin, KMU</p>
            </div>
        </div>

        <!-- Star rating -->
        <div class="flex items-center justify-center gap-1 mt-6">
            <?php for ($i = 0; $i < 5; $i++): ?>
            <i data-lucide="star" class="w-5 h-5 text-gold fill-current" style="fill:#f5a623;"></i>
            <?php endfor; ?>
        </div>
    </div>
</section>


<!-- ================================================================
     CTA SECTION
     ================================================================ -->
<section class="py-24 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <!-- Icon -->
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-accent/10 mb-8">
            <i data-lucide="calendar-plus" class="w-10 h-10 text-accent"></i>
        </div>

        <h2 class="text-3xl sm:text-4xl font-extrabold text-primary mb-4">
            Bereit für Ihr nächstes Event?
        </h2>
        <p class="text-gray-500 text-lg mb-10 max-w-2xl mx-auto">
            Ob kleiner Teamanlass oder grosses Firmenjubiläum – wir planen und realisieren Ihr Event mit Schweizer Qualität und persönlichem Engagement. Nehmen Sie jetzt Kontakt auf!
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/kontakt.php"
               class="inline-flex items-center justify-center gap-2 bg-accent text-white font-bold px-10 py-4 rounded-xl shadow-lg hover:bg-accent-dark hover:shadow-xl transition-all duration-200 text-base">
                <i data-lucide="send" class="w-5 h-5"></i>
                Jetzt anfragen
            </a>
            <a href="tel:<?= preg_replace('/[^+\d]/', '', $settings['contact_phone'] ?? '') ?>"
               class="inline-flex items-center justify-center gap-2 bg-primary/5 text-primary font-bold px-10 py-4 rounded-xl border-2 border-primary/10 hover:bg-primary hover:text-white hover:border-primary transition-all duration-200 text-base">
                <i data-lucide="phone" class="w-5 h-5"></i>
                Anrufen
            </a>
        </div>

        <!-- Trust badges -->
        <div class="mt-14 grid grid-cols-2 sm:grid-cols-4 gap-6">
            <?php
            $badges = [
                ['icon' => 'shield-check', 'label' => 'Schweizer Qualität'],
                ['icon' => 'clock',        'label' => 'Pünktlich & zuverlässig'],
                ['icon' => 'heart',        'label' => 'Mit Herzblut'],
                ['icon' => 'award',        'label' => '10+ Jahre Erfahrung'],
            ];
            foreach ($badges as $b): ?>
            <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 rounded-xl border border-gray-100">
                <div class="w-10 h-10 rounded-lg bg-primary/5 flex items-center justify-center">
                    <i data-lucide="<?= $b['icon'] ?>" class="w-5 h-5 text-primary"></i>
                </div>
                <p class="text-xs font-semibold text-gray-600 text-center leading-tight"><?= e($b['label']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
