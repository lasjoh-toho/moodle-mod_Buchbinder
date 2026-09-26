# Buchbinder (mod_buchbinder)

Moodle-Aktivität, die statische PDFs, Dokumentenscans, Word-Dateien und Webinhalte in modulare,
interaktive und wiederverwendbare Lernmaterialien verwandelt. Lehrkräfte laden Dokumente nicht als
starre Einzeldateien hoch, sondern bearbeiten sie in einem **Publishing Studio**: Scans bereinigen,
direkt auf Seiten schreiben, unsichtbare Audio-Trigger, Glossarbegriffe und Lösungsabdeckungen
anlegen, eine Textansicht für Smartphones anbieten und papiersparend drucken.

> Komponentenname: `mod_buchbinder`. Moodle verlangt kleingeschriebene Komponentennamen, daher
> heißt das Verzeichnis `mod/buchbinder`, obwohl das Repository `moodle-mod_Buchbinder` heißt.

Status: **0.5.0 (Alpha)**. Der gesamte Arbeitsablauf ist umgesetzt; Punkte, die noch fehlen,
stehen unter [Roadmap](#roadmap).

## Broschüre und Satzstudio

Jedes Dokument wird von Anfang an als **Broschüre** gedacht: Seite 1 ist (wie im Buch) eine rechte
Seite, danach folgen Doppelseiten 2|3, 4|5 … (umstellbar in den Einstellungen). Der Satzspiegel ist
gespiegelt, der Bundsteg liegt immer an der Bindung.

- **Import als Rahmen:** Word (inkl. Bilder), HTML, Markdown, Web-Ausschnitte und die Zwischenablage
  werden in Blöcke zerlegt und per Satzlauf als verschiebbare **Text- und Bildrahmen** auf Seiten
  verteilt. Lange Absätze, Listen und Tabellen werden auf Folgeseiten fortgesetzt, Überschriften
  bleiben beim folgenden Text, Seitenumbrüche aus Word/Markdown werden übernommen. Dokumente
  beginnen auf Wunsch auf einer rechten Seite.
- **Seitenlage als bewusste Entscheidung:** In der Seitenpalette öffnet ein Rechtsklick (oder die
  Kontextmenü-Taste) die Seiteneigenschaften: Seiten mit der Folgeseite **verketten** (Kettensymbol,
  eine Doppelseite) und Seiten als **linke oder rechte Seite festlegen** (Markierung L/R). Werden
  davor Seiten eingefügt, gelöscht oder verschoben, bleiben verkettete und festgelegte Seiten auf
  ihrer Seite: **automatische Leerseiten** (schraffiert) werden eingefügt und verschwinden wieder,
  sobald sie nicht mehr gebraucht werden. Erst wenn eine Seite freigegeben wird, verschiebt sie sich.
  Gescannte Doppelseiten sind automatisch verkettet, Dokumente mit „rechts beginnen“ automatisch
  auf rechts festgelegt. Leerseiten lassen sich außerdem vor oder nach jeder Seite einfügen.
- **Formatvorlage Tufte:** Soll die Formatierung der Quelle nicht übernommen werden, setzt die
  Vorlage „Tufte“ (nach den Büchern von Edward Tufte, vgl. Tufte CSS) Dokumente und Webseiten in
  eine schmale Textspalte in Serifenschrift mit kursiven Überschriften und einem breiten Außenrand.
  Fußnoten (Markdown, Word, HTML), Randnotizen (`<span class="sidenote">`, `<span class="marginnote">`,
  Quarto `::: {.column-margin}`, `<aside>`) und kleine Abbildungen stehen dort neben der Zeile, auf
  die sie sich beziehen. Die breiten Ränder liegen immer außen – auch im Druck.
- **Satzstudio (`desk.php`):** Vollbild-Arbeitsplatz im Stil von DTP-Programmen mit Seitenpalette
  (Doppelseiten-Miniaturen), Werkzeugleiste, Montagefläche mit der aktuellen Doppelseite und
  Satzspiegel-Hilfslinien (bei Tufte-Seiten mit Notizspalte) mit Einrasten. Das Eigenschaften-Panel
  erscheint nur, wenn ein Rahmen oder Objekt ausgewählt ist, und schwebt auf der Seite gegenüber der
  bearbeiteten Seite; mit ⇄ lässt es sich auf die andere Seite verschieben, Esc schließt es. Rahmen
  lassen sich über den Bund auf die andere Seite ziehen; Doppelklick bearbeitet den Text direkt im
  Rahmen (fett, kursiv, Überschriften, Listen), ein rotes „+“ zeigt Übersatz an. Ausschnitt-Werkzeug
  und Spaltenerkennung stehen auf Bildseiten (Scans, PDF-Seiten) zur Verfügung.
- **Druck:** Bei 2 und 4 Seiten pro Blatt und bei der Broschüre werden Doppelseiten eingehalten
  (Seite 1 rechts), sodass Außenränder und Notizen außen liegen.

## Arbeitsablauf im Publishing Studio

| Schritt | Reiter | Funktionen |
|---|---|---|
| 1a Import | **1. Import** | PDF, Word (.docx), HTML, Markdown (.md), Bilder (PNG/JPG/GIF/WebP/TIFF), Comic-Archive (.cbz/.zip), leere Arbeitsblätter (Blanko, Liniert, Kariert, Notenlinien), Web-Ausschnitt (Harvester), Einfügen aus der Zwischenablage, Asset Bank |
| 1b Bereinigung | **2. Seiten & Bereinigung** | Ränder beschneiden, entzerren (Schräglage ±5°), Schatten/Vergilbung entfernen, Doppelseiten am Mittelsteg trennen, Querformatschutz, Doppelseiten verknüpfen/lösen, drehen, sortieren |
| 1c Fundstellen | **3. Quellen** | Herkunft (Titel, Autor/in, URL, Abrufdatum) wird beim Import erfasst und als verlinktes Zitat am Seitenende angezeigt; Quellen lassen sich löschen (wahlweise mit ihren Seiten) |
| 2–4 Satzstudio | **4. Satzstudio** (Vollbild) | Text- und Bildrahmen, Leerseiten, Ausschnitte für gestaltete Seiten, Überschreiben mit Papierfarb-Abgleich, Abdeckungen, Audio-Trigger, Glossarbegriffe, Textblöcke für die Textansicht, Spaltenbereiche mit automatischer Spaltenerkennung |
| 5 Veröffentlichung | **5. Veröffentlichen** | Seitenauszug (z. B. `5-12`), Textansicht und Druck an/aus, Master für die Asset Bank, Verwendungsnachweis |

## Funktionen im Detail

### Import (`classes/local/importer.php`)
- **PDF** wird mit Ghostscript (`$CFG->pathtogs`) seitenweise gerastert (Auflösung einstellbar).
- **Word**: Ist ein Dokumentkonverter (z. B. `fileconverter_unoconv`) eingerichtet, wird das Layout
  über PDF erhalten, sonst werden Überschriften, Absätze, Fett/Kursiv, Listen, Tabellen und manuelle
  Seitenumbrüche in HTML-Seiten übernommen (`docx_reader`).
- **Bilder/TIFF/CBZ**: jede Datei bzw. jedes Archivbild (natürliche Sortierung) wird eine Seite.
  Mehrseitige TIFFs benötigen die PHP-Erweiterung `imagick`.
- **Web-Ausschnitte** (`harvester`): serverseitiger Abruf über Moodles cURL-Klasse (Site-Proxy und
  „cURL blockierte Hosts“ gelten), Auswahl per einfachem Selektor (`article`, `#id`, `.klasse`,
  `table.klasse`), Skripte/Formulare/Event-Handler werden entfernt, Links absolut gemacht, Bilder in
  Moodle kopiert, das Ergebnis mit HTMLPurifier bereinigt.
- **Zwischenablage**: Editor mit Bildern; Quelle wird mit abgefragt.

- **Import im Hintergrund**: Hochgeladene Dateien werden als Adhoc-Task per Cron verarbeitet, damit
  große PDFs und Scan-Stapel nicht an Zeitlimits scheitern. Das Studio zeigt den Status und lädt sich
  neu, sobald der Import fertig ist; wartet ein Import über 10 Minuten, weist es auf einen evtl.
  nicht laufenden Cron hin. Abschaltbar in den Einstellungen (dann sofortiger Import).

### Layout-System: gestaltete Seiten (`classes/local/layout_renderer.php`, `layout.php`)
Neue Seiten werden in Markdown mit den Layout-Blöcken des **Quarto**-Formats geschrieben. Quarto
selbst (ein externes Programm) wird nicht benötigt; Buchbinder rendert die Syntax direkt:

- Spalten `::: {.columns}` / `::: {.column width="40%"}`, verschachtelbar
- Hinweisboxen `::: {.callout-note|tip|warning|important|caution}` mit Titel aus `title=` oder
  der ersten Überschrift
- Bilder mit Breite `![Bildunterschrift](ausschnitt-1.png){width=50%}`, allein stehende Bilder
  werden zur Abbildung mit Unterschrift
- Seitenumbruch `{{< pagebreak >}}` erzeugt weitere Seiten
- Buchbinder-Erweiterungen: `::: {.lines n=6}` (Schreiblinien), `::: {.box}` (Rahmen)
- Vorlagen: Leere Seite, Arbeitsblatt, Zwei Spalten, Bild und Text, Vokabelliste

**Ausschnitte** verbinden Import und Layout: Mit dem Werkzeug „Ausschnitt“ im Canvas wird ein
Bereich aus einer importierten Seite ausgeschnitten und steht als `ausschnitt-N.png` zur
Verfügung. Gestaltete Seiten werden am Bildschirm responsiv (Spalten untereinander auf
Smartphones) und im Eco-Print als Tabellenlayout ausgegeben.

### Scan-Optimierung (`classes/local/image_cleanup.php`, nur GD)
- Border Chopping: dunkle Randzeilen/-spalten werden entfernt (max. 20 % je Kante).
- Deskew: Projektionsprofil-Verfahren über die dunklen Pixel, Korrektur bis ±5°.
- Schattenentfernung: lokale Hintergrundschätzung je Kachel, bilinear interpoliert, Normalisierung.
- Doppelseiten: Mittelsteg als Schattental oder leerer Mittelstreifen. Inhalte, die über die Mitte
  laufen (Landkarten, breite Tabellen), haben keins von beiden und bleiben **geschützte
  Querformatseiten**. Getrennte Hälften bleiben als Doppelseite verbunden und werden immer
  gemeinsam verschoben; im Viewer stehen sie nebeneinander.

### Interaktive Ebenen (`overlay_types`, `amd/src/editor.js`, `amd/src/viewer.js`)
- **Überschreiben (Type-over)**: Beim Aufziehen wird die Papierfarbe am Kastenrand gemessen (die
  hellere Hälfte der Randpixel, damit Schrift nicht mitzählt) – so lassen sich alte Texte oder
  Tippfehler auf vergilbtem Papier unauffällig überdecken. Schriftgröße relativ zur Seitenbreite,
  damit Bildschirm und Druck übereinstimmen.
- **Abdeckungen**: Weißen/Schwärzen, optional durch Lernende aufdeckbar. Nicht aufdeckbare
  Abdeckungen werden für Lernende **serverseitig ins Seitenbild eingebrannt** (Cache in der
  Dateifläche `pagemasked`); die Lösung ist also auch über die Bild-URL nicht sichtbar. Lehrkräfte
  (`mod/buchbinder:viewsolutions`) sehen das Original mit halbtransparenter Abdeckung.
- **Audio-Trigger**: unsichtbare, per Tastatur erreichbare Hotspots (z. B. über Sprechblasen oder
  Notenzeilen) mit Mikrofon-Direktaufnahme (MediaRecorder), Datei-Upload oder Text-to-Speech
  (Web Speech API, Sprache einstellbar). „Hotspots anzeigen“ macht sie sichtbar.
- **Glossar**: Begriff + Glossar des Kurses; die Definition wird bei Berührung über die Moodle
  Glossar-Datenbank aufgelöst (Begriff oder Alias, nur freigegebene Einträge, Sichtbarkeit und
  Rechte des Glossars werden geprüft) und als Schwebekarte angezeigt.

### Mobile Comfort
- **Textansicht (Reflow)**: markierte Textblöcke (Absatz, Überschrift, Aufzählung) werden in
  Lesereihenfolge (Spalte für Spalte) als einspaltiger, barrierefreier Text angezeigt.
- **Column Zoom Anchoring**: Spaltenbereiche (manuell oder per „Spalten erkennen“ über
  Weißraumanalyse) rasten auf Smartphones beim Antippen auf Bildschirmbreite ein.

### Asset Bank
Aktivitäten können als **Master** angeboten werden. Lehrkräfte, die den Master bearbeiten dürfen,
übernehmen einen Auszug (z. B. `5-12`) inklusive Overlays und Audio in eine Aktivität eines anderen
Kurses. Herkunft (`masterid`, `masterrange`) wird gespeichert; der Master listet, wo er verwendet
wird. Zusätzlich kann jede Aktivität per `pagerange` nur einen Teil ihrer Seiten veröffentlichen.

### Backup/Restore
Kurs-Backups, Kursimport und „Duplizieren“ übernehmen Seiten, Quellen, Overlays und alle Dateien
(Originale, Seitenbilder, eingebettete Bilder, Audio). Doppelseiten bleiben verknüpft,
Glossar-Overlays zeigen nach der Wiederherstellung auf das wiederhergestellte Glossar des neuen Kurses.
Die Herkunft aus der Asset Bank bleibt erhalten, sofern der Master auf derselben Website existiert.

### Eco-Print (`classes/local/eco_print.php`, `imposition.php`)
1, 2 oder 4 Seiten pro Blatt oder **Broschüre** (Ausschießen für beidseitigen Druck über die kurze
Kante, Stapel in der Mitte falten). Der **Tintensparmodus** invertiert dunkle Seiten, wandelt in
Graustufen und hebt helle Hintergründe auf Weiß. Abdeckungen werden eingebrannt (außer
Lehrkraft-Version), Überschreibungen und Quellenangaben mitgedruckt.

## Einstellungen (Website-Administration → Plugins → Aktivitäten → Buchbinder)
- **Maximale Dateigröße** für Importe und Audio (begrenzt zusätzlich durch Site-/Kurs-Limits)
- **Maximale Seiten pro Import**, **Auflösung** gerenderter Seiten
- **Content-Harvester-Proxy aktivieren/deaktivieren** (Standard: aus), Bilder übernehmen, Zeitlimit

## Voraussetzungen
- Moodle 4.3 oder neuer – getestet mit **Moodle 4.4** (PHP 8.3) und **Moodle 5.0** (PHP 8.4), jeweils PostgreSQL 16
- PHP-Erweiterungen `gd`, `zip`, `dom` (Standard in Moodle); optional `imagick` für TIFF
- Ghostscript für den PDF-Import (Website-Administration → Server → Systempfade → `pathtogs`)
- Optional ein Dokumentkonverter (z. B. unoconv) für layouttreuen Word-Import

## Rechte
| Capability | Zweck |
|---|---|
| `mod/buchbinder:view` | Dokument lesen |
| `mod/buchbinder:edit` | Publishing Studio |
| `mod/buchbinder:harvest` | Web-Ausschnitte abrufen |
| `mod/buchbinder:useassetbank` | Seiten aus Mastern übernehmen |
| `mod/buchbinder:print` | Eco-Print |
| `mod/buchbinder:viewsolutions` | Abdeckungen durchsehen, Lehrkraft-Version drucken |

## Entwicklung
```
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite mod_buchbinder_testsuite
```
Die Tests decken Seitenbereiche, Ausschießen, Scan-Bereinigung (synthetische Scans), das
Dokumentmodell (Doppelseiten, Overlays, eingebrannte Abdeckungen, Asset Bank), die Webservices
inklusive Rechteprüfung, Backup/Restore und Duplizieren, das Layout-System, Ausschnitte, den Hintergrund-Import, den PDF-Druck in allen Layouts, den Word-Import und die Harvester-Helfer ab.

JavaScript liegt in `amd/src`; nach Änderungen im Plugin-Verzeichnis `npx grunt amd` ausführen
(im Moodle-Verzeichnis vorher `npm install`) und `amd/build` mit committen – die CI prüft, dass die
Builds aktuell sind.

## Release erstellen

Die GitHub Actions in `.github/workflows` übernehmen Prüfung und Veröffentlichung:

- **CI** (`ci.yml`) läuft bei jedem Push und Pull Request mit
  [moodle-plugin-ci](https://moodlehq.github.io/moodle-plugin-ci/) gegen Moodle 4.4 (PHP 8.1 und 8.3),
  4.5 (PHP 8.3) und 5.0 (PHP 8.4): PHP-Lint, Moodle-Codestil, PHPDoc, Plugin-Struktur,
  Upgrade-Savepoints, Mustache-Templates, ESLint/aktuelle JavaScript-Builds und PHPUnit.
- **Release** (`release.yml`) prüft Version und Changelog, lässt die CI laufen, baut
  `mod_buchbinder_<release>.zip` und veröffentlicht es als GitHub-Release. Alpha-, Beta- und
  RC-Versionen werden als Vorabversion markiert.

**Variante 1 – per Knopfdruck:** Unter *Actions → Release → Run workflow* die neue Versionsnummer
(z. B. `0.4.0`) und den Reifegrad wählen. Der Workflow setzt `version.php` (Versionsnummer im
Format JJJJMMTTXX, immer steigend), macht aus dem Abschnitt `[Unreleased]` in `CHANGES.md` die neue
Version, committet, taggt und veröffentlicht. Ist der Branch geschützt, muss
`github-actions[bot]` pushen dürfen – sonst Variante 2 verwenden.

**Variante 2 – von Hand:** lokal `.github/scripts/release.sh bump 0.4.0 MATURITY_BETA` ausführen
(oder `version.php`/`CHANGES.md` selbst anpassen), committen und den Tag pushen:
```
git tag v0.4.0 && git push origin v0.4.0
```

Der Tag muss `v` + `$plugin->release` lauten und `CHANGES.md` einen Abschnitt für die Version
enthalten, sonst bricht der Release ab. Das ZIP enthält den Ordner `buchbinder/` und wird unter
*Website-Administration → Plugins → Plugin installieren* hochgeladen (oder nach `mod/` entpackt).

**Variante 3 – über die GitHub-Oberfläche:** *Releases → Draft a new release*, Tag wählen oder neu
anlegen und veröffentlichen. Der Workflow hängt das ZIP nach wenigen Minuten an das Release an
(Name, Text und Vorabversion-Häkchen bleiben so, wie sie eingegeben wurden). Tags mit `v` müssen
zur Version in `version.php` passen, andere Tags wie `latest` werden ohne Prüfung akzeptiert.

**Automatisch nach jedem Push:** Ist die CI grün und gibt es zur Version in `version.php` schon ein
Release ohne ZIP, wird das ZIP angehängt. Auf dem Hauptbranch wird ein fehlendes Release neu
erstellt – Versionsnummer erhöhen (`release.sh bump`) und mergen genügt.

**Optional: moodle.org.** Ist das Plugin im Moodle-Plugin-Verzeichnis registriert, lädt der
Workflow jede Version automatisch hoch, sobald das Repository-Secret `MOODLE_ORG_TOKEN` gesetzt ist
(Token unter moodle.org → Profil → Sicherheitsschlüssel, Dienst „Plugins directory API“).

## Roadmap
- Texterkennung (OCR), um Reflow-Textblöcke automatisch zu füllen – geplant über Tesseract auf dem
  Server (wie Ghostscript per Pfad konfiguriert); Moodles KI-Schnittstelle (ab 4.5) bietet keine
  Texterkennung
- Serverseitiges Text-to-Speech als Alternative zur Browser-Sprachausgabe
- Unterstützung der Moodle-App
