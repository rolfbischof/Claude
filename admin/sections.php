<?php
$pageTitle = 'Seiteninhalt';
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $key = $_POST['section_key'] ?? '';
    if ($key) {
        db_run('INSERT INTO sections (section_key, title, subtitle, content)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle), content=VALUES(content)',
            [$key, $_POST['title'] ?? '', $_POST['subtitle'] ?? '', $_POST['content'] ?? '']);
        flash('success', 'Abschnitt gespeichert.');
    }
    redirect('/admin/sections.php');
}

$hero  = section_get('hero');
$about = section_get('about');
?>

<div class="tabs">
  <button class="tab-btn active" data-tab="hero">Hero-Bereich</button>
  <button class="tab-btn" data-tab="about">Über uns</button>
</div>

<div id="tab-hero" class="tab-pane card" style="margin-top:20px">
  <div class="card-header"><h2>Hero-Bereich</h2></div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="section_key" value="hero">
    <div class="form-group">
      <label>Hauptüberschrift</label>
      <input name="title" value="<?= h($hero['title'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>Untertitel</label>
      <textarea name="subtitle" rows="3"><?= h($hero['subtitle'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label>Zusatztext (optional)</label>
      <textarea name="content" rows="3"><?= h($hero['content'] ?? '') ?></textarea>
    </div>
    <button class="btn btn-primary">Speichern</button>
  </form>
</div>

<div id="tab-about" class="tab-pane card" style="display:none;margin-top:20px">
  <div class="card-header"><h2>Über uns</h2></div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="section_key" value="about">
    <div class="form-group">
      <label>Titel</label>
      <input name="title" value="<?= h($about['title'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Lead-Text</label>
      <textarea name="subtitle" rows="3"><?= h($about['subtitle'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label>Haupttext</label>
      <textarea name="content" rows="6"><?= h($about['content'] ?? '') ?></textarea>
    </div>
    <button class="btn btn-primary">Speichern</button>
  </form>
</div>

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab).style.display = 'block';
  });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
