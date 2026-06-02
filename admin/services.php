<?php
$pageTitle = 'Leistungen';
require_once __DIR__ . '/includes/header.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $features = array_filter(array_map('trim', explode("\n", $_POST['features'] ?? '')));
        $data = [
            $_POST['icon']        ?? '⚙',
            $_POST['title']       ?? '',
            $_POST['description'] ?? '',
            json_encode(array_values($features), JSON_UNESCAPED_UNICODE),
            isset($_POST['is_featured']) ? 1 : 0,
            (int)($_POST['sort_order'] ?? 0),
            isset($_POST['active']) ? 1 : 0,
        ];
        if (!empty($_POST['id'])) {
            db_run('UPDATE services SET icon=?,title=?,description=?,features=?,is_featured=?,sort_order=?,active=? WHERE id=?',
                array_merge($data, [(int)$_POST['id']]));
            flash('success', 'Leistung aktualisiert.');
        } else {
            db_run('INSERT INTO services (icon,title,description,features,is_featured,sort_order,active) VALUES (?,?,?,?,?,?,?)', $data);
            flash('success', 'Leistung hinzugefügt.');
        }

    } elseif ($action === 'delete' && !empty($_POST['id'])) {
        db_run('DELETE FROM services WHERE id=?', [(int)$_POST['id']]);
        flash('success', 'Leistung gelöscht.');

    } elseif ($action === 'toggle' && !empty($_POST['id'])) {
        db_run('UPDATE services SET active = 1 - active WHERE id=?', [(int)$_POST['id']]);
    }
    redirect('/admin/services.php');
}

$edit = null;
if (!empty($_GET['edit'])) {
    $edit = db_get('SELECT * FROM services WHERE id=?', [(int)$_GET['edit']]);
}

$services = db_all('SELECT * FROM services ORDER BY sort_order ASC, id ASC');
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
  <p><?= count($services) ?> Leistungen</p>
  <a href="/admin/services.php?edit=new" class="btn btn-primary">+ Neue Leistung</a>
</div>

<?php if (isset($_GET['edit'])): ?>
<div class="card" style="margin-bottom:32px">
  <div class="card-header">
    <h2><?= $edit ? 'Leistung bearbeiten' : 'Neue Leistung' ?></h2>
    <a href="/admin/services.php" class="btn btn-sm">Abbrechen</a>
  </div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif ?>
    <div class="form-row">
      <div class="form-group">
        <label>Icon (Emoji)</label>
        <input name="icon" value="<?= h($edit['icon'] ?? '⚙') ?>" placeholder="⚙" maxlength="10">
      </div>
      <div class="form-group" style="flex:3">
        <label>Titel *</label>
        <input name="title" value="<?= h($edit['title'] ?? '') ?>" required>
      </div>
    </div>
    <div class="form-group">
      <label>Beschreibung</label>
      <textarea name="description" rows="3"><?= h($edit['description'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label>Features (eine pro Zeile)</label>
      <textarea name="features" rows="4" placeholder="Feature 1&#10;Feature 2&#10;Feature 3"><?php
        $feats = json_decode($edit['features'] ?? '[]', true) ?: [];
        echo h(implode("\n", $feats));
      ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Sortierung</label>
        <input type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 0) ?>">
      </div>
      <div class="form-group" style="display:flex;gap:24px;align-items:flex-end;padding-bottom:4px">
        <label class="checkbox-label">
          <input type="checkbox" name="is_featured" <?= !empty($edit['is_featured']) ? 'checked' : '' ?>>
          Als "Beliebt" markieren
        </label>
        <label class="checkbox-label">
          <input type="checkbox" name="active" <?= ($edit['active'] ?? 1) ? 'checked' : '' ?>>
          Aktiv
        </label>
      </div>
    </div>
    <button class="btn btn-primary">Speichern</button>
  </form>
</div>
<?php endif ?>

<div class="card">
  <table class="table">
    <thead><tr><th>Icon</th><th>Titel</th><th>Sortierung</th><th>Featured</th><th>Aktiv</th><th>Aktionen</th></tr></thead>
    <tbody>
      <?php foreach ($services as $s): ?>
        <tr>
          <td style="font-size:1.4rem"><?= h($s['icon']) ?></td>
          <td><?= h($s['title']) ?></td>
          <td><?= (int)$s['sort_order'] ?></td>
          <td><?= $s['is_featured'] ? '⭐' : '–' ?></td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button class="toggle-btn <?= $s['active'] ? 'on' : 'off' ?>" type="submit">
                <?= $s['active'] ? 'Aktiv' : 'Inaktiv' ?>
              </button>
            </form>
          </td>
          <td>
            <a href="/admin/services.php?edit=<?= (int)$s['id'] ?>" class="btn btn-sm">Bearbeiten</a>
            <form method="POST" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit"
                data-confirm="Leistung «<?= h($s['title']) ?>» wirklich löschen?">Löschen</button>
            </form>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
