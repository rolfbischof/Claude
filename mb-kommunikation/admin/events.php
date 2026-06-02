<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
$auth->requireRole('viewer'); // minimum role to access admin

$auth->requireRole('editor');

$db        = Database::getInstance();
$csrfToken = $auth->getCsrfToken();
$errors    = [];

// ── Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireCsrf();
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['create_event', 'edit_event'])) {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $eventDate   = $_POST['event_date'] ?? null;
        $location    = trim($_POST['location'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $image       = trim($_POST['image'] ?? '');
        $published   = isset($_POST['published']) ? 1 : 0;
        $featured    = isset($_POST['featured'])  ? 1 : 0;

        if (empty($title)) $errors[] = 'Titel ist erforderlich.';
        if ($eventDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            $errors[] = 'Ungültiges Datum.';
            $eventDate = null;
        }
        if ($eventDate === '') $eventDate = null;

        if (empty($errors)) {
            $data = [
                'title'       => $title,
                'description' => $description,
                'event_date'  => $eventDate ?: null,
                'location'    => $location,
                'category'    => $category,
                'image'       => $image,
                'published'   => $published,
                'featured'    => $featured,
            ];
            if ($action === 'create_event') {
                $maxOrder = (int) $db->fetchColumn('SELECT COALESCE(MAX(sort_order),0) FROM events_portfolio');
                $data['sort_order'] = $maxOrder + 1;
                $data['created_by'] = $auth->getUserId();
                $db->insert('events_portfolio', $data);
                $auth->flash('Event «'.$title.'» wurde erstellt.', 'success');
            } else {
                $eid = (int) ($_POST['event_id'] ?? 0);
                $db->update('events_portfolio', $data, ['id' => $eid]);
                $auth->flash('Event «'.$title.'» wurde aktualisiert.', 'success');
            }
            header('Location: events.php');
            exit;
        }

    } elseif ($action === 'delete_event') {
        $eid = (int) ($_POST['event_id'] ?? 0);
        $db->delete('events_portfolio', ['id' => $eid]);
        $auth->flash('Event wurde gelöscht.', 'success');
        header('Location: events.php');
        exit;

    } elseif ($action === 'toggle_published') {
        $eid = (int) ($_POST['event_id'] ?? 0);
        $ev  = $db->find('events_portfolio', $eid);
        if ($ev) $db->update('events_portfolio', ['published' => $ev['published'] ? 0 : 1], ['id' => $eid]);
        header('Location: events.php');
        exit;

    } elseif ($action === 'toggle_featured') {
        $eid = (int) ($_POST['event_id'] ?? 0);
        $ev  = $db->find('events_portfolio', $eid);
        if ($ev) $db->update('events_portfolio', ['featured' => $ev['featured'] ? 0 : 1], ['id' => $eid]);
        header('Location: events.php');
        exit;

    } elseif ($action === 'reorder') {
        $eid       = (int) ($_POST['event_id'] ?? 0);
        $direction = $_POST['direction'] ?? '';
        $ev = $db->find('events_portfolio', $eid);
        if ($ev && in_array($direction, ['up','down'])) {
            if ($direction === 'up') {
                $swap = $db->fetchOne(
                    'SELECT * FROM events_portfolio WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1',
                    [$ev['sort_order']]
                );
            } else {
                $swap = $db->fetchOne(
                    'SELECT * FROM events_portfolio WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1',
                    [$ev['sort_order']]
                );
            }
            if ($swap) {
                $db->update('events_portfolio', ['sort_order' => $swap['sort_order']], ['id' => $ev['id']]);
                $db->update('events_portfolio', ['sort_order' => $ev['sort_order']],  ['id' => $swap['id']]);
            }
        }
        header('Location: events.php');
        exit;
    }
}

// ── Load events ───────────────────────────────────────────────────────────
$filterCat  = $_GET['category'] ?? '';
$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = 20;

$whereSQL = '1=1';
$params   = [];
if ($filterCat !== '') {
    $whereSQL .= ' AND category = ?';
    $params[]  = $filterCat;
}

$total      = (int) $db->fetchColumn("SELECT COUNT(*) FROM events_portfolio WHERE $whereSQL", $params);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$events = $db->fetchAll(
    "SELECT * FROM events_portfolio WHERE $whereSQL ORDER BY sort_order ASC, id DESC LIMIT $perPage OFFSET $offset",
    $params
);

// Categories for filter
$categories = $db->fetchAll(
    "SELECT DISTINCT category FROM events_portfolio WHERE category != '' ORDER BY category"
);

// Edit mode
$editEvent = null;
if (isset($_GET['action']) && in_array($_GET['action'], ['edit','new']) && isset($_GET['id'])) {
    $editEvent = $db->find('events_portfolio', (int) $_GET['id']);
}
$showForm = (isset($_GET['action']) && $_GET['action'] === 'new') || $editEvent || !empty($errors);

$pageTitle   = 'Events';
$currentPage = 'events';
require_once 'layout.php';
?>

<?php if ($showForm): ?>
<!-- ── FORM VIEW ─────────────────────────────────────────────────────────── -->
<div class="mb-5">
    <a href="events.php" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Zurück zur Übersicht
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl">
    <?php foreach ($errors as $err): ?><p class="text-sm text-red-700"><?= htmlspecialchars($err) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="action"   value="<?= $editEvent ? 'edit_event' : 'create_event' ?>">
    <?php if ($editEvent): ?>
    <input type="hidden" name="event_id" value="<?= $editEvent['id'] ?>">
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Main -->
        <div class="lg:col-span-2 space-y-5">
            <div class="card p-6 space-y-4">
                <h2 class="font-semibold text-gray-800 border-b border-gray-100 pb-3">
                    <?= $editEvent ? 'Event bearbeiten' : 'Neuer Event' ?>
                </h2>
                <div>
                    <label class="form-label">Titel <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required
                           value="<?= htmlspecialchars($editEvent['title'] ?? ($_POST['title'] ?? '')) ?>"
                           class="form-input text-lg">
                </div>
                <div>
                    <label class="form-label">Beschreibung</label>
                    <textarea name="description" rows="5" class="form-input"><?= htmlspecialchars($editEvent['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Datum</label>
                        <input type="date" name="event_date"
                               value="<?= htmlspecialchars($editEvent['event_date'] ?? ($_POST['event_date'] ?? '')) ?>"
                               class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Ort / Location</label>
                        <input type="text" name="location"
                               value="<?= htmlspecialchars($editEvent['location'] ?? ($_POST['location'] ?? '')) ?>"
                               class="form-input">
                    </div>
                </div>
                <div>
                    <label class="form-label">Kategorie</label>
                    <input type="text" name="category" list="categoryList"
                           value="<?= htmlspecialchars($editEvent['category'] ?? ($_POST['category'] ?? '')) ?>"
                           class="form-input" placeholder="z.B. Konzert, Firmenanlass…">
                    <datalist id="categoryList">
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>">
                        <?php endforeach; ?>
                        <option value="Konzert"><option value="Firmenanlass"><option value="Geburtstagsfeier">
                        <option value="Team-Event"><option value="Jubiläum">
                    </datalist>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-5">
            <!-- Publish settings -->
            <div class="card p-5">
                <h3 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wider">Einstellungen</h3>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <div class="relative">
                            <input type="checkbox" name="published" value="1"
                                   <?= ($editEvent['published'] ?? 1) ? 'checked' : '' ?>
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full
                                        peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px]
                                        after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full
                                        after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        </div>
                        <span class="text-sm text-gray-700">Veröffentlicht</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <div class="relative">
                            <input type="checkbox" name="featured" value="1"
                                   <?= ($editEvent['featured'] ?? 0) ? 'checked' : '' ?>
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full
                                        peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px]
                                        after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full
                                        after:h-5 after:w-5 after:transition-all peer-checked:bg-gold"></div>
                        </div>
                        <span class="text-sm text-gray-700">Hervorgehoben</span>
                    </label>
                </div>
                <div class="pt-4 space-y-2">
                    <button type="submit" class="btn-primary w-full justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <?= $editEvent ? 'Änderungen speichern' : 'Event erstellen' ?>
                    </button>
                    <a href="events.php" class="btn-gray w-full justify-center">Abbrechen</a>
                </div>
            </div>

            <!-- Image -->
            <div class="card p-5">
                <h3 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wider">Bild</h3>
                <div id="imgPreviewWrap" class="<?= ($editEvent['image'] ?? '') ? '' : 'hidden' ?> mb-3">
                    <img id="imgPreview"
                         src="<?= htmlspecialchars($editEvent['image'] ?? '') ?>"
                         alt="Vorschau" class="w-full h-40 object-cover rounded-lg border border-gray-200">
                </div>
                <input type="hidden" name="image" id="imageInput"
                       value="<?= htmlspecialchars($editEvent['image'] ?? ($_POST['image'] ?? '')) ?>">
                <label class="btn-gray w-full justify-center cursor-pointer text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Bild hochladen
                    <input type="file" accept="image/*" class="hidden" onchange="uploadImage(this,'imageInput','imgPreview','imgPreviewWrap')">
                </label>
            </div>
        </div>
    </div>
</form>

<?php else: ?>
<!-- ── LIST VIEW ──────────────────────────────────────────────────────────── -->
<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <p class="text-sm text-gray-500"><?= $total ?> Event(s)</p>
    <a href="events.php?action=new" class="btn-primary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Neuer Event
    </a>
</div>

<!-- Filter -->
<div class="card p-4 mb-5">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div class="min-w-[180px]">
            <label class="form-label">Kategorie</label>
            <select name="category" class="form-input">
                <option value="">Alle Kategorien</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['category']) ?>"
                    <?= $filterCat === $cat['category'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Filtern</button>
            <a href="events.php" class="btn-gray">Reset</a>
        </div>
    </form>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="table-th w-16">Bild</th>
                    <th class="table-th">Titel</th>
                    <th class="table-th">Datum</th>
                    <th class="table-th">Ort</th>
                    <th class="table-th">Kategorie</th>
                    <th class="table-th">Status</th>
                    <th class="table-th text-right">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                <tr><td colspan="7" class="table-td text-center py-8 text-gray-400">Keine Events gefunden.</td></tr>
                <?php endif; ?>
                <?php foreach ($events as $ev): ?>
                <tr class="table-tr">
                    <td class="table-td">
                        <?php if ($ev['image']): ?>
                        <img src="<?= htmlspecialchars($ev['image']) ?>" alt=""
                             class="w-12 h-10 object-cover rounded-lg border border-gray-200">
                        <?php else: ?>
                        <div class="w-12 h-10 rounded-lg bg-gray-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="table-td">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($ev['title']) ?></p>
                        <?php if ($ev['featured']): ?>
                        <span class="badge bg-yellow-100 text-yellow-700 mt-0.5">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            Featured
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="table-td text-gray-500"><?= $ev['event_date'] ? format_date($ev['event_date']) : '–' ?></td>
                    <td class="table-td text-gray-500"><?= htmlspecialchars($ev['location'] ?: '–') ?></td>
                    <td class="table-td">
                        <?php if ($ev['category']): ?>
                        <span class="badge bg-primary/10 text-primary"><?= htmlspecialchars($ev['category']) ?></span>
                        <?php else: ?>
                        <span class="text-gray-400">–</span>
                        <?php endif; ?>
                    </td>
                    <td class="table-td">
                        <form method="post" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action"   value="toggle_published">
                            <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                            <button type="submit"
                                    class="badge <?= $ev['published'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?> cursor-pointer transition-colors">
                                <?= $ev['published'] ? 'Aktiv' : 'Inaktiv' ?>
                            </button>
                        </form>
                    </td>
                    <td class="table-td">
                        <div class="flex items-center justify-end gap-1">
                            <!-- Sort up -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action"     value="reorder">
                                <input type="hidden" name="event_id"   value="<?= $ev['id'] ?>">
                                <input type="hidden" name="direction"  value="up">
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Nach oben">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                </button>
                            </form>
                            <!-- Sort down -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action"     value="reorder">
                                <input type="hidden" name="event_id"   value="<?= $ev['id'] ?>">
                                <input type="hidden" name="direction"  value="down">
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Nach unten">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </form>
                            <!-- Featured toggle -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action"   value="toggle_featured">
                                <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                <button type="submit"
                                        class="p-1.5 rounded-lg transition-colors <?= $ev['featured'] ? 'text-yellow-500 hover:bg-yellow-50' : 'text-gray-300 hover:text-yellow-500 hover:bg-yellow-50' ?>"
                                        title="Featured toggle">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                </button>
                            </form>
                            <!-- Edit -->
                            <a href="events.php?action=edit&id=<?= $ev['id'] ?>"
                               class="p-1.5 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Bearbeiten">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <!-- Delete -->
                            <form method="post" class="inline" onsubmit="return confirm('Event wirklich löschen?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action"   value="delete_event">
                                <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Löschen">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-sm text-gray-500">Seite <?= $page ?> von <?= $totalPages ?></p>
        <div class="flex gap-1">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?= $p ?>&category=<?= urlencode($filterCat) ?>"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm <?= $p === $page ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
async function uploadImage(inputEl, hiddenId, previewId, wrapId) {
    if (!inputEl.files[0]) return;
    const formData = new FormData();
    formData.append('file', inputEl.files[0]);
    formData.append('csrf_token', '<?= htmlspecialchars($csrfToken) ?>');
    formData.append('type', 'events');
    try {
        const res  = await fetch('/admin/upload.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            document.getElementById(hiddenId).value    = data.url;
            document.getElementById(previewId).src     = data.url;
            document.getElementById(wrapId).classList.remove('hidden');
        } else { alert(data.error || 'Upload fehlgeschlagen.'); }
    } catch(e) { alert('Upload-Fehler.'); }
}
</script>

<?php require_once 'layout_end.php'; ?>
