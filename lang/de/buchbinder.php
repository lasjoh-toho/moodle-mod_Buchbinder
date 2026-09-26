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

$string['addpages'] = 'Seiten hinzufügen';
$string['align'] = 'Ausrichtung';
$string['align_center'] = 'Zentriert';
$string['align_left'] = 'Links';
$string['align_right'] = 'Rechts';
$string['allpages'] = 'Alle Seiten';
$string['apply'] = 'Anwenden';
$string['assetbank'] = 'Asset Bank';
$string['assetbank_help'] = 'Seiten eines Master-Dokuments in diese Aktivität übernehmen, z. B. nur S. 5–12 für Woche 2. Overlays und Audio werden mitkopiert; die Herkunft wird im Master protokolliert.';
$string['assetusage'] = 'Verwendet in';
$string['assetusage_none'] = 'Bisher verwendet keine andere Aktivität Seiten dieses Dokuments.';
$string['audio_file'] = 'Aufnahme oder Audiodatei';
$string['audio_tts'] = 'Text-to-Speech';
$string['audiosaved'] = 'Audio gespeichert';
$string['audiosource'] = 'Audioquelle';
$string['bgcolor'] = 'Hintergrundfarbe';
$string['blankpages'] = 'Leere Arbeitsblätter';
$string['bold'] = 'Fett';
$string['buchbinder:addinstance'] = 'Neues Buchbinder-Dokument anlegen';
$string['buchbinder:edit'] = 'Publishing Studio verwenden';
$string['buchbinder:harvest'] = 'Inhalte von Webseiten übernehmen';
$string['buchbinder:print'] = 'Eco-Print-PDFs erstellen';
$string['buchbinder:useassetbank'] = 'Seiten aus der Asset Bank übernehmen';
$string['buchbinder:view'] = 'Buchbinder-Dokumente ansehen';
$string['buchbinder:viewsolutions'] = 'Abgedeckte Lösungen sehen';
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
$string['columnhelp'] = 'Auf Smartphones zoomt ein Tippen auf diese Spalte sie auf Bildschirmbreite.';
$string['columnsfound'] = '{$a} Spalten erkannt.';
$string['confirmdeletepage'] = 'Diese Seite samt allen Overlays löschen?';
$string['copypages'] = 'Seiten übernehmen';
$string['createpdf'] = 'PDF erstellen';
$string['deleteoverlay'] = 'Löschen';
$string['derivedfrom'] = 'Dieses Dokument enthält Seiten des Masters';
$string['detectcolumns'] = 'Spalten erkennen';
$string['ecoprint'] = 'Eco-Print';
$string['ecoprint_help'] = 'Papiersparendes PDF erstellen: 2 oder 4 Seiten pro Blatt oder eine faltbare Broschüre. Der Tintensparmodus invertiert dunkle Seiten und entfernt graue Hintergründe. Abgedeckte Lösungen bleiben abgedeckt, außer bei der Lehrkraft-Version.';
$string['editorhelp'] = 'Werkzeug wählen und ein Rechteck auf der Seite aufziehen. Kästen per Ziehen verschieben, am Anfasser skalieren, mit den Pfeiltasten feinjustieren und mit Entf löschen.';
$string['editpage'] = 'Seite bearbeiten';
$string['editsource'] = 'Quelle bearbeiten';
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
$string['errorpagerange'] = 'Ungültiger Seitenbereich. Beispiel: 1-4, 7, 10-';
$string['errorpdfconversion'] = 'Das PDF konnte nicht umgewandelt werden: {$a}';
$string['fontsize'] = 'Schriftgröße';
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
$string['import'] = 'Importieren';
$string['importfiles'] = 'Dokumente importieren';
$string['importfiles_help'] = 'Unterstützt: PDF, Word (.docx), HTML, Bild-Scans (PNG, JPG, GIF, WebP, TIFF) und Comic-Archive (.cbz/.zip). Jede Seite wird zu einer Seite des Dokuments.';
$string['inksaver'] = 'Tintensparmodus';
$string['inksaver_help'] = 'Dunkle Seiten werden invertiert, Farben in Graustufen umgewandelt und helle Hintergründe entfernt.';
$string['ismaster'] = 'Als Master in der Asset Bank anbieten';
$string['ismaster_help'] = 'Lehrkräfte, die diese Aktivität bearbeiten dürfen, können Auszüge daraus in Buchbinder-Aktivitäten anderer Kurse übernehmen.';
$string['label'] = 'Beschriftung';
$string['landscape'] = 'Querformat';
$string['landscapeprotected'] = 'Geschütztes Querformat';
$string['layout_1up'] = '1 Seite pro Blatt';
$string['layout_2up'] = '2 Seiten pro Blatt';
$string['layout_4up'] = '4 Seiten pro Blatt';
$string['layout_booklet'] = 'Broschüre (beidseitig über die kurze Kante drucken, falten)';
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
$string['nocitation'] = 'Kein Zitat angezeigt';
$string['nocolumnsfound'] = 'Kein mehrspaltiges Layout erkannt.';
$string['noglossaries'] = 'In diesem Kurs gibt es kein Glossar.';
$string['nomasters'] = 'Keine Master-Dokumente verfügbar. Eine Buchbinder-Aktivität in den Veröffentlichungseinstellungen als Master markieren.';
$string['nopages'] = 'Dieses Dokument hat noch keine Seiten.';
$string['nosources'] = 'Noch nichts importiert.';
$string['numberofpages'] = 'Anzahl Seiten';
$string['overlay_audio'] = 'Audio-Trigger';
$string['overlay_column'] = 'Spalte';
$string['overlay_glossary'] = 'Glossarbegriff';
$string['overlay_mask'] = 'Abdeckung';
$string['overlay_reflow'] = 'Textblock';
$string['overlay_textbox'] = 'Überschreiben';
$string['pagecontent'] = 'Inhalt';
$string['pagerange'] = 'Veröffentlichte Seiten';
$string['pagerange_help'] = 'Leer lassen, um alle Seiten zu veröffentlichen. Beispiele: „5-12“ oder „1-3, 8, 10-“.';
$string['pages'] = 'Seiten';
$string['pagesimported'] = '{$a} Seite(n) hinzugefügt.';
$string['pagex'] = 'Seite {$a}';
$string['pluginadministration'] = 'Buchbinder-Administration';
$string['pluginname'] = 'Buchbinder';
$string['preview'] = 'Vorschau';
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
$string['snippetharvested'] = 'Der Ausschnitt wurde als neue Seite angelegt. Hier prüfen und bearbeiten.';
$string['source'] = 'Quelle';
$string['sourceaccessed'] = 'Abgerufen am';
$string['sourceauthor'] = 'Autor/in';
$string['sourcemeta'] = 'Quelle (Zitat)';
$string['sourcetitle'] = 'Titel';
$string['sourcetype'] = 'Art';
$string['sourcetype_blank'] = 'Leeres Arbeitsblatt';
$string['sourcetype_cbz'] = 'Comic-Archiv';
$string['sourcetype_clipboard'] = 'Zwischenablage';
$string['sourcetype_docx'] = 'Word';
$string['sourcetype_html'] = 'HTML';
$string['sourcetype_image'] = 'Bild-Scan';
$string['sourcetype_pdf'] = 'PDF';
$string['sourcetype_web'] = 'Web-Ausschnitt';
$string['sourceurl'] = 'URL';
$string['spread_left'] = 'Linke Seite';
$string['spread_right'] = 'Rechte Seite';
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
$string['textview'] = 'Textansicht';
$string['tool_select'] = 'Auswählen';
$string['toolbar'] = 'Dokumentwerkzeuge';
$string['tools'] = 'Werkzeuge';
$string['ttslang'] = 'Sprache (z. B. de-DE, fr-FR)';
$string['ttstext'] = 'Vorzulesender Text';
$string['unlinkspread'] = 'Doppelseite lösen';
$string['unprotect'] = 'Schutz aufheben';
$string['uploadaudio'] = 'Audiodatei hochladen';
$string['websnippet'] = 'Web-Ausschnitt';
$string['withsolutions'] = 'Lehrkraft-Version mit Lösungen';
