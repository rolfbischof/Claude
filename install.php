<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proimprove – Installation</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:#fff;border-radius:16px;padding:48px;max-width:580px;width:100%;box-shadow:0 8px 40px rgba(0,0,0,.1)}
  .logo{font-size:1.8rem;font-weight:800;margin-bottom:8px}.logo span{color:#f4a820}
  h1{font-size:1.3rem;color:#0f2545;margin-bottom:8px}
  p{color:#64748b;font-size:.9rem;line-height:1.6;margin-bottom:20px}
  .step-badge{display:inline-block;background:#eef2ff;color:#3b5bdb;font-size:.75rem;font-weight:700;padding:3px 10px;border-radius:100px;margin-bottom:16px}
  label{display:block;font-size:.85rem;font-weight:600;color:#1e293b;margin-bottom:5px}
  input{width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.9rem;margin-bottom:16px;outline:none;transition:border-color .2s}
  input:focus{border-color:#3b5bdb}
  .sep{border:none;border-top:1px solid #f1f3f7;margin:20px 0}
  button{width:100%;padding:13px;background:#0f2545;color:#fff;border:none;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;margin-top:8px;transition:background .2s}
  button:hover{background:#1a3a6b}
  .alert{padding:14px 18px;border-radius:8px;font-size:.88rem;margin-bottom:20px;line-height:1.6}
  .alert-error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
  .alert-success{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
  .check{display:flex;align-items:center;gap:10px;padding:8px 0;font-size:.88rem}
  .check-ok{color:#16a34a;font-weight:700}
  .check-fail{color:#dc2626;font-weight:700}
  .req-label{color:#374151}
  code{background:#f8f9fb;border:1px solid #e2e8f0;padding:2px 8px;border-radius:4px;font-family:monospace;font-size:.82rem;display:block;margin:12px 0;word-break:break-all}
</style>
</head>
<body>
<?php
$configPath = __DIR__ . '/config.php';

if (file_exists($configPath)) {
    echo '<div class="card"><div class="logo">Pro<span>improve</span></div><h1>Bereits installiert</h1><p>Die Installation wurde bereits abgeschlossen. Bitte löschen Sie diese Datei vom Server.<br><br><a href="/" style="color:#0f2545;font-weight:600">→ Zur Website</a> &nbsp; <a href="/admin/" style="color:#0f2545;font-weight:600">→ Zum Admin</a></p></div></body></html>';
    exit;
}

$step   = (int)($_POST['step'] ?? 1);
$errors = [];

// --- Step 2: Run install ---
if ($step === 2) {
    if (empty($_POST['db_host']) || empty($_POST['db_name']) || empty($_POST['db_user']) ||
        empty($_POST['admin_user']) || empty($_POST['admin_email']) || empty($_POST['admin_pass'])) {
        $errors[] = 'Bitte alle Pflichtfelder ausfüllen.';
        $step = 1;
    } elseif (strlen($_POST['admin_pass']) < 8) {
        $errors[] = 'Das Admin-Passwort muss mindestens 8 Zeichen lang sein.';
        $step = 1;
    } else {
        try {
            $dsn = 'mysql:host=' . $_POST['db_host'] . ';dbname=' . $_POST['db_name'] . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $_POST['db_user'], $_POST['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            $errors[] = 'Datenbankverbindung fehlgeschlagen: ' . $e->getMessage();
            $step = 1;
        }
    }

    if (empty($errors) && $step === 2) {
        // Create tables
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) UNIQUE NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_key VARCHAR(100) UNIQUE NOT NULL,
  title VARCHAR(500) DEFAULT '',
  subtitle TEXT DEFAULT '',
  content TEXT DEFAULT '',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  icon VARCHAR(20) DEFAULT '⚙',
  title VARCHAR(255) NOT NULL,
  description TEXT,
  features TEXT COMMENT 'JSON array',
  is_featured TINYINT(1) DEFAULT 0,
  sort_order INT DEFAULT 0,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS testimonials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  role VARCHAR(255) DEFAULT '',
  company VARCHAR(255) DEFAULT '',
  quote TEXT NOT NULL,
  avatar_initials VARCHAR(5) DEFAULT '',
  active TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vorname VARCHAR(255) DEFAULT '',
  nachname VARCHAR(255) DEFAULT '',
  email VARCHAR(255) DEFAULT '',
  unternehmen VARCHAR(255) DEFAULT '',
  interesse VARCHAR(255) DEFAULT '',
  nachricht TEXT DEFAULT '',
  ip_address VARCHAR(45) DEFAULT '',
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) DEFAULT '',
  mime_type VARCHAR(100) DEFAULT '',
  file_size INT DEFAULT 0,
  alt_text VARCHAR(500) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
        try {
            foreach (explode(';', $sql) as $q) {
                $q = trim($q);
                if ($q) $pdo->exec($q);
            }

            // Admin user
            $hash = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT IGNORE INTO users (username, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$_POST['admin_user'], $_POST['admin_email'], $hash]);

            // Default settings
            $defaults = [
                'site_name'    => 'Proimprove',
                'site_tagline' => 'Prozesse. Menschen. Ergebnisse.',
                'phone'        => '+41 44 123 45 67',
                'email'        => 'info@proimprove.ch',
                'address'      => 'Zürich, Schweiz',
                'hero_badge'   => 'Schweizer Beratungsunternehmen',
            ];
            $si = $pdo->prepare('INSERT IGNORE INTO settings (`key`,`value`) VALUES (?,?)');
            foreach ($defaults as $k => $v) $si->execute([$k, $v]);

            // Default sections
            $sections = [
                ['hero',  'Prozesse optimieren. Ergebnisse erzielen.',
                 'Wir befähigen Unternehmen, nachhaltigen Wandel zu gestalten – mit bewährten Methoden, konkreten Lösungen und echtem Engagement.',
                 ''],
                ['about', 'Menschen, die Verbesserung leben',
                 'Proimprove wurde mit einer klaren Überzeugung gegründet: Echte Verbesserung entsteht nur, wenn Menschen und Prozesse gemeinsam weiterentwickelt werden.',
                 'Unser Team aus erfahrenen Beratenden, Coaches und Branchenexperten bringt jahrzehntelange Praxiserfahrung aus Industrie, Dienstleistung und dem öffentlichen Sektor mit. Als Schweizer Unternehmen kennen wir die lokalen Gegebenheiten und sprechen Ihre Sprache.'],
            ];
            $ss = $pdo->prepare('INSERT IGNORE INTO sections (section_key,title,subtitle,content) VALUES (?,?,?,?)');
            foreach ($sections as $s) $ss->execute($s);

            // Default services
            $services = [
                ['⚙','Prozessoptimierung','Wir analysieren Ihre bestehenden Abläufe, identifizieren Verschwendung und gestalten schlanke, effiziente Prozesse – nachhaltig und messbar.',
                 '["Prozessanalyse & Mapping","Lean-Implementierung","KPI-Systeme & Dashboards"]',0,1],
                ['👥','Organisationsentwicklung','Strukturen und Kulturen, die Hochleistung ermöglichen. Wir begleiten Veränderungsprozesse von der Strategie bis zur gelebten Praxis.',
                 '["Change Management","Führungskräfteentwicklung","Kulturwandel & Transformation"]',1,2],
                ['🎯','Strategieberatung','Von der Vision zur konkreten Roadmap. Wir helfen Ihnen, die richtigen Prioritäten zu setzen und Ressourcen optimal einzusetzen.',
                 '["Strategieworkshops","OKR-Einführung","Geschäftsmodellentwicklung"]',0,3],
                ['📚','Coaching & Training','Wissen, das bleibt. Unsere Trainings und Coaching-Programme befähigen Ihre Mitarbeitenden dauerhaft zur kontinuierlichen Verbesserung.',
                 '["Lean/Six Sigma Ausbildung","Führungscoaching","Teamworkshops"]',0,4],
                ['📊','Agile Transformation','Flexibler werden ohne Chaos. Wir begleiten Ihre Agile-Einführung mit Augenmas und Pragmatismus.',
                 '["Scrum & Kanban","Agile Führung","Hybride Methoden"]',0,5],
                ['🔍','Unternehmensaudit','Ein klarer Blick von aussen. Unsere Audits liefern ehrliche Bestandsaufnahmen und konkrete Handlungsempfehlungen.',
                 '["Reifegradanalyse","Gap-Analyse","Massnahmenplan"]',0,6],
            ];
            $sv = $pdo->prepare('INSERT IGNORE INTO services (icon,title,description,features,is_featured,sort_order) VALUES (?,?,?,?,?,?)');
            foreach ($services as $s) $sv->execute($s);

            // Default testimonials
            $testi = [
                ['Markus Huber','COO','Industrieunternehmen, Bern','Proimprove hat unsere Produktionsprozesse in nur drei Monaten grundlegend transformiert. Die Effizienzgewinne von über 30% haben unsere Erwartungen weit übertroffen.','MH',0],
                ['Sandra Keller','HR-Direktorin','Dienstleistungsunternehmen, Zürich','Die Zusammenarbeit war von Beginn an partnerschaftlich und auf Augenhöhe. Das Team versteht nicht nur Prozesse, sondern auch Menschen und Unternehmenskultur.','SK',1],
                ['Thomas Roth','CTO','Technologieunternehmen, Basel','Dank Proimprove haben wir unsere Agile-Transformation erfolgreich abgeschlossen. Heute liefern wir schneller, qualitativ besser und mit einem motivierteren Team.','TR',2],
            ];
            $tv = $pdo->prepare('INSERT IGNORE INTO testimonials (name,role,company,quote,avatar_initials,sort_order) VALUES (?,?,?,?,?,?)');
            foreach ($testi as $t) $tv->execute($t);

            // Write config.php
            $siteUrl = rtrim($_POST['site_url'] ?? 'https://www.proimprove.ch', '/');
            $config = "<?php\ndefine('DB_HOST',    " . var_export($_POST['db_host'], true) . ");\n"
                    . "define('DB_NAME',    " . var_export($_POST['db_name'], true) . ");\n"
                    . "define('DB_USER',    " . var_export($_POST['db_user'], true) . ");\n"
                    . "define('DB_PASS',    " . var_export($_POST['db_pass'], true) . ");\n"
                    . "define('DB_CHARSET', 'utf8mb4');\n"
                    . "define('SITE_URL',   " . var_export($siteUrl, true) . ");\n"
                    . "define('UPLOAD_DIR', __DIR__ . '/assets/uploads/');\n"
                    . "define('UPLOAD_URL', SITE_URL . '/assets/uploads/');\n"
                    . "define('MAX_UPLOAD_MB', 5);\n";

            if (!file_put_contents($configPath, $config)) {
                $errors[] = 'config.php konnte nicht geschrieben werden. Bitte erstellen Sie die Datei manuell (Inhalt unten).';
                $manualConfig = $config;
            }
        } catch (Exception $e) {
            $errors[] = 'Installationsfehler: ' . $e->getMessage();
            $step = 1;
        }
    }
}

// Requirements check
$reqs = [
    'PHP ≥ 7.4'      => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO MySQL'       => extension_loaded('pdo_mysql'),
    'JSON'            => extension_loaded('json'),
    'Schreibrechte'   => is_writable(__DIR__),
    'uploads/ Ordner' => is_dir(__DIR__ . '/assets/uploads') && is_writable(__DIR__ . '/assets/uploads'),
];
?>
<div class="card">
  <div class="logo">Pro<span>improve</span></div>

  <?php if ($step === 2 && empty($errors)): ?>
    <div class="step-badge">✓ Installation abgeschlossen</div>
    <h1>Erfolgreich installiert!</h1>
    <?php if (!empty($manualConfig)): ?>
      <div class="alert alert-error">config.php konnte nicht automatisch erstellt werden. Bitte erstellen Sie die Datei manuell mit folgendem Inhalt:</div>
      <code><?= h($manualConfig) ?></code>
    <?php else: ?>
      <p>Die Datenbank und alle Inhalte wurden erfolgreich eingerichtet.<br>
      <strong>Wichtig:</strong> Bitte löschen Sie <code style="display:inline">install.php</code> jetzt vom Server!</p>
    <?php endif ?>
    <div style="display:flex;gap:12px;margin-top:20px">
      <a href="/" style="flex:1;display:block;padding:12px;background:#f4a820;color:#0f2545;font-weight:700;text-align:center;border-radius:8px;text-decoration:none">→ Zur Website</a>
      <a href="/admin/login.php" style="flex:1;display:block;padding:12px;background:#0f2545;color:#fff;font-weight:700;text-align:center;border-radius:8px;text-decoration:none">→ Zum Admin-Login</a>
    </div>

  <?php else: ?>
    <div class="step-badge">Schritt 1 von 1 – Systemprüfung & Konfiguration</div>
    <h1>Installation</h1>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-error"><?= h($e) ?></div>
    <?php endforeach ?>

    <p><strong>Systemanforderungen:</strong></p>
    <?php foreach ($reqs as $label => $ok): ?>
      <div class="check">
        <span class="<?= $ok ? 'check-ok' : 'check-fail' ?>"><?= $ok ? '✓' : '✗' ?></span>
        <span class="req-label"><?= h($label) ?></span>
      </div>
    <?php endforeach ?>

    <?php $allOk = !in_array(false, $reqs, true); ?>
    <?php if (!$allOk): ?>
      <div class="alert alert-error" style="margin-top:16px">Bitte beheben Sie die fehlgeschlagenen Anforderungen, bevor Sie fortfahren.</div>
    <?php endif ?>

    <form method="POST" action="install.php" style="margin-top:24px">
      <input type="hidden" name="step" value="2">

      <p><strong>Datenbankverbindung</strong></p>
      <label>DB-Host *</label>
      <input name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" required placeholder="localhost">
      <label>Datenbankname *</label>
      <input name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required placeholder="proimprove_db">
      <label>Datenbankbenutzer *</label>
      <input name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required placeholder="db_user">
      <label>Datenbankpasswort</label>
      <input name="db_pass" type="password" placeholder="(leer falls kein Passwort)">
      <label>Website-URL</label>
      <input name="site_url" value="<?= h($_POST['site_url'] ?? 'https://www.proimprove.ch') ?>" placeholder="https://www.proimprove.ch">

      <hr class="sep">
      <p><strong>Admin-Konto</strong></p>
      <label>Benutzername *</label>
      <input name="admin_user" value="<?= h($_POST['admin_user'] ?? 'admin') ?>" required>
      <label>E-Mail *</label>
      <input name="admin_email" type="email" value="<?= h($_POST['admin_email'] ?? '') ?>" required placeholder="admin@proimprove.ch">
      <label>Passwort * (min. 8 Zeichen)</label>
      <input name="admin_pass" type="password" required minlength="8">

      <button type="submit" <?= !$allOk ? 'disabled' : '' ?>>Installation starten</button>
    </form>
  <?php endif ?>
</div>
</body>
</html>
