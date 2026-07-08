# Phase 0: Server-Grundeinrichtung (Hosttech vServer)

Server-IP: **194.56.189.141** (Hosttech, bestellt gemäss `../KONZEPT.md`
Abschnitt 12.4: 6 vCPU/8 GB/200 GB, Ubuntu 24.04 LTS minimal, kein System
Management/voller Root-Zugriff).

Alle Schritte hier führst **du** in einem eigenen Terminal aus (Mac: Terminal-
App, Windows: Windows Terminal/PuTTY) – aus dieser Session heraus gibt es
keinen Netzwerkzugriff auf den Server.

## 1. Erster Login

Zugangsdaten (Root-Passwort) stehen in der Willkommens-E-Mail von Hosttech.

```bash
ssh root@194.56.189.141
```

Bei der ersten Verbindung kommt eine Fingerprint-Warnung – mit `yes`
bestätigen.

## 2. SSH-Schlüssel statt Passwort einrichten (wichtig, zuerst!)

Falls auf deinem eigenen Rechner noch kein SSH-Schlüsselpaar existiert:

```bash
ssh-keygen -t ed25519 -C "hoberg-gastro-server"
```

(Enter für Standardpfad, optional eine Passphrase setzen.)

Öffentlichen Schlüssel auf den Server kopieren:

```bash
ssh-copy-id root@194.56.189.141
```

Falls `ssh-copy-id` nicht verfügbar ist (z. B. Windows ohne WSL): Inhalt von
`~/.ssh/id_ed25519.pub` manuell in `/root/.ssh/authorized_keys` auf dem
Server einfügen.

**Testen, bevor du weitermachst:** neues Terminal-Fenster öffnen und
`ssh root@194.56.189.141` ausführen – muss ohne Passwortabfrage
funktionieren. Das alte, noch offene Terminal-Fenster **nicht schliessen**,
bis der Test erfolgreich war (sonst Gefahr, sich auszusperren).

Erst danach Passwort-Login deaktivieren:

```bash
sudo sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
sudo sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin prohibit-password/' /etc/ssh/sshd_config
sudo systemctl restart sshd
```

## 3. Grundhärtung, Docker, Coolify

Ab hier automatisiert über `setup.sh` in diesem Ordner. Datei auf den Server
kopieren und ausführen:

```bash
scp ops/setup.sh root@194.56.189.141:/root/
ssh root@194.56.189.141 "chmod +x /root/setup.sh && /root/setup.sh"
```

Das Skript erledigt: System-Updates, automatische Sicherheitsupdates
(unattended-upgrades), Firewall (nur SSH/80/443), Fail2ban gegen
SSH-Bruteforce, Docker + Docker Compose Plugin, Coolify-Installation.

Am Ende gibt das Coolify-Installationsskript eine URL wie
`http://194.56.189.141:8000` aus, unter der das Coolify-Dashboard
erreichbar ist (erster Aufruf: Admin-Account anlegen, **2FA für diesen
Account gleich aktivieren**, siehe KONZEPT.md 9.1/11).

## 4. Domain/DNS verbinden

Sobald feststeht, welche bestehende Hosttech-Subdomain die App bekommen soll
(z. B. `app.eure-domain.ch`): im Hosttech-DNS-Editor für diese Domain

- **A-Record** `app` → `194.56.189.141`
- **AAAA-Record** `app` → IPv6-Adresse des Servers (aus dem Hosttech-Kundencenter)

Danach in Coolify die Domain hinterlegen – TLS-Zertifikat wird automatisch
per Let's Encrypt ausgestellt.

## 5. Danach (nächste Schritte, nicht mehr Teil von Phase 0)

- Postgres + PostgREST + Auth-Dienst + Vaultwarden + Uptime Kuma als
  Coolify-Ressourcen anlegen
- `../db/phase1_stammdaten_wein_preise.sql` als erste Migration einspielen
- Backup-Job in Coolify einrichten (Ziel: externer S3-Speicher oder
  Hosttech-Backupspeicher, siehe KONZEPT.md 12.2)
