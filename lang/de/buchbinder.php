<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * German strings for mod_buchbinder.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addpage'] = 'Seite';
$string['addpages'] = 'Seiten hinzufügen';
$string['align'] = 'Ausrichtung';
$string['align_center'] = 'Zentriert';
$string['align_left'] = 'Links';
$string['align_right'] = 'Rechts';
$string['alignspread'] = 'Leerseite davor einfügen';
$string['allpages'] = 'Alle Seiten';
$string['alttext'] = 'Alternativtext';
$string['apply'] = 'Anwenden';
$string['assetbank'] = 'Asset Bank';
$string['assetbank_help'] = 'Seiten eines Master-Dokuments in diese Aktivität übernehmen, z. B. nur S. 5–12 für Woche 2. Overlays und Audio werden mitkopiert; die Herkunft wird im Master protokolliert.';
$string['assetusage'] = 'Verwendet in';
$string['assetusage_none'] = 'Bisher verwendet keine andere Aktivität Seiten dieses Dokuments.';
$string['audio_file'] = 'Aufnahme oder Audiodatei';
$string['audio_tts'] = 'Text-to-Speech';
$string['audiosaved'] = 'Audio gespeichert';
$string['audiosource'] = 'Audioquelle';
$string['backgroundimport'] = 'Import im Hintergrund';
$string['backgroundimport_desc'] = 'Importe werden per Cron (Adhoc-Task) verarbeitet, damit große PDFs und Scan-Stapel nicht an Zeitlimits des Webservers scheitern. Setzt einen regelmäßig laufenden Cron voraus.';
$string['backtocourse'] = 'Zurück zum Kurs';
$string['bgcolor'] = 'Hintergrundfarbe';
$string['blankafter'] = 'Leerseite danach einfügen';
$string['blankbefore'] = 'Leerseite davor einfügen';
$string['blankpages'] = 'Leere Arbeitsblätter';
$string['bold'] = 'Fett';
$string['buchbinder:addinstance'] = 'Neues Buchbinder-Dokument anlegen';
$string['buchbinder:edit'] = 'Publishing Studio verwenden';
$string['buchbinder:harvest'] = 'Inhalte von Webseiten übernehmen';
$string['buchbinder:print'] = 'Eco-Print-PDFs erstellen';
$string['buchbinder:useassetbank'] = 'Seiten aus der Asset Bank übernehmen';
$string['buchbinder:view'] = 'Buchbinder-Dokumente ansehen';
$string['buchbinder:viewsolutions'] = 'Abgedeckte Lösungen sehen';
$string['bulletlist'] = 'Aufzählung';
$string['callout_caution'] = 'Vorsicht';
$string['callout_important'] = 'Wichtig';
$string['callout_note'] = 'Hinweis';
$string['callout_tip'] = 'Tipp';
$string['callout_warning'] = 'Achtung';
$string['caption'] = 'Bildunterschrift';
$string['citation'] = 'Zitat';
$string['citationaccessed'] = 'abgerufen am {$a}';
$string['cleanup_chop'] = 'Schwarze Scanränder beschneiden';
$string['cleanup_chop_short'] = 'Ränder';
$string['cleanup_deskew'] = 'Schräge Scans entzerren';
$string['cleanup_deskew_short'] = 'Entzerren';
$string['cleanup_shadow'] = 'Schatten und Vergilbung entfernen';
$string['cleanup_shadow_short'] = 'Schatten';
$string['cleanup_split'] = 'Gescannte Doppelseiten trennen';
$string['cleanup_split_help'] = 'Querformat-Scans werden am Mittelsteg in linke und rechte Seite getrennt. Beide Hälften bleiben als Doppelseite verbunden, werden immer gemeinsam verschoben und nebeneinander angezeigt. Querformatseiten ohne Mittelsteg (breite Abbildungen, Landkarten, Tabellen) bleiben als geschützte Querformat-Einzelseiten erhalten.';
$string['cleanup_split_short'] = 'Trennen';
$string['cleanupall'] = 'Alle gescannten Seiten optimieren';
$string['cleanupwarning'] = 'Bereinigung vor dem Anlegen von Overlays ausführen: Beim Trennen einer Seite werden deren Overlays entfernt.';
$string['clipboardsnippet'] = 'Aus Zwischenablage einfügen';
$string['clipboardsnippet_help'] = 'Texte, Tabellen und Bilder aus Webseiten oder Dokumenten einfügen. Bitte die Herkunft angeben – das Zitat wird am Seitenende angezeigt.';
$string['clipcreated'] = 'Ausschnitt gespeichert. In eine gestaltete Seite einfügen mit {$a}';
$string['clips'] = 'Ausschnitte';
$string['clips_help'] = 'Ausschnitte sind Bereiche aus importierten Seiten. Sie werden mit dem Werkzeug „Ausschnitt“ erstellt unter';
$string['columnhelp'] = 'Auf Smartphones zoomt ein Tippen auf diese Spalte sie auf Bildschirmbreite.';
$string['columnsfound'] = '{$a} Spalten erkannt.';
$string['confirmdeletepage'] = 'Diese Seite samt allen Overlays löschen?';
$string['copypages'] = 'Seiten übernehmen';
$string['createpdf'] = 'PDF erstellen';
$string['deleteoverlay'] = 'Löschen';
$string['deletesource'] = 'Quelle löschen';
$string['deletesource_confirm'] = 'Die Quelle „{$a->title}“ löschen? Aus ihr wurden {$a->pages} Seite(n) importiert.';
$string['deletesource_keeppages'] = 'Nur die Quelle löschen, Seiten behalten';
$string['deletesource_withpages'] = 'Quelle und ihre {$a} Seite(n) löschen';
$string['derivedfrom'] = 'Dieses Dokument enthält Seiten des Masters';
$string['desk'] = 'Satzstudio';
$string['detectcolumns'] = 'Spalten erkennen';
$string['dismiss'] = 'Aus der Liste entfernen';
$string['ecoprint'] = 'Eco-Print';
$string['ecoprint_help'] = 'Papiersparendes PDF erstellen: 2 oder 4 Seiten pro Blatt oder eine faltbare Broschüre. Der Tintensparmodus invertiert dunkle Seiten und entfernt graue Hintergründe. Abgedeckte Lösungen bleiben abgedeckt, außer bei der Lehrkraft-Version.';
$string['editlayoutpage'] = 'Gestaltete Seite bearbeiten';
$string['editorhelp'] = 'Werkzeug wählen und ein Rechteck auf der Seite aufziehen. Kästen per Ziehen verschieben, am Anfasser skalieren, mit den Pfeiltasten feinjustieren und mit Entf löschen.';
$string['editpage'] = 'Seite bearbeiten';
$string['editsource'] = 'Quelle bearbeiten';
$string['edittext'] = 'Text bearbeiten';
$string['enableharvester'] = 'Content-Harvester-Proxy aktivieren';
$string['enableharvester_desc'] = 'Erlaubt Lehrkräften, Texte, Tabellen und Bilder von Webseiten über den Moodle-Server abzurufen. Anfragen nutzen den Proxy der Website und beachten die Einstellung „cURL blockierte Hosts“.';
$string['enableprint'] = 'Eco-Print für Teilnehmende erlauben';
$string['enablereflow'] = 'Textansicht für Smartphones anbieten';
$string['enablereflow_help'] = 'Im Studio markierte Textblöcke werden als einspaltiger, barrierefreier Text angezeigt.';
$string['erroraudiotype'] = 'Dies ist keine unterstützte Audiodatei.';
$string['errorfiletoolarge'] = 'Die Datei ist zu groß (Maximum: {$a}).';
$string['errorharvest'] = 'Die Webseite konnte nicht abgerufen werden ({$a}).';
$string['errorharvestempty'] = 'Auf der Webseite wurde kein Inhalt gefunden.';
$string['errorimage'] = 'Das Bild konnte nicht gelesen werden.';
$string['errorimportformat'] = 'Dieses Dateiformat wird nicht unterstützt.';
$string['errornoghostscript'] = 'Für den PDF-Import wird Ghostscript benötigt. Bitte die Administration bitten, den Pfad zu Ghostscript (pathtogs) zu konfigurieren.';
$string['errornoimagick'] = 'Der TIFF-Import benötigt die PHP-Erweiterung imagick.';
$string['errornothtml'] = 'Hier können nur Textseiten bearbeitet werden.';
$string['errornotimage'] = 'Ausschnitte können nur aus Bildseiten erstellt werden.';
$string['errornotlayout'] = 'Diese Seite ist keine gestaltete Seite.';
$string['errorpagerange'] = 'Ungültiger Seitenbereich. Beispiel: 1-4, 7, 10-';
$string['errorpdfconversion'] = 'Das PDF konnte nicht umgewandelt werden: {$a}';
$string['firstpageright'] = 'Erste Seite ist eine rechte Seite';
$string['firstpageright_help'] = 'Das Dokument wird als Broschüre gesetzt. Wie im gedruckten Buch ist Seite 1 eine rechte Seite, danach folgen Doppelseiten (2|3, 4|5, …). Gescannte Doppelseiten werden so platziert, dass ihre linke Hälfte auf einer linken Seite liegt; wo nötig, werden Leerseiten eingefügt.';
$string['fit_contain'] = 'Einpassen (ganzes Bild)';
$string['fit_cover'] = 'Rahmen füllen (zuschneiden)';
$string['fontscale'] = 'Schriftgröße (Faktor)';
$string['fontsize'] = 'Schriftgröße';
$string['frameborder'] = 'Rahmenlinie';
$string['glossary'] = 'Glossar';
$string['glossarynotfound'] = 'Für diesen Begriff gibt es noch keinen Glossareintrag.';
$string['glossaryopen'] = 'Im Glossar öffnen';
$string['harvest'] = 'Ausschnitt abrufen';
$string['harvester'] = 'Content Harvester';
$string['harvesterdisabled'] = 'Der Content Harvester ist auf dieser Website deaktiviert. Inhalte können weiterhin aus der Zwischenablage eingefügt werden.';
$string['harvestertimeout'] = 'Zeitlimit';
$string['harvestertimeout_desc'] = 'Sekunden, die auf eine Webseite gewartet wird.';
$string['harvestimages'] = 'Bilder übernehmen';
$string['harvestimages_desc'] = 'Bilder übernommener Ausschnitte in Moodle speichern statt sie zu entfernen.';
$string['imagefit'] = 'Bild im Rahmen';
$string['imagesaved'] = 'Bild gespeichert';
$string['import'] = 'Importieren';
$string['importfiles'] = 'Dokumente importieren';
$string['importfiles_help'] = 'Unterstützt: PDF, Word (.docx), HTML, Markdown (.md), Bild-Scans (PNG, JPG, GIF, WebP, TIFF) und Comic-Archive (.cbz/.zip). Word, HTML und Markdown werden als verschiebbare Text- und Bildrahmen auf Broschürenseiten gesetzt; Scans und PDFs werden zu Bildseiten.';
$string['importjobs'] = 'Importe';
$string['importqueued'] = 'Der Import läuft im Hintergrund. Die Seiten erscheinen, sobald er fertig ist.';
$string['inksaver'] = 'Tintensparmodus';
$string['inksaver_help'] = 'Dunkle Seiten werden invertiert, Farben in Graustufen umgewandelt und helle Hintergründe entfernt.';
$string['ismaster'] = 'Als Master in der Asset Bank anbieten';
$string['ismaster_help'] = 'Lehrkräfte, die diese Aktivität bearbeiten dürfen, können Auszüge daraus in Buchbinder-Aktivitäten anderer Kurse übernehmen.';
$string['italic'] = 'Kursiv';
$string['jobstale'] = 'Dieser Import wartet seit über 10 Minuten. Läuft der Cron dieser Moodle-Instanz?';
$string['jobstatus_done'] = 'Fertig';
$string['jobstatus_failed'] = 'Fehlgeschlagen';
$string['jobstatus_queued'] = 'Wartet';
$string['jobstatus_running'] = 'Läuft';
$string['label'] = 'Beschriftung';
$string['landscape'] = 'Querformat';
$string['landscapeprotected'] = 'Geschütztes Querformat';
$string['layout_1up'] = '1 Seite pro Blatt';
$string['layout_2up'] = '2 Seiten pro Blatt';
$string['layout_4up'] = '4 Seiten pro Blatt';
$string['layout_booklet'] = 'Broschüre (beidseitig über die kurze Kante drucken, falten)';
$string['layoutissues'] = '{$a} Doppelseite(n) liegen nicht auf einer linken und einer rechten Seite.';
$string['layoutpage'] = 'Gestaltete Seite';
$string['layoutsaved'] = '{$a} gestaltete Seite(n) gespeichert.';
$string['layoutsource'] = 'Inhalt';
$string['layoutsource_help'] = 'Die Seite wird in Markdown mit den Layout-Blöcken von Quarto geschrieben: Spalten, Hinweisboxen, Bilder mit Breite und Seitenumbrüche. Die Syntax steht im Kasten rechts. Ein Seitenumbruch erzeugt eine weitere Seite.';
$string['layoutsyntax'] = 'Syntax';
$string['layoutsyntax_help'] = '# Überschrift
**fett**, *kursiv*, - Liste, 1. Liste
| A | B |
|---|---|
| Tabelle | Zelle |

::: {.columns}
::: {.column width="40%"}
![Bildunterschrift](ausschnitt-1.png)
:::
::: {.column width="60%"}
Text neben dem Bild
:::
:::

::: {.callout-note}
## Titel
Hinweis (auch: tip, warning,
important, caution)
:::

::: {.lines n=6}
Schreiblinien für Antworten
:::

::: {.box}
Kasten mit Rahmen
:::

![](ausschnitt-2.png){width=50%}

{{< pagebreak >}}';
$string['layouttemplate_blank'] = 'Leere Seite';
$string['layouttemplate_blank_desc'] = 'Mit Überschrift und Text beginnen.';
$string['layouttemplate_blank_source'] = '# Titel

Text
';
$string['layouttemplate_imagetext'] = 'Bild und Text';
$string['layouttemplate_imagetext_desc'] = 'Ein Ausschnitt aus einer importierten Seite neben Erläuterungen.';
$string['layouttemplate_imagetext_source'] = '# Titel

::: {.columns}
::: {.column width="40%"}
![Bildunterschrift](ausschnitt-1.png)
:::
::: {.column width="60%"}
Erläuterung zum Bild.

::: {.callout-tip}
## Genau hinsehen
Was fällt dir auf?
:::
:::
:::
';
$string['layouttemplate_twocolumns'] = 'Zwei Spalten';
$string['layouttemplate_twocolumns_desc'] = 'Text in zwei gleich breiten Spalten.';
$string['layouttemplate_twocolumns_source'] = '# Titel

::: {.columns}
::: {.column width="50%"}
## Links

Text
:::
::: {.column width="50%"}
## Rechts

Text
:::
:::
';
$string['layouttemplate_vocabulary'] = 'Vokabelliste';
$string['layouttemplate_vocabulary_desc'] = 'Tabelle mit Wörtern, Übersetzungen und Beispielen.';
$string['layouttemplate_vocabulary_source'] = '# Vokabeln

| Wort | Übersetzung | Beispiel |
|---|---|---|
| bonjour | guten Tag | Bonjour, Marie ! |
|  |  |  |
|  |  |  |
';
$string['layouttemplate_worksheet'] = 'Arbeitsblatt';
$string['layouttemplate_worksheet_desc'] = 'Titel, Aufgaben mit Arbeitsanweisung und Schreiblinien.';
$string['layouttemplate_worksheet_source'] = '# Arbeitsblatt

Name: ______________________  Datum: ____________

::: {.callout-note}
## Aufgabe 1
Lies den Text und beantworte die Fragen.
:::

::: {.lines n=5}
:::

::: {.callout-note}
## Aufgabe 2
Beschreibe das Bild.
:::

::: {.columns}
::: {.column width="40%"}
![](ausschnitt-1.png)
:::
::: {.column width="60%"}
::: {.lines n=6}
:::
:::
:::
';
$string['linkspread'] = 'Mit nächster Seite verbinden';
$string['mask_black'] = 'Schwärzen';
$string['mask_white'] = 'Weißen';
$string['maskstyle'] = 'Stil';
$string['matchbackground'] = 'Papierfarbe übernehmen';
$string['maxbytes'] = 'Maximale Dateigröße';
$string['maxbytes_desc'] = 'Maximale Größe importierter Dokumente und Audiodateien.';
$string['maxpages'] = 'Maximale Seiten pro Import';
$string['maxpages_desc'] = 'Längere Dokumente werden abgeschnitten.';
$string['modulename'] = 'Buchbinder';
$string['modulename_help'] = 'Buchbinder verwandelt PDFs, Scans, Word-Dateien und Webinhalte in modulare, interaktive Lernmaterialien: Scans bereinigen, direkt auf Seiten schreiben, unsichtbare Audio-Trigger, Glossarbegriffe und Lösungsabdeckungen ergänzen, eine Textansicht für Smartphones anbieten und papiersparende Broschüren drucken.';
$string['modulenameplural'] = 'Buchbinder-Dokumente';
$string['newframetext'] = 'Text';
$string['newlayoutpage'] = 'Neue gestaltete Seite';
$string['nextspread'] = 'Nächste Doppelseite';
$string['nobackground'] = 'Transparenter Hintergrund';
$string['nocitation'] = 'Kein Zitat angezeigt';
$string['nocolumnsfound'] = 'Kein mehrspaltiges Layout erkannt.';
$string['noglossaries'] = 'In diesem Kurs gibt es kein Glossar.';
$string['nomasters'] = 'Keine Master-Dokumente verfügbar. Eine Buchbinder-Aktivität in den Veröffentlichungseinstellungen als Master markieren.';
$string['nopages'] = 'Dieses Dokument hat noch keine Seiten.';
$string['nosources'] = 'Noch nichts importiert.';
$string['numberedlist'] = 'Nummerierte Liste';
$string['numberofpages'] = 'Anzahl Seiten';
$string['overlay_audio'] = 'Audio-Trigger';
$string['overlay_column'] = 'Spalte';
$string['overlay_glossary'] = 'Glossarbegriff';
$string['overlay_imageframe'] = 'Bildrahmen';
$string['overlay_mask'] = 'Abdeckung';
$string['overlay_reflow'] = 'Textblock';
$string['overlay_textbox'] = 'Überschreiben';
$string['overlay_textframe'] = 'Textrahmen';
$string['overset'] = 'Der Text passt nicht in den Rahmen (Übersatz). Rahmen vergrößern oder Schrift verkleinern.';
$string['pagecontent'] = 'Inhalt';
$string['pagerange'] = 'Veröffentlichte Seiten';
$string['pagerange_help'] = 'Leer lassen, um alle Seiten zu veröffentlichen. Beispiele: „5-12“ oder „1-3, 8, 10-“.';
$string['pages'] = 'Seiten';
$string['pagesimported'] = '{$a} Seite(n) hinzugefügt.';
$string['pagex'] = 'Seite {$a}';
$string['paragraph'] = 'Absatz';
$string['pluginadministration'] = 'Buchbinder-Administration';
$string['pluginname'] = 'Buchbinder';
$string['preview'] = 'Vorschau';
$string['previousspread'] = 'Vorherige Doppelseite';
$string['printdisabled'] = 'Das Drucken ist für dieses Dokument deaktiviert.';
$string['printlayout'] = 'Layout';
$string['printlayout_help'] = '2 bzw. 4 Seiten pro Blatt sparen Papier. Die Broschüre ordnet die Seiten so an, dass der gedruckte Stapel in der Mitte gefaltet werden kann.';
$string['printrange'] = 'Seiten';
$string['printrange_help'] = 'Leer lassen, um alle veröffentlichten Seiten zu drucken.';
$string['privacy:metadata'] = 'Die Aktivität Buchbinder speichert ausschließlich Lehrmaterial und keine personenbezogenen Daten.';
$string['protect'] = 'Querformat schützen';
$string['publishing'] = 'Veröffentlichung';
$string['rasterdpi'] = 'Auflösung gerenderter Seiten';
$string['rasterdpi_desc'] = 'DPI für das Rendern von PDF-Seiten und leeren Arbeitsblättern.';
$string['record'] = 'Aufnehmen';
$string['reflowrole'] = 'Art';
$string['revealable'] = 'Teilnehmende können aufdecken';
$string['revealsolution'] = 'Lösung anzeigen';
$string['role_h2'] = 'Überschrift';
$string['role_h3'] = 'Zwischenüberschrift';
$string['role_li'] = 'Aufzählungspunkt';
$string['role_p'] = 'Absatz';
$string['rotate'] = 'Drehen';
$string['save'] = 'Speichern';
$string['saved'] = 'Gespeichert';
$string['selector'] = 'Element';
$string['selector_help'] = 'Optional: der zu übernehmende Teil der Seite, z. B. „article“, „#content“, „.infobox“ oder „table.data“. Ohne Angabe wird der Hauptartikel verwendet.';
$string['selectoverlay'] = 'Einen Kasten auf der Seite auswählen oder neu aufziehen.';
$string['showcitation'] = 'Zitat unter den Seiten anzeigen';
$string['showhotspots'] = 'Hotspots anzeigen';
$string['side_left'] = 'links';
$string['side_right'] = 'rechts';
$string['snippetharvested'] = 'Der Ausschnitt wurde als neue Seite angelegt. Hier prüfen und bearbeiten.';
$string['source'] = 'Quelle';
$string['sourceaccessed'] = 'Abgerufen am';
$string['sourceauthor'] = 'Autor/in';
$string['sourcedeleted'] = 'Quelle gelöscht ({$a} Seite(n) entfernt).';
$string['sourcemeta'] = 'Quelle (Zitat)';
$string['sourcetitle'] = 'Titel';
$string['sourcetype'] = 'Art';
$string['sourcetype_blank'] = 'Leeres Arbeitsblatt';
$string['sourcetype_cbz'] = 'Comic-Archiv';
$string['sourcetype_clipboard'] = 'Zwischenablage';
$string['sourcetype_docx'] = 'Word';
$string['sourcetype_html'] = 'HTML';
$string['sourcetype_image'] = 'Bild-Scan';
$string['sourcetype_md'] = 'Markdown';
$string['sourcetype_pdf'] = 'PDF';
$string['sourcetype_web'] = 'Web-Ausschnitt';
$string['sourceurl'] = 'URL';
$string['spread_left'] = 'Linke Seite';
$string['spread_right'] = 'Rechte Seite';
$string['spreadmisaligned'] = 'Die Hälften dieser gescannten Doppelseite liegen nicht auf einer linken und einer rechten Seite.';
$string['startright'] = 'Mit einer rechten Seite beginnen';
$string['startright_help'] = 'Importierte Dokumente beginnen wie ein neues Kapitel auf einer rechten Seite. Wo nötig, wird eine Leerseite eingefügt.';
$string['stoprecording'] = 'Aufnahme beenden';
$string['studio'] = 'Publishing Studio';
$string['tab_canvas'] = 'Canvas & Overlays';
$string['tab_import'] = 'Import';
$string['tab_pages'] = 'Seiten & Bereinigung';
$string['tab_publish'] = 'Veröffentlichen';
$string['tab_sources'] = 'Quellen';
$string['template'] = 'Vorlage';
$string['template_blank'] = 'Blanko';
$string['template_lined'] = 'Liniert';
$string['template_squared'] = 'Kariert';
$string['template_staff'] = 'Notenlinien';
$string['term'] = 'Begriff';
$string['text'] = 'Text';
$string['textcolor'] = 'Textfarbe';
$string['textcolumns'] = 'Spalten';
$string['textview'] = 'Textansicht';
$string['tool_clip'] = 'Ausschnitt';
$string['tool_select'] = 'Auswählen';
$string['toolbar'] = 'Dokumentwerkzeuge';
$string['tools'] = 'Werkzeuge';
$string['ttslang'] = 'Sprache (z. B. de-DE, fr-FR)';
$string['ttstext'] = 'Vorzulesender Text';
$string['unlinkspread'] = 'Doppelseite lösen';
$string['unprotect'] = 'Schutz aufheben';
$string['uploadaudio'] = 'Audiodatei hochladen';
$string['uploadimage'] = 'Bild hochladen';
$string['websnippet'] = 'Web-Ausschnitt';
$string['withsolutions'] = 'Lehrkraft-Version mit Lösungen';
$string['zoom'] = 'Zoom';
