<?php
$pageTitle = 'Referenzen';
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', $_POST['name'] ?? ''), 0, 2))));
        $data = [
            $_POST['name']    ?? '',
            $_POST['role']    ?? '',
            $_POST['company'] ?? '',
            $_POST['quote']   ?? '',
            $initials,
            (int)($_POST['sort_order'] ?? 0),
            isset($_POST['active']) ? 1 : 0,
        ];
        if (!empty($_POST['id'])) {
            db_run('UPDATE testimonials SET name=?,role=?,company=?,quote=?,avatar_initials=?,sort_order=?,active=? WHERE id=?',
                array_merge($data, [(int)$_POST['id']]));
            flash('success', 'Referenz aktualisiert.');
        } else {
            db_run('INSERT INTO testimonials (name,role,company,quote,avatar_initials,sort_order,active) VALUES (?,?,?,?,?,?,?)', $data);
            flash('success', 'Referenz hinzugefügt.');
        }
    } elseif ($action === 'delete' && !empty($_POST['id'])) {
        db_run('DELETE FROM testimonials WHERE id=?', [(int)$_POST['id']]);
        flash('success', 'Referenz gelöscht.');
    } elseif ($action === 'toggle' && !empty($_POST['id'])) {
        db_run('UPDATE testimonials SET active = 1 - active WHERE id=?', [(int)$_POST['id']]);
    }
    redirect('/admin/testimonials.php');
}

$edit = null;
if (!empty($_GET['edit'])) {
    $edit = db_get('SELECT * FROM testimonials WHERE id=?', [(int)$_GET['edit']]);
}

$items = db_all('SELECT * FROM testimonials ORDER BY sort_order ASC, id ASC');
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
  <p><?= count($items) ?> Referenzen</p>
  <a href="/admin/testimonials.php?edit=new" class="btn btn-primary">+ Neue Referenz</a>
</div>

<?php if (isset($_GET['edit'])): ?>
<div class="card" style="margin-bottom:32px">
  <div class="card-header">
    <h2><?= $edit ? 'Referenz bearbeiten' : 'Neue Referenz' ?></h2>
    <a href="/admin/testimonials.php" class="btn btn-sm">Abbrechen</a>
  </div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif ?>
    <div class="form-row">
      <div class="form-group">
        <label>Name *</label>
        <input name="name" value="<?= h($edit['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Funktion</label>
        <input name="role" value="<?= h($edit['role'] ?? '') ?>" placeholder="COO">
      </div>
    </div>
    <div class="form-group">
      <label>Unternehmen</label>
      <input name="company" value="<?= h($edit['company'] ?? '') ?>" placeholder="Unternehmen, Ort">
    </div>
    <div class="form-group">
      <label>Zitat *</label>
      <textarea name="quote" rows="4" required><?= h($edit['quote'] ?? '') ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Sortierung</label>
        <input type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 0) ?>">
      </div>
      <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px">
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
    <thead><tr><th>Name</th><th>Funktion</th><th>Unternehmen</th><th>Aktiv</th><th>Aktionen</th></tr></thead>
    <tbody>
      <?php foreach ($items as $t): ?>
        <tr>
          <td><strong><?= h($t['name']) ?></strong></td>
          <td><?= h($t['role']) ?></td>
          <td><?= h($t['company']) ?></td>
          <td>
            <form method="POST" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <button class="toggle-btn <?= $t['active'] ? 'on' : 'off' ?>" type="submit">
                <?= $t['active'] ? 'Aktiv' : 'Inaktiv' ?>
              </button>
            </form>
          </td>
          <td>
            <a href="/admin/testimonials.php?edit=<?= (int)$t['id'] ?>" class="btn btn-sm">Bearbeiten</a>
            <form method="POST" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit"
                data-confirm="Referenz von «<?= h($t['name']) ?>» wirklich löschen?">Löschen</button>
            </form>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
