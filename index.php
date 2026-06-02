<?php
$cfgPath = __DIR__ . '/config.php';
if (!file_exists($cfgPath)) {
    header('Location: /install.php');
    exit;
}
require_once $cfgPath;
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$hero        = section_get('hero');
$about       = section_get('about');
$services    = db_all('SELECT * FROM services WHERE active=1 ORDER BY sort_order ASC, id ASC');
$testimonials= db_all('SELECT * FROM testimonials WHERE active=1 ORDER BY sort_order ASC');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= h(setting('site_name','Proimprove')) ?> – <?= h(setting('site_tagline','Prozesse. Menschen. Ergebnisse.')) ?>" />
  <title><?= h(setting('site_name','Proimprove')) ?> – <?= h(setting('site_tagline','Prozesse. Menschen. Ergebnisse.')) ?></title>
  <link rel="stylesheet" href="/css/styles.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
</head>
<body>

  <!-- Navigation -->
  <header class="nav-header" id="nav-header">
    <nav class="nav-container">
      <a href="#hero" class="nav-logo">
        <span class="logo-pro">Pro</span><span class="logo-improve">improve</span>
      </a>
      <ul class="nav-links" id="nav-links">
        <li><a href="#leistungen">Leistungen</a></li>
        <li><a href="#ueber-uns">Über uns</a></li>
        <li><a href="#vorteile">Warum wir</a></li>
        <li><a href="#referenzen">Referenzen</a></li>
        <li><a href="#kontakt" class="nav-cta">Kontakt</a></li>
      </ul>
      <button class="hamburger" id="hamburger" aria-label="Menü öffnen">
        <span></span><span></span><span></span>
      </button>
    </nav>
  </header>

  <!-- Hero -->
  <section class="hero" id="hero">
    <div class="hero-bg"></div>
    <div class="hero-content">
      <span class="hero-badge"><?= h(setting('hero_badge','Schweizer Beratungsunternehmen')) ?></span>
      <h1 class="hero-title">
        <?php
        $heroTitle = $hero['title'] ?? 'Prozesse optimieren. Ergebnisse erzielen.';
        $parts = explode('.', $heroTitle, 2);
        echo h(trim($parts[0])) . '.';
        if (!empty($parts[1])) echo '<br><span class="hero-accent">' . h(trim($parts[1])) . '.</span>';
        ?>
      </h1>
      <p class="hero-subtitle"><?= h($hero['subtitle'] ?? 'Wir befähigen Unternehmen, nachhaltigen Wandel zu gestalten – mit bewährten Methoden, konkreten Lösungen und echtem Engagement.') ?></p>
      <div class="hero-actions">
        <a href="#kontakt" class="btn btn-primary">Kostenlose Erstberatung</a>
        <a href="#leistungen" class="btn btn-secondary">Leistungen entdecken</a>
      </div>
      <div class="hero-stats">
        <div class="stat">
          <span class="stat-number"><?= h(setting('stat_projects','200+')) ?></span>
          <span class="stat-label">Projekte</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat">
          <span class="stat-number"><?= h(setting('stat_years','15+')) ?></span>
          <span class="stat-label">Jahre Erfahrung</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat">
          <span class="stat-number"><?= h(setting('stat_rating','98%')) ?></span>
          <span class="stat-label">Kundenzufriedenheit</span>
        </div>
      </div>
    </div>
    <div class="hero-visual">
      <div class="hero-card card-1">
        <div class="card-icon">&#9650;</div>
        <div class="card-text"><strong>+34%</strong><span>Effizienzsteigerung</span></div>
      </div>
      <div class="hero-card card-2">
        <div class="card-icon">&#128200;</div>
        <div class="card-text"><strong>Lean &amp; Agile</strong><span>Methodik</span></div>
      </div>
      <div class="hero-card card-3">
        <div class="card-icon">&#10003;</div>
        <div class="card-text"><strong>Nachhaltig</strong><span>Implementiert</span></div>
      </div>
    </div>
  </section>

  <!-- Leistungen -->
  <section class="section" id="leistungen">
    <div class="container">
      <div class="section-header">
        <span class="section-tag">Was wir tun</span>
        <h2 class="section-title">Unsere Leistungen</h2>
        <p class="section-sub">Massgeschneiderte Lösungen für Ihre individuellen Herausforderungen – vom ersten Audit bis zur nachhaltigen Implementierung.</p>
      </div>
      <div class="services-grid">
        <?php foreach ($services as $s): ?>
          <div class="service-card <?= $s['is_featured'] ? 'featured' : '' ?>" data-animate>
            <?php if ($s['is_featured']): ?><div class="service-badge">Beliebt</div><?php endif ?>
            <div class="service-icon"><?= h($s['icon']) ?></div>
            <h3><?= h($s['title']) ?></h3>
            <p><?= h($s['description']) ?></p>
            <?php $features = json_decode($s['features'] ?? '[]', true) ?: []; ?>
            <?php if ($features): ?>
              <ul class="service-list">
                <?php foreach ($features as $f): ?>
                  <li><?= h($f) ?></li>
                <?php endforeach ?>
              </ul>
            <?php endif ?>
          </div>
        <?php endforeach ?>
      </div>
    </div>
  </section>

  <!-- Über uns -->
  <section class="section section-alt" id="ueber-uns">
    <div class="container">
      <div class="about-layout">
        <div class="about-visual">
          <div class="about-image-wrap">
            <div class="about-image-placeholder">
              <div class="about-initials">PI</div>
            </div>
            <div class="about-badge-wrap">
              <div class="about-badge">
                <span class="badge-num"><?= h(setting('stat_years','15+')) ?></span>
                <span class="badge-txt">Jahre Erfahrung</span>
              </div>
            </div>
          </div>
        </div>
        <div class="about-content">
          <span class="section-tag">Wer wir sind</span>
          <h2 class="section-title"><?= h($about['title'] ?? 'Menschen, die Verbesserung leben') ?></h2>
          <p class="about-lead"><?= h($about['subtitle'] ?? '') ?></p>
          <?php if (!empty($about['content'])): ?>
            <p><?= h($about['content']) ?></p>
          <?php endif ?>
          <div class="about-values">
            <div class="value"><div class="value-icon">&#9733;</div><div><strong>Qualität</strong><p>Erstklassige Beratung, keine Kompromisse.</p></div></div>
            <div class="value"><div class="value-icon">&#9679;</div><div><strong>Partnerschaft</strong><p>Wir sind Teil Ihres Teams, nicht nur Berater.</p></div></div>
            <div class="value"><div class="value-icon">&#8635;</div><div><strong>Nachhaltigkeit</strong><p>Ergebnisse, die über das Projekt hinaus bestehen.</p></div></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Vorteile -->
  <section class="section" id="vorteile">
    <div class="container">
      <div class="section-header">
        <span class="section-tag">Warum Proimprove</span>
        <h2 class="section-title">Ihr Mehrwert auf einen Blick</h2>
        <p class="section-sub">Wir liefern nicht nur Konzepte – wir setzen gemeinsam um und begleiten Sie bis zum messbaren Erfolg.</p>
      </div>
      <div class="advantages-grid">
        <?php
        $advantages = [
            ['01','Ergebnisorientiert','Wir definieren von Anfang an klare Ziele und messen unsere Arbeit konsequent an Ihrem Erfolg – nicht an Stundenzahlen.'],
            ['02','Praxiserprobt','Alle unsere Berater verfügen über mindestens 10 Jahre operative Erfahrung. Wir kennen die Herausforderungen aus erster Hand.'],
            ['03','Massgeschneidert','Keine Standardlösungen von der Stange. Jedes Mandat wird individuell konzipiert und auf Ihre spezifischen Bedürfnisse zugeschnitten.'],
            ['04','Schnell einsatzbereit','Wir starten innerhalb weniger Tage und liefern erste Quick Wins bereits in den ersten Wochen – ohne langen Anlauf.'],
            ['05','Wissenstransfer inklusive','Wir befähigen Ihre Mitarbeitenden, selbstständig weiterzumachen. Abhängigkeit von externen Beratern ist nicht unser Geschäftsmodell.'],
            ['06','Schweizer Qualität','Präzision, Verlässlichkeit und Diskretion – diese Werte leben wir in jedem Mandat. Dafür stehen wir mit unserem Namen.'],
        ];
        foreach ($advantages as [$num, $title, $text]):
        ?>
          <div class="advantage" data-animate>
            <div class="adv-number"><?= h($num) ?></div>
            <h3><?= h($title) ?></h3>
            <p><?= h($text) ?></p>
          </div>
        <?php endforeach ?>
      </div>
    </div>
  </section>

  <!-- Referenzen -->
  <section class="section section-alt" id="referenzen">
    <div class="container">
      <div class="section-header">
        <span class="section-tag">Kundenstimmen</span>
        <h2 class="section-title">Was unsere Kunden sagen</h2>
      </div>
      <div class="testimonials-grid">
        <?php foreach ($testimonials as $t): ?>
          <div class="testimonial" data-animate>
            <div class="testimonial-quote">&#8220;</div>
            <p><?= h($t['quote']) ?></p>
            <div class="testimonial-author">
              <div class="author-avatar"><?= h($t['avatar_initials']) ?></div>
              <div>
                <strong><?= h($t['name']) ?></strong>
                <span><?= h($t['role']) ?><?= $t['role'] && $t['company'] ? ', ' : '' ?><?= h($t['company']) ?></span>
              </div>
            </div>
          </div>
        <?php endforeach ?>
      </div>
      <div class="client-logos">
        <p class="logos-label">Vertrauen von führenden Unternehmen</p>
        <div class="logos-row">
          <div class="logo-placeholder">Industrie AG</div>
          <div class="logo-placeholder">TechGroup</div>
          <div class="logo-placeholder">Finance SA</div>
          <div class="logo-placeholder">Health GmbH</div>
          <div class="logo-placeholder">Retail Corp</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Kontakt -->
  <section class="section section-contact" id="kontakt">
    <div class="container">
      <div class="contact-layout">
        <div class="contact-info">
          <span class="section-tag section-tag-light">Kontakt</span>
          <h2 class="section-title section-title-light">Starten Sie Ihre Verbesserungsreise</h2>
          <p class="contact-lead">Vereinbaren Sie jetzt ein kostenfreies Erstgespräch. In 30 Minuten klären wir, wie wir Ihnen konkret helfen können.</p>
          <div class="contact-details">
            <div class="contact-item">
              <div class="contact-icon">&#9993;</div>
              <a href="mailto:<?= h(setting('email','info@proimprove.ch')) ?>"><?= h(setting('email','info@proimprove.ch')) ?></a>
            </div>
            <div class="contact-item">
              <div class="contact-icon">&#128222;</div>
              <a href="tel:<?= h(preg_replace('/\s+/','',(setting('phone','+41441234567')))) ?>"><?= h(setting('phone','+41 44 123 45 67')) ?></a>
            </div>
            <div class="contact-item">
              <div class="contact-icon">&#128205;</div>
              <span><?= h(setting('address','Zürich, Schweiz')) ?></span>
            </div>
          </div>
        </div>
        <div class="contact-form-wrap">
          <form class="contact-form" id="contact-form" novalidate>
            <div class="form-row">
              <div class="form-group">
                <label for="vorname">Vorname</label>
                <input type="text" id="vorname" name="vorname" placeholder="Max" required />
              </div>
              <div class="form-group">
                <label for="nachname">Nachname</label>
                <input type="text" id="nachname" name="nachname" placeholder="Mustermann" required />
              </div>
            </div>
            <div class="form-group">
              <label for="email">E-Mail</label>
              <input type="email" id="email" name="email" placeholder="max@unternehmen.ch" required />
            </div>
            <div class="form-group">
              <label for="unternehmen">Unternehmen</label>
              <input type="text" id="unternehmen" name="unternehmen" placeholder="Ihr Unternehmen AG" />
            </div>
            <div class="form-group">
              <label for="interesse">Interesse an</label>
              <select id="interesse" name="interesse">
                <option value="">Bitte wählen...</option>
                <?php foreach ($services as $s): ?>
                  <option><?= h($s['title']) ?></option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="form-group">
              <label for="nachricht">Nachricht</label>
              <textarea id="nachricht" name="nachricht" rows="4" placeholder="Beschreiben Sie kurz Ihre Situation..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-full" id="submit-btn">Gespräch anfragen</button>
            <p class="form-note">Wir antworten innerhalb von 24 Stunden.</p>
          </form>
          <div class="form-success" id="form-success" hidden>
            <div class="success-icon">&#10003;</div>
            <h3>Vielen Dank!</h3>
            <p>Wir haben Ihre Anfrage erhalten und melden uns innerhalb von 24 Stunden bei Ihnen.</p>
          </div>
          <div class="form-error" id="form-error" hidden style="color:#dc2626;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:14px;margin-top:12px;font-size:.9rem"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <div class="nav-logo"><span class="logo-pro">Pro</span><span class="logo-improve">improve</span></div>
          <p>Ihr Schweizer Partner für nachhaltige Prozessoptimierung und Organisationsentwicklung.</p>
        </div>
        <div class="footer-col">
          <h4>Leistungen</h4>
          <ul>
            <?php foreach (array_slice($services, 0, 4) as $s): ?>
              <li><a href="#leistungen"><?= h($s['title']) ?></a></li>
            <?php endforeach ?>
          </ul>
        </div>
        <div class="footer-col">
          <h4>Unternehmen</h4>
          <ul>
            <li><a href="#ueber-uns">Über uns</a></li>
            <li><a href="#referenzen">Referenzen</a></li>
            <li><a href="#kontakt">Kontakt</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h4>Kontakt</h4>
          <ul>
            <li><a href="mailto:<?= h(setting('email')) ?>"><?= h(setting('email','info@proimprove.ch')) ?></a></li>
            <li><a href="tel:<?= h(preg_replace('/\s+/','',setting('phone'))) ?>"><?= h(setting('phone','+41 44 123 45 67')) ?></a></li>
            <li><?= h(setting('address','Zürich, Schweiz')) ?></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> <?= h(setting('site_name','Proimprove')) ?>. Alle Rechte vorbehalten.</p>
        <div class="footer-legal"><a href="#">Impressum</a><a href="#">Datenschutz</a></div>
      </div>
    </div>
  </footer>

  <script src="/js/main.js"></script>
</body>
</html>
