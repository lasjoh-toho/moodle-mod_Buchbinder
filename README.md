# Buchbinder (mod_buchbinder)

Moodle-Aktivität, die statische PDFs, Dokumentenscans, Word-Dateien und Webinhalte in modulare,
interaktive und wiederverwendbare Lernmaterialien verwandelt. Lehrkräfte laden Dokumente nicht als
starre Einzeldateien hoch, sondern bearbeiten sie in einem **Publishing Studio**: Scans bereinigen,
direkt auf Seiten schreiben, unsichtbare Audio-Trigger, Glossarbegriffe und Lösungsabdeckungen
anlegen, eine Textansicht für Smartphones anbieten und papiersparend drucken.

> Komponentenname: `mod_buchbinder`. Moodle verlangt kleingeschriebene Komponentennamen, daher
> heißt das Verzeichnis `mod/buchbinder`, obwohl das Repository `moodle-mod_Buchbinder` heißt.

Status: **0.2.0 (Alpha)**. Der gesamte Arbeitsablauf ist umgesetzt; Punkte, die noch fehlen,
stehen unter [Roadmap](#roadmap).

## Arbeitsablauf im Publishing Studio

| Schritt | Reiter | Funktionen |
|---|---|---|
| 1a Import | **1. Import** | PDF, Word (.docx), HTML, Bilder (PNG/JPG/GIF/WebP/TIFF), Comic-Archive (.cbz/.zip), leere Arbeitsblätter (Blanko, Liniert, Kariert, Notenlinien), Web-Ausschnitt (Harvester), Einfügen aus der Zwischenablage, Asset Bank |
| 1b Bereinigung | **2. Seiten & Bereinigung** | Ränder beschneiden, entzerren (Schräglage ±5°), Schatten/Vergilbung entfernen, Doppelseiten am Mittelsteg trennen, Querformatschutz, Doppelseiten verknüpfen/lösen, drehen, sortieren |
| 1c Fundstellen | **3. Quellen** | Herkunft (Titel, Autor/in, URL, Abrufdatum) wird beim Import erfasst und als verlinktes Zitat am Seitenende angezeigt |
| 2–4 Canvas | **4. Canvas & Overlays** | Überschreiben mit Papierfarb-Abgleich, Abdeckungen, Audio-Trigger, Glossarbegriffe, Textblöcke für die Textansicht, Spaltenbereiche mit automatischer Spaltenerkennung |
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
inklusive Rechteprüfung, Backup/Restore und Duplizieren, den PDF-Druck in allen Layouts, den Word-Import und die Harvester-Helfer ab.

JavaScript liegt in `amd/src` als AMD-Module; `amd/build/*.min.js` sind unminifizierte Kopien mit
Modulnamen. Mit `grunt amd` im Moodle-Verzeichnis lassen sich echte Builds erzeugen.

## Roadmap
- Layout-System für neue Seiten und das Arrangieren importierter Inhalte (z. B. auf Basis von Quarto)
- Texterkennung (OCR), um Reflow-Textblöcke automatisch zu füllen
- Serverseitiges Text-to-Speech als Alternative zur Browser-Sprachausgabe
- Rendering großer Importe als Hintergrundaufgabe (ad-hoc Task) statt im Request
- Unterstützung der Moodle-App
