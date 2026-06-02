<?php
$pageTitle = 'Kontaktanfragen';
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && !empty($_POST['id'])) {
        db_run('DELETE FROM contact_submissions WHERE id=?', [(int)$_POST['id']]);
        flash('success', 'Anfrage gelöscht.');
        redirect('/admin/contacts.php');
    }
}

// View single submission
if (!empty($_GET['view'])) {
    $item = db_get('SELECT * FROM contact_submissions WHERE id=?', [(int)$_GET['view']]);
    if ($item) {
        db_run('UPDATE contact_submissions SET is_read=1 WHERE id=?', [(int)$_GET['view']]);
    }
}

$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;
$total   = (int)(db_get('SELECT COUNT(*) c FROM contact_submissions')['c'] ?? 0);
$items   = db_all('SELECT * FROM contact_submissions ORDER BY created_at DESC LIMIT ? OFFSET ?', [$perPage, $offset]);
$pages   = max(1, ceil($total / $perPage));
?>

<?php if (!empty($item)): ?>
<div class="card" style="margin-bottom:28px">
  <div class="card-header">
    <h2>Anfrage von <?= h($item['vorname'].' '.$item['nachname']) ?></h2>
    <a href="/admin/contacts.php" class="btn btn-sm">← Zurück zur Liste</a>
  </div>
  <dl class="detail-list">
    <dt>Name</dt><dd><?= h($item['vorname'].' '.$item['nachname']) ?></dd>
    <dt>E-Mail</dt><dd><a href="mailto:<?= h($item['email']) ?>"><?= h($item['email']) ?></a></dd>
    <dt>Unternehmen</dt><dd><?= h($item['unternehmen'] ?: '–') ?></dd>
    <dt>Interesse</dt><dd><?= h($item['interesse'] ?: '–') ?></dd>
    <dt>Nachricht</dt><dd style="white-space:pre-wrap"><?= h($item['nachricht']) ?></dd>
    <dt>Datum</dt><dd><?= date('d.m.Y H:i', strtotime($item['created_at'])) ?></dd>
    <dt>IP</dt><dd><?= h($item['ip_address']) ?></dd>
  </dl>
  <div style="margin-top:20px;display:flex;gap:12px">
    <a href="mailto:<?= h($item['email']) ?>?subject=Ihre Anfrage bei Proimprove" class="btn btn-primary">Per E-Mail antworten</a>
    <form method="POST" style="display:inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
      <button class="btn btn-danger" data-confirm="Anfrage wirklich löschen?">Löschen</button>
    </form>
  </div>
</div>
<?php endif ?>

<div class="card">
  <div class="card-header">
    <h2><?= $total ?> Kontaktanfragen</h2>
  </div>
  <?php if ($items): ?>
    <table class="table">
      <thead><tr><th></th><th>Name</th><th>E-Mail</th><th>Interesse</th><th>Datum</th><th>Aktionen</th></tr></thead>
      <tbody>
        <?php foreach ($items as $r): ?>
          <tr class="<?= !$r['is_read'] ? 'row-unread' : '' ?>">
            <td><?= !$r['is_read'] ? '<span style="color:#f4a820;font-weight:700">●</span>' : '' ?></td>
            <td><?= h($r['vorname'].' '.$r['nachname']) ?></td>
            <td><?= h($r['email']) ?></td>
            <td><?= h($r['interesse'] ?: '–') ?></td>
            <td><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
            <td>
              <a href="/admin/contacts.php?view=<?= (int)$r['id'] ?>" class="btn btn-sm">Ansehen</a>
              <form method="POST" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-sm btn-danger" data-confirm="Anfrage löschen?">×</button>
              </form>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a href="?p=<?= $i ?>" class="<?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor ?>
      </div>
    <?php endif ?>
  <?php else: ?>
    <p class="empty">Noch keine Kontaktanfragen.</p>
  <?php endif ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
