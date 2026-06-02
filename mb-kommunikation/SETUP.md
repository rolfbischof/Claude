# mb Kommunikation + Events – Setup-Anleitung

## Technologie-Stack

| Komponente | Technologie |
|------------|-------------|
| Server | PHP 8.1+ |
| Datenbank | MySQL 5.7+ / MariaDB 10.4+ |
| Styling | Tailwind CSS (CDN) |
| JavaScript | Alpine.js (CDN) |
| Hosting | Hoststar.ch (PHP/MySQL) |
| Cloud-DB (Optional) | Supabase (PostgreSQL) |

---

## Schnellstart auf Hoststar.ch

### Schritt 1: Datenbank anlegen
1. Hoststar Control Panel → MySQL-Datenbanken → Neue Datenbank erstellen
2. Name: `mb_kommunikation` (oder Ihr gewünschter Name)
3. Benutzer erstellen und Passwort notieren

### Schritt 2: Konfiguration anpassen
Öffnen Sie `config/config.php` und passen Sie an:

```php
define('DB_HOST', 'localhost');        // Normalerweise 'localhost' bei Hoststar
define('DB_NAME', 'ihre_datenbank');   // Name aus Schritt 1
define('DB_USER', 'ihr_benutzer');     // DB-Benutzer aus Schritt 1
define('DB_PASS', 'ihr_passwort');     // Passwort aus Schritt 1
define('SITE_URL', 'https://www.mb-kommunikation-events.ch');
```

### Schritt 3: Datenbank importieren
1. phpMyAdmin öffnen (über Hoststar Control Panel)
2. Datenbank auswählen
3. "Importieren" klicken
4. Datei `database.sql` hochladen und ausführen

### Schritt 4: Dateien hochladen
1. FTP/SFTP-Client öffnen (z.B. FileZilla)
2. Alle Dateien in das `public_html/` Verzeichnis hochladen
3. Ausnahme: `backup/` und `logs/` ausserhalb von `public_html/` wenn möglich

### Schritt 5: Verzeichnis-Berechtigungen setzen
```
uploads/       → 755 oder 775
backup/files/  → 700 (nur Server-Zugriff)
logs/          → 700 (nur Server-Zugriff)
```

### Schritt 6: Ersten Login
- URL: `https://ihre-domain.ch/login`
- E-Mail: `info@mb-kommunikation-events.ch`
- Passwort: `Admin2024!` ← **SOFORT ÄNDERN!**

---

## Benutzerrollen

| Rolle | Rechte |
|-------|--------|
| **superadmin** | Vollzugriff, kann keine anderen Superadmins löschen |
| **admin** | Inhalt + Benutzer verwalten, Backup, Einstellungen |
| **editor** | Seiten, Events, Referenzen, Nachrichten verwalten |
| **viewer** | Nur Lesezugriff auf Admin-Bereich |

---

## Admin-Bereich

URL: `/admin/`

### Funktionen:
- **Dashboard** – Statistiken, ungelesene Nachrichten
- **Seiten** – Homepage-Inhalte, Texte bearbeiten
- **Events** – Events anlegen, bearbeiten, Bilder hochladen
- **Referenzen** – Portfolio verwalten
- **Nachrichten** – Kontaktanfragen lesen und beantworten
- **Benutzer** – User anlegen, Rollen zuweisen, deaktivieren
- **Backup** – Datenbank- und Datei-Backups erstellen/herunterladen
- **Einstellungen** – Logo, Kontaktdaten, Social Media, SEO

---

## Backup-System

### Manuelles Backup
Admin → Backup → "Neues Backup erstellen"

- **Datenbank-Backup**: SQL-Export aller Tabellen
- **Datei-Backup**: ZIP aller uploads/
- **Vollbackup**: Datenbank + Dateien

### Backup-Dateien
Backups werden in `/backup/files/` gespeichert (geschützt vor Webzugriff).

### Automatisches Backup via Cron (optional)
In Hoststar Control Panel → Cron-Jobs:
```
0 2 * * * php /path/to/public_html/backup/cron_backup.php
```

---

## Sicherheitshinweise

1. **Passwort ändern** nach erstem Login
2. **config/config.php** enthält Datenbankzugangsdaten – nie im Browser zugänglich machen
3. **backup/files/** Verzeichnis ist durch .htaccess geschützt
4. **CSRF-Token** auf allen Formularen implementiert
5. **Prepared Statements** für alle Datenbankabfragen
6. **Passwörter** werden mit bcrypt (PHP password_hash) gespeichert

---

## Optionale Supabase-Integration

Ein Supabase-Projekt wurde erstellt (ID: `igxngxzibeozcqehxkpr`, Region: EU Central).

Verwendungszwecke:
- Cloud-Backup der Datenbank
- API-Zugriff auf Daten
- Skalierung bei hohem Traffic

Supabase-Zugangsdaten in `config/config.php` ergänzen:
```php
define('SUPABASE_URL', 'https://igxngxzibeozcqehxkpr.supabase.co');
define('SUPABASE_KEY', 'IHR_ANON_KEY');
```

---

## Dateistruktur

```
mb-kommunikation/
├── index.php              # Startseite
├── kommunikation.php      # Kommunikations-Seite
├── events.php             # Events-Seite
├── referenzen.php         # Referenzen/Portfolio
├── kontakt.php            # Kontaktformular
├── login.php              # Login
├── logout.php             # Logout
├── .htaccess              # URL-Routing & Sicherheit
├── config/
│   └── config.php         # ← ANPASSEN!
├── includes/
│   ├── db.php             # Datenbankklasse (PDO)
│   ├── auth.php           # Authentifizierung
│   ├── functions.php      # Hilfsfunktionen
│   ├── header.php         # Navigation
│   └── footer.php         # Footer
├── admin/
│   ├── index.php          # Dashboard
│   ├── users.php          # Benutzerverwaltung
│   ├── content.php        # Inhaltsverwaltung
│   ├── events.php         # Event-Verwaltung
│   ├── references.php     # Referenzen-Verwaltung
│   ├── messages.php       # Nachrichten
│   ├── backup.php         # Backup-System
│   ├── settings.php       # Einstellungen
│   └── upload.php         # Datei-Upload Handler
├── assets/
│   ├── css/style.css      # Zusätzliche Stile
│   └── js/app.js          # JavaScript
├── uploads/               # Hochgeladene Bilder (755)
├── backup/
│   └── files/             # Backup-Dateien (700)
├── logs/                  # Fehler-Logs (700)
└── database.sql           # MySQL-Schema
```

---

## Support

Bei Fragen wenden Sie sich an Ihren Entwickler oder konsultieren Sie die Hoststar-Dokumentation unter https://support.hoststar.ch
