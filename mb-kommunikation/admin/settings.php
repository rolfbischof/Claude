<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('admin');

$db        = Database::getInstance();
$csrfToken = $auth->getCsrfToken();
$errors    = [];

// ── Settings definition ────────────────────────────────────────────────────
// Organised by tab. Each entry: [key, label, type, placeholder, hint]
// type: text | email | tel | url | textarea | boolean
$tabs = [
    'allgemein' => [
        'label'    => 'Allgemein',
        'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'settings' => [
            ['site_name',     'Website-Name',     'text',     'mb Kommunikation + Events',                         'Erscheint im Browser-Tab und in automatischen E-Mails.'],
            ['site_tagline',  'Tagline',           'text',     'Professionelle Kommunikation & Events',             'Kurze Beschreibung unter dem Site-Namen.'],
            ['hero_title',    'Hero-Titel',        'text',     'Kommunikation & Events',                            'Grosser Titel auf der Startseite.'],
            ['hero_subtitle', 'Hero-Untertitel',   'textarea', 'Professionelle Beratung für Ihr Unternehmen…',     'Beschreibungstext unterhalb des Hero-Titels.'],
            ['about_text',    'Über-mich Text',    'textarea', 'Manuela Bischof ist die Inhaberin…',               'Haupttext im Über-mich-Abschnitt der Startseite.'],
        ],
    ],
    'kontakt' => [
        'label'    => 'Kontakt',
        'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
        'settings' => [
            ['contact_email',   'Kontakt E-Mail',  'email', 'info@mb-kommunikation-events.ch', 'Wird im Kontaktformular und Impressum angezeigt.'],
            ['contact_phone',   'Telefon',         'tel',   '+41 41 xxx xx xx',                'Telefonnummer für Kontaktseite und Impressum.'],
            ['contact_address', 'Adresse',         'text',  'Hochdorf, Schweiz',               'Adresszeile für Kontaktseite und Impressum.'],
        ],
    ],
    'social' => [
        'label'    => 'Social Media',
        'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>',
        'settings' => [
            ['instagram_url', 'Instagram URL', 'url', 'https://www.instagram.com/…', 'Vollständige URL zum Instagram-Profil.'],
            ['facebook_url',  'Facebook URL',  'url', 'https://www.facebook.com/…',  'Vollständige URL zur Facebook-Seite.'],
            ['linkedin_url',  'LinkedIn URL',  'url', 'https://www.linkedin.com/…',  'Vollständige URL zum LinkedIn-Profil.'],
        ],
    ],
    'seo' => [
        'label'    => 'SEO',
        'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>',
        'settings' => [],
    ],
    'erweitert' => [
        'label'    => 'Erweitert',
        'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>',
        'settings' => [
            ['maintenance_mode', 'Wartungsmodus',       'boolean', '',             'Aktiviert eine Wartungsseite für Besucher. Admins können weiterhin zugreifen.'],
            ['google_analytics', 'Google Analytics ID', 'text',    'G-XXXXXXXXXX', 'Measurement-ID (z.B. G-XXXXXXXXXX). Leer lassen um Analytics zu deaktivieren.'],
        ],
    ],
];

// ── Load all settings from DB ──────────────────────────────────────────────
$allSettings = $db->fetchAll('SELECT * FROM settings ORDER BY `key`');
$settingsMap = [];
foreach ($allSettings as $s) {
    $settingsMap[$s['key']] = $s;
}

// Helper: get current DB value for a key
$getValue = static function (string $key) use (&$settingsMap): string {
    return (string) ($settingsMap[$key]['value'] ?? '');
};

// ── Load pages for SEO tab ─────────────────────────────────────────────────
$pages = $db->fetchAll(
    "SELECT slug, title, meta_title, meta_description FROM pages ORDER BY id"
);

// ── Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $tab        = $_POST['tab'] ?? 'allgemein';
        $savedCount = 0;

        if (!isset($tabs[$tab])) {
            $tab = 'allgemein';
        }

        // --- Save settings-table keys for this tab ---
        foreach ($tabs[$tab]['settings'] as [$key, $label, $type]) {

            if ($type === 'boolean') {
                $newVal = isset($_POST['setting'][$key]) ? '1' : '0';
            } else {
                $newVal = trim($_POST['setting'][$key] ?? '');

                if ($type === 'email' && $newVal !== '' && !filter_var($newVal, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = htmlspecialchars($label) . ': Ungültige E-Mail-Adresse.';
                    continue;
                }
                if ($type === 'url' && $newVal !== '' && !filter_var($newVal, FILTER_VALIDATE_URL)) {
                    $errors[] = htmlspecialchars($label) . ': Ungültige URL (https://… erforderlich).';
                    continue;
                }
            }

            // Upsert
            $existing = $db->fetchOne('SELECT `key` FROM settings WHERE `key` = ?', [$key]);
            if ($existing) {
                $db->update('settings', ['value' => $newVal], ['key' => $key]);
            } else {
                $db->insert('settings', [
                    'key'   => $key,
                    'value' => $newVal,
                    'label' => $label,
                    'type'  => $type,
                ]);
            }
            $settingsMap[$key]['value'] = $newVal; // keep local map fresh
            $savedCount++;
        }

        // --- Save SEO page meta fields (tab === 'seo') ---
        if ($tab === 'seo' && isset($_POST['page_meta']) && is_array($_POST['page_meta'])) {
            foreach ($_POST['page_meta'] as $slug => $metaData) {
                $slug  = preg_replace('/[^a-z0-9\-]/', '', (string) $slug);
                $metaT = trim($metaData['meta_title']        ?? '');
                $metaD = trim($metaData['meta_description']  ?? '');
                if ($slug !== '') {
                    $db->query(
                        'UPDATE pages SET meta_title = ?, meta_description = ?, updated_at = NOW() WHERE slug = ?',
                        [$metaT ?: null, $metaD ?: null, $slug]
                    );
                    $savedCount++;
                }
            }
            // Refresh pages after save
            $pages = $db->fetchAll("SELECT slug, title, meta_title, meta_description FROM pages ORDER BY id");
        }

        if (empty($errors)) {
            $auth->flash(
                $savedCount > 0 ? 'Einstellungen wurden erfolgreich gespeichert.' : 'Keine Änderungen erkannt.',
                $savedCount > 0 ? 'success' : 'info'
            );
            redirect('/admin/settings.php?tab=' . urlencode($tab));
        }
    }
}

// ── Active tab ─────────────────────────────────────────────────────────────
$activeTab = $_GET['tab'] ?? 'allgemein';
if (!isset($tabs[$activeTab])) {
    $activeTab = 'allgemein';
}

// On failed POST keep tab from form submission
if (!empty($errors) && isset($_POST['tab']) && isset($tabs[$_POST['tab']])) {
    $activeTab = $_POST['tab'];
}

$isMaintenance = ($getValue('maintenance_mode') === '1');

// Helper: get value – POST override first (for error repopulation), then DB
$getFormVal = static function (string $key) use (&$settingsMap, $errors): string {
    if (!empty($errors) && isset($_POST['setting'][$key])) {
        return (string) $_POST['setting'][$key];
    }
    return (string) ($settingsMap[$key]['value'] ?? '');
};

$pageTitle   = 'Einstellungen';
$currentPage = 'settings';
require_once 'layout.php';
?>

<!-- ── Maintenance warning banner ─────────────────────────────────────────── -->
<?php if ($isMaintenance): ?>
<div class="mb-5 flex items-start gap-3 p-4 bg-yellow-50 border border-yellow-300 rounded-xl text-sm text-yellow-800">
    <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
    </svg>
    <div>
        <strong>Wartungsmodus ist aktiv.</strong>
        Besucher sehen aktuell eine Wartungsseite.
        Deaktivieren Sie ihn im Tab «Erweitert».
    </div>
</div>
<?php endif; ?>

<!-- ── Validation errors ─────────────────────────────────────────────────── -->
<?php if (!empty($errors)): ?>
<div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl">
    <p class="text-sm font-semibold text-red-700 mb-1">Bitte korrigieren Sie folgende Fehler:</p>
    <ul class="list-disc list-inside space-y-0.5">
        <?php foreach ($errors as $err): ?>
        <li class="text-sm text-red-600"><?= $err ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- ── Alpine.js tab container ────────────────────────────────────────────── -->
<div x-data="{ activeTab: '<?= htmlspecialchars($activeTab) ?>' }">

    <!-- Tab navigation bar -->
    <div class="flex gap-1 bg-white rounded-xl p-1 shadow-sm border border-gray-100 mb-6 flex-wrap">
        <?php foreach ($tabs as $tabKey => $tabDef): ?>
        <button type="button"
                @click="activeTab = '<?= $tabKey ?>'"
                :class="activeTab === '<?= $tabKey ?>'
                    ? 'bg-primary text-white shadow-sm'
                    : 'text-gray-600 hover:bg-gray-100'"
                class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 flex-shrink-0">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <?= $tabDef['icon'] ?>
            </svg>
            <?= htmlspecialchars($tabDef['label']) ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         TAB: ALLGEMEIN
    ════════════════════════════════════════════════════════════════ -->
    <div x-show="activeTab === 'allgemein'" x-cloak>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action"     value="save_settings">
            <input type="hidden" name="tab"        value="allgemein">

            <div class="card p-6">
                <h2 class="text-base font-semibold text-gray-800 mb-1">Allgemeine Einstellungen</h2>
                <p class="text-sm text-gray-500 mb-6">Grundlegende Informationen und Texte Ihrer Website.</p>

                <div class="space-y-5">
                    <?php foreach ($tabs['allgemein']['settings'] as [$key, $label, $type, $placeholder, $hint]): ?>
                    <div>
                        <label for="set_<?= htmlspecialchars($key) ?>" class="form-label">
                            <?= htmlspecialchars($label) ?>
                        </label>
                        <?php if ($type === 'textarea'): ?>
                        <textarea id="set_<?= htmlspecialchars($key) ?>"
                                  name="setting[<?= htmlspecialchars($key) ?>]"
                                  rows="5"
                                  placeholder="<?= htmlspecialchars($placeholder) ?>"
                                  class="form-input"><?= htmlspecialchars($getFormVal($key)) ?></textarea>
                        <?php else: ?>
                        <input type="text"
                               id="set_<?= htmlspecialchars($key) ?>"
                               name="setting[<?= htmlspecialchars($key) ?>]"
                               value="<?= htmlspecialchars($getFormVal($key)) ?>"
                               placeholder="<?= htmlspecialchars($placeholder) ?>"
                               class="form-input">
                        <?php endif; ?>
                        <?php if ($hint !== ''): ?>
                        <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($hint) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-end mt-5">
                <button type="submit" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Allgemeine Einstellungen speichern
                </button>
            </div>
        </form>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         TAB: KONTAKT
    ════════════════════════════════════════════════════════════════ -->
    <div x-show="activeTab === 'kontakt'" x-cloak>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action"     value="save_settings">
            <input type="hidden" name="tab"        value="kontakt">

            <div class="card p-6">
                <h2 class="text-base font-semibold text-gray-800 mb-1">Kontaktdaten</h2>
                <p class="text-sm text-gray-500 mb-6">
                    Diese Angaben erscheinen auf der Kontaktseite, im Impressum und in automatischen E-Mails.
                </p>

                <div class="space-y-5">
                    <?php foreach ($tabs['kontakt']['settings'] as [$key, $label, $type, $placeholder, $hint]): ?>
                    <div>
                        <label for="set_<?= htmlspecialchars($key) ?>" class="form-label">
                            <?= htmlspecialchars($label) ?>
                        </label>
                        <input type="<?= htmlspecialchars($type) ?>"
                               id="set_<?= htmlspecialchars($key) ?>"
                               name="setting[<?= htmlspecialchars($key) ?>]"
                               value="<?= htmlspecialchars($getFormVal($key)) ?>"
                               placeholder="<?= htmlspecialchars($placeholder) ?>"
                               class="form-input">
                        <?php if ($hint !== ''): ?>
                        <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($hint) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-end mt-5">
                <button type="submit" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Kontaktdaten speichern
                </button>
            </div>
        </form>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         TAB: SOCIAL MEDIA
    ════════════════════════════════════════════════════════════════ -->
    <div x-show="activeTab === 'social'" x-cloak>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action"     value="save_settings">
            <input type="hidden" name="tab"        value="social">

            <div class="card p-6">
                <h2 class="text-base font-semibold text-gray-800 mb-1">Social-Media-Links</h2>
                <p class="text-sm text-gray-500 mb-6">
                    Vollständige URLs Ihrer Profile. Leer lassen um einen Link im Footer auszublenden.
                </p>

                <div class="space-y-6">

                    <?php
                    $socialIcons = [
                        'instagram_url' => [
                            'color' => 'text-pink-600',
                            'svg'   => '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>',
                        ],
                        'facebook_url' => [
                            'color' => 'text-blue-600',
                            'svg'   => '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>',
                        ],
                        'linkedin_url' => [
                            'color' => 'text-blue-700',
                            'svg'   => '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>',
                        ],
                    ];
                    foreach ($tabs['social']['settings'] as [$key, $label, $type, $placeholder, $hint]):
                        $icon = $socialIcons[$key] ?? ['color' => 'text-gray-500', 'svg' => ''];
                        $val  = $getFormVal($key);
                    ?>
                    <div>
                        <label for="set_<?= htmlspecialchars($key) ?>" class="form-label">
                            <span class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4 <?= $icon['color'] ?> flex-shrink-0"
                                     fill="currentColor" viewBox="0 0 24 24">
                                    <?= $icon['svg'] ?>
                                </svg>
                                <?= htmlspecialchars($label) ?>
                            </span>
                        </label>
                        <input type="url"
                               id="set_<?= htmlspecialchars($key) ?>"
                               name="setting[<?= htmlspecialchars($key) ?>]"
                               value="<?= htmlspecialchars($val) ?>"
                               placeholder="<?= htmlspecialchars($placeholder) ?>"
                               class="form-input">
                        <div class="flex items-center justify-between mt-1">
                            <?php if ($hint !== ''): ?>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($hint) ?></p>
                            <?php else: ?>
                            <span></span>
                            <?php endif; ?>
                            <?php if ($val !== ''): ?>
                            <a href="<?= htmlspecialchars($val) ?>" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1 text-xs text-primary hover:underline flex-shrink-0 ml-3">
                                Ansehen
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>
            </div>

            <div class="flex justify-end mt-5">
                <button type="submit" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Social-Media-Links speichern
                </button>
            </div>
        </form>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         TAB: SEO
    ════════════════════════════════════════════════════════════════ -->
    <div x-show="activeTab === 'seo'" x-cloak>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action"     value="save_settings">
            <input type="hidden" name="tab"        value="seo">

            <div class="space-y-5">

                <!-- Info banner -->
                <div class="flex items-start gap-3 p-4 bg-blue-50 border border-blue-100 rounded-xl">
                    <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm text-blue-700">
                        <p class="font-semibold mb-0.5">SEO-Einstellungen pro Seite</p>
                        <p>Optimieren Sie Meta-Titel und Beschreibung für jede Seite individuell.
                           Ideal: Titel 50–60 Zeichen, Beschreibung 150–160 Zeichen.</p>
                    </div>
                </div>

                <?php foreach ($pages as $pg):
                    $postMetaTitle = $_POST['page_meta'][$pg['slug']]['meta_title']        ?? null;
                    $postMetaDesc  = $_POST['page_meta'][$pg['slug']]['meta_description']  ?? null;
                    $currentTitle  = !empty($errors) && $postMetaTitle !== null ? $postMetaTitle : ($pg['meta_title']        ?? '');
                    $currentDesc   = !empty($errors) && $postMetaDesc  !== null ? $postMetaDesc  : ($pg['meta_description']  ?? '');
                ?>
                <div class="card p-6">
                    <div class="flex items-center gap-2 mb-5 pb-3 border-b border-gray-100">
                        <span class="badge bg-primary/10 text-primary">/<?= htmlspecialchars($pg['slug']) ?></span>
                        <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($pg['title']) ?></h3>
                    </div>
                    <div class="space-y-4">
                        <!-- Meta title -->
                        <div x-data="{ len: <?= mb_strlen($currentTitle, 'UTF-8') ?> }">
                            <div class="flex items-center justify-between mb-1">
                                <label class="form-label mb-0"
                                       for="seo_mt_<?= htmlspecialchars($pg['slug']) ?>">
                                    Meta-Titel
                                </label>
                                <span class="text-xs"
                                      :class="len > 60 ? 'text-red-500' : (len >= 40 ? 'text-green-600' : 'text-gray-400')"
                                      x-text="len + ' / 60 Zeichen'"></span>
                            </div>
                            <input type="text"
                                   id="seo_mt_<?= htmlspecialchars($pg['slug']) ?>"
                                   name="page_meta[<?= htmlspecialchars($pg['slug']) ?>][meta_title]"
                                   value="<?= htmlspecialchars($currentTitle) ?>"
                                   placeholder="<?= htmlspecialchars($pg['title']) ?> | mb Kommunikation + Events"
                                   maxlength="200"
                                   class="form-input"
                                   @input="len = $el.value.length">
                        </div>
                        <!-- Meta description -->
                        <div x-data="{ len: <?= mb_strlen($currentDesc, 'UTF-8') ?> }">
                            <div class="flex items-center justify-between mb-1">
                                <label class="form-label mb-0"
                                       for="seo_md_<?= htmlspecialchars($pg['slug']) ?>">
                                    Meta-Beschreibung
                                </label>
                                <span class="text-xs"
                                      :class="len > 160 ? 'text-red-500' : (len >= 120 ? 'text-green-600' : 'text-gray-400')"
                                      x-text="len + ' / 160 Zeichen'"></span>
                            </div>
                            <textarea id="seo_md_<?= htmlspecialchars($pg['slug']) ?>"
                                      name="page_meta[<?= htmlspecialchars($pg['slug']) ?>][meta_description]"
                                      rows="2"
                                      placeholder="Kurze, prägnante Beschreibung dieser Seite für Suchmaschinen…"
                                      maxlength="300"
                                      class="form-input"
                                      @input="len = $el.value.length"><?= htmlspecialchars($currentDesc) ?></textarea>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($pages)): ?>
                <div class="card p-10 text-center text-gray-400">
                    <p class="text-sm">Keine Seiten vorhanden.</p>
                </div>
                <?php endif; ?>

            </div>

            <div class="flex justify-end mt-5">
                <button type="submit" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    SEO-Einstellungen speichern
                </button>
            </div>
        </form>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         TAB: ERWEITERT
    ════════════════════════════════════════════════════════════════ -->
    <div x-show="activeTab === 'erweitert'" x-cloak>
        <form method="post"
              x-data="{ maintenance: <?= $getFormVal('maintenance_mode') === '1' ? 'true' : 'false' ?> }">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action"     value="save_settings">
            <input type="hidden" name="tab"        value="erweitert">

            <div class="space-y-5">

                <!-- ── Maintenance mode ── -->
                <div class="card p-6 transition-colors"
                     :class="maintenance ? 'border-yellow-300 bg-yellow-50/40' : ''">

                    <div class="flex items-start justify-between gap-6">
                        <div>
                            <h3 class="font-semibold text-gray-800 mb-1">Wartungsmodus</h3>
                            <p class="text-sm text-gray-500">
                                Wenn aktiviert, sehen Website-Besucher eine Wartungsseite.
                                Admin-Benutzer können sich weiterhin anmelden und alle Bereiche verwalten.
                            </p>
                        </div>
                        <!-- Toggle switch -->
                        <div class="flex-shrink-0 pt-0.5">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <!-- Hidden checkbox that actually submits -->
                                <input type="checkbox"
                                       name="setting[maintenance_mode]"
                                       value="1"
                                       class="sr-only"
                                       x-model="maintenance"
                                       <?= $getFormVal('maintenance_mode') === '1' ? 'checked' : '' ?>>
                                <!-- Visual toggle -->
                                <div class="relative w-12 h-6 rounded-full transition-colors duration-200"
                                     :class="maintenance ? 'bg-yellow-500' : 'bg-gray-300'">
                                    <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform duration-200"
                                         :class="maintenance ? 'translate-x-6' : 'translate-x-0'"></div>
                                </div>
                                <span class="text-sm font-medium select-none"
                                      :class="maintenance ? 'text-yellow-700' : 'text-gray-500'"
                                      x-text="maintenance ? 'Aktiv' : 'Inaktiv'"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Warning banner (shown when toggle is on) -->
                    <div x-show="maintenance"
                         x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-4 flex items-center gap-2.5 p-3 bg-yellow-100 border border-yellow-200 rounded-lg text-sm text-yellow-800">
                        <svg class="w-4 h-4 flex-shrink-0 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span><strong>Achtung:</strong> Wartungsmodus ist aktiviert. Besucher sehen aktuell keine Website-Inhalte.</span>
                    </div>
                </div>

                <!-- ── Google Analytics ── -->
                <div class="card p-6">
                    <h3 class="font-semibold text-gray-800 mb-1">Google Analytics</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Google Analytics 4 Measurement-ID.
                        Leer lassen um das Tracking zu deaktivieren (DSGVO-konform).
                    </p>
                    <div>
                        <label for="set_google_analytics" class="form-label">Measurement-ID</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <input type="text"
                                   id="set_google_analytics"
                                   name="setting[google_analytics]"
                                   value="<?= htmlspecialchars($getFormVal('google_analytics')) ?>"
                                   placeholder="G-XXXXXXXXXX"
                                   class="form-input pl-9">
                        </div>
                        <?php $gaVal = $getFormVal('google_analytics'); ?>
                        <?php if ($gaVal !== ''): ?>
                        <p class="text-xs text-green-600 mt-1.5 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Analytics konfiguriert: <?= htmlspecialchars($gaVal) ?>
                        </p>
                        <?php else: ?>
                        <p class="text-xs text-gray-400 mt-1.5">Analytics ist deaktiviert – kein Tracking-Code hinterlegt.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── System info ── -->
                <div class="card p-6">
                    <h3 class="font-semibold text-gray-800 mb-1">System-Informationen</h3>
                    <p class="text-sm text-gray-500 mb-4">Technische Übersicht der aktuellen Umgebung.</p>
                    <dl class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                        <?php
                        $sysInfo = [
                            'PHP Version'     => PHP_VERSION,
                            'Umgebung'        => APP_ENV,
                            'Zeitzone'        => date_default_timezone_get(),
                            'Datum / Uhrzeit' => date('d.m.Y H:i'),
                            'Upload-Limit'    => MAX_UPLOAD_SIZE_LABEL,
                            'Wartungsmodus'   => $isMaintenance ? 'Aktiv' : 'Deaktiviert',
                        ];
                        foreach ($sysInfo as $sLabel => $sVal): ?>
                        <div class="bg-gray-50 rounded-lg px-4 py-3">
                            <dt class="text-gray-400 text-xs uppercase tracking-wider mb-0.5"><?= htmlspecialchars($sLabel) ?></dt>
                            <dd class="text-gray-700 font-medium text-sm">
                                <?php if ($sLabel === 'Wartungsmodus'): ?>
                                <span class="badge <?= $isMaintenance ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' ?>">
                                    <?= htmlspecialchars($sVal) ?>
                                </span>
                                <?php else: ?>
                                <?= htmlspecialchars($sVal) ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php endforeach; ?>
                    </dl>
                </div>

            </div>

            <div class="flex justify-end mt-5">
                <button type="submit"
                        class="btn-primary transition-colors"
                        :class="maintenance ? 'bg-yellow-600 hover:bg-yellow-700' : ''">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Erweiterte Einstellungen speichern
                </button>
            </div>
        </form>
    </div>

</div><!-- /x-data tabs -->

<?php require_once 'layout_end.php'; ?>
