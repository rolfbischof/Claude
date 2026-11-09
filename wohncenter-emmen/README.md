# Wohncenter Emmen – Öffnungszeiten-Plugin

WordPress-Plugin für wohncenter-emmen.ch (Theme Avada). Damit lassen sich die regulären Öffnungszeiten und die Sonderöffnungszeiten im WordPress-Backend pflegen. Die Sonderöffnungszeiten können jährlich per Excel/CSV importiert werden, und vergangene Daten blendet das Plugin auf der Website automatisch aus.

| | |
|---|---|
| Plugin-Name | Wohncenter Öffnungszeiten |
| Ordner / Slug | `woce-oeffnungszeiten` |
| Version | 1.1.0 |
| Admin-Menü | «Öffnungszeiten» (`wp-admin/admin.php?page=woce-oeffnungszeiten`) |
| Berechtigung | Rolle mit `edit_pages` (Redaktor, Administrator) |
| Voraussetzung | WordPress, PHP ≥ 7.4, Erweiterung `mbstring` |
| Erstellt | 12.10.2026, proimprove (mit Claude Code) |

## Shortcodes

| Shortcode | Ausgabe |
|---|---|
| `[oeffnungszeiten]` | Reguläre Öffnungszeiten: pro Sparte eine Zeile, Wochentage als Spalten |
| `[sonderoeffnungszeiten]` | Sonderöffnungszeiten ab heute (Zeitzone der Website) |
| `[sonderoeffnungszeiten vergangene="ja"]` | Zeigt zusätzlich vergangene Daten |
| `[sonderoeffnungszeiten leer="…"]` | Eigener Text, wenn keine Einträge vorhanden sind (`leer=""` gibt nichts aus) |

Beide Tabellen nutzen das Avada-Markup `<div class="table-1">` und sehen deshalb aus wie die übrigen Avada-Tabellen. Für eigenes CSS stehen die Klassen `woce-loez` und `woce-loez-regular` zur Verfügung.

## Bedienung im Backend

**Reguläre Öffnungszeiten.** Für jede Sparte gibt es eine Zeile, für jeden Wochentag ein Feld mit Freitext, zum Beispiel `09.00 – 19.00`. Mit den Häkchen im Tabellenkopf legst du fest, welche Wochentage auf der Website erscheinen. Sparten fügst du mit «+ Sparte hinzufügen» hinzu, und über die Löschen-Checkbox entfernst du sie wieder.

**Sonderöffnungszeiten.** Jede Zeile ist ein Datum mit Anlass und einer Zeit pro Sparte. Den Wochentag berechnet das Plugin selbst. Pro Datum gibt es höchstens einen Eintrag. Vergangene Einträge sind ausgeblendet, «Vergangene anzeigen» blendet sie ein.

**Spalten (Sparten) der Sonderöffnungszeiten.** Du findest sie unter «Spalten (Sparten) anpassen» als kommagetrennte Liste, Stand November 2026: `Möbelgeschäfte, Lebensmittel, Gastronomie`. Die Werte sind nach Spaltenposition gespeichert. Neue Sparten hängst du deshalb immer hinten an. Wenn du eine Sparte vorne einfügst oder die Reihenfolge änderst, verschieben sich die bestehenden Zeiten in die falsche Spalte.

## Jährlicher Ablauf (Excel-Import)

1. Excel-Datei mit diesen Spalten vorbereiten (Kopfzeile optional):
   `Datum; Feiertag; Möbelgeschäfte; Lebensmittel; Gastronomie`
   Eine vorangestellte Spalte `Wochentag` ist erlaubt. Das Plugin erkennt sie und ignoriert sie.
2. In Excel «Speichern unter → CSV (Trennzeichen-getrennt)» wählen. Alternativ kopierst du die Zeilen direkt aus Excel in das Textfeld «Import».
3. Im Backend unter «Import» die Datei wählen und den Modus festlegen:
   - **Ergänzen**: bestehende Einträge bleiben, ein gleiches Datum wird überschrieben.
   - **Alle ersetzen**: löscht zuerst alle Sonderöffnungszeiten.
4. Kontrollieren und bei Bedarf mit «Vergangene Einträge löschen» aufräumen.

Das Plugin akzeptiert diese Datumsformate: `13.5.2026`, `13.05.26`, `2026-05-13` und Excel-Seriennummern. Als Trennzeichen erkennt es `;`, `,` oder Tab automatisch. Dateien in UTF-8 (mit oder ohne BOM) und Windows-1252 funktionieren beide. Zeilen mit ungültigem Datum überspringt der Import, und die Meldung nennt die betroffenen Zeilennummern.

`import-vorlage.csv` ist eine Beispieldatei mit drei Sparten. «Als CSV exportieren» lädt den aktuellen Bestand als CSV herunter. Das ist praktisch als Vorlage für das nächste Jahr und als Sicherung.

## Datenspeicherung

Alle Daten liegen in der Tabelle `wp_options`. Das Plugin legt keine eigenen Datenbanktabellen an.

| Option | Inhalt |
|---|---|
| `woce_loez_entries` | Sonderöffnungszeiten: Liste aus `{date: Y-m-d, label, values[]}`, nach Datum sortiert |
| `woce_loez_columns` | Spaltennamen der Sonderöffnungszeiten (Array) |
| `woce_loez_regular` | Reguläre Zeiten: `{days: int[], rows: [{label, hours: {0..6: Text}}]}` (0 = Sonntag) |

Bei der ersten Aktivierung befüllt das Plugin die Sonderöffnungszeiten mit dem Stand der Website vom Oktober 2026. Das passiert nur, wenn `woce_loez_entries` noch nicht existiert. Ohne gespeicherte reguläre Zeiten zeigt es die ursprünglichen Zeiten der Website an.

## Installation und Update

1. Den Ordner `woce-oeffnungszeiten/` als ZIP packen:
   `cd wohncenter-emmen && zip -r woce-oeffnungszeiten.zip woce-oeffnungszeiten`
2. In WordPress unter Plugins → Installieren → Plugin hochladen das ZIP hochladen und bei einem Update «Aktuelle Version ersetzen» wählen.
3. Bei einem Update bleiben die Daten erhalten, weil sie in `wp_options` liegen.

## Wiederherstellung im Notfall

1. Plugin aus diesem Repository installieren (siehe oben).
2. Daten zurückholen, je nach verfügbarer Sicherung:
   - Website-Backup (All-in-One WP Migration) einspielen. Darin sind die `wp_options` enthalten.
   - Oder den letzten CSV-Export mit «Alle ersetzen» importieren und danach die Sparten-Liste prüfen.
   - Die regulären Zeiten sind nicht im CSV-Export enthalten. Ohne Backup trägst du sie von Hand ein. Es sind nur wenige Felder.
3. Auf der Seite «Öffnungszeiten» müssen die Shortcodes `[oeffnungszeiten]` und `[sonderoeffnungszeiten]` stehen.

## Sicherheit

Alle Formulare sind mit Nonce und Berechtigungsprüfung (`edit_pages`) geschützt. Eingaben werden mit `sanitize_text_field` bereinigt und Ausgaben mit `esc_html` bzw. `esc_attr` maskiert.

## Versionen

- **1.1.0** (12.10.2026): reguläre Öffnungszeiten mit Shortcode `[oeffnungszeiten]` ergänzt, Spaltenbreiten im Backend angepasst.
- **1.0.0** (12.10.2026): Sonderöffnungszeiten mit Import, Export, automatischem Ausblenden und Löschen vergangener Einträge.
- **Nov. 2026**: Sparte «Gastronomie» im Backend hinzugefügt. Das war eine reine Datenänderung ohne Code-Anpassung.

Hinweis zur Herkunft: Das Original-ZIP liegt auf dem MacBook unter `~/claude/Woce löz/woce-oeffnungszeiten.zip`. Der Code in diesem Repository wurde am 09.11.2026 aus dem Verlauf der Entwicklungssitzung rekonstruiert, Schritt für Schritt mit Prüfung jeder Änderung. Version 1.0 stimmt byte-genau mit dem damaligen ZIP überein. Nur `import-vorlage.csv` wurde um die Spalte Gastronomie erweitert.
