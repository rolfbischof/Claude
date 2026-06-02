<?php
/**
 * mb Kommunikation + Events
 * Admin – References / Portfolio CRUD
 *
 * Table  : references_portfolio
 * Role   : editor+
 * Fields : id, title, client, description, category (kommunikation/events/design/alle),
 *          image, url, published, featured, sort_order, created_at, updated_at
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
 * Returns relative path ("references/filename.jpg") on success,
 * '' if no file was submitted, or '' + appends to $errors on failure.
 */
function handleRefImage(array $file, array &$errors): string
{
    // No file chosen
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

    // MIME type via finfo (inspects actual file content)
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

    // Destination directory
    $subDir  = 'references';
    $destDir = rtrim(UPLOAD_PATH, '/') . '/' . $subDir . '/';
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
        $errors[] = 'Upload-Verzeichnis konnte nicht erstellt werden.';
        return '';
    }

    // Collision-safe filename
    $originalBase = pathinfo(basename($file['name']), PATHINFO_FILENAME);
    $safeBase     = preg_replace('/[^a-z0-9\-_]/', '', strtolower($originalBase)) ?: 'referenz';
    $filename     = $safeBase . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath     = $destDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        $errors[] = 'Bild konnte nicht gespeichert werden.';
        return '';
    }

    return $subDir . '/' . $filename;
}

// ====================================================================
// CONSTANTS
// ====================================================================

$ALLOWED_CATS = ['kommunikation', 'events', 'design', 'alle'];

$CAT_LABELS = [
    'kommunikation' => 'Kommunikation',
    'events'        => 'Events',
    'design'        => 'Design',
    'alle'          => 'Alle / Sonstige',
];

$CAT_BADGE_COLORS = [
    'kommunikation' => 'bg-blue-100 text-blue-700',
    'events'        => 'bg-red-100 text-red-700',
    'design'        => 'bg-yellow-100 text-yellow-700',
    'alle'          => 'bg-gray-100 text-gray-600',
];

// ====================================================================
// POST HANDLER
// ====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireCsrf();
    $action = sanitize_input($_POST['action'] ?? '');

    // ── CREATE ────────────────────────────────────────────────────────
    if ($action === 'create') {

        $title       = sanitize_input($_POST['title']       ?? '');
        $client      = sanitize_input($_POST['client']      ?? '');
        $description = trim($_POST['description'] ?? '');
        $category    = sanitize_input($_POST['category']    ?? 'alle');
        $url         = trim($_POST['url'] ?? '');
        $published   = isset($_POST['published']) ? 1 : 0;
        $featured    = isset($_POST['featured'])  ? 1 : 0;
        $sortOrder   = max(0, (int) ($_POST['sort_order'] ?? 0));

        if ($title === '') {
            $errors[] = 'Titel ist ein Pflichtfeld.';
        }
        if (!in_array($category, $ALLOWED_CATS, true)) {
            $category = 'alle';
        }
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Die URL ist ungültig (muss mit http:// oder https:// beginnen).';
        }

        $imagePath = handleRefImage($_FILES['image'] ?? [], $errors);

        if (empty($errors)) {
            $db->insert('references_portfolio', [
                'title'       => $title,
                'client'      => $client,
                'description' => $description,
                'category'    => $category,
                'image'       => $imagePath,
                'url'         => $url,
                'published'   => $published,
                'featured'    => $featured,
                'sort_order'  => $sortOrder,
            ]);
            $auth->flash('Referenz «' . $title . '» wurde erstellt.', 'success');
            redirect('references.php');
        }

    // ── UPDATE ────────────────────────────────────────────────────────
    } elseif ($action === 'update') {

        $id          = (int) ($_POST['id'] ?? 0);
        $title       = sanitize_input($_POST['title']       ?? '');
        $client      = sanitize_input($_POST['client']      ?? '');
        $description = trim($_POST['description'] ?? '');
        $category    = sanitize_input($_POST['category']    ?? 'alle');
        $url         = trim($_POST['url'] ?? '');
        $published   = isset($_POST['published']) ? 1 : 0;
        $featured    = isset($_POST['featured'])  ? 1 : 0;
        $sortOrder   = max(0, (int) ($_POST['sort_order'] ?? 0));

        $existing = $db->find('references_portfolio', $id);
        if (!$existing) {
            $auth->flash('Referenz nicht gefunden.', 'error');
            redirect('references.php');
        }

        if ($title === '') {
            $errors[] = 'Titel ist ein Pflichtfeld.';
        }
        if (!in_array($category, $ALLOWED_CATS, true)) {
            $category = 'alle';
        }
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Die URL ist ungültig (muss mit http:// oder https:// beginnen).';
        }

        $newImagePath = handleRefImage($_FILES['image'] ?? [], $errors);

        // Delete old image only when successfully replaced
        if ($newImagePath !== '' && !empty($existing['image'])) {
            delete_file($existing['image']);
        }

        $imagePath = $newImagePath !== '' ? $newImagePath : ($existing['image'] ?? '');

        if (empty($errors)) {
            $db->update('references_portfolio', [
                'title'       => $title,
                'client'      => $client,
                'description' => $description,
                'category'    => $category,
                'image'       => $imagePath,
                'url'         => $url,
                'published'   => $published,
                'featured'    => $featured,
                'sort_order'  => $sortOrder,
            ], ['id' => $id]);
            $auth->flash('Referenz «' . $title . '» wurde gespeichert.', 'success');
            redirect('references.php');
        }

    // ── TOGGLE PUBLISHED ─────────────────────────────────────────────
    } elseif ($action === 'toggle_published') {
        $id  = (int) ($_POST['id'] ?? 0);
        $row = $db->find('references_portfolio', $id);
        if ($row) {
            $db->update('references_portfolio', ['published' => $row['published'] ? 0 : 1], ['id' => $id]);
            $auth->flash('Veröffentlichungs-Status geändert.', 'success');
        }
        $cat = sanitize_input($_POST['cat'] ?? '');
        redirect('references.php' . ($cat !== '' ? '?cat=' . urlencode($cat) : ''));

    // ── TOGGLE FEATURED ──────────────────────────────────────────────
    } elseif ($action === 'toggle_featured') {
        $id  = (int) ($_POST['id'] ?? 0);
        $row = $db->find('references_portfolio', $id);
        if ($row) {
            $db->update('references_portfolio', ['featured' => $row['featured'] ? 0 : 1], ['id' => $id]);
            $auth->flash('Highlight-Status geändert.', 'success');
        }
        $cat = sanitize_input($_POST['cat'] ?? '');
        redirect('references.php' . ($cat !== '' ? '?cat=' . urlencode($cat) : ''));

    // ── DELETE ────────────────────────────────────────────────────────
    } elseif ($action === 'delete') {
        $id  = (int) ($_POST['id'] ?? 0);
        $row = $db->find('references_portfolio', $id);
        if ($row) {
            if (!empty($row['image'])) {
                delete_file($row['image']);
            }
            $db->delete('references_portfolio', ['id' => $id]);
            $auth->flash('Referenz wurde gelöscht.', 'success');
        }
        $cat = sanitize_input($_POST['cat'] ?? '');
        redirect('references.php' . ($cat !== '' ? '?cat=' . urlencode($cat) : ''));

    // ── SORT UP ───────────────────────────────────────────────────────
    } elseif ($action === 'sort_up') {
        $id  = (int) ($_POST['id'] ?? 0);
        $row = $db->find('references_portfolio', $id);
        if ($row) {
            $prev = $db->fetchOne(
                'SELECT id, sort_order FROM references_portfolio
                  WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1',
                [$row['sort_order']]
            );
            if ($prev) {
                $db->update('references_portfolio', ['sort_order' => $prev['sort_order']], ['id' => $row['id']]);
                $db->update('references_portfolio', ['sort_order' => $row['sort_order']],  ['id' => $prev['id']]);
            } else {
                $db->update('references_portfolio', ['sort_order' => max(0, $row['sort_order'] - 1)], ['id' => $id]);
            }
        }
        $cat = sanitize_input($_POST['cat'] ?? '');
        redirect('references.php' . ($cat !== '' ? '?cat=' . urlencode($cat) : ''));

    // ── SORT DOWN ─────────────────────────────────────────────────────
    } elseif ($action === 'sort_down') {
        $id  = (int) ($_POST['id'] ?? 0);
        $row = $db->find('references_portfolio', $id);
        if ($row) {
            $next = $db->fetchOne(
                'SELECT id, sort_order FROM references_portfolio
                  WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1',
                [$row['sort_order']]
            );
            if ($next) {
                $db->update('references_portfolio', ['sort_order' => $next['sort_order']], ['id' => $row['id']]);
                $db->update('references_portfolio', ['sort_order' => $row['sort_order']],  ['id' => $next['id']]);
            } else {
                $db->update('references_portfolio', ['sort_order' => $row['sort_order'] + 1], ['id' => $id]);
            }
        }
        $cat = sanitize_input($_POST['cat'] ?? '');
        redirect('references.php' . ($cat !== '' ? '?cat=' . urlencode($cat) : ''));
    }
}

// ====================================================================
// GET – build list data
// ====================================================================

$filterCat  = sanitize_input($_GET['cat']  ?? '');
$page       = max(1, (int) ($_GET['page']  ?? 1));
$perPage    = ADMIN_ITEMS_PER_PAGE;

// Validate filter category
if ($filterCat !== '' && !in_array($filterCat, $ALLOWED_CATS, true)) {
    $filterCat = '';
}

// Edit mode (GET ?edit=ID) or re-open form after failed POST
$editId  = (int) ($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    $editRow = $db->find('references_portfolio', $editId);
}
if (!empty($errors) && isset($_POST['id'])) {
    $editId  = (int) $_POST['id'];
    $editRow = $editId > 0 ? $db->find('references_portfolio', $editId) : null;
}
$formOpen = $editRow !== null || ($editId === 0 && !empty($errors));

// Recover submitted values on POST error
$posted = !empty($errors) && !empty($_POST) ? $_POST : [];

// Build WHERE clause
$whereSQL = $filterCat !== '' ? 'WHERE category = ?' : '';
$params   = $filterCat !== '' ? [$filterCat] : [];

$totalCount = (int) $db->fetchColumn(
    "SELECT COUNT(*) FROM references_portfolio $whereSQL", $params
);
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$refs = $db->fetchAll(
    "SELECT * FROM references_portfolio $whereSQL
     ORDER BY sort_order ASC, created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

// Per-category counts for filter pills
$catCountRows = $db->fetchAll(
    'SELECT category, COUNT(*) AS cnt FROM references_portfolio GROUP BY category'
);
$catCounts = [];
foreach ($catCountRows as $row) {
    $catCounts[$row['category']] = (int) $row['cnt'];
}
$totalAll = (int) $db->fetchColumn('SELECT COUNT(*) FROM references_portfolio');

// ====================================================================
// URL helper
// ====================================================================

function refsUrl(string $cat = '', int $pg = 1): string {
    $p = [];
    if ($cat !== '') $p['cat']  = $cat;
    if ($pg  >    1) $p['page'] = $pg;
    return 'references.php' . ($p ? '?' . http_build_query($p) : '');
}

// ====================================================================
// Layout
// ====================================================================

$pageTitle   = 'Referenzen';
$currentPage = 'references';
require_once 'layout.php';

// Resolve form field values
$fv = [
    'title'       => $posted['title']       ?? ($editRow['title']       ?? ''),
    'client'      => $posted['client']      ?? ($editRow['client']      ?? ''),
    'description' => $posted['description'] ?? ($editRow['description'] ?? ''),
    'category'    => $posted['category']    ?? ($editRow['category']    ?? 'alle'),
    'url'         => $posted['url']         ?? ($editRow['url']         ?? ''),
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
                <?= $editRow ? 'Referenz bearbeiten' : 'Neue Referenz hinzufügen' ?>
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
                               placeholder="Projekttitel eingeben">
                    </div>

                    <!-- Kategorie -->
                    <div>
                        <label class="form-label">Kategorie</label>
                        <select name="category" class="form-input">
                            <?php foreach ($ALLOWED_CATS as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"
                                    <?= $fv['category'] === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($CAT_LABELS[$cat] ?? $cat) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Kunde -->
                    <div>
                        <label class="form-label">Kunde / Auftraggeber</label>
                        <input type="text" name="client"
                               value="<?= htmlspecialchars((string) $fv['client']) ?>"
                               class="form-input"
                               placeholder="z.B. KMU Luzern">
                    </div>

                    <!-- URL -->
                    <div>
                        <label class="form-label">
                            Projekt-URL
                            <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <input type="url" name="url"
                               value="<?= htmlspecialchars((string) $fv['url']) ?>"
                               class="form-input"
                               placeholder="https://…">
                    </div>

                    <!-- Reihenfolge -->
                    <div>
                        <label class="form-label">Reihenfolge</label>
                        <input type="number" name="sort_order" min="0"
                               value="<?= (int) $fv['sort_order'] ?>"
                               class="form-input">
                    </div>

                    <!-- Beschreibung -->
                    <div class="lg:col-span-2">
                        <label class="form-label">Beschreibung</label>
                        <textarea name="description" rows="4"
                                  class="form-input"
                                  placeholder="Kurze Projektbeschreibung…"><?= htmlspecialchars((string) $fv['description']) ?></textarea>
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
                        <?= $editRow ? 'Änderungen speichern' : 'Referenz erstellen' ?>
                    </button>
                    <?php if ($editRow): ?>
                    <a href="references.php<?= $filterCat ? '?cat=' . urlencode($filterCat) : '' ?>"
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
        <a href="<?= htmlspecialchars(refsUrl()) ?>"
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
        <?php
        // Filter pills for each proper category (excluding 'alle' which is a catch-all)
        $pillCats = ['kommunikation', 'events', 'design'];
        foreach ($pillCats as $cat):
            $cnt    = $catCounts[$cat] ?? 0;
            $active = $filterCat === $cat;
        ?>
        <a href="<?= htmlspecialchars(refsUrl($cat)) ?>"
           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                  <?= $active ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
            <?= htmlspecialchars($CAT_LABELS[$cat]) ?>
            <?php if ($cnt > 0): ?>
            <span class="ml-0.5 px-1.5 py-0.5 rounded-full text-xs font-bold
                         <?= $active ? 'bg-white/25 text-white' : 'bg-gray-200 text-gray-600' ?>">
                <?= $cnt ?>
            </span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <?php
        // Pill for "Alle / Sonstige" category (stored as 'alle')
        $cnt    = $catCounts['alle'] ?? 0;
        $active = $filterCat === 'alle';
        if ($cnt > 0):
        ?>
        <a href="<?= htmlspecialchars(refsUrl('alle')) ?>"
           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                  <?= $active ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
            Sonstige
            <span class="ml-0.5 px-1.5 py-0.5 rounded-full text-xs font-bold
                         <?= $active ? 'bg-white/25 text-white' : 'bg-gray-200 text-gray-600' ?>">
                <?= $cnt ?>
            </span>
        </a>
        <?php endif; ?>
    </div>

    <p class="text-sm text-gray-500">
        <?= $totalCount ?> Referenz<?= $totalCount !== 1 ? 'en' : '' ?>
        <?= $filterCat ? ' in «' . htmlspecialchars($CAT_LABELS[$filterCat] ?? $filterCat) . '»' : ' gesamt' ?>
    </p>
</div>


<!-- ================================================================
     REFERENCES TABLE
================================================================ -->
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="table-th w-14">Bild</th>
                    <th class="table-th">Titel / Kunde</th>
                    <th class="table-th">Kategorie</th>
                    <th class="table-th">Status</th>
                    <th class="table-th text-center w-24">Sort</th>
                    <th class="table-th text-right">Aktionen</th>
                </tr>
            </thead>
            <tbody>

                <?php if (empty($refs)): ?>
                <tr>
                    <td colspan="6" class="table-td text-center py-12 text-gray-400">
                        <svg class="w-10 h-10 mx-auto mb-3 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0
                                     01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0
                                     012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        Keine Referenzen gefunden.
                        <?php if ($filterCat): ?>
                        <br><a href="references.php" class="text-primary hover:underline text-sm mt-1 inline-block">
                            Filter zurücksetzen
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($refs as $ref): ?>
                <tr class="table-tr">

                    <!-- Thumbnail -->
                    <td class="table-td">
                        <?php if (!empty($ref['image'])): ?>
                        <img src="<?= htmlspecialchars(upload_url($ref['image'])) ?>"
                             alt="<?= htmlspecialchars($ref['title']) ?>"
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

                    <!-- Titel + Kunde -->
                    <td class="table-td max-w-[220px]">
                        <p class="font-medium text-gray-800 truncate"><?= htmlspecialchars($ref['title']) ?></p>
                        <?php if ($ref['client']): ?>
                        <p class="text-xs text-gray-500 truncate mt-0.5">
                            <?= htmlspecialchars($ref['client']) ?>
                        </p>
                        <?php endif; ?>
                        <?php if ($ref['url']): ?>
                        <a href="<?= htmlspecialchars($ref['url']) ?>" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1 text-xs text-primary hover:underline mt-0.5">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0
                                         002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            Link
                        </a>
                        <?php endif; ?>
                    </td>

                    <!-- Kategorie -->
                    <td class="table-td">
                        <span class="badge <?= $CAT_BADGE_COLORS[$ref['category']] ?? 'bg-gray-100 text-gray-600' ?>">
                            <?= htmlspecialchars($CAT_LABELS[$ref['category']] ?? $ref['category']) ?>
                        </span>
                    </td>

                    <!-- Status badges (inline toggle forms) -->
                    <td class="table-td">
                        <div class="flex flex-col gap-1">
                            <!-- Published toggle -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="toggle_published">
                                <input type="hidden" name="id"     value="<?= (int) $ref['id'] ?>">
                                <?php if ($filterCat !== ''): ?>
                                <input type="hidden" name="cat" value="<?= htmlspecialchars($filterCat) ?>">
                                <?php endif; ?>
                                <button type="submit"
                                        class="badge cursor-pointer transition-colors
                                               <?= $ref['published']
                                                   ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                                   : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>"
                                        title="Klicken zum Umschalten">
                                    <?= $ref['published'] ? 'Publiziert' : 'Entwurf' ?>
                                </button>
                            </form>
                            <!-- Featured toggle -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="toggle_featured">
                                <input type="hidden" name="id"     value="<?= (int) $ref['id'] ?>">
                                <?php if ($filterCat !== ''): ?>
                                <input type="hidden" name="cat" value="<?= htmlspecialchars($filterCat) ?>">
                                <?php endif; ?>
                                <button type="submit"
                                        class="badge cursor-pointer transition-colors
                                               <?= $ref['featured']
                                                   ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200'
                                                   : 'bg-gray-50 text-gray-400 hover:bg-gray-100' ?>"
                                        title="Klicken zum Umschalten">
                                    <?= $ref['featured'] ? '★ Highlight' : 'Normal' ?>
                                </button>
                            </form>
                        </div>
                    </td>

                    <!-- Sort order + up/down buttons -->
                    <td class="table-td">
                        <div class="flex items-center justify-center gap-1">
                            <!-- Up -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="sort_up">
                                <input type="hidden" name="id"     value="<?= (int) $ref['id'] ?>">
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
                                <?= (int) $ref['sort_order'] ?>
                            </span>
                            <!-- Down -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="sort_down">
                                <input type="hidden" name="id"     value="<?= (int) $ref['id'] ?>">
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
                            <a href="references.php?edit=<?= (int) $ref['id'] ?><?= $filterCat ? '&cat=' . urlencode($filterCat) : '' ?>"
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
                                  onsubmit="return confirm('Referenz «<?= htmlspecialchars(addslashes($ref['title'])) ?>» wirklich löschen?\nDiese Aktion kann nicht rückgängig gemacht werden.')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"     value="<?= (int) $ref['id'] ?>">
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
            <a href="<?= htmlspecialchars(refsUrl($filterCat, $page - 1)) ?>"
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
            <a href="<?= htmlspecialchars(refsUrl($filterCat, $p)) ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm
                      <?= $p === $page ? 'bg-primary text-white font-semibold' : 'text-gray-600 hover:bg-gray-100' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars(refsUrl($filterCat, $page + 1)) ?>"
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
