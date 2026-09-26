# Änderungen

Neue Einträge kommen unter **[Unreleased]**. Beim Release wird dieser Abschnitt automatisch zur
neuen Version (siehe README, „Release erstellen“).

## [Unreleased]

## [0.4.0] - 2026-09-26

- Release-Workflow: GitHub Actions prüft jeden Push (Moodle 4.4, 4.5 und 5.0) und veröffentlicht
  bei einem Versions-Tag ein installierbares ZIP als GitHub-Release
- JavaScript wird als minifizierter Build ausgeliefert

## [0.3.0] - 2026-09-26

- Import im Hintergrund per Cron mit Statusanzeige im Studio
- Layout-System: gestaltete Seiten in Quarto-Markdown (Spalten, Hinweisboxen, Bilder mit Breite,
  Seitenumbrüche, Schreiblinien, Rahmen) mit Vorlagen, Vorschau und Druckausgabe
- Ausschnitt-Werkzeug im Canvas, um Bereiche importierter Seiten in Layouts zu verwenden
- Behoben: Bilder aus Web-Ausschnitten fehlten im gedruckten PDF

## [0.2.0] - 2026-09-26

- Backup/Restore, Kursimport und Duplizieren mit allen Seiten, Overlays und Dateien
- Getestet mit Moodle 4.4 (PHP 8.3) und Moodle 5.0 (PHP 8.4)

## [0.1.0] - 2026-09-25

- Erste Version: Multi-Format-Import, Scan-Bereinigung, Canvas-Editor mit Overlays (Überschreiben,
  Abdeckungen, Audio, Glossar, Textblöcke, Spalten), Smartphone-Ansicht, Asset Bank und Eco-Print
