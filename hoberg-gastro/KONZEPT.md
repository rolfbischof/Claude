# Hoberg Gastro – Konzept für eine modulare Betriebssoftware

Status: Entwurf v2 | Datum: 2026-07-01

## 1. Ausgangslage

Hoberg betreibt mehrere Sparten unter einem Dach: Hotel, Restaurant, Catering,
Spezialitätenproduktion, Glaceherstellung, Störkoch. Ein erstes Modul
(Menükarten-System mit PDF-Export in CI und Web-Anzeige) existiert bereits
(Bolt.new). Ziel ist **keine Sammlung von Einzellösungen**, sondern ein System
mit **einer gemeinsamen Datenbasis**, auf die beliebig viele Module zugreifen –
neue Module sollen sich anschliessen lassen, ohne Daten zu duplizieren.

Erster konkreter Baustein: **Produktdatenbank Wein**, inkl. unterschiedlicher
Verkaufspreise pro Sparte (Restaurant, Catering, …) und einer sauberen
**Preis-Mutation** ausgehend von den Stammdaten.

**Zusätzliche Vorgabe:** Das gesamte System muss auf einem **eigenen Server**
(z. B. bei Hosttech) betrieben werden können – keine Abhängigkeit von einem
Cloud-Anbieter, der die Daten ausserhalb der eigenen Kontrolle hält. Inklusive
einer verlässlichen **Backup-Strategie für alle Daten, Tabellen und die
gesamte Datenbank** (siehe Abschnitt 12). Der ursprüngliche Ansatz
(Supabase als Cloud-Dienst) wird deshalb durch einen **self-hosted-Stack**
ersetzt, der auf jedem Linux-vServer/Managed-Server mit Root-Zugriff läuft –
funktional bleibt das Konzept (Stammdaten, Sparten, Preislisten, Module)
unverändert, nur die Betriebsumgebung ändert sich.

> Annahme für dieses Konzept: Hosttech **vServer oder Managed Server mit
> Root-/SSH-Zugriff** (Linux, Docker-fähig). Klassisches Shared-Hosting
> (nur PHP/MySQL über Plesk, kein Root, kein Docker) reicht für PostgreSQL,
> eigene Hintergrunddienste und automatisierte Backups **nicht** aus – dort
> müsste der Unterbau grundlegend auf PHP/MariaDB umgebaut werden. Falls das
> der tatsächlich verfügbare Hosttech-Plan ist, bitte Rückmeldung geben,
> dann wird dieser Abschnitt angepasst.

## 2. Architekturprinzipien

1. **Stammdaten-first, Single Source of Truth** – ein Produkt (z. B. ein Wein)
   existiert genau einmal in der Datenbank. Module lesen/verändern diese
   Stammdaten, sie duplizieren sie nicht.
2. **Sparte ist ein Attribut, keine eigene Datenbank.** Hotel, Restaurant,
   Catering usw. sind Werte einer `sparten`-Tabelle, keine getrennten Systeme.
   Das erlaubt sparten-spezifische Preise/Sichtbarkeit bei gemeinsamen
   Produktdaten.
3. **API-first / Headless.** Jedes Modul (inkl. bestehendes Menükarten-Tool,
   künftige Website, Kassensystem) spricht über eine gemeinsame, selbst
   gehostete REST-API mit der Datenbank. Kein Modul hält eigene Kopien von
   Produkt- oder Preisdaten.
4. **Erweiterbar statt starr.** Neue Produktarten (Speisen, Zutaten,
   Glaceartikel, Handelswaren) hängen sich als **Zusatztabellen** an eine
   generische `produkte`-Kerntabelle – analog zum Wein-Beispiel. Neue Module
   docken an dieselben Kern-Entitäten (Produkte, Preislisten, Adressen,
   Sparten) an.
5. **Jede Preisänderung ist nachvollziehbar.** Mutationen an Einkaufs- oder
   Verkaufspreisen werden protokolliert (wer, wann, alt → neu, warum),
   nicht überschrieben.
6. **Automatisierung über Events/Views, nicht über Spezialcode pro Modul.**
   Schnittstellen (POS, Website, Buchungsportale) docken über klar definierte
   Views, Funktionen und Webhooks an – das ist die Grundlage, um später
   Bereiche automatisiert zu verknüpfen (z. B. Lagerbestand → Bestellvorschlag,
   Kassenumsatz → Lagerabgang).

## 3. Modulübersicht

Alle Module teilen sich den Kern (Stammdaten, Produkte, Preislisten, Adressen,
Sparten). Die Tabelle zeigt, welche Kern-Entitäten jedes Modul nutzt bzw.
erweitert.

| Modul | Zweck | nutzt Kern-Entitäten | eigene Zusatzdaten (Beispiele) |
|---|---|---|---|
| **Stammdaten** (Kern) | zentrale Wahrheit für Produkte, Sparten, Lieferanten, Adressen | – | Sparten, Kategorien, Einheiten, MwSt-Sätze |
| **Produktverwaltung** | Erfassung/Pflege aller Artikel (Wein, Speisen, Zutaten, Glace, Handelsware) | Produkte | typ-spezifische Zusatztabellen (`weine`, später `speisen`, `glace_artikel`, …) |
| **Preislisten** | sparten-spezifische Verkaufspreise, automatische Ableitung aus Kalkulation | Produkte, Sparten | Preislisten, Preislistenpositionen, Preis-Historie, Kalkulationsregeln |
| **Einkauf** | Bestellungen, Wareneingang, Einkaufspreise | Produkte, Lieferanten | Bestellungen, Bestellpositionen, Wareneingänge |
| **Verkauf** | Angebote, Rechnungen, Aufträge (Catering-Events, Bankette) | Produkte, Preislisten, Adressen | Aufträge, Auftragspositionen, Rechnungen |
| **CRM / Adressverwaltung** | Kunden, Lieferanten, Interessenten, Kontakthistorie | Adressen | Firmen, Kontakte, Aktivitäten |
| **Hotelbuchungen** | Zimmerverfügbarkeit, Reservationen | Adressen, Preislisten (Zimmerkategorien als Produkte) | Zimmer, Reservationen, Belegungspläne |
| **Rezeptverwaltung** | Rezepte, Kalkulation über Zutatenpreise | Produkte (als Zutaten) | Rezepte, Rezeptpositionen, Nährwert-/Allergenangaben |
| **Lagerbewirtschaftung** | Bestände, Inventur, Mindestbestände je Standort/Sparte | Produkte, Sparten | Lagerorte, Lagerbestände, Bewegungen |
| **Interne Kommunikation** | Aufgaben, Schichtinfos, Ankündigungen | Adressen (Mitarbeitende) | Nachrichten, Aufgaben |
| **Kassensystem-Anbindung** | Verkaufsdaten importieren, Preise exportieren | Produkte, Preislisten | Mapping-Tabelle POS-Artikel ↔ Produkt, Verkaufsbelege |
| **Webseitenverwaltung / Menükarten** | öffentliche Darstellung, PDF-Export (bereits vorhanden) | Produkte, Preislisten | Menüaufbau/Layout, CI-Vorlagen |
| **Automatisierung / Integration Hub** | Schnittstellen, Webhooks, wiederkehrende Jobs | alle | API-Keys, Webhook-Log, Job-Historie |

## 4. Systemarchitektur (High Level)

Alles läuft als **Docker-Compose-Stack auf einem einzigen (Hosttech-)Server**
– keine externen Cloud-Abhängigkeiten für Kerndaten:

```mermaid
flowchart TB
    subgraph Server["Eigener Server (z.B. Hosttech vServer/Managed Server)"]
        subgraph DB["PostgreSQL (Docker-Container, self-hosted)"]
            Core["Kern-Schema:\nsparten, produkte, preislisten,\nadressen, lieferanten"]
            Wein["wein.weine\n(Zusatzattribute)"]
            Weitere["weitere Zusatzschemas:\nrezepte, lager, crm, hotel, pos, ..."]
        end

        API["REST-API (PostgREST, self-hosted)\n+ Views + RLS pro Sparte/Rolle"]
        Auth["Auth-Dienst (eigener JWT-Login)"]
        Jobs["Automatisierungs-/PDF-Dienst\n(Node.js, eigener Container)"]
        Proxy["Reverse Proxy (Nginx/Traefik)\n+ TLS (Let's Encrypt)"]
        Backup["Backup-Job (Cron-Container:\npg_dump + rclone/rsync offsite)"]

        Core --- Wein
        Core --- Weitere
        DB --> API
        DB --> Jobs
        Auth --> API
        Proxy --> API
        Proxy --> Auth
        DB --> Backup
    end

    Menu["Menükarten-Modul\n(bestehend, wird umgehängt)"]
    Web["Webseite"]
    POS["Kassensystem"]
    Einkauf["Einkauf/Verkauf-UI"]
    Hotel["Hotelbuchungs-UI"]
    Neu["weitere künftige Module"]
    Offsite["Offsite-Backup-Speicher\n(zweiter Ort/Anbieter)"]

    Proxy --> Menu
    Proxy --> Web
    Proxy --> POS
    Proxy --> Einkauf
    Proxy --> Hotel
    Proxy --> Neu
    Jobs --> Menu
    Jobs --> Web
    Backup --> Offsite
```

Jedes Modul ist ein eigenes Frontend/Service, aber es gibt **eine** Datenbank
und **eine** API-Schicht, beide auf demselben Server. Damit landet z. B. eine
Preisänderung in den Stammdaten automatisch in Menükarte, Webseite und
(später) im Kassensystem – ohne manuellen Doppelaufwand. Der Server ist
bewusst so aufgebaut, dass ein Umzug auf einen anderen Hoster jederzeit
möglich bleibt (reines Docker Compose, keine proprietären Dienste).

## 5. Sparten-Konzept

`sparten` ist eine einfache Stammdatentabelle:

`HOTEL`, `RESTAURANT`, `CATERING`, `SPEZIALITAETEN`, `GLACE`, `STOERKOCH`
(erweiterbar, keine Codeänderung nötig für neue Sparten).

Ein Produkt (z. B. ein Wein) ist sparten-unabhängig in den Stammdaten erfasst.
Ob und zu welchem Preis es in einer Sparte erscheint, wird über eine
**Preisliste pro Sparte** gesteuert (siehe Abschnitt 7). So kann derselbe Wein:

- im Restaurant zu CHF 39.– im Glas/Flasche erscheinen,
- im Catering zu einem Pauschal-/Mengenpreis,
- im Hotel z. B. gar nicht (weil nicht gelistet) –

ohne dass er drei separate Produktstämme braucht.

## 6. Datenbankkonzept

### 6.1 Schema-Organisation

Postgres-Schemas trennen Fachbereiche, teilen sich aber Fremdschlüssel auf den
Kern:

- `core` – Sparten, Produkte, Kategorien, Lieferanten, Preislisten, MwSt
- `wein` – Zusatzattribute für Produkte vom Typ `WEIN`
- `crm` – Adressen, Firmen, Kontakte (Phase 3)
- `lager`, `einkauf`, `verkauf`, `rezepte`, `hotel`, `pos` – künftige Module,
  referenzieren `core.produkte` / `core.sparten` / `crm.adressen`

### 6.2 Namenskonventionen (verbindlich)

- Tabellen: `snake_case`, Plural (`produkte`, `preislisten`)
- Primärschlüssel: `id uuid default gen_random_uuid()`
- Fremdschlüssel: `<tabelle_singular>_id` (`produkt_id`, `sparte_id`)
- Zeitstempel: `created_at`, `updated_at` (immer `timestamptz`)
- Geldbeträge: `numeric(10,2)`, Währung separat vermerken (`waehrung`, default `CHF`)
- Jede fachliche Änderung an einem Preis läuft über eine Funktion/Trigger,
  **nie** durch stilles Überschreiben ohne Historieneintrag

### 6.3 Kern-ER-Diagramm

```mermaid
erDiagram
    SPARTEN ||--o{ PREISLISTEN : "gilt fuer"
    LIEFERANTEN ||--o{ PRODUKTE : liefert
    PRODUKT_KATEGORIEN ||--o{ PRODUKTE : gruppiert
    PRODUKTE ||--o| WEINE : "Zusatzattribute (Typ=WEIN)"
    PRODUKTE ||--o{ PREISLISTEN_POSITIONEN : "hat Preis in"
    PREISLISTEN ||--o{ PREISLISTEN_POSITIONEN : enthaelt
    PRODUKTE ||--o{ PREIS_HISTORIE : "Aenderungen"
    PREISLISTEN_POSITIONEN ||--o{ PREIS_HISTORIE : "Aenderungen"
```

### 6.4 Kerntabellen (Phase 1 – siehe `db/phase1_stammdaten_wein_preise.sql`)

**`core.sparten`** – Betriebsbereiche (Hotel, Restaurant, Catering, …)

**`core.mwst_saetze`** – MwSt-Sätze mit Gültigkeitszeitraum (Sätze ändern
sich mit der Zeit, daher historisiert statt einfacher Prozentwert)

**`core.lieferanten`** – Weinhändler/Produzenten als Lieferant

**`core.produkt_kategorien`** – hierarchische Kategorien
(`Getränke > Wein > Rotwein`)

**`core.produkte`** – generische Produkt-Stammdaten für **alle** Artikeltypen
(`produkt_typ` als Diskriminator: `WEIN`, `SPEISE`, `ZUTAT`, `HANDELSWARE`,
`GLACE`, …). Enthält Artikelnummer, Bezeichnung, Einkaufspreis,
Basis-Einheit, Lieferant, MwSt, Status.

**`wein.weine`** – 1:1-Zusatztabelle zu `core.produkte` für alle
Wein-spezifischen Felder: Rebsorte(n), Jahrgang, Land/Region/Appellation,
Winzer, Farbe, Flascheninhalt, Alkoholgehalt, Süssegrad, Bio-Zertifizierung,
Allergene/Sulfite, Verkostungsnotiz, Trinktemperatur, Lagerfähigkeit.

→ **Muster für alle künftigen Produktarten**: neue Sparte/Produktart = neue
Zusatztabelle mit `produkt_id` als PK/FK, niemals eine Kopie von `produkte`.

**`core.preislisten`** – Preisliste pro Sparte mit Gültigkeitszeitraum und
Status (`ENTWURF`, `AKTIV`, `ARCHIVIERT`). Beispiel: "Weinkarte Restaurant
2026", "Preisliste Catering Sommer 2026".

**`core.preislisten_positionen`** – Verkaufspreis eines Produkts in einer
Preisliste, mit eigenem Gültigkeitszeitraum (→ Mutationen ohne Datenverlust:
alte Zeile bekommt `gueltig_bis`, neue Zeile wird eingefügt).

**`core.preis_historie`** – Audit-Log **jeder** Preisänderung
(Einkaufspreis in den Stammdaten **und** Verkaufspreise in Preislisten):
alter Wert, neuer Wert, wer, wann, Grund. Wird per Trigger automatisch
befüllt – Mutation ist damit strukturell erzwungen, nicht optional.

## 7. Preislogik im Detail (Wein-Beispiel)

1. **Einkaufspreis / Kalkulationsbasis** liegt in `core.produkte.einkaufspreis`
   – das ist der Stammdatenwert, den der Einkauf pflegt.
2. **Verkaufspreise pro Sparte** liegen in `core.preislisten_positionen`,
   je Sparte eine eigene aktive Preisliste. Ein Wein kann also gleichzeitig
   einen Restaurant-Preis und einen Catering-Preis haben, beide unabhängig
   voneinander veränderbar.
3. **Mutation:**
   - Ändert sich der Einkaufspreis in den Stammdaten → Trigger schreibt
     einen Eintrag in `core.preis_historie` und kann optional (Kalkulations-
     regel, z. B. Faktor 2.8 für Restaurant, Faktor 1.6 für Catering) einen
     **Preisvorschlag** für die betroffenen Preislisten-Positionen erzeugen.
   - Die eigentliche Übernahme des Vorschlags in die aktive Preisliste ist
     ein bewusster Freigabeschritt (kein automatisches Überschreiben von
     Kundenpreisen), wird aber ebenfalls historisiert.
4. **MwSt** wird pro Preislisten-Position referenziert (Restaurant vs.
   Take-away/Catering können unterschiedliche Sätze haben).
5. Dieses Muster (Stammpreis → sparten-spezifische Preisliste → Historie)
   gilt unverändert für alle künftigen Produktarten, nicht nur Wein.

## 8. Schnittstellen & Automatisierung

- **Interne API:** self-hosted REST-API (PostgREST) auf `core.*`-Tabellen
  und -Views, Zugriff über Row Level Security nach Sparte/Rolle gesteuert.
- **Bestehendes Menükarten-Modul (Bolt.new):** wird so umgehängt, dass es
  Produkte/Preise über die gemeinsame API aus `core.produkte` +
  `core.preislisten_positionen` liest statt eigene Daten zu halten. PDF- und
  Web-Ausgabe bleiben wie gehabt, nur die Datenquelle wird zentralisiert.
- **Kassensystem:** Mapping-Tabelle POS-Artikelcode ↔ `produkt_id`;
  Verkaufsbelege fliessen als Events zurück (Basis für spätere
  Lagerabgang-Automatisierung).
- **Automatisierungs-/Integrations-Hub:** Webhook-Log- und Job-Tabellen im
  Kern, damit neue Automatisierungen (z. B. "Preisliste PDF neu generieren,
  wenn sich eine Preislisten-Position ändert") einheitlich angebunden werden,
  statt pro Modul Spezialcode zu schreiben.

## 9. Rollen & Berechtigungen (Kurzform)

Rollenkonzept über eigenen Auth-Dienst (JWT-Login) + Postgres Row Level
Security, grob:

- **Stammdaten-Pflege** (Einkaufspreise, Produktdaten): Einkauf/Admin
- **Preislisten-Freigabe** (Verkaufspreise je Sparte): Sparten-Verantwortliche
- **Lesezugriff** je Modul/Sparte: entsprechendes Personal
- **Reine Leserechte** für Webseite/Kassensystem (technische Service-Accounts)

## 10. Roadmap

0. **Phase 0 (Vorbereitung):** Server bei Hosttech einrichten (vServer/Managed
   Server, Root-Zugriff), Docker + Docker Compose, Firewall, TLS, Backup-Job
   gemäss Abschnitt 12 – **bevor** die erste Fachanwendung produktiv geht.
1. **Phase 1 (jetzt):** Kern-Stammdaten + Produktdatenbank Wein +
   Preislisten Restaurant/Catering + Preis-Historie (siehe SQL-Datei),
   lokal/auf Testserver aufsetzen und Backup-Restore einmal durchspielen.
2. **Phase 2:** Bestehendes Menükarten-Modul auf die neue Datenbank umstellen
   (statt Eigendaten), inkl. automatischem PDF-Export aus Preislisten.
3. **Phase 3:** Einkauf, Lager, CRM/Adressverwaltung.
4. **Phase 4:** Hotelbuchungen, Kassensystem-Anbindung, Rezeptverwaltung,
   interne Kommunikation.
5. **Laufend:** Automatisierungs-Hub ausbauen (Bestellvorschläge,
   Kassenabgleich, Website-Sync).

## 11. Technologie-Empfehlung (self-hosted)

Ziel: gleiche Vorteile wie ein Cloud-Backend (eine DB, automatisch generierte
API, Rollen/Rechte, Auth, Dateiablage, Automatisierung), aber **vollständig
auf dem eigenen Server** und ohne Bindung an einen einzelnen Anbieter:

| Baustein | Wahl | Begründung |
|---|---|---|
| Datenbank | **PostgreSQL** (Docker-Container) | identisch zum bisherigen Schema, keine Anpassung der SQL-Datei nötig, sehr gute Backup-Werkzeuge |
| API-Schicht | **PostgREST** (self-hosted, Open Source) | generiert automatisch eine REST-API aus dem Postgres-Schema inkl. Row Level Security – gleiches Prinzip wie zuvor, aber ohne Cloud-Anbieter |
| Auth | eigener, schlanker JWT-Auth-Dienst (Node.js) oder Postgres-Rollen direkt | reicht für Mitarbeitendenanzahl eines Betriebs dieser Grösse; Keycloak als Option, falls später viele Systeme/SSO gebraucht werden |
| Automatisierung/PDF | eigener **Node.js-Dienst** (Docker-Container) | übernimmt PDF-Erstellung (wie bisher im Menükarten-Modul), Webhooks, geplante Jobs |
| Reverse Proxy/TLS | **Nginx** oder **Traefik** + Let's Encrypt | ein Einstiegspunkt für alle Module, automatisches HTTPS-Zertifikat |
| Dateiablage (Bilder, PDFs) | lokales Docker-Volume, bei Bedarf später **MinIO** (self-hosted, S3-kompatibel) | einfach im Backup mit einzubeziehen, kein externer Objektspeicher nötig |
| Deployment | **Docker Compose** auf dem Hosttech-Server | ein `docker-compose.yml` beschreibt den ganzen Stack, reproduzierbar, portierbar auf jeden anderen Server |
| Backup | siehe Abschnitt 12 | zentral, automatisiert, mit Offsite-Kopie |

Das bestehende Bolt.new-Menükarten-Tool lässt sich darauf umstellen, ohne die
UI verwerfen zu müssen – nur die Datenquelle wechselt von "eigene
Cloud-Tabelle" zu "eigene PostgreSQL-Instanz über PostgREST".

Konkreter nächster Schritt (Programmierung, nach Freigabe dieses Konzepts):
Server-Grundgerüst (Docker Compose mit Postgres + PostgREST + Nginx + Backup-
Job) aufsetzen und `db/phase1_stammdaten_wein_preise.sql` als erste Migration
einspielen.

## 12. Hosting & Backup-Konzept

### 12.1 Serveranforderungen

- Linux-Server mit Root-/SSH-Zugriff (Hosttech vServer oder Managed Server),
  Docker + Docker Compose installiert.
- Ausreichend Speicherplatz für Datenbank **und** Backups (Faustregel:
  mindestens das 3–4-fache der erwarteten DB-Grösse einplanen).
- Firewall (nur Port 443/80 und SSH offen), SSH nur mit Schlüssel (kein
  Passwort-Login), automatische Sicherheitsupdates des Betriebssystems.
- Getrennte Umgebungen: mind. **Produktion**, empfohlen zusätzlich eine
  **Test-/Staging-Instanz** auf demselben oder einem separaten Server, um
  neue Module/Migrationen vorab zu prüfen.

### 12.2 Backup-Strategie (3-2-1-Prinzip)

**3 Kopien, 2 verschiedene Medien/Orte, 1 davon offsite:**

1. **Automatischer täglicher Dump** der gesamten PostgreSQL-Datenbank
   (`pg_dump` im Custom-Format, komprimiert) per Cron-Job/Cron-Container,
   inkl. Zeitstempel im Dateinamen.
2. **Dateiablage sichern** (Produktbilder, erzeugte PDFs, Konfigurationen)
   zusammen mit dem DB-Dump in einem Archiv.
3. **Offsite-Kopie**: automatischer Transfer (rclone/rsync) des Backups auf
   einen zweiten Ort – z. B. Hosttech-Zusatzprodukt "Backup-Space", einen
   zweiten Server oder einen externen S3-kompatiblen Speicher. Backups
   dürfen **nicht ausschliesslich** auf demselben physischen Server liegen
   wie die Live-Datenbank (sonst kein Schutz bei Hardware-/Serverausfall).
4. **Aufbewahrung/Rotation** (Generationsprinzip): z. B. 7 tägliche,
   4 wöchentliche, 12 monatliche Stände – ältere Stände werden automatisch
   gelöscht, damit der Speicher nicht unbegrenzt wächst.
5. **Restore-Test**: mindestens einmal im Quartal ein Backup tatsächlich in
   eine Testumgebung zurückspielen und prüfen – ein Backup zählt erst, wenn
   die Wiederherstellung nachweislich funktioniert.
6. **Alarmierung**: schlägt der Backup-Job fehl (z. B. Datenbank nicht
   erreichbar, Transfer schlägt fehl), erfolgt automatisch eine
   Benachrichtigung (E-Mail) – Backups dürfen nicht "still" ausfallen.
7. **Optional für später (geringeres Datenverlustrisiko):** kontinuierliche
   WAL-Archivierung (z. B. mit `pgBackRest` oder `wal-g`) für
   Point-in-Time-Recovery, falls ein Tagesabstand zwischen Backups nicht mehr
   ausreicht.

### 12.3 Umzugsfähigkeit

Da der gesamte Stack als Docker Compose beschrieben ist und Backups
vollständige `pg_dump`-Stände sind, ist ein Wechsel des Hosting-Anbieters
(z. B. weg von oder zu Hosttech) jederzeit möglich: Server neu aufsetzen,
Docker Compose starten, letzten Backup-Stand einspielen.
