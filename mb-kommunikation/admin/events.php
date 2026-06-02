<?php
/**
 * mb Kommunikation + Events
 * Admin – Events Portfolio CRUD
 *
 * Table  : events_portfolio
 * Role   : editor+
 * Fields : id, title, description, event_date, location, category,
 *          image, published, featured, sort_order, created_by, created_at, updated_at
 */

declare(strict_types=1);

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireRole('editor');

$db     = Database::getInstance();
$csrf   = $auth->getCsrfToken();
$errors = [];

// ====================================================================
// UPLOAD HELPER
// ====================================================================

/**
 * Validate and save an uploaded image from $_FILES['image'].
 * Returns relative path ("events/filename.jpg") on success, '' if no
 * file was submitted, or '' + appends to $errors on failure.
 */
function handleEventImage(array $file, array &$errors): string
{
    // No file chosen – not an error
    if (!isset($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    // PHP upload error codes
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $phpErrors = [
            UPLOAD_ERR_INI_SIZE   => 'Datei überschreitet das PHP-Upload-Limit.',
            UPLOAD_ERR_FORM_SIZE  => 'Datei überschreitet das Formular-Limit.',
            UPLOAD_ERR_PARTIAL    => 'Datei wurde nur teilweise hochgeladen.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt.',
            UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht gespeichert werden.',
            UPLOAD_ERR_EXTENSION  => 'Upload durch PHP-Erweiterung blockiert.',
        ];
        $errors[] = $phpErrors[$file['error']] ?? 'Unbekannter Upload-Fehler (Code ' . $file['error'] . ').';
        return '';
    }

    // Size check: max 10 MB
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $errors[] = 'Bild ist zu gross. Maximum: ' . MAX_UPLOAD_SIZE_LABEL . '.';
        return '';
    }

    // MIME type via finfo (reads actual file content, not the claimed type)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    if (!array_key_exists($mimeType, $allowedMimes)) {
        $errors[] = 'Ungültiger Dateityp. Erlaubt: JPEG, PNG, GIF, WebP.';
        return '';
    }

    $ext = $allowedMimes[$mimeType];

    // Build destination directory
    $subDir  = 'events';
    $destDir = rtrim(UPLOAD_PATH, '/') . '/' . $subDir . '/';
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
        $errors[] = 'Upload-Verzeichnis konnte nicht erstellt werden.';
        return '';
    }

    // Generate collision-safe filename
    $originalBase = pathinfo(basename($file['name']), PATHINFO_FILENAME);
    $safeBase     = preg_replace('/[^a-z0-9\-_]/', '', strtolower($originalBase)) ?: 'event';
    $filename     = $safeBase . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath     = $destDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        $errors[] = 'Bild konnte nicht gespeichert werden.';
        return '';
    }

    return $subDir . '/' . $filename;
}

// ====================================================================
// POST HANDLER
// ====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireCsrf();
    $action = sanitize_input($_POST['action'] ?? '');

    // ── CREATE ────────────────────────────────────────────────────────
    if ($action === 'create') {

        $title       = sanitize_input($_POST['title']       ?? '');
        $description = trim($_POST['description'] ?? '');
        $eventDate   = sanitize_input($_POST['event_date']  ?? '');
        $location    = sanitize_input($_POST['location']    ?? '');
        $category    = sanitize_input($_POST['category']    ?? '');
        $published   = isset($_POST['published']) ? 1 : 0;
        $featured    = isset($_POST['featured'])  ? 1 : 0;
        $sortOrder   = max(0, (int) ($_POST['sort_order'] ?? 0));

        if ($title === '') {
            $errors[] = 'Titel ist ein Pflichtfeld.';
        }
        if ($eventDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            $errors[] = 'Ungültiges Datum (Format: JJJJ-MM-TT).';
            $eventDate = '';
        }

        $imagePath = handleEventImage($_FILES['image'] ?? [], $errors);

        if (empty($errors)) {
            $db->insert('events_portfolio', [
                'title'       => $title,
                'description' => $description,
                'event_date'  => $eventDate !== '' ? $eventDate : null,
                'location'    => $location,
                'category'    => $category,
                'image'       => $imagePath,
                'published'   => $published,
                'featured'    => $featured,
                'sort_order'  => $sortOrder,
                'created_by'  => $auth->getUserId(),
            ]);
            $auth->flash('Event «' . $title . '» wurde erstellt.', 'success');
            redirect('events.php');
        }

    // ── UPDATE ────────────────────────────────────────────────────────
    } elseif ($action === 'update') {

        $id          = (int) ($_POST['id'] ?? 0);
        $title       = sanitize_input($_POST['title']       ?? '');
        $description = trim($_POST['description'] ?? '');
        $eventDate   = sanitize_input($_POST['event_date']  ?? '');
        $location    = sanitize_input($_POST['location']    ?? '');
        $category    = sanitize_input($_POST['category']    ?? '');
        $published   = isset($_POST['published']) ? 1 : 0;
        $featured    = isset($_POST['featured'])  ? 1 : 0;
        $sortOrder   = max(0, (int) ($_POST['sort_order'] ?? 0));

        $existing = $db->find('events_portfolio', $id);
        if (!$existing) {
            $auth->flash('Event nicht gefunden.', 'error');
            redirect('events.php');
        }

        if ($title === '') {
            $errors[] = 'Titel ist ein Pflichtfeld.';
        }
        if ($eventDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            $errors[] = 'Ungültiges Datum (Format: JJJJ-MM-TT).';
            $eventDate = '';
        }

        $newImagePath = handleEventImage($_FILES['image'] ?? [], $errors);

        // Delete old image only when a new one was successfully uploaded
        if ($newImagePath !== '' && !empty($existing['image'])) {
            delete_file($existing['image']);
        }

        // Keep existing image when no new file was provided
        $imagePath = $newImagePath !== '' ? $newImagePath : ($existing['image'] ?? '');

        if (empty($errors)) {
            $db->update('events_portfolio', [
                'title'       => $title,
                'description' => $description,
                'event_date'  => $eventDate !== '' ? $eventDate : null,
                'location'    => $location,
                'category'    => $category,
                'image'       => $imagePath,
                'published'   => $published,
                'featured'    => $featured,
                'sort_order'  => $sortOrder,
            ], ['id' => $id]);
            $auth->flash('Event «' . $title . '» wurde gespeichert.', 'success');
            redirect('events.php');
        }

    // ── TOGGLE PUBLISHED ─────────────────────────────────────────────
    } elseif ($action === 'toggle_published') {
        $id  = (int) ($_POST['id'] ?? 0);
        $row = $db->find('events_portfolio', $id);
        if ($row) {
            $db->update('events_portfolio', ['published' => $row['published'] ? 0 : 1], ['id' => $id]);
            $auth->flash('Veröffentlichungs-Status geändert.', 'success');
        }
        $cat = sanitize_input($_POST['cat'] ?? '');
        redirect('events.php' . ($cat !== '' ? '?cat=' . urlencode($cat) : ''));

    // ── TOGGLE FEATURED ──────────────────────────────────────────────
    } elseif ($action === 'toggle_featured') {
        $id  = (int) ($_POST['id'] ?? 0);
        $row = $db->find('events_portfolio', $id);
        if ($row) {
            $db->update('events_portfolio', ['featured' => $row['featured'] ? 0 : 1], ['id' => $id]);
            $auth->flash('Highlight-Status geändert.', 'success');
        }
        $cat = sanitize_input($_POST['cat'] ?? '');
        redirect('events.php' . ($cat !== '' ? '?cat=' . urlencode($cat) : ''));

    // ── DELETE ────────────────────────────────────────────────────────
    } elseif ($action === 'delete') {
        $id  = (int) ($_POST['id'] ?? 0);
        $row = $db->find('events_portfolio', $id);
        if ($row) {
            if (!empty($row['image'])) {
                delete_file($row['image']);
            }
            $db->delete('events_portfolio', ['id' => $id]);
            $auth->flash('Event wurde gelöscht.', 'success');
        }
        $cat = sanitize_input($_POST['cat'] ?? '');
        redirect('events.php' . ($cat !== '' ? '?cat=' . urlencode($cat) : ''));

    // ── SORT UP ───────────────────────────────────────────────────────
    } elseif ($action === 'sort_up') {
        $id  = (int) ($_POST['id'] ?? 0);
        $row = $db->find('events_portfolio', $id);
        if ($row) {
            $prev = $db->fetchOne(
                'SELECT id, sort_order FROM events_portfolio
                  WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1',
                [$row['sort_order']]
            );
            if ($prev) {
                $db->update('events_portfolio', ['sort_order' => $prev['sort_order']], ['id' => $row['id']]);
                $db->update('events_portfolio', ['sort_order' => $row['sort_order']],  ['id' => $prev['id']]);
            } else {
                // Already first; push one step up numerically
                $db->update('events_portfolio', ['sort_order' => max(0, $row['sort_order'] - 1)], ['id' => $id]);
            }
        }
        $cat = sanitize_input($_POST['cat'] ?? '');
        redirect('events.php' . ($cat !== '' ? '?cat=' . urlencode($cat) : ''));

    // ── SORT DOWN ─────────────────────────────────────────────────────
    } elseif ($action === 'sort_down') {
        $id  = (int) ($_POST['id'] ?? 0);
        $row = $db->find('events_portfolio', $id);
        if ($row) {
            $next = $db->fetchOne(
                'SELECT id, sort_order FROM events_portfolio
                  WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1',
                [$row['sort_order']]
            );
            if ($next) {
                $db->update('events_portfolio', ['sort_order' => $next['sort_order']], ['id' => $row['id']]);
                $db->update('events_portfolio', ['sort_order' => $row['sort_order']],  ['id' => $next['id']]);
            } else {
                $db->update('events_portfolio', ['sort_order' => $row['sort_order'] + 1], ['id' => $id]);
            }
        }
        $cat = sanitize_input($_POST['cat'] ?? '');
        redirect('events.php' . ($cat !== '' ? '?cat=' . urlencode($cat) : ''));
    }
}

// ====================================================================
// GET – build list data
// ====================================================================

$filterCat  = sanitize_input($_GET['cat']  ?? '');
$page       = max(1, (int) ($_GET['page']  ?? 1));
$perPage    = ADMIN_ITEMS_PER_PAGE;

// Detect edit mode (GET ?edit=ID) or re-open form after failed POST
$editId  = (int) ($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    $editRow = $db->find('events_portfolio', $editId);
}
if (!empty($errors) && isset($_POST['id'])) {
    // Failed POST: keep form open with submitted id
    $editId  = (int) $_POST['id'];
    $editRow = $editId > 0 ? $db->find('events_portfolio', $editId) : null;
}
// For a failed CREATE, editId stays 0 and editRow stays null (form stays open)
$formOpen = $editRow !== null || $editId === 0 && !empty($errors);

// Recover submitted values on error
$posted = !empty($errors) && !empty($_POST) ? $_POST : [];

// Category filter for query
$whereSQL = $filterCat !== '' ? 'WHERE category = ?' : '';
$params   = $filterCat !== '' ? [$filterCat] : [];

$totalCount = (int) $db->fetchColumn(
    "SELECT COUNT(*) FROM events_portfolio $whereSQL", $params
);
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$events = $db->fetchAll(
    "SELECT * FROM events_portfolio $whereSQL
     ORDER BY sort_order ASC, created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

// Per-category counts for filter pills
$catRows = $db->fetchAll(
    "SELECT category, COUNT(*) AS cnt FROM events_portfolio
     WHERE category != '' GROUP BY category ORDER BY category"
);
$catCounts = [];
foreach ($catRows as $row) {
    $catCounts[$row['category']] = (int) $row['cnt'];
}
$totalAll = (int) $db->fetchColumn('SELECT COUNT(*) FROM events_portfolio');

// Canonical category list (for form select + filter pills)
$EVENT_CATEGORIES = ['Konzert', 'Geburtstagsfeier', 'Firmenanlass', 'Team-Event', 'Jubiläum', 'Sonstiges'];

// ====================================================================
// URL helper
// ====================================================================

function eventsUrl(string $cat = '', int $pg = 1): string {
    $p = [];
    if ($cat !== '') $p['cat']  = $cat;
    if ($pg  >    1) $p['page'] = $pg;
    return 'events.php' . ($p ? '?' . http_build_query($p) : '');
}

// ====================================================================
// Layout
// ====================================================================

$pageTitle   = 'Events';
$currentPage = 'events';
require_once 'layout.php';

// Resolve form field values: prefer submitted POST data (on error), then
// existing DB row (on edit), then defaults.
$fv = [
    'title'       => $posted['title']       ?? ($editRow['title']       ?? ''),
    'description' => $posted['description'] ?? ($editRow['description'] ?? ''),
    'event_date'  => $posted['event_date']  ?? ($editRow['event_date']  ?? ''),
    'location'    => $posted['location']    ?? ($editRow['location']    ?? ''),
    'category'    => $posted['category']   ?? ($editRow['category']    ?? ''),
    'sort_order'  => $posted['sort_order']  ?? ($editRow['sort_order']  ?? 0),
    'published'   => array_key_exists('published', $posted)
                        ? (bool) $posted['published']
                        : (bool) ($editRow['published'] ?? 1),
    'featured'    => array_key_exists('featured', $posted)
                        ? (bool) $posted['featured']
                        : (bool) ($editRow['featured'] ?? 0),
];
?>

<!-- ================================================================
     ADD / EDIT FORM  (collapsible via Alpine.js)
================================================================ -->
<div x-data="{
        open: <?= ($formOpen || $editRow) ? 'true' : 'false' ?>,
        preview: '<?= ($editRow && $editRow['image']) ? htmlspecialchars(upload_url($editRow['image']), ENT_QUOTES) : '' ?>'
     }"
     class="mb-6">

    <!-- Toggle bar -->
    <div class="card">
        <button type="button"
                @click="open = !open"
                class="w-full flex items-center justify-between px-5 py-4 text-left group">
            <span class="font-semibold text-gray-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 4v16m8-8H4"/>
                </svg>
                <?= $editRow ? 'Event bearbeiten' : 'Neues Event hinzufügen' ?>
            </span>
            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200"
                 :class="open ? 'rotate-180' : ''"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <!-- Form body -->
        <div x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="border-t border-gray-100">

            <?php if (!empty($errors)): ?>
            <div class="mx-5 mt-4 p-4 bg-red-50 border border-red-200 rounded-xl">
                <?php foreach ($errors as $err): ?>
                <p class="text-sm text-red-700"><?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="p-5 space-y-5">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action"
                       value="<?= $editRow ? 'update' : 'create' ?>">
                <?php if ($editRow): ?>
                <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

                    <!-- Titel -->
                    <div class="lg:col-span-2">
                        <label class="form-label">
                            Titel <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="title" required
                               value="<?= htmlspecialchars((string) $fv['title']) ?>"
                               class="form-input"
                               placeholder="Event-Titel eingeben">
                    </div>

                    <!-- Kategorie -->
                    <div>
                        <label class="form-label">Kategorie</label>
                        <select name="category" class="form-input">
                            <option value="">– keine –</option>
                            <?php foreach ($EVENT_CATEGORIES as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"
                                    <?= $fv['category'] === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Datum -->
                    <div>
                        <label class="form-label">Datum</label>
                        <input type="date" name="event_date"
                               value="<?= htmlspecialchars((string) $fv['event_date']) ?>"
                               class="form-input">
                    </div>

                    <!-- Ort -->
                    <div>
                        <label class="form-label">Ort / Location</label>
                        <input type="text" name="location"
                               value="<?= htmlspecialchars((string) $fv['location']) ?>"
                               class="form-input"
                               placeholder="z.B. Hochdorf, Luzern">
                    </div>

                    <!-- Reihenfolge -->
                    <div>
                        <label class="form-label">Reihenfolge</label>
                        <input type="number" name="sort_order" min="0"
                               value="<?= (int) $fv['sort_order'] ?>"
                               class="form-input">
                    </div>

                    <!-- Beschreibung -->
                    <div class="md:col-span-2">
                        <label class="form-label">Beschreibung</label>
                        <textarea name="description" rows="4"
                                  class="form-input"
                                  placeholder="Kurze Beschreibung des Events…"><?= htmlspecialchars((string) $fv['description']) ?></textarea>
                    </div>

                    <!-- Bild-Upload -->
                    <div>
                        <label class="form-label">Bild</label>

                        <!-- Live preview -->
                        <div x-show="preview" class="mb-2">
                            <img :src="preview" alt="Vorschau"
                                 class="w-full h-36 object-cover rounded-lg border border-gray-200">
                        </div>

                        <label class="flex flex-col items-center justify-center gap-2
                                      border-2 border-dashed border-gray-300 rounded-lg
                                      p-4 cursor-pointer transition-colors
                                      hover:border-primary hover:bg-primary/5">
                            <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586
                                         a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0
                                         002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-xs text-gray-500">
                                <?= ($editRow && $editRow['image']) ? 'Bild ersetzen (optional)' : 'Bild hochladen' ?>
                            </span>
                            <span class="text-xs text-gray-400">JPEG · PNG · GIF · WebP · max. 10 MB</span>
                            <input type="file" name="image" accept="image/*" class="hidden"
                                   @change="
                                       const f = $event.target.files[0];
                                       if (f) {
                                           const r = new FileReader();
                                           r.onload = e => preview = e.target.result;
                                           r.readAsDataURL(f);
                                       }
                                   ">
                        </label>

                        <?php if ($editRow && $editRow['image']): ?>
                        <p class="text-xs text-gray-400 mt-1 truncate">
                            Aktuell: <?= htmlspecialchars(basename($editRow['image'])) ?>
                        </p>
                        <?php endif; ?>
                    </div>

                    <!-- Status toggles -->
                    <div class="flex flex-wrap gap-x-6 gap-y-3 items-center md:col-span-2 lg:col-span-3 pt-1">
                        <!-- Published -->
                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <div class="relative flex-shrink-0">
                                <input type="checkbox" name="published" value="1"
                                       class="sr-only peer"
                                       <?= $fv['published'] ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer
                                            peer-checked:bg-primary
                                            peer-checked:after:translate-x-full peer-checked:after:border-white
                                            after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                            after:bg-white after:border-gray-300 after:border after:rounded-full
                                            after:h-5 after:w-5 after:transition-all"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Veröffentlicht</span>
                        </label>
                        <!-- Featured -->
                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <div class="relative flex-shrink-0">
                                <input type="checkbox" name="featured" value="1"
                                       class="sr-only peer"
                                       <?= $fv['featured'] ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer
                                            peer-checked:bg-gold
                                            peer-checked:after:translate-x-full peer-checked:after:border-white
                                            after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                            after:bg-white after:border-gray-300 after:border after:rounded-full
                                            after:h-5 after:w-5 after:transition-all"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-700">Highlight / Featured</span>
                        </label>
                    </div>

                </div><!-- /grid -->

                <div class="flex flex-wrap items-center gap-3 pt-3 border-t border-gray-100">
                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <?= $editRow ? 'Änderungen speichern' : 'Event erstellen' ?>
                    </button>
                    <?php if ($editRow): ?>
                    <a href="events.php<?= $filterCat ? '?cat=' . urlencode($filterCat) : '' ?>"
                       class="btn-gray">Abbrechen</a>
                    <?php else: ?>
                    <button type="reset" @click="preview=''" class="btn-gray">
                        Zurücksetzen
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div><!-- /collapsible -->
    </div><!-- /card -->
</div><!-- /x-data form -->


<!-- ================================================================
     FILTER PILLS + LIST HEADER
================================================================ -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">

    <!-- Category filter pills -->
    <div class="flex flex-wrap gap-2">
        <!-- "Alle" pill -->
        <a href="<?= htmlspecialchars(eventsUrl()) ?>"
           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                  <?= $filterCat === ''
                      ? 'bg-primary text-white'
                      : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
            Alle
            <span class="ml-0.5 px-1.5 py-0.5 rounded-full text-xs font-bold
                         <?= $filterCat === '' ? 'bg-white/25 text-white' : 'bg-gray-200 text-gray-600' ?>">
                <?= $totalAll ?>
            </span>
        </a>
        <?php foreach ($EVENT_CATEGORIES as $cat):
              $cnt = $catCounts[$cat] ?? 0;
              $active = $filterCat === $cat;
        ?>
        <a href="<?= htmlspecialchars(eventsUrl($cat)) ?>"
           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                  <?= $active ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
            <?= htmlspecialchars($cat) ?>
            <?php if ($cnt > 0): ?>
            <span class="ml-0.5 px-1.5 py-0.5 rounded-full text-xs font-bold
                         <?= $active ? 'bg-white/25 text-white' : 'bg-gray-200 text-gray-600' ?>">
                <?= $cnt ?>
            </span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <p class="text-sm text-gray-500">
        <?= $totalCount ?> Event<?= $totalCount !== 1 ? 's' : '' ?>
        <?= $filterCat ? ' in «' . htmlspecialchars($filterCat) . '»' : ' gesamt' ?>
    </p>
</div>


<!-- ================================================================
     EVENTS TABLE
================================================================ -->
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="table-th w-14">Bild</th>
                    <th class="table-th">Titel</th>
                    <th class="table-th">Datum</th>
                    <th class="table-th">Ort</th>
                    <th class="table-th">Kategorie</th>
                    <th class="table-th">Status</th>
                    <th class="table-th text-center w-24">Sort</th>
                    <th class="table-th text-right">Aktionen</th>
                </tr>
            </thead>
            <tbody>

                <?php if (empty($events)): ?>
                <tr>
                    <td colspan="8" class="table-td text-center py-12 text-gray-400">
                        <svg class="w-10 h-10 mx-auto mb-3 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Keine Events gefunden.
                        <?php if ($filterCat): ?>
                        <br><a href="events.php" class="text-primary hover:underline text-sm mt-1 inline-block">
                            Filter zurücksetzen
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($events as $ev): ?>
                <tr class="table-tr">

                    <!-- Thumbnail -->
                    <td class="table-td">
                        <?php if (!empty($ev['image'])): ?>
                        <img src="<?= htmlspecialchars(upload_url($ev['image'])) ?>"
                             alt="<?= htmlspecialchars($ev['title']) ?>"
                             class="w-12 h-12 object-cover rounded-lg border border-gray-200 flex-shrink-0">
                        <?php else: ?>
                        <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586
                                         a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0
                                         002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <?php endif; ?>
                    </td>

                    <!-- Titel + Beschreibungsschnipsel -->
                    <td class="table-td max-w-[220px]">
                        <p class="font-medium text-gray-800 truncate"><?= htmlspecialchars($ev['title']) ?></p>
                        <?php if ($ev['description']): ?>
                        <p class="text-xs text-gray-400 truncate mt-0.5">
                            <?= htmlspecialchars(truncate_text($ev['description'], 60)) ?>
                        </p>
                        <?php endif; ?>
                    </td>

                    <!-- Datum -->
                    <td class="table-td text-gray-600 text-xs whitespace-nowrap">
                        <?= $ev['event_date'] ? format_date($ev['event_date']) : '–' ?>
                    </td>

                    <!-- Ort -->
                    <td class="table-td text-gray-600 text-xs max-w-[110px] truncate">
                        <?= $ev['location'] ? htmlspecialchars($ev['location']) : '–' ?>
                    </td>

                    <!-- Kategorie -->
                    <td class="table-td">
                        <?php if ($ev['category']): ?>
                        <span class="badge bg-blue-50 text-blue-700">
                            <?= htmlspecialchars($ev['category']) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-gray-400 text-xs">–</span>
                        <?php endif; ?>
                    </td>

                    <!-- Status badges (inline toggle forms) -->
                    <td class="table-td">
                        <div class="flex flex-col gap-1">
                            <!-- Published toggle -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="toggle_published">
                                <input type="hidden" name="id"     value="<?= (int) $ev['id'] ?>">
                                <?php if ($filterCat !== ''): ?>
                                <input type="hidden" name="cat" value="<?= htmlspecialchars($filterCat) ?>">
                                <?php endif; ?>
                                <button type="submit"
                                        class="badge cursor-pointer transition-colors
                                               <?= $ev['published']
                                                   ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                                   : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>"
                                        title="Klicken zum Umschalten">
                                    <?= $ev['published'] ? 'Publiziert' : 'Entwurf' ?>
                                </button>
                            </form>
                            <!-- Featured toggle -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="toggle_featured">
                                <input type="hidden" name="id"     value="<?= (int) $ev['id'] ?>">
                                <?php if ($filterCat !== ''): ?>
                                <input type="hidden" name="cat" value="<?= htmlspecialchars($filterCat) ?>">
                                <?php endif; ?>
                                <button type="submit"
                                        class="badge cursor-pointer transition-colors
                                               <?= $ev['featured']
                                                   ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200'
                                                   : 'bg-gray-50 text-gray-400 hover:bg-gray-100' ?>"
                                        title="Klicken zum Umschalten">
                                    <?= $ev['featured'] ? '★ Highlight' : 'Normal' ?>
                                </button>
                            </form>
                        </div>
                    </td>

                    <!-- Sort order display + up/down buttons -->
                    <td class="table-td">
                        <div class="flex items-center justify-center gap-1">
                            <!-- Up -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="sort_up">
                                <input type="hidden" name="id"     value="<?= (int) $ev['id'] ?>">
                                <?php if ($filterCat !== ''): ?>
                                <input type="hidden" name="cat" value="<?= htmlspecialchars($filterCat) ?>">
                                <?php endif; ?>
                                <button type="submit"
                                        class="p-1 text-gray-400 hover:text-primary hover:bg-primary/10 rounded transition-colors"
                                        title="Nach oben">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                    </svg>
                                </button>
                            </form>
                            <span class="text-xs text-gray-500 w-6 text-center select-none">
                                <?= (int) $ev['sort_order'] ?>
                            </span>
                            <!-- Down -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="sort_down">
                                <input type="hidden" name="id"     value="<?= (int) $ev['id'] ?>">
                                <?php if ($filterCat !== ''): ?>
                                <input type="hidden" name="cat" value="<?= htmlspecialchars($filterCat) ?>">
                                <?php endif; ?>
                                <button type="submit"
                                        class="p-1 text-gray-400 hover:text-primary hover:bg-primary/10 rounded transition-colors"
                                        title="Nach unten">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>

                    <!-- Aktionen -->
                    <td class="table-td">
                        <div class="flex items-center justify-end gap-1">

                            <!-- Edit: open form at top with ?edit=ID -->
                            <a href="events.php?edit=<?= (int) $ev['id'] ?><?= $filterCat ? '&cat=' . urlencode($filterCat) : '' ?>"
                               class="p-1.5 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors"
                               title="Bearbeiten">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0
                                             002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>

                            <!-- Delete -->
                            <form method="post" class="inline"
                                  onsubmit="return confirm('Event «<?= htmlspecialchars(addslashes($ev['title'])) ?>» wirklich löschen?\nDiese Aktion kann nicht rückgängig gemacht werden.')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?= (int) $ev['id'] ?>">
                                <?php if ($filterCat !== ''): ?>
                                <input type="hidden" name="cat" value="<?= htmlspecialchars($filterCat) ?>">
                                <?php endif; ?>
                                <button type="submit"
                                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Löschen">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0
                                                 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0
                                                 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

            </tbody>
        </table>
    </div><!-- /overflow-x-auto -->

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="px-5 py-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">
            Seite <?= $page ?> von <?= $totalPages ?>
            &nbsp;&middot;&nbsp; <?= $totalCount ?> Einträge
        </p>
        <div class="flex gap-1">
            <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars(eventsUrl($filterCat, $page - 1)) ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm text-gray-600 hover:bg-gray-100">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <?php endif; ?>
            <?php
            $pStart = max(1, $page - 2);
            $pEnd   = min($totalPages, $page + 2);
            for ($p = $pStart; $p <= $pEnd; $p++):
            ?>
            <a href="<?= htmlspecialchars(eventsUrl($filterCat, $p)) ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm
                      <?= $p === $page ? 'bg-primary text-white font-semibold' : 'text-gray-600 hover:bg-gray-100' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars(eventsUrl($filterCat, $page + 1)) ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm text-gray-600 hover:bg-gray-100">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /card table -->

<?php require_once 'layout_end.php'; ?>
