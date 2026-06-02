-- ============================================================
-- mb Kommunikation + Events
-- MySQL Database Schema
-- Hoststar.ch compatible (MySQL 5.7+ / MariaDB 10.2+)
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET time_zone = '+01:00';
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(50)  UNIQUE NOT NULL,
  `email`         VARCHAR(100) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name`    VARCHAR(50)  DEFAULT NULL,
  `last_name`     VARCHAR(50)  DEFAULT NULL,
  `role`          ENUM('superadmin','admin','editor','viewer') NOT NULL DEFAULT 'viewer',
  `active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login`    DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: pages
-- ============================================================
CREATE TABLE IF NOT EXISTS `pages` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `slug`             VARCHAR(100) UNIQUE NOT NULL,
  `title`            VARCHAR(200) NOT NULL,
  `subtitle`         VARCHAR(300) DEFAULT NULL,
  `content`          LONGTEXT     DEFAULT NULL,
  `meta_title`       VARCHAR(200) DEFAULT NULL,
  `meta_description` VARCHAR(300) DEFAULT NULL,
  `hero_image`       VARCHAR(500) DEFAULT NULL,
  `published`        TINYINT(1)   DEFAULT 1,
  `created_by`       INT          DEFAULT NULL,
  `created_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: events_portfolio
-- ============================================================
CREATE TABLE IF NOT EXISTS `events_portfolio` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `title`       VARCHAR(200) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `event_date`  DATE         DEFAULT NULL,
  `location`    VARCHAR(200) DEFAULT NULL,
  `category`    VARCHAR(100) DEFAULT NULL,
  `image`       VARCHAR(500) DEFAULT NULL,
  `gallery`     JSON         DEFAULT NULL,
  `published`   TINYINT(1)   DEFAULT 1,
  `featured`    TINYINT(1)   DEFAULT 0,
  `sort_order`  INT          DEFAULT 0,
  `created_by`  INT          DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: references_portfolio
-- ============================================================
CREATE TABLE IF NOT EXISTS `references_portfolio` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `title`       VARCHAR(200) NOT NULL,
  `client`      VARCHAR(200) DEFAULT NULL,
  `description` TEXT         DEFAULT NULL,
  `category`    ENUM('kommunikation','events','design','alle') DEFAULT 'alle',
  `image`       VARCHAR(500) DEFAULT NULL,
  `url`         VARCHAR(500) DEFAULT NULL,
  `published`   TINYINT(1)   DEFAULT 1,
  `featured`    TINYINT(1)   DEFAULT 0,
  `sort_order`  INT          DEFAULT 0,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: contact_messages
-- ============================================================
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(100) NOT NULL,
  `phone`      VARCHAR(50)  DEFAULT NULL,
  `subject`    VARCHAR(200) DEFAULT NULL,
  `message`    TEXT         NOT NULL,
  `is_read`    TINYINT(1)   DEFAULT 0,
  `replied_at` DATETIME     DEFAULT NULL,
  `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: settings
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(100) PRIMARY KEY,
  `value`      TEXT         DEFAULT NULL,
  `label`      VARCHAR(200) DEFAULT NULL,
  `type`       ENUM('text','textarea','email','tel','url','image','boolean') DEFAULT 'text',
  `updated_at` DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: backups
-- ============================================================
CREATE TABLE IF NOT EXISTS `backups` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `filename`    VARCHAR(300) NOT NULL,
  `size_bytes`  BIGINT       DEFAULT NULL,
  `backup_type` ENUM('full','database','files') DEFAULT 'full',
  `notes`       TEXT         DEFAULT NULL,
  `created_by`  INT          DEFAULT NULL,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: categories
-- ============================================================
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(100) UNIQUE NOT NULL,
  `type`        ENUM('event','reference','page') DEFAULT 'event',
  `description` TEXT         DEFAULT NULL,
  `color`       VARCHAR(7)   DEFAULT '#1e3a5f',
  `sort_order`  INT          DEFAULT 0,
  `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DEFAULT DATA
-- ============================================================

-- Default superadmin user (password: Admin2024!)
-- Hash generated with: password_hash('Admin2024!', PASSWORD_BCRYPT, ['cost' => 12])
INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `role`) VALUES
('admin', 'info@mb-kommunikation-events.ch', '$2y$12$LcKp8Y2OKvhTqxkDZqpPF.8J/IYm8fGDRoqjB7MVuBBhYxFWPl4Wy', 'Manuela', 'Bischof', 'superadmin');

-- ============================================================
-- Default settings
-- ============================================================
INSERT INTO `settings` (`key`, `value`, `label`, `type`) VALUES
('site_name',        'mb Kommunikation + Events',                                                  'Website Name',         'text'),
('site_tagline',     'Professionelle Kommunikation & unvergessliche Events',                        'Tagline',              'text'),
('contact_email',    'info@mb-kommunikation-events.ch',                                            'Kontakt E-Mail',       'email'),
('contact_phone',    '',                                                                            'Telefon',              'tel'),
('contact_address',  'Hochdorf, Schweiz',                                                          'Adresse',              'text'),
('facebook_url',     '',                                                                            'Facebook URL',         'url'),
('instagram_url',    'https://www.instagram.com/bischof_manuela72/',                               'Instagram URL',        'url'),
('linkedin_url',     '',                                                                            'LinkedIn URL',         'url'),
('hero_title',       'Kommunikation & Events',                                                     'Hero Titel',           'text'),
('hero_subtitle',    'Professionelle Beratung und unvergessliche Erlebnisse für Ihr Unternehmen',  'Hero Untertitel',      'textarea'),
('about_text',       'Manuela Bischof ist die Inhaberin von mb Kommunikation + Events. Mit Leidenschaft und Professionalität berät sie Unternehmen in Kommunikationsfragen und organisiert unvergessliche Events.', 'Über mich Text', 'textarea'),
('google_analytics', '',                                                                            'Google Analytics ID',  'text'),
('maintenance_mode', '0',                                                                           'Wartungsmodus',        'boolean');

-- ============================================================
-- Default pages
-- ============================================================
INSERT INTO `pages` (`slug`, `title`, `subtitle`, `meta_title`, `meta_description`, `published`, `created_by`) VALUES
('home',
 'Startseite',
 'Professionelle Kommunikation & unvergessliche Events',
 'mb Kommunikation + Events | Hochdorf, Schweiz',
 'Manuela Bischof – Ihre Expertin für Kommunikationsberatung und professionelle Eventplanung in der Zentralschweiz.',
 1, 1),
('kommunikation',
 'Kommunikation',
 'Ihre Botschaft, professionell vermittelt',
 'Kommunikationsberatung & Print | mb Kommunikation + Events',
 'Kommunikationsberatung, Drucksachen, Medienarbeit, Websites und Werbetechnik – alles aus einer Hand in Hochdorf.',
 1, 1),
('events',
 'Events',
 'Unvergessliche Erlebnisse für Ihr Unternehmen',
 'Eventplanung & Durchführung | mb Kommunikation + Events',
 'Von der Eventberatung über die Konzeption bis zur Durchführung – Firmenevents, Teamevents und Jubiläen.',
 1, 1),
('referenzen',
 'Referenzen',
 'Projekte, die begeistern',
 'Referenzen | mb Kommunikation + Events',
 'Ausgewählte Referenzprojekte aus Kommunikation, Events und Design von mb Kommunikation + Events.',
 1, 1),
('ueber-mich',
 'Über mich',
 'Manuela Bischof – Inhaberin',
 'Über mich | mb Kommunikation + Events',
 'Manuela Bischof – Leidenschaft für Kommunikation und unvergessliche Events. Inhaberin von mb Kommunikation + Events in Hochdorf.',
 1, 1),
('kontakt',
 'Kontakt',
 'Wir freuen uns auf Ihre Nachricht',
 'Kontakt | mb Kommunikation + Events',
 'Nehmen Sie Kontakt auf – mb Kommunikation + Events, Manuela Bischof, Hochdorf, Schweiz.',
 1, 1);

-- ============================================================
-- Default categories
-- ============================================================
INSERT INTO `categories` (`name`, `slug`, `type`, `color`, `sort_order`) VALUES
('Konzert',          'konzert',          'event',     '#1e3a5f', 1),
('Firmenanlass',     'firmenanlass',     'event',     '#1e3a5f', 2),
('Geburtstagsfeier', 'geburtstagsfeier', 'event',     '#e94560', 3),
('Team-Event',       'team-event',       'event',     '#1e3a5f', 4),
('Jubiläum',         'jubilaeum',        'event',     '#f5a623', 5),
('Kommunikation',    'kommunikation',    'reference', '#1e3a5f', 1),
('Events',           'events-ref',       'reference', '#e94560', 2),
('Design',           'design',           'reference', '#f5a623', 3);

-- ============================================================
-- Sample events portfolio
-- ============================================================
INSERT INTO `events_portfolio` (`title`, `description`, `event_date`, `location`, `category`, `published`, `featured`, `sort_order`, `created_by`) VALUES
('Rock-Night 2025 Hochdorf',
 'Unvergessliche Rock-Night mit verschiedenen Bands und Akustik-Aufführungen. Ein Abend voller Musik und Energie für alle Musikbegeisterten der Region.',
 '2025-09-15', 'Hochdorf, Luzern', 'Konzert', 1, 1, 1, 1),

('Schlagerparty – 50 Jahre Manu',
 'Grosse Geburtstagsfeier mit Schlagermusik, tollem Ambiente und unvergesslichen Momenten. Eine Nacht, die alle Gäste noch lange in Erinnerung behalten werden.',
 '2023-06-10', 'Hochdorf', 'Geburtstagsfeier', 1, 1, 2, 1),

('Firmenjubiläum 25 Jahre',
 'Planung und Durchführung eines grossen Firmenjubiläums mit 150 Gästen. Von der Konzeption über die Dekoration bis zur Unterhaltung – alles aus einer Hand.',
 '2024-10-05', 'Luzern', 'Firmenanlass', 1, 0, 3, 1),

('Team-Building Event',
 'Innovatives Team-Building Programm für ein mittelständisches Unternehmen. Outdoor-Aktivitäten, Workshops und gemeinsames Abendessen für 40 Mitarbeitende.',
 '2024-08-20', 'Schweiz', 'Team-Event', 1, 0, 4, 1);

-- ============================================================
-- Sample references portfolio
-- ============================================================
INSERT INTO `references_portfolio` (`title`, `client`, `description`, `category`, `published`, `featured`, `sort_order`) VALUES
('Unternehmenskommunikation',
 'KMU Luzern',
 'Entwicklung einer ganzheitlichen Kommunikationsstrategie inklusive Print-Materialien und digitaler Präsenz.',
 'kommunikation', 1, 1, 1),

('Corporate Identity Design',
 'Startup Zug',
 'Vollständige CI-Entwicklung: Logo, Geschäftspapiere, Flyer und Website-Konzept.',
 'design', 1, 1, 2),

('Jahresevent Organisation',
 'Verein Hochdorf',
 'Planung und Durchführung des Jahresevents mit 80 Teilnehmenden.',
 'events', 1, 1, 3),

('Newsletter-Konzept',
 'Detailhandel Kunde',
 'Konzeption und Gestaltung eines monatlichen Mitarbeiter-Newsletters.',
 'kommunikation', 1, 0, 4);
