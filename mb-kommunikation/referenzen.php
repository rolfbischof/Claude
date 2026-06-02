<?php
/**
 * mb Kommunikation + Events – Referenzen
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$page_title  = 'Referenzen';
$active_page = 'referenzen';

$settings = get_settings();

// Load page data
$page_data = null;
try {
    $db = Database::getInstance();
    $page_data = $db->find('pages', 'referenzen', 'slug');
} catch (Throwable $e) { /* silent */ }

$hero_title    = $page_data['title']    ?? 'Referenzen';
$hero_subtitle = $page_data['subtitle'] ?? 'Projekte, die begeistern';

// Load all published references
$all_refs = [];
try {
    $db = Database::getInstance();
    $all_refs = $db->fetchAll(
        "SELECT * FROM `references_portfolio`
          WHERE `published` = 1
          ORDER BY `sort_order` ASC, `created_at` DESC"
    );
} catch (Throwable $e) { /* silent */ }

// Category labels
$cat_labels = [
    'kommunikation' => 'Kommunikation',
    'events'        => 'Events',
    'design'        => 'Design',
    'alle'          => 'Alle',
];

// Build unique filter list from DB data
$available_cats = ['alle'];
foreach ($all_refs as $r) {
    $cat = $r['category'] ?? 'alle';
    if ($cat !== 'alle' && !in_array($cat, $available_cats, true)) {
        $available_cats[] = $cat;
    }
}

// Prepare for Alpine: map image paths to full URLs
$refs_for_js = array_map(function($r) use ($cat_labels) {
    return [
        'id'          => $r['id'],
        'title'       => $r['title'],
        'client'      => $r['client']      ?? '',
        'description' => $r['description'] ?? '',
        'category'    => $r['category']    ?? 'alle',
        'cat_label'   => $cat_labels[$r['category'] ?? 'alle'] ?? ucfirst($r['category'] ?? ''),
        'image'       => !empty($r['image']) ? UPLOAD_URL . ltrim($r['image'], '/') : '',
        'url'         => $r['url']         ?? '',
        'featured'    => (bool) $r['featured'],
    ];
}, $all_refs);

require_once __DIR__ . '/includes/header.php';
?>

<!-- ================================================================
     HERO SECTION
     ================================================================ -->
<section class="relative bg-primary overflow-hidden py-24 lg:py-32">
    <div class="absolute inset-0 opacity-5"
         style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:28px 28px;"></div>
    <div class="absolute -top-24 right-0 w-96 h-96 rounded-full bg-gold/10 blur-3xl"></div>
    <div class="absolute bottom-0 -left-24 w-80 h-80 rounded-full bg-accent/10 blur-3xl"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-blue-300 text-sm mb-8">
                <a href="/" class="hover:text-white transition-colors">Home</a>
                <i data-lucide="chevron-right" class="w-4 h-4 flex-shrink-0"></i>
                <span class="text-white font-medium">Referenzen</span>
            </nav>

            <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-4">Portfolio</p>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight mb-6">
                <?= e($hero_title) ?>
            </h1>
            <p class="text-blue-200 text-xl leading-relaxed mb-10 max-w-2xl">
                <?= e($hero_subtitle) ?>
            </p>

            <!-- Stats -->
            <div class="flex flex-wrap gap-8">
                <?php
                $ref_stats = [
                    ['num' => count($all_refs) . '+', 'label' => 'Referenzprojekte'],
                    ['num' => '100+', 'label' => 'Zufriedene Kunden'],
                    ['num' => '3',    'label' => 'Kategorien'],
                ];
                foreach ($ref_stats as $rs): ?>
                <div>
                    <p class="text-2xl font-extrabold text-white"><?= e($rs['num']) ?></p>
                    <p class="text-blue-300 text-xs uppercase tracking-wider font-medium"><?= e($rs['label']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
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
     REFERENCES GRID with Alpine.js filtering + Lightbox
     ================================================================ -->
<section class="py-24 bg-gray-50">
    <div
        class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"
        x-data="{
            activeFilter: 'alle',
            refs: <?= json_encode($refs_for_js) ?>,
            available: <?= json_encode($available_cats) ?>,
            lightbox: false,
            selected: null,
            get filtered() {
                if (this.activeFilter === 'alle') return this.refs;
                return this.refs.filter(r => r.category === this.activeFilter);
            },
            openLightbox(ref) {
                this.selected = ref;
                this.lightbox = true;
                document.body.style.overflow = 'hidden';
            },
            closeLightbox() {
                this.lightbox = false;
                this.selected = null;
                document.body.style.overflow = '';
            }
        }"
        @keydown.escape.window="closeLightbox()"
    >
        <!-- Section header -->
        <div class="text-center mb-12">
            <p class="text-accent font-semibold text-sm uppercase tracking-widest mb-3">Unsere Arbeit</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-primary mb-4">Ausgewählte Projekte</h2>
            <p class="text-gray-500 text-lg max-w-xl mx-auto">
                Jedes Projekt ist einzigartig – klicken Sie für Details.
            </p>
        </div>

        <!-- Filter Tabs -->
        <div class="flex flex-wrap justify-center gap-2 mb-12">
            <?php
            $filter_labels = [
                'alle'          => 'Alle',
                'kommunikation' => 'Kommunikation',
                'events'        => 'Events',
                'design'        => 'Design',
            ];
            foreach ($available_cats as $cat_key):
                $cat_display = $filter_labels[$cat_key] ?? ucfirst($cat_key);
            ?>
            <button
                @click="activeFilter = '<?= e($cat_key) ?>'"
                :class="activeFilter === '<?= e($cat_key) ?>'
                    ? 'bg-primary text-white shadow-md'
                    : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
                class="px-5 py-2.5 rounded-full text-sm font-semibold transition-all duration-200"
            >
                <?= e($cat_display) ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Result count -->
        <p class="text-gray-400 text-sm text-center mb-8">
            <span x-text="filtered.length"></span> Projekte
        </p>

        <!-- Grid -->
        <template x-if="filtered.length > 0">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-7">
                <template x-for="ref in filtered" :key="ref.id">
                    <div
                        @click="openLightbox(ref)"
                        class="group bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 cursor-pointer"
                    >
                        <!-- Image -->
                        <div class="relative h-48 overflow-hidden">
                            <template x-if="ref.image">
                                <img :src="ref.image" :alt="ref.title"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            </template>
                            <template x-if="!ref.image">
                                <div class="w-full h-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            </template>
                            <!-- Hover overlay -->
                            <div class="absolute inset-0 bg-primary/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                <div class="flex items-center gap-2 bg-white text-primary font-semibold text-sm px-5 py-2.5 rounded-full shadow-lg">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Details ansehen
                                </div>
                            </div>
                            <!-- Category badge -->
                            <span
                                class="absolute top-3 left-3 text-xs font-bold px-3 py-1.5 rounded-full"
                                :class="{
                                    'bg-primary text-white':      ref.category === 'kommunikation',
                                    'bg-accent text-white':       ref.category === 'events',
                                    'bg-gold text-primary-dark':  ref.category === 'design',
                                    'bg-gray-200 text-gray-700':  ref.category === 'alle',
                                }"
                                x-text="ref.cat_label"
                            ></span>
                        </div>
                        <!-- Content -->
                        <div class="p-5">
                            <h3 class="text-primary font-bold text-lg mb-1 group-hover:text-accent transition-colors line-clamp-1" x-text="ref.title"></h3>
                            <p class="text-gray-400 text-xs font-medium mb-2" x-show="ref.client" x-text="ref.client"></p>
                            <p class="text-gray-500 text-sm leading-relaxed line-clamp-2"
                               x-text="ref.description.length > 100 ? ref.description.substring(0, 100) + '…' : ref.description"></p>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <!-- Empty state -->
        <template x-if="filtered.length === 0">
            <div class="text-center py-20 bg-white rounded-2xl border border-gray-100">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <p class="text-gray-500 font-medium">Keine Projekte in dieser Kategorie</p>
                <button @click="activeFilter = 'alle'" class="mt-3 text-accent text-sm font-semibold hover:underline">
                    Alle Projekte anzeigen
                </button>
            </div>
        </template>


        <!-- ================================================================
             LIGHTBOX MODAL
             ================================================================ -->
        <div
            x-show="lightbox"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-cloak
            class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            @click.self="closeLightbox()"
        >
            <div
                x-show="lightbox"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
                @click.stop
            >
                <template x-if="selected">
                    <div>
                        <!-- Image -->
                        <div class="relative h-64 sm:h-80 bg-gradient-to-br from-gray-100 to-gray-200 rounded-t-3xl overflow-hidden">
                            <template x-if="selected.image">
                                <img :src="selected.image" :alt="selected.title"
                                     class="w-full h-full object-cover">
                            </template>
                            <template x-if="!selected.image">
                                <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-primary to-primary-light">
                                    <svg class="w-20 h-20 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            </template>
                            <!-- Close button -->
                            <button
                                @click="closeLightbox()"
                                class="absolute top-4 right-4 w-10 h-10 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center text-gray-700 hover:bg-white hover:text-primary transition-colors shadow-lg"
                                aria-label="Schliessen"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="p-8">
                            <!-- Category badge -->
                            <span
                                class="inline-block text-xs font-bold uppercase tracking-wider px-3 py-1.5 rounded-full mb-4"
                                :class="{
                                    'bg-primary/10 text-primary':    selected.category === 'kommunikation',
                                    'bg-accent/10 text-accent':      selected.category === 'events',
                                    'bg-gold/20 text-yellow-700':    selected.category === 'design',
                                    'bg-gray-100 text-gray-600':     selected.category === 'alle',
                                }"
                                x-text="selected.cat_label"
                            ></span>

                            <h2 class="text-2xl font-extrabold text-primary mb-2" x-text="selected.title"></h2>
                            <p class="text-gray-400 text-sm font-medium mb-4" x-show="selected.client">
                                Kunde: <span class="text-gray-600 font-semibold" x-text="selected.client"></span>
                            </p>

                            <p class="text-gray-600 leading-relaxed text-base mb-6" x-text="selected.description"></p>

                            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                                <template x-if="selected.url">
                                    <a
                                        :href="selected.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-2 bg-primary text-white text-sm font-semibold px-5 py-2.5 rounded-xl hover:bg-primary-dark transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                        Projekt ansehen
                                    </a>
                                </template>
                                <a
                                    href="/kontakt.php"
                                    class="inline-flex items-center gap-2 bg-accent/10 text-accent text-sm font-semibold px-5 py-2.5 rounded-xl hover:bg-accent hover:text-white transition-all"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    Ähnliches Projekt anfragen
                                </a>
                                <button
                                    @click="closeLightbox()"
                                    class="ml-auto text-gray-400 hover:text-gray-600 text-sm font-medium transition-colors"
                                >
                                    Schliessen
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

    </div><!-- end Alpine scope -->
</section>


<!-- ================================================================
     CTA SECTION
     ================================================================ -->
<section class="py-24 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-primary/5 mb-8">
            <i data-lucide="briefcase" class="w-10 h-10 text-primary"></i>
        </div>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-primary mb-4">
            Ihr Projekt gehört auch hierher
        </h2>
        <p class="text-gray-500 text-lg mb-10 max-w-2xl mx-auto">
            Wir freuen uns darauf, auch für Sie ein Projekt zu realisieren, das begeistert. Nehmen Sie jetzt Kontakt auf!
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/kontakt.php"
               class="inline-flex items-center justify-center gap-2 bg-accent text-white font-bold px-10 py-4 rounded-xl shadow-lg hover:bg-accent-dark hover:shadow-xl transition-all duration-200">
                <i data-lucide="send" class="w-5 h-5"></i>
                Jetzt Kontakt aufnehmen
            </a>
            <a href="/kommunikation.php"
               class="inline-flex items-center justify-center gap-2 bg-white text-primary font-bold px-10 py-4 rounded-xl border-2 border-primary/10 hover:border-primary hover:bg-primary hover:text-white transition-all duration-200">
                <i data-lucide="layers" class="w-5 h-5"></i>
                Leistungen ansehen
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
