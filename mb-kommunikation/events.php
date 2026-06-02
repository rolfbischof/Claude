<?php
/**
 * mb Kommunikation + Events – Events
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$page_title  = 'Events';
$active_page = 'events';

$settings = get_settings();

// Load page data
$page_data = null;
try {
    $db = Database::getInstance();
    $page_data = $db->find('pages', 'events', 'slug');
} catch (Throwable $e) { /* silent */ }

$hero_title    = $page_data['title']    ?? 'Events';
$hero_subtitle = $page_data['subtitle'] ?? 'Unvergessliche Erlebnisse für Ihr Unternehmen';

// Load all published events
$all_events = [];
try {
    $db = Database::getInstance();
    $all_events = $db->fetchAll(
        "SELECT * FROM `events_portfolio`
          WHERE `published` = 1
          ORDER BY `sort_order` ASC, `event_date` DESC"
    );
} catch (Throwable $e) { /* silent */ }

// Build category list from DB entries
$event_categories = ['Alle'];
foreach ($all_events as $ev) {
    $cat = trim($ev['category'] ?? '');
    if ($cat && !in_array($cat, $event_categories, true)) {
        $event_categories[] = $cat;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ================================================================
     HERO SECTION
     ================================================================ -->
<section class="relative bg-primary overflow-hidden py-24 lg:py-32">
    <div class="absolute inset-0 opacity-5"
         style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:28px 28px;"></div>
    <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-accent/10 blur-3xl"></div>
    <div class="absolute -bottom-12 left-1/3 w-80 h-80 rounded-full bg-gold/10 blur-3xl"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-blue-300 text-sm mb-8">
                <a href="/" class="hover:text-white transition-colors">Home</a>
                <i data-lucide="chevron-right" class="w-4 h-4 flex-shrink-0"></i>
                <span class="text-white font-medium">Events</span>
            </nav>

            <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-4">Event-Management</p>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight mb-6">
                <?= e($hero_title) ?>
            </h1>
            <p class="text-blue-200 text-xl leading-relaxed mb-10 max-w-2xl">
                <?= e($hero_subtitle) ?>
            </p>
            <div class="flex flex-wrap gap-4">
                <a href="/kontakt.php"
                   class="inline-flex items-center gap-2 bg-accent text-white font-bold px-8 py-4 rounded-xl shadow-lg hover:bg-accent-dark transition-all duration-200">
                    <i data-lucide="calendar-plus" class="w-5 h-5"></i>
                    Event anfragen
                </a>
                <a href="#events-grid"
                   class="inline-flex items-center gap-2 bg-white/10 border border-white/20 text-white font-semibold px-8 py-4 rounded-xl hover:bg-white/20 transition-all duration-200">
                    <i data-lucide="grid" class="w-5 h-5"></i>
                    Portfolio ansehen
                </a>
            </div>
        </div>
    </div>

    <!-- Event type icons strip -->
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-16">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <?php
            $event_types = [
                ['icon' => 'building-2',  'label' => 'Firmenevents'],
                ['icon' => 'users',       'label' => 'Team-Events'],
                ['icon' => 'party-popper','label' => 'Feiern & Feste'],
                ['icon' => 'cake',        'label' => 'Jubiläen'],
            ];
            foreach ($event_types as $t): ?>
            <div class="flex items-center gap-3 bg-white/10 border border-white/15 rounded-xl px-4 py-3 backdrop-blur-sm">
                <i data-lucide="<?= $t['icon'] ?>" class="w-5 h-5 text-gold flex-shrink-0"></i>
                <span class="text-white text-sm font-medium"><?= e($t['label']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Bottom wave -->
    <div class="absolute bottom-0 left-0 right-0 leading-none">
        <svg viewBox="0 0 1440 60" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="w-full h-12">
            <path d="M0,60 L0,30 Q360,0 720,30 Q1080,60 1440,30 L1440,60 Z" fill="#f9fafb"/>
        </svg>
    </div>
</section>


<!-- ================================================================
     EVENTS GRID with Alpine.js filtering
     ================================================================ -->
<section id="events-grid" class="py-24 bg-gray-50">
    <div
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
        x-data="{
            activeFilter: 'Alle',
            filters: <?= json_encode($event_categories) ?>,
            events: <?= json_encode(array_map(function($e) {
                return [
                    'id'          => $e['id'],
                    'title'       => $e['title'],
                    'description' => $e['description'] ?? '',
                    'event_date'  => $e['event_date']  ?? '',
                    'location'    => $e['location']    ?? '',
                    'category'    => $e['category']    ?? '',
                    'image'       => $e['image']       ?? '',
                    'featured'    => (bool) $e['featured'],
                ];
            }, $all_events)) ?>,
            get filtered() {
                if (this.activeFilter === 'Alle') return this.events;
                return this.events.filter(e => e.category === this.activeFilter);
            }
        }"
    >
        <!-- Section Header -->
        <div class="text-center mb-12">
            <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-3">Portfolio</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-primary mb-4">Unsere Events</h2>
            <p class="text-gray-500 text-lg max-w-xl mx-auto">
                Jedes Event ist einzigartig – entdecken Sie eine Auswahl unserer realisierten Projekte.
            </p>
        </div>

        <!-- Filter Tabs -->
        <div class="flex flex-wrap justify-center gap-2 mb-12">
            <template x-for="filter in filters" :key="filter">
                <button
                    @click="activeFilter = filter"
                    :class="activeFilter === filter
                        ? 'bg-primary text-white shadow-md'
                        : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                    class="px-5 py-2.5 rounded-full text-sm font-semibold transition-all duration-200"
                    x-text="filter"
                ></button>
            </template>
        </div>

        <!-- Results count -->
        <p class="text-gray-400 text-sm text-center mb-8">
            <span x-text="filtered.length"></span> Events gefunden
        </p>

        <!-- Grid -->
        <template x-if="filtered.length > 0">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <template x-for="event in filtered" :key="event.id">
                    <article
                        x-show="true"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="group bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
                    >
                        <!-- Image -->
                        <div class="relative h-52 bg-gradient-to-br from-primary to-primary-light overflow-hidden">
                            <template x-if="event.image">
                                <img :src="'<?= rtrim(UPLOAD_URL, '/') ?>/' + event.image"
                                     :alt="event.title"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            </template>
                            <template x-if="!event.image">
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <svg class="w-16 h-16 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            </template>
                            <!-- Category badge -->
                            <span
                                x-show="event.category"
                                x-text="event.category"
                                class="absolute top-4 left-4 bg-accent text-white text-xs font-bold px-3 py-1.5 rounded-full"
                            ></span>
                            <!-- Featured badge -->
                            <template x-if="event.featured">
                                <span class="absolute top-4 right-4 bg-gold text-primary-dark text-xs font-bold px-2.5 py-1 rounded-full">
                                    Featured
                                </span>
                            </template>
                        </div>

                        <!-- Content -->
                        <div class="p-6">
                            <!-- Meta -->
                            <div class="flex items-center flex-wrap gap-3 text-xs text-gray-400 mb-3">
                                <template x-if="event.event_date">
                                    <span class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span x-text="new Date(event.event_date).toLocaleDateString('de-CH', {day:'2-digit',month:'2-digit',year:'numeric'})"></span>
                                    </span>
                                </template>
                                <template x-if="event.location">
                                    <span class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span x-text="event.location"></span>
                                    </span>
                                </template>
                            </div>

                            <h3
                                class="text-primary font-bold text-lg mb-2 group-hover:text-accent transition-colors line-clamp-2"
                                x-text="event.title"
                            ></h3>
                            <p
                                class="text-gray-500 text-sm leading-relaxed line-clamp-3"
                                x-text="event.description.length > 130 ? event.description.substring(0, 130) + '…' : event.description"
                            ></p>
                        </div>
                    </article>
                </template>
            </div>
        </template>

        <!-- Empty state -->
        <template x-if="filtered.length === 0">
            <div class="text-center py-20 bg-white rounded-2xl border border-gray-100">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <p class="text-gray-500 font-medium mb-1">Keine Events in dieser Kategorie</p>
                <button @click="activeFilter = 'Alle'" class="mt-3 text-accent text-sm font-semibold hover:underline">
                    Alle Events anzeigen
                </button>
            </div>
        </template>
    </div>
</section>


<!-- ================================================================
     EVENT SERVICES SECTION
     ================================================================ -->
<section id="konzept" class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-3">So arbeiten wir</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-primary mb-4">Unser Event-Service</h2>
            <p class="text-gray-500 text-lg max-w-2xl mx-auto">
                Von der ersten Idee bis zur gelungenen Feier – wir sind Ihr verlässlicher Partner.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-10">
            <?php
            $services = [
                [
                    'icon'  => 'lightbulb',
                    'title' => 'Eventberatung',
                    'color' => 'bg-primary/5 text-primary border-primary/20',
                    'icon_bg' => 'bg-primary/10',
                    'icon_color' => 'text-primary',
                    'items' => [
                        'Analyse Ihrer Bedürfnisse',
                        'Budgetplanung',
                        'Machbarkeitsprüfung',
                        'Empfehlungen & Ideen',
                        'Angebote von Dienstleistern',
                    ],
                ],
                [
                    'icon'  => 'file-text',
                    'title' => 'Eventkonzept',
                    'color' => 'bg-accent/5 text-accent border-accent/20',
                    'icon_bg' => 'bg-accent/10',
                    'icon_color' => 'text-accent',
                    'items' => [
                        'Kreatives Gesamtkonzept',
                        'Detaillierter Ablaufplan',
                        'Raumgestaltung & Dekoration',
                        'Catering-Koordination',
                        'Programm & Entertainment',
                    ],
                ],
                [
                    'icon'  => 'star',
                    'title' => 'Eventdurchführung',
                    'color' => 'bg-gold/10 text-yellow-700 border-gold/30',
                    'icon_bg' => 'bg-gold/10',
                    'icon_color' => 'text-yellow-600',
                    'items' => [
                        'Koordination am Eventtag',
                        'Leitung aller Beteiligten',
                        'Gästebetreuung',
                        'Troubleshooting',
                        'Nachbearbeitung & Auswertung',
                    ],
                ],
            ];
            foreach ($services as $svc): ?>
            <div id="durchfuehrung" class="border-2 <?= $svc['color'] ?> rounded-2xl p-8 hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center gap-4 mb-6">
                    <div class="flex items-center justify-center w-14 h-14 rounded-xl <?= $svc['icon_bg'] ?>">
                        <i data-lucide="<?= $svc['icon'] ?>" class="w-7 h-7 <?= $svc['icon_color'] ?>"></i>
                    </div>
                    <h3 class="text-primary font-bold text-xl"><?= e($svc['title']) ?></h3>
                </div>
                <ul class="space-y-3">
                    <?php foreach ($svc['items'] as $item): ?>
                    <li class="flex items-center gap-3 text-gray-600 text-sm">
                        <i data-lucide="check" class="w-4 h-4 text-green-500 flex-shrink-0"></i>
                        <?= e($item) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<!-- ================================================================
     CTA SECTION
     ================================================================ -->
<section class="py-24 bg-gradient-to-br from-primary via-primary to-[#1a4980] relative overflow-hidden">
    <div class="absolute inset-0 opacity-5"
         style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:28px 28px;"></div>
    <div class="absolute -top-20 -right-20 w-80 h-80 rounded-full bg-accent/10 blur-3xl"></div>
    <div class="absolute -bottom-16 -left-16 w-72 h-72 rounded-full bg-gold/10 blur-3xl"></div>

    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-white/10 mb-8">
            <i data-lucide="calendar-plus" class="w-10 h-10 text-white"></i>
        </div>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-white mb-4">
            Ihr nächstes Event planen?
        </h2>
        <p class="text-blue-200 text-lg mb-10 max-w-2xl mx-auto">
            Erzählen Sie uns von Ihrem Vorhaben – wir erstellen Ihnen gerne ein unverbindliches Angebot und begleiten Sie von der ersten Idee bis zur Durchführung.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/kontakt.php"
               class="inline-flex items-center justify-center gap-2 bg-accent text-white font-bold px-10 py-4 rounded-xl shadow-lg hover:bg-accent-dark hover:shadow-xl transition-all duration-200 text-base">
                <i data-lucide="send" class="w-5 h-5"></i>
                Jetzt Event anfragen
            </a>
            <a href="/referenzen.php"
               class="inline-flex items-center justify-center gap-2 bg-white/10 border border-white/30 text-white font-semibold px-10 py-4 rounded-xl hover:bg-white/20 transition-all duration-200 text-base">
                <i data-lucide="star" class="w-5 h-5"></i>
                Referenzen ansehen
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
