# Änderungen

Neue Einträge kommen unter **[Unreleased]**. Beim Release wird dieser Abschnitt automatisch zur
neuen Version (siehe README, „Release erstellen“).

## [Unreleased]

## [0.7.0] - 2026-09-27

- Importtisch (`import.php`) als Vollbild-Arbeitsplatz: Importe landen zuerst im Importbereich und
  erscheinen als Miniaturen in Doppelseiten; ein Seitenbereich (z. B. `1-3, 5` oder per Häkchen) wird an
  eine wählbare Stelle des Dokuments übernommen
- Im Importtisch per Rechtsklick: Seiten teilen (Scan-Doppelseiten), verketten und als linke oder rechte
  Seite festlegen; automatische Leerseiten werden in der Vorschau angezeigt
- Fortlaufende Quellen (Word, HTML, Markdown, Web, Zwischenablage): Klick auf eine Miniatur zeigt die
  Passagen der Seite, einzelne Passagen lassen sich ausblenden; Formatvorlage und „rechts beginnen“
  sind nachträglich umstellbar
- Seitenumfang wird im Browser mit Pretext exakt gemessen; Absätze werden genau an Zeilenenden umbrochen
- Rahmen werden gespiegelt, wenn eine gesetzte Seite beim Übernehmen oder Verschieben die Seite wechselt,
  sodass breite Ränder und Notizen außen bleiben
- Satzstudio mit Editorleiste wie in einer Textverarbeitung: Formatvorlagen (Normaler Text, Titel,
  Überschrift 1–3, Zitat, Code), fett, kursiv, unterstrichen, durchgestrichen, Text- und Markierungsfarbe,
  Link, Ausrichtung, Listen, Einzug, Formatierung löschen, Rahmenstil und Schriftgröße in pt,
  Rückgängig/Wiederholen

## [0.6.0] - 2026-09-26

- Seitenpalette mit Kontextmenü (Rechtsklick oder Kontextmenü-Taste): Seiten mit der Folgeseite
  verketten (Kettensymbol) und als linke oder rechte Seite festlegen (L/R)
- Automatische Leerseiten halten verkettete und festgelegte Seiten beim Einfügen, Löschen und
  Verschieben auf ihrer Seite und verschwinden wieder, wenn sie nicht mehr gebraucht werden
- Eigenschaften-Panel erscheint nur bei Auswahl, schwebt gegenüber der bearbeiteten Seite und lässt
  sich auf die andere Seite verschieben
- Formatvorlage „Tufte“ für Word, HTML, Markdown und Web-Ausschnitte: schmale Textspalte, Serifenschrift,
  breiter Außenrand mit Fußnoten, Randnotizen und kleinen Abbildungen neben dem Text; Rahmen-Formatierung
  im Panel umstellbar
- Fußnoten aus Word werden übernommen
- Druck mit 2 oder 4 Seiten pro Blatt hält Doppelseiten ein, Außenränder und Notizen liegen außen
- Behoben: Fehler „errornotimage“, wenn das Ausschnitt-Werkzeug auf einer Rahmenseite benutzt wurde;
  das Werkzeug ist jetzt nur auf Bildseiten aktiv

## [0.5.0] - 2026-09-26

- Broschüren-Modell: Jede Seite liegt links oder rechts (Seite 1 rechts, dann Doppelseiten 2|3, 4|5 …),
  gespiegelter Satzspiegel mit Bundsteg; Leseansicht zeigt Doppelseiten nebeneinander
- Gescannte Doppelseiten landen automatisch auf linker und rechter Seite, dafür werden bei Bedarf
  Leerseiten eingefügt; falsch liegende Doppelseiten werden markiert und lassen sich per Klick ausrichten
- Leerseiten können überall eingefügt werden
- Word, HTML, Markdown (neu), Web-Ausschnitte und Zwischenablage werden als verschiebbare Text- und
  Bildrahmen gesetzt (Satzlauf mit Seitenumbruch, geteilten Absätzen/Listen/Tabellen, Bildern aus Word)
- Vollbild-Satzstudio im DTP-Stil: Seitenpalette, Werkzeuge, Montagefläche mit Doppelseite,
  Hilfslinien mit Einrasten, Rahmen über den Bund ziehen, Text direkt im Rahmen bearbeiten,
  Übersatz-Anzeige, Zoom
- Quellen können gelöscht werden, wahlweise mit den importierten Seiten
- Druck und Smartphone-Textansicht berücksichtigen Text- und Bildrahmen

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
