<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
$auth->requireRole('viewer'); // minimum role to access admin

$auth->requireRole('admin');

$db        = Database::getInstance();
$csrfToken = $auth->getCsrfToken();

// ── Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        // Fetch all settings meta from DB to know expected keys/types
        $allMeta = $db->fetchAll('SELECT `key`, `type` FROM settings');
        $metaMap = [];
        foreach ($allMeta as $m) $metaMap[$m['key']] = $m['type'];

        foreach ($metaMap as $key => $type) {
            if ($type === 'boolean') {
                $value = isset($_POST['setting'][$key]) ? '1' : '0';
            } else {
                $value = trim($_POST['setting'][$key] ?? '');
                // Handle image upload
                if ($type === 'image' && isset($_FILES['setting_image'][$key])) {
                    $file = $_FILES['setting_image'][$key];
                    // (image uploaded via JS upload.php, so the hidden input carries the URL)
                }
            }
            $db->setSetting($key, $value);
        }

        $auth->flash('Einstellungen wurden gespeichert.', 'success');
        header('Location: settings.php');
        exit;
    }
}

// ── Load all settings ─────────────────────────────────────────────────────
$allSettings = $db->getAllSettings(); // keyed by key, each row has key/value/label/type

// Group by logical category
$groups = [
    'Allgemein' => [
        'site_name', 'site_tagline', 'hero_title', 'hero_subtitle', 'about_text',
    ],
    'Kontakt' => [
        'contact_email', 'contact_phone', 'contact_address',
    ],
    'Social Media' => [
        'facebook_url', 'instagram_url', 'linkedin_url',
    ],
    'SEO & Tracking' => [
        'google_analytics',
    ],
    'Erweitert' => [
        'maintenance_mode',
    ],
];

$pageTitle   = 'Einstellungen';
$currentPage = 'settings';
require_once 'layout.php';
?>

<form method="post" id="settingsForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="action"     value="save_settings">

    <div class="space-y-6">

        <?php foreach ($groups as $groupName => $keys): ?>
        <?php
        // Collect only existing settings for this group
        $groupSettings = [];
        foreach ($keys as $key) {
            if (isset($allSettings[$key])) $groupSettings[$key] = $allSettings[$key];
        }
        if (empty($groupSettings)) continue;
        ?>

        <div class="card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($groupName) ?></h3>
            </div>
            <div class="p-6 space-y-5">

                <?php foreach ($groupSettings as $key => $setting):
                $value = $setting['value'] ?? '';
                $label = $setting['label'] ?? $key;
                $type  = $setting['type']  ?? 'text';
                ?>

                <div class="<?= $type === 'boolean' ? 'flex items-center justify-between' : '' ?>">
                    <?php if ($type === 'boolean'): ?>
                    <!-- Toggle switch -->
                    <div>
                        <label class="text-sm font-medium text-gray-700"><?= htmlspecialchars($label) ?></label>
                        <?php if ($key === 'maintenance_mode'): ?>
                        <p class="text-xs text-gray-400 mt-0.5">Wenn aktiviert, sehen Besucher eine Wartungsseite.</p>
                        <?php endif; ?>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="setting[<?= htmlspecialchars($key) ?>]"
                               value="1" <?= $value === '1' ? 'checked' : '' ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer
                                    peer-checked:after:translate-x-full peer-checked:after:border-white
                                    after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                    after:bg-white after:border-gray-300 after:border after:rounded-full
                                    after:h-5 after:w-5 after:transition-all peer-checked:bg-accent"></div>
                    </label>

                    <?php elseif ($type === 'textarea'): ?>
                    <label class="form-label"><?= htmlspecialchars($label) ?></label>
                    <textarea name="setting[<?= htmlspecialchars($key) ?>]"
                              rows="4" class="form-input"><?= htmlspecialchars($value) ?></textarea>

                    <?php elseif ($type === 'image'): ?>
                    <label class="form-label"><?= htmlspecialchars($label) ?></label>
                    <div class="flex items-center gap-4">
                        <div id="imgWrap_<?= $key ?>" class="<?= $value ? '' : 'hidden' ?>">
                            <img id="imgPrev_<?= $key ?>" src="<?= htmlspecialchars($value) ?>"
                                 alt="" class="h-16 w-auto rounded-lg border border-gray-200 object-contain">
                        </div>
                        <div>
                            <input type="hidden" name="setting[<?= htmlspecialchars($key) ?>]"
                                   id="imgVal_<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
                            <label class="btn-gray cursor-pointer text-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Bild wählen
                                <input type="file" accept="image/*" class="hidden"
                                       onchange="uploadSettingImage(this, 'imgVal_<?= $key ?>', 'imgPrev_<?= $key ?>', 'imgWrap_<?= $key ?>')">
                            </label>
                        </div>
                    </div>

                    <?php elseif ($type === 'email'): ?>
                    <label class="form-label"><?= htmlspecialchars($label) ?></label>
                    <input type="email" name="setting[<?= htmlspecialchars($key) ?>]"
                           value="<?= htmlspecialchars($value) ?>" class="form-input">

                    <?php elseif ($type === 'tel'): ?>
                    <label class="form-label"><?= htmlspecialchars($label) ?></label>
                    <input type="tel" name="setting[<?= htmlspecialchars($key) ?>]"
                           value="<?= htmlspecialchars($value) ?>" class="form-input">

                    <?php elseif ($type === 'url'): ?>
                    <label class="form-label"><?= htmlspecialchars($label) ?></label>
                    <input type="url" name="setting[<?= htmlspecialchars($key) ?>]"
                           value="<?= htmlspecialchars($value) ?>" class="form-input"
                           placeholder="https://…">

                    <?php else: /* text */ ?>
                    <label class="form-label"><?= htmlspecialchars($label) ?></label>
                    <input type="text" name="setting[<?= htmlspecialchars($key) ?>]"
                           value="<?= htmlspecialchars($value) ?>" class="form-input">

                    <?php endif; ?>
                </div>

                <?php endforeach; ?>
            </div>
        </div>

        <?php endforeach; ?>

        <!-- Save button (sticky at bottom) -->
        <div class="sticky bottom-4 z-10">
            <div class="card p-4 flex items-center justify-between gap-4 shadow-lg border-primary/20">
                <p class="text-sm text-gray-500">Änderungen werden sofort aktiv.</p>
                <div class="flex gap-3">
                    <a href="settings.php" class="btn-gray">Abbrechen</a>
                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Einstellungen speichern
                    </button>
                </div>
            </div>
        </div>

    </div>
</form>

<script>
async function uploadSettingImage(inputEl, valId, prevId, wrapId) {
    if (!inputEl.files[0]) return;
    const formData = new FormData();
    formData.append('file', inputEl.files[0]);
    formData.append('csrf_token', '<?= htmlspecialchars($csrfToken) ?>');
    formData.append('type', 'general');
    try {
        const res  = await fetch('/admin/upload.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            document.getElementById(valId).value  = data.url;
            document.getElementById(prevId).src   = data.url;
            document.getElementById(wrapId).classList.remove('hidden');
        } else { alert(data.error || 'Upload fehlgeschlagen.'); }
    } catch(e) { alert('Upload-Fehler.'); }
}
</script>

<?php require_once 'layout_end.php'; ?>
