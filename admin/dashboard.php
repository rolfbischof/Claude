<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$stats = [
    'Leistungen'     => db_get('SELECT COUNT(*) c FROM services WHERE active=1')['c'] ?? 0,
    'Referenzen'     => db_get('SELECT COUNT(*) c FROM testimonials WHERE active=1')['c'] ?? 0,
    'Kontaktanfragen'=> db_get('SELECT COUNT(*) c FROM contact_submissions')['c'] ?? 0,
    'Ungelesen'      => db_get('SELECT COUNT(*) c FROM contact_submissions WHERE is_read=0')['c'] ?? 0,
    'Bilder'         => db_get('SELECT COUNT(*) c FROM media')['c'] ?? 0,
];
$recent = db_all('SELECT * FROM contact_submissions ORDER BY created_at DESC LIMIT 5');
?>
<div class="stats-grid">
  <?php foreach ($stats as $label => $val): ?>
    <div class="stat-card<?= $label==='Ungelesen'&&$val>0?' stat-card--warn':''?>">
      <div class="stat-value"><?= (int)$val ?></div>
      <div class="stat-label"><?= h($label) ?></div>
    </div>
  <?php endforeach ?>
</div>

<div class="card" style="margin-top:32px">
  <div class="card-header">
    <h2>Neueste Kontaktanfragen</h2>
    <a href="/admin/contacts.php" class="btn btn-sm">Alle anzeigen</a>
  </div>
  <?php if ($recent): ?>
    <table class="table">
      <thead><tr><th>Name</th><th>E-Mail</th><th>Interesse</th><th>Datum</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr class="<?= !$r['is_read'] ? 'row-unread' : '' ?>">
            <td><?= h($r['vorname'].' '.$r['nachname']) ?></td>
            <td><?= h($r['email']) ?></td>
            <td><?= h($r['interesse']) ?></td>
            <td><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
            <td><a href="/admin/contacts.php?view=<?= (int)$r['id'] ?>" class="btn btn-sm">Ansehen</a></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="empty">Noch keine Kontaktanfragen.</p>
  <?php endif ?>
</div>

<div class="quick-links" style="margin-top:24px">
  <a href="/admin/services.php" class="quick-card">⚙️ Leistungen bearbeiten</a>
  <a href="/admin/testimonials.php" class="quick-card">💬 Referenzen verwalten</a>
  <a href="/admin/media.php" class="quick-card">🖼️ Bilder hochladen</a>
  <a href="/admin/settings.php" class="quick-card">⚙️ Einstellungen</a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
