<?php
$pageTitle = 'Einstellungen';
require_once __DIR__ . '/includes/header.php';

$fields = [
    'site_name'    => ['Webseitenname',          'text',  'Proimprove'],
    'site_tagline' => ['Slogan',                  'text',  'Prozesse. Menschen. Ergebnisse.'],
    'hero_badge'   => ['Hero-Badge-Text',          'text',  'Schweizer Beratungsunternehmen'],
    'phone'        => ['Telefonnummer',            'tel',   '+41 44 123 45 67'],
    'email'        => ['E-Mail-Adresse',           'email', 'info@proimprove.ch'],
    'address'      => ['Adresse',                  'text',  'Zürich, Schweiz'],
    'stat_projects'=> ['Statistik: Projekte',      'text',  '200+'],
    'stat_years'   => ['Statistik: Jahre',         'text',  '15+'],
    'stat_rating'  => ['Statistik: Zufriedenheit', 'text',  '98%'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    foreach ($fields as $key => [$label]) {
        if (isset($_POST[$key])) {
            save_setting($key, trim($_POST[$key]));
        }
    }
    // Admin password change
    if (!empty($_POST['new_password'])) {
        if (strlen($_POST['new_password']) < 8) {
            flash('error', 'Das neue Passwort muss mindestens 8 Zeichen lang sein.');
        } elseif ($_POST['new_password'] !== $_POST['new_password2']) {
            flash('error', 'Die Passwörter stimmen nicht überein.');
        } else {
            $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            db_run('UPDATE users SET password_hash=? WHERE id=?', [$hash, $_SESSION['admin_id']]);
            flash('success', 'Einstellungen und Passwort gespeichert.');
            redirect('/admin/settings.php');
        }
    } else {
        flash('success', 'Einstellungen gespeichert.');
        redirect('/admin/settings.php');
    }
}
?>

<form method="POST">
  <?= csrf_field() ?>
  <div class="card" style="margin-bottom:24px">
    <div class="card-header"><h2>Website-Einstellungen</h2></div>
    <?php foreach ($fields as $key => [$label, $type, $placeholder]): ?>
      <div class="form-group">
        <label><?= h($label) ?></label>
        <input type="<?= h($type) ?>" name="<?= h($key) ?>"
          value="<?= h(setting($key, $placeholder)) ?>"
          placeholder="<?= h($placeholder) ?>">
      </div>
    <?php endforeach ?>
  </div>

  <div class="card" style="margin-bottom:24px">
    <div class="card-header"><h2>Admin-Passwort ändern</h2></div>
    <p style="color:#64748b;font-size:.88rem;margin-bottom:16px">Leer lassen, wenn Sie das Passwort nicht ändern möchten.</p>
    <div class="form-row">
      <div class="form-group">
        <label>Neues Passwort (min. 8 Zeichen)</label>
        <input type="password" name="new_password" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label>Passwort wiederholen</label>
        <input type="password" name="new_password2" autocomplete="new-password">
      </div>
    </div>
  </div>

  <button class="btn btn-primary">Einstellungen speichern</button>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
