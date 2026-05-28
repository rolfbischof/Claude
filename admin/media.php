<?php
$pageTitle = 'Medien';
require_once __DIR__ . '/includes/header.php';

$uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . '/../../assets/uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload' && !empty($_FILES['file']['name'])) {
        $file    = $_FILES['file'];
        $maxSize = (defined('MAX_UPLOAD_MB') ? MAX_UPLOAD_MB : 5) * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Upload-Fehler: Code ' . $file['error']);
        } elseif ($file['size'] > $maxSize) {
            flash('error', 'Datei zu groß (max. ' . (defined('MAX_UPLOAD_MB') ? MAX_UPLOAD_MB : 5) . ' MB).');
        } else {
            $mime = mime_content_type($file['tmp_name']);
            if (!allowed_image($mime)) {
                flash('error', 'Ungültiger Dateityp. Erlaubt: JPEG, PNG, GIF, WebP, SVG.');
            } else {
                $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = uniqid('img_', true) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    db_run('INSERT INTO media (filename, original_name, mime_type, file_size, alt_text) VALUES (?,?,?,?,?)',
                        [$filename, $file['name'], $mime, $file['size'], $_POST['alt_text'] ?? '']);
                    flash('success', 'Bild hochgeladen.');
                } else {
                    flash('error', 'Datei konnte nicht gespeichert werden. Bitte Schreibrechte prüfen.');
                }
            }
        }

    } elseif ($action === 'delete' && !empty($_POST['id'])) {
        $row = db_get('SELECT filename FROM media WHERE id=?', [(int)$_POST['id']]);
        if ($row) {
            @unlink($uploadDir . $row['filename']);
            db_run('DELETE FROM media WHERE id=?', [(int)$_POST['id']]);
            flash('success', 'Bild gelöscht.');
        }
    }
    redirect('/admin/media.php');
}

$media = db_all('SELECT * FROM media ORDER BY created_at DESC');
$uploadUrl = defined('UPLOAD_URL') ? UPLOAD_URL : '/assets/uploads/';
?>

<div class="card" style="margin-bottom:28px">
  <div class="card-header"><h2>Bild hochladen</h2></div>
  <form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload">
    <div class="form-row">
      <div class="form-group" style="flex:2">
        <label>Bilddatei (JPEG, PNG, GIF, WebP, SVG – max. <?= defined('MAX_UPLOAD_MB') ? MAX_UPLOAD_MB : 5 ?> MB)</label>
        <input type="file" name="file" accept="image/*" required>
      </div>
      <div class="form-group" style="flex:2">
        <label>Alt-Text (für SEO & Barrierefreiheit)</label>
        <input name="alt_text" placeholder="Bildbeschreibung">
      </div>
    </div>
    <button class="btn btn-primary">Hochladen</button>
  </form>
</div>

<div class="card">
  <div class="card-header"><h2><?= count($media) ?> Bilder</h2></div>
  <?php if ($media): ?>
    <div class="media-grid">
      <?php foreach ($media as $m): ?>
        <div class="media-item">
          <div class="media-thumb">
            <?php if (in_array($m['mime_type'], ['image/svg+xml'])): ?>
              <img src="<?= h($uploadUrl . $m['filename']) ?>" alt="<?= h($m['alt_text']) ?>" style="object-fit:contain">
            <?php else: ?>
              <img src="<?= h($uploadUrl . $m['filename']) ?>" alt="<?= h($m['alt_text']) ?>">
            <?php endif ?>
          </div>
          <div class="media-info">
            <span class="media-name" title="<?= h($m['original_name']) ?>"><?= h($m['original_name']) ?></span>
            <span class="media-size"><?= round($m['file_size'] / 1024) ?> KB</span>
            <div class="media-url">
              <input type="text" readonly value="<?= h($uploadUrl . $m['filename']) ?>"
                onclick="this.select();document.execCommand('copy');this.setAttribute('title','Kopiert!')" title="Klicken zum Kopieren">
            </div>
          </div>
          <form method="POST" class="media-delete">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-sm btn-danger" data-confirm="Bild «<?= h($m['original_name']) ?>» wirklich löschen?">×</button>
          </form>
        </div>
      <?php endforeach ?>
    </div>
  <?php else: ?>
    <p class="empty">Noch keine Bilder hochgeladen.</p>
  <?php endif ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
