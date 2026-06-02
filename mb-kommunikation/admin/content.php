<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
$auth->requireRole('viewer'); // minimum role to access admin

$auth->requireRole('editor');

$db        = Database::getInstance();
$csrfToken = $auth->getCsrfToken();
$errors    = [];

// ── Save page ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_page') {
        $pageId   = (int) ($_POST['page_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $content  = $_POST['content'] ?? '';   // allow HTML from WYSIWYG
        $metaTitle = trim($_POST['meta_title'] ?? '');
        $metaDesc  = trim($_POST['meta_description'] ?? '');
        $published = isset($_POST['published']) ? 1 : 0;
        $heroImage = trim($_POST['hero_image'] ?? '');

        if (empty($title)) $errors[] = 'Titel darf nicht leer sein.';

        if (empty($errors)) {
            $data = [
                'title'            => $title,
                'subtitle'         => $subtitle,
                'content'          => $content,
                'meta_title'       => $metaTitle,
                'meta_description' => $metaDesc,
                'published'        => $published,
                'hero_image'       => $heroImage,
            ];
            if ($pageId > 0) {
                $db->update('pages', $data, ['id' => $pageId]);
                $auth->flash('Seite «'.$title.'» wurde gespeichert.', 'success');
            } else {
                $slug = slugify($title);
                // ensure unique slug
                $i = 1;
                $baseSlug = $slug;
                while ($db->exists('pages', ['slug' => $slug])) {
                    $slug = $baseSlug . '-' . $i++;
                }
                $data['slug']       = $slug;
                $data['created_by'] = $auth->getUserId();
                $db->insert('pages', $data);
                $auth->flash('Seite «'.$title.'» wurde erstellt.', 'success');
            }
            header('Location: content.php');
            exit;
        }

    } elseif ($action === 'toggle_published') {
        $pageId = (int) ($_POST['page_id'] ?? 0);
        $page   = $db->find('pages', $pageId);
        if ($page) {
            $db->update('pages', ['published' => $page['published'] ? 0 : 1], ['id' => $pageId]);
            $auth->flash('Status geändert.', 'success');
        }
        header('Location: content.php');
        exit;

    } elseif ($action === 'delete_page') {
        $pageId = (int) ($_POST['page_id'] ?? 0);
        $page   = $db->find('pages', $pageId);
        // Protect core slugs
        $coreSlug = ['home','kommunikation','events','referenzen','ueber-mich','kontakt'];
        if ($page && in_array($page['slug'], $coreSlug)) {
            $auth->flash('Kern-Seiten können nicht gelöscht werden.', 'error');
        } elseif ($page) {
            $db->delete('pages', ['id' => $pageId]);
            $auth->flash('Seite wurde gelöscht.', 'success');
        }
        header('Location: content.php');
        exit;
    }
}

// ── Load pages ────────────────────────────────────────────────────────────
$pages = $db->fetchAll(
    'SELECT p.*, u.username AS editor_name
     FROM pages p
     LEFT JOIN users u ON p.created_by = u.id
     ORDER BY p.slug ASC'
);

// Edit mode
$editPage = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editPage = $db->find('pages', (int) $_GET['id']);
}

$pageTitle   = 'Seiten / Content';
$currentPage = 'content';
require_once 'layout.php';
?>

<!-- TinyMCE CDN (loaded only when editing) -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
function initTinyMCE(selector) {
    if (typeof tinymce !== 'undefined') {
        tinymce.remove(selector);
        tinymce.init({
            selector: selector,
            language: 'de',
            height: 350,
            menubar: false,
            plugins: ['advlist','autolink','lists','link','image','charmap','preview','anchor',
                      'searchreplace','visualblocks','code','fullscreen','insertdatetime','media',
                      'table','help','wordcount'],
            toolbar: 'undo redo | blocks | bold italic underline | forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link | removeformat | code | help',
            content_style: 'body { font-family:Inter,Arial,sans-serif; font-size:14px; }',
            promotion: false,
        });
    }
}
</script>

<?php if ($editPage): ?>
<!-- ── EDIT VIEW ───────────────────────────────────────────────────────── -->
<div class="mb-5">
    <a href="content.php" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Zurück zur Übersicht
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl">
    <?php foreach ($errors as $err): ?><p class="text-sm text-red-700"><?= htmlspecialchars($err) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="action"  value="save_page">
    <input type="hidden" name="page_id" value="<?= $editPage['id'] ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Main content -->
        <div class="lg:col-span-2 space-y-5">
            <div class="card p-6 space-y-4">
                <h2 class="font-semibold text-gray-800 border-b border-gray-100 pb-3">Seiteninhalt</h2>
                <div>
                    <label class="form-label">Titel <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required
                           value="<?= htmlspecialchars($editPage['title']) ?>" class="form-input text-lg font-medium">
                </div>
                <div>
                    <label class="form-label">Untertitel</label>
                    <input type="text" name="subtitle"
                           value="<?= htmlspecialchars($editPage['subtitle'] ?? '') ?>" class="form-input">
                </div>
                <div>
                    <label class="form-label">Inhalt</label>
                    <textarea name="content" id="pageContent" class="form-input" rows="12"><?= htmlspecialchars($editPage['content'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Sidebar settings -->
        <div class="space-y-5">
            <!-- Publish -->
            <div class="card p-5">
                <h3 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wider">Veröffentlichung</h3>
                <div class="flex items-center gap-3 mb-4">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="published" value="1"
                               <?= $editPage['published'] ? 'checked' : '' ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer
                                    peer-checked:after:translate-x-full peer-checked:after:border-white
                                    after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                    after:bg-white after:border-gray-300 after:border after:rounded-full
                                    after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                    </label>
                    <span class="text-sm text-gray-700">Veröffentlicht</span>
                </div>
                <div class="text-xs text-gray-400">
                    Slug: <code class="bg-gray-100 px-1 py-0.5 rounded"><?= htmlspecialchars($editPage['slug']) ?></code>
                </div>
                <div class="pt-4">
                    <button type="submit" class="btn-primary w-full justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Speichern
                    </button>
                </div>
            </div>

            <!-- Hero image -->
            <div class="card p-5">
                <h3 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wider">Hero-Bild</h3>
                <div id="heroPreview" class="<?= $editPage['hero_image'] ? '' : 'hidden' ?> mb-3">
                    <img id="heroPreviewImg" src="<?= htmlspecialchars($editPage['hero_image'] ?? '') ?>"
                         alt="Hero" class="w-full h-32 object-cover rounded-lg border border-gray-200">
                </div>
                <input type="hidden" name="hero_image" id="heroImageInput"
                       value="<?= htmlspecialchars($editPage['hero_image'] ?? '') ?>">
                <label class="btn-gray w-full justify-center cursor-pointer text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Bild hochladen
                    <input type="file" accept="image/*" class="hidden" onchange="uploadHeroImage(this)">
                </label>
                <?php if ($editPage['hero_image']): ?>
                <button type="button" onclick="document.getElementById('heroImageInput').value='';document.getElementById('heroPreview').classList.add('hidden');"
                        class="mt-2 text-xs text-red-500 hover:underline w-full text-center">Bild entfernen</button>
                <?php endif; ?>
            </div>

            <!-- SEO -->
            <div class="card p-5">
                <h3 class="font-semibold text-gray-700 mb-3 text-sm uppercase tracking-wider">SEO</h3>
                <div class="space-y-3">
                    <div>
                        <label class="form-label text-xs">Meta-Titel</label>
                        <input type="text" name="meta_title"
                               value="<?= htmlspecialchars($editPage['meta_title'] ?? '') ?>" class="form-input text-sm">
                    </div>
                    <div>
                        <label class="form-label text-xs">Meta-Beschreibung</label>
                        <textarea name="meta_description" rows="3" class="form-input text-sm"><?= htmlspecialchars($editPage['meta_description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initTinyMCE('#pageContent');
});

async function uploadHeroImage(input) {
    if (!input.files[0]) return;
    const formData = new FormData();
    formData.append('file', input.files[0]);
    formData.append('csrf_token', '<?= htmlspecialchars($csrfToken) ?>');
    formData.append('type', 'pages');
    try {
        const res  = await fetch('/admin/upload.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            document.getElementById('heroImageInput').value = data.url;
            document.getElementById('heroPreviewImg').src   = data.url;
            document.getElementById('heroPreview').classList.remove('hidden');
        } else {
            alert(data.error || 'Upload fehlgeschlagen.');
        }
    } catch(e) { alert('Upload-Fehler.'); }
}
</script>

<?php else: ?>
<!-- ── LIST VIEW ───────────────────────────────────────────────────────── -->
<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <p class="text-sm text-gray-500"><?= count($pages) ?> Seite(n)</p>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="table-th">Seite</th>
                    <th class="table-th">Slug</th>
                    <th class="table-th">Status</th>
                    <th class="table-th">Zuletzt geändert</th>
                    <th class="table-th text-right">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pages)): ?>
                <tr><td colspan="5" class="table-td text-center py-8 text-gray-400">Keine Seiten gefunden.</td></tr>
                <?php endif; ?>
                <?php
                $coreSlugs = ['home','kommunikation','events','referenzen','ueber-mich','kontakt'];
                foreach ($pages as $pg):
                $isCore = in_array($pg['slug'], $coreSlugs);
                ?>
                <tr class="table-tr">
                    <td class="table-td">
                        <div>
                            <p class="font-medium text-gray-800"><?= htmlspecialchars($pg['title']) ?></p>
                            <?php if ($pg['subtitle']): ?>
                            <p class="text-xs text-gray-400 truncate max-w-xs"><?= htmlspecialchars($pg['subtitle']) ?></p>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="table-td">
                        <code class="text-xs bg-gray-100 px-2 py-0.5 rounded"><?= htmlspecialchars($pg['slug']) ?></code>
                        <?php if ($isCore): ?>
                        <span class="badge bg-blue-100 text-blue-600 ml-1">Kern</span>
                        <?php endif; ?>
                    </td>
                    <td class="table-td">
                        <form method="post" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action"  value="toggle_published">
                            <input type="hidden" name="page_id" value="<?= $pg['id'] ?>">
                            <button type="submit"
                                    class="badge <?= $pg['published'] ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?> cursor-pointer transition-colors">
                                <?= $pg['published'] ? 'Veröffentlicht' : 'Entwurf' ?>
                            </button>
                        </form>
                    </td>
                    <td class="table-td text-gray-500 text-xs"><?= format_date($pg['updated_at'], 'd.m.Y H:i') ?></td>
                    <td class="table-td">
                        <div class="flex items-center justify-end gap-1">
                            <a href="content.php?action=edit&id=<?= $pg['id'] ?>"
                               class="p-1.5 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Bearbeiten">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <a href="/<?= htmlspecialchars($pg['slug']) === 'home' ? '' : htmlspecialchars($pg['slug']) ?>" target="_blank"
                               class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Ansehen">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                            <?php if (!$isCore): ?>
                            <form method="post" class="inline"
                                  onsubmit="return confirm('Seite wirklich löschen?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action"  value="delete_page">
                                <input type="hidden" name="page_id" value="<?= $pg['id'] ?>">
                                <button type="submit"
                                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Löschen">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php require_once 'layout_end.php'; ?>
