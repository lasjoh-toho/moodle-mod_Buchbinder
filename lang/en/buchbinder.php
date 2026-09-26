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
 * English strings for mod_buchbinder.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addpage'] = 'Page';
$string['addpages'] = 'Add pages';
$string['align'] = 'Alignment';
$string['align_center'] = 'Centre';
$string['align_left'] = 'Left';
$string['align_right'] = 'Right';
$string['alignspread'] = 'Realign';
$string['allpages'] = 'All pages';
$string['alttext'] = 'Alternative text';
$string['apply'] = 'Apply';
$string['assetbank'] = 'Asset bank';
$string['assetbank_help'] = 'Copy pages of a master document into this activity, e.g. only pages 5-12 for week 2. Overlays and audio are copied too; the origin is tracked in the master.';
$string['assetusage'] = 'Used in';
$string['assetusage_none'] = 'No other activity uses pages of this document yet.';
$string['audio_file'] = 'Recording or audio file';
$string['audio_tts'] = 'Text to speech';
$string['audiosaved'] = 'Audio saved';
$string['audiosource'] = 'Audio source';
$string['backgroundimport'] = 'Import in the background';
$string['backgroundimport_desc'] = 'Imports are processed by cron (ad-hoc task), so large PDFs and scan batches do not hit time limits of the web server. Requires a regularly running cron.';
$string['backtocourse'] = 'Back to the course';
$string['bgcolor'] = 'Background colour';
$string['blankafter'] = 'Insert blank page after';
$string['blankbefore'] = 'Insert blank page before';
$string['blankpages'] = 'Empty worksheets';
$string['bold'] = 'Bold';
$string['buchbinder:addinstance'] = 'Add a new Buchbinder document';
$string['buchbinder:edit'] = 'Use the publishing studio';
$string['buchbinder:harvest'] = 'Harvest content from web pages';
$string['buchbinder:print'] = 'Create eco print PDFs';
$string['buchbinder:useassetbank'] = 'Copy pages from the asset bank';
$string['buchbinder:view'] = 'View Buchbinder documents';
$string['buchbinder:viewsolutions'] = 'See masked solutions';
$string['bulletlist'] = 'Bulleted list';
$string['callout_caution'] = 'Caution';
$string['callout_important'] = 'Important';
$string['callout_note'] = 'Note';
$string['callout_tip'] = 'Tip';
$string['callout_warning'] = 'Warning';
$string['caption'] = 'Caption';
$string['citation'] = 'Citation';
$string['citationaccessed'] = 'accessed {$a}';
$string['cleanup_chop'] = 'Chop black scanner borders';
$string['cleanup_chop_short'] = 'Borders';
$string['cleanup_deskew'] = 'Straighten skewed scans';
$string['cleanup_deskew_short'] = 'Deskew';
$string['cleanup_shadow'] = 'Remove shadows and yellowing';
$string['cleanup_shadow_short'] = 'Shadow';
$string['cleanup_split'] = 'Split scanned double pages';
$string['cleanup_split_help'] = 'Landscape scans are split at the book gutter into a left and a right page. Both halves stay linked as a double page, so they are always moved together and shown side by side. Landscape pages without a gutter (wide figures, maps, tables) are kept as protected landscape pages.';
$string['cleanup_split_short'] = 'Split';
$string['cleanupall'] = 'Optimise all scanned pages';
$string['cleanupwarning'] = 'Run the cleanup before adding overlays: splitting a page removes its overlays.';
$string['clipboardsnippet'] = 'Paste from clipboard';
$string['clipboardsnippet_help'] = 'Paste text, tables and images copied from a web page or document. Please record where the content comes from – the citation is shown below the page.';
$string['clipcreated'] = 'Clip saved. Insert it into a composed page with {$a}';
$string['clips'] = 'Clips';
$string['clips_help'] = 'Clips are regions cut out of imported pages. Create them with the "Clip" tool in';
$string['columnhelp'] = 'On smartphones a tap on this column zooms it to the screen width.';
$string['columnsfound'] = '{$a} columns detected.';
$string['confirmdeletepage'] = 'Delete this page including all overlays?';
$string['copypages'] = 'Copy pages';
$string['createpdf'] = 'Create PDF';
$string['deleteoverlay'] = 'Delete';
$string['deletepage'] = 'Delete page';
$string['deletesource'] = 'Delete source';
$string['deletesource_confirm'] = 'Delete the source "{$a->title}"? {$a->pages} page(s) were imported from it.';
$string['deletesource_keeppages'] = 'Delete only the source, keep the pages';
$string['deletesource_withpages'] = 'Delete the source and its {$a} page(s)';
$string['derivedfrom'] = 'This document contains pages of the master';
$string['desk'] = 'Layout desk';
$string['detectcolumns'] = 'Detect columns';
$string['dismiss'] = 'Remove from list';
$string['ecoprint'] = 'Eco print';
$string['ecoprint_help'] = 'Create a paper saving PDF: 2 or 4 pages per sheet or a folded booklet. The ink saver inverts dark pages and removes grey backgrounds. Masked solutions are always covered unless you choose the teacher copy.';
$string['editlayoutpage'] = 'Edit composed page';
$string['editorhelp'] = 'Choose a tool and drag a rectangle on the page. Move boxes by dragging, resize them with the handle, fine-tune with the arrow keys and delete them with the Delete key.';
$string['editpage'] = 'Edit page';
$string['editsource'] = 'Edit source';
$string['edittext'] = 'Edit text';
$string['enableharvester'] = 'Enable content harvester proxy';
$string['enableharvester_desc'] = 'Allows teachers to fetch text, tables and images from web pages through the Moodle server. Requests use the site proxy and respect the "cURL blocked hosts" setting.';
$string['enableprint'] = 'Allow eco print for learners';
$string['enablereflow'] = 'Offer text view on smartphones';
$string['enablereflow_help'] = 'Text blocks marked in the studio are shown as single column, accessible text.';
$string['erroraudiotype'] = 'This is not a supported audio file.';
$string['errorfiletoolarge'] = 'The file is too large (maximum: {$a}).';
$string['errorharvest'] = 'The web page could not be fetched ({$a}).';
$string['errorharvestempty'] = 'No content found on the web page.';
$string['errorimage'] = 'The image could not be read.';
$string['errorimportformat'] = 'This file format is not supported.';
$string['errornoghostscript'] = 'Ghostscript is required to import PDF files. Please ask the administrator to configure the path to Ghostscript (pathtogs).';
$string['errornoimagick'] = 'TIFF import requires the PHP imagick extension.';
$string['errornothtml'] = 'Only text pages can be edited here.';
$string['errornotimage'] = 'Clips can only be cut out of image pages (scans, PDF pages). Choose an image page for the clip tool.';
$string['errornotlayout'] = 'This page is not a composed page.';
$string['errorpagerange'] = 'Invalid page range. Example: 1-4, 7, 10-';
$string['errorpdfconversion'] = 'The PDF could not be converted: {$a}';
$string['fillerpage'] = 'Automatic blank page: keeps chained and pinned pages on their side. It disappears by itself when it is no longer needed; place content on it to keep it.';
$string['firstpageright'] = 'First page is a right page';
$string['firstpageright_help'] = 'The document is laid out as a booklet. Like in a printed book, page 1 is a right page and the following pages form double pages (2|3, 4|5, …). Scanned double pages are placed so that their left half lies on a left page; blank pages are inserted where necessary.';
$string['fit_contain'] = 'Fit (whole image)';
$string['fit_cover'] = 'Fill frame (crop)';
$string['fontscale'] = 'Text size (factor)';
$string['fontsize'] = 'Font size';
$string['frameborder'] = 'Frame border';
$string['framestyle'] = 'Formatting';
$string['framestyle_sidenote'] = 'Margin note';
$string['framestyle_standard'] = 'Standard';
$string['glossary'] = 'Glossary';
$string['glossarynotfound'] = 'There is no entry for this term in the glossary yet.';
$string['glossaryopen'] = 'Open in glossary';
$string['harvest'] = 'Fetch snippet';
$string['harvester'] = 'Content harvester';
$string['harvesterdisabled'] = 'The content harvester is disabled on this site. You can still paste content from the clipboard.';
$string['harvestertimeout'] = 'Timeout';
$string['harvestertimeout_desc'] = 'Seconds to wait for a web page.';
$string['harvestimages'] = 'Copy images';
$string['harvestimages_desc'] = 'Store images of harvested snippets in Moodle instead of dropping them.';
$string['imagefit'] = 'Image in frame';
$string['imagesaved'] = 'Image saved';
$string['import'] = 'Import';
$string['importfiles'] = 'Import documents';
$string['importfiles_help'] = 'Supported: PDF, Word (.docx), HTML, Markdown (.md), image scans (PNG, JPG, GIF, WebP, TIFF) and comic archives (.cbz/.zip). Word, HTML and Markdown are placed as movable text and image frames on booklet pages; scans and PDFs become image pages.';
$string['importjobs'] = 'Imports';
$string['importqueued'] = 'The import runs in the background. Pages appear as soon as it is finished.';
$string['inksaver'] = 'Ink saver';
$string['inksaver_help'] = 'Dark pages are inverted, colours converted to greyscale and light backgrounds removed.';
$string['ismaster'] = 'Offer as master in the asset bank';
$string['ismaster_help'] = 'Teachers who can edit this activity can copy excerpts of it into Buchbinder activities of other courses.';
$string['italic'] = 'Italic';
$string['jobstale'] = 'This import is waiting for more than 10 minutes. Is cron running on this site?';
$string['jobstatus_done'] = 'Done';
$string['jobstatus_failed'] = 'Failed';
$string['jobstatus_queued'] = 'Queued';
$string['jobstatus_running'] = 'Running';
$string['label'] = 'Label';
$string['landscape'] = 'Landscape';
$string['landscapeprotected'] = 'Protected landscape';
$string['layout_1up'] = '1 page per sheet';
$string['layout_2up'] = '2 pages per sheet';
$string['layout_4up'] = '4 pages per sheet';
$string['layout_booklet'] = 'Booklet (print double-sided, flip on short edge, fold)';
$string['layoutissues'] = '{$a} page(s) are not on their side.';
$string['layoutpage'] = 'Composed page';
$string['layoutsaved'] = '{$a} composed page(s) saved.';
$string['layoutsource'] = 'Content';
$string['layoutsource_help'] = 'Write the page in Markdown with the layout blocks of Quarto: columns, callouts, images with width and page breaks. The box on the right lists the syntax. A page break creates a further page.';
$string['layoutsyntax'] = 'Syntax';
$string['layoutsyntax_help'] = '# Heading
**bold**, *italic*, - list, 1. list
| A | B |
|---|---|
| table | cell |

::: {.columns}
::: {.column width="40%"}
![Caption](ausschnitt-1.png)
:::
::: {.column width="60%"}
Text beside the picture
:::
:::

::: {.callout-note}
## Title
Note (also: tip, warning,
important, caution)
:::

::: {.lines n=6}
Writing lines for answers
:::

::: {.box}
Framed box
:::

![](ausschnitt-2.png){width=50%}

{{< pagebreak >}}';
$string['layouttemplate_blank'] = 'Empty page';
$string['layouttemplate_blank_desc'] = 'Start with a heading and text.';
$string['layouttemplate_blank_source'] = '# Title

Text
';
$string['layouttemplate_imagetext'] = 'Picture and text';
$string['layouttemplate_imagetext_desc'] = 'A clip from an imported page beside explanations.';
$string['layouttemplate_imagetext_source'] = '# Title

::: {.columns}
::: {.column width="40%"}
![Caption](ausschnitt-1.png)
:::
::: {.column width="60%"}
Explanation of the picture.

::: {.callout-tip}
## Look closely
What do you notice?
:::
:::
:::
';
$string['layouttemplate_twocolumns'] = 'Two columns';
$string['layouttemplate_twocolumns_desc'] = 'Text in two equal columns.';
$string['layouttemplate_twocolumns_source'] = '# Title

::: {.columns}
::: {.column width="50%"}
## Left

Text
:::
::: {.column width="50%"}
## Right

Text
:::
:::
';
$string['layouttemplate_vocabulary'] = 'Vocabulary list';
$string['layouttemplate_vocabulary_desc'] = 'Table with words, translations and examples.';
$string['layouttemplate_vocabulary_source'] = '# Vocabulary

| Word | Translation | Example |
|---|---|---|
| bonjour | hello | Bonjour, Marie ! |
|  |  |  |
|  |  |  |
';
$string['layouttemplate_worksheet'] = 'Worksheet';
$string['layouttemplate_worksheet_desc'] = 'Title, tasks with instructions and writing lines.';
$string['layouttemplate_worksheet_source'] = '# Worksheet

Name: ______________________  Date: ____________

::: {.callout-note}
## Task 1
Read the text and answer the questions.
:::

::: {.lines n=5}
:::

::: {.callout-note}
## Task 2
Describe the picture.
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
$string['linkedpages'] = 'Chained double page';
$string['linkspread'] = 'Link with next page';
$string['mask_black'] = 'Blackout';
$string['mask_white'] = 'Whiteout';
$string['maskstyle'] = 'Style';
$string['matchbackground'] = 'Match paper colour';
$string['maxbytes'] = 'Maximum file size';
$string['maxbytes_desc'] = 'Maximum size of imported documents and audio files.';
$string['maxpages'] = 'Maximum pages per import';
$string['maxpages_desc'] = 'Longer documents are truncated.';
$string['menu_linknext'] = 'Chain with next page (double page)';
$string['menu_pinleft'] = 'Must be a left page';
$string['menu_pinright'] = 'Must be a right page';
$string['menu_unlink'] = 'Remove chain';
$string['menu_unpin'] = 'Release side';
$string['modulename'] = 'Buchbinder';
$string['modulename_help'] = 'Buchbinder turns PDFs, scans, Word files and web content into modular, interactive learning material: clean up scans, write on pages, add invisible audio triggers, glossary terms and solution masks, offer a text view for smartphones and print paper saving booklets.';
$string['modulenameplural'] = 'Buchbinder documents';
$string['movepanel'] = 'Move panel to the other side';
$string['newframetext'] = 'Text';
$string['newlayoutpage'] = 'New composed page';
$string['nextspread'] = 'Next double page';
$string['nobackground'] = 'Transparent background';
$string['nocitation'] = 'No citation shown';
$string['nocolumnsfound'] = 'No multi column layout detected.';
$string['noglossaries'] = 'There is no glossary in this course.';
$string['nomasters'] = 'No master documents available. Mark a Buchbinder activity as master in its publishing settings.';
$string['nopages'] = 'This document has no pages yet.';
$string['nosources'] = 'Nothing imported yet.';
$string['numberedlist'] = 'Numbered list';
$string['numberofpages'] = 'Number of pages';
$string['overlay_audio'] = 'Audio trigger';
$string['overlay_column'] = 'Column';
$string['overlay_glossary'] = 'Glossary term';
$string['overlay_imageframe'] = 'Image frame';
$string['overlay_mask'] = 'Mask';
$string['overlay_reflow'] = 'Text block';
$string['overlay_textbox'] = 'Type-over';
$string['overlay_textframe'] = 'Text frame';
$string['overset'] = 'The text does not fit into the frame (overset). Enlarge the frame or reduce the text size.';
$string['pagecontent'] = 'Content';
$string['pagerange'] = 'Published pages';
$string['pagerange_help'] = 'Leave empty to publish all pages. Examples: "5-12" or "1-3, 8, 10-".';
$string['pages'] = 'Pages';
$string['pagesimported'] = '{$a} page(s) added.';
$string['pagesmenu'] = 'Page properties';
$string['pagesmenuhint'] = 'Right-click a page (or press the context menu key) to chain pages or to pin a page to the left or right.';
$string['pagestyle'] = 'Formatting';
$string['pagestyle_help'] = 'Keep the formatting of the source, or set documents and web pages with a template. "Tufte" follows the books of Edward Tufte: a narrow serif text column and a wide outer margin in which footnotes, side notes and small figures are placed next to the text. On double pages and in print the wide margins always lie outside.';
$string['pagestyle_standard'] = 'Keep formatting of the source';
$string['pagestyle_tufte'] = 'Template: Tufte (wide margin with notes)';
$string['pagex'] = 'Page {$a}';
$string['paragraph'] = 'Paragraph';
$string['pinned_left'] = 'L';
$string['pinned_right'] = 'R';
$string['pinnedpage'] = 'Pinned side: this page stays on its side, blank pages are added automatically';
$string['pluginadministration'] = 'Buchbinder administration';
$string['pluginname'] = 'Buchbinder';
$string['preview'] = 'Preview';
$string['previousspread'] = 'Previous double page';
$string['printdisabled'] = 'Printing is disabled for this document.';
$string['printlayout'] = 'Layout';
$string['printlayout_help'] = '2 and 4 pages per sheet save paper. The booklet arranges the pages so that the printed stack can be folded in the middle.';
$string['printrange'] = 'Pages';
$string['printrange_help'] = 'Leave empty to print all published pages.';
$string['privacy:metadata'] = 'The Buchbinder activity stores teaching material only and no personal data.';
$string['properties'] = 'Properties';
$string['protect'] = 'Protect landscape';
$string['publishing'] = 'Publishing';
$string['rasterdpi'] = 'Resolution of rendered pages';
$string['rasterdpi_desc'] = 'DPI used to render PDF pages and empty worksheets.';
$string['record'] = 'Record';
$string['reflowrole'] = 'Type';
$string['revealable'] = 'Learners can reveal it';
$string['revealsolution'] = 'Show solution';
$string['role_h2'] = 'Heading';
$string['role_h3'] = 'Subheading';
$string['role_li'] = 'List item';
$string['role_p'] = 'Paragraph';
$string['rotate'] = 'Rotate';
$string['save'] = 'Save';
$string['saved'] = 'Saved';
$string['selector'] = 'Element';
$string['selector_help'] = 'Optional: the part of the page to copy, e.g. "article", "#content", ".infobox" or "table.data". Without an element the main article is used.';
$string['selectoverlay'] = 'Select a box on the page or draw a new one.';
$string['showcitation'] = 'Show citation below the pages';
$string['showhotspots'] = 'Show hotspots';
$string['side_left'] = 'left';
$string['side_right'] = 'right';
$string['snippetharvested'] = 'The snippet was added as a new page. Check and edit it here.';
$string['source'] = 'Source';
$string['sourceaccessed'] = 'Accessed on';
$string['sourceauthor'] = 'Author';
$string['sourcedeleted'] = 'Source deleted ({$a} page(s) removed).';
$string['sourcemeta'] = 'Source (citation)';
$string['sourcetitle'] = 'Title';
$string['sourcetype'] = 'Type';
$string['sourcetype_blank'] = 'Empty worksheet';
$string['sourcetype_cbz'] = 'Comic archive';
$string['sourcetype_clipboard'] = 'Clipboard';
$string['sourcetype_docx'] = 'Word';
$string['sourcetype_html'] = 'HTML';
$string['sourcetype_image'] = 'Image scan';
$string['sourcetype_md'] = 'Markdown';
$string['sourcetype_pdf'] = 'PDF';
$string['sourcetype_web'] = 'Web snippet';
$string['sourceurl'] = 'URL';
$string['spread_left'] = 'Left page';
$string['spread_right'] = 'Right page';
$string['spreadmisaligned'] = 'This page is not on its side (left/right).';
$string['startright'] = 'Start on a right page';
$string['startright_help'] = 'Imported documents begin on a right page like a new chapter. A blank page is inserted if necessary.';
$string['stoprecording'] = 'Stop recording';
$string['studio'] = 'Publishing studio';
$string['tab_canvas'] = 'Canvas & overlays';
$string['tab_import'] = 'Import';
$string['tab_pages'] = 'Pages & cleanup';
$string['tab_publish'] = 'Publish';
$string['tab_sources'] = 'Sources';
$string['template'] = 'Template';
$string['template_blank'] = 'Blank';
$string['template_lined'] = 'Lined';
$string['template_squared'] = 'Squared';
$string['template_staff'] = 'Music staves';
$string['term'] = 'Term';
$string['text'] = 'Text';
$string['textcolor'] = 'Text colour';
$string['textcolumns'] = 'Columns';
$string['textview'] = 'Text view';
$string['tool_clip'] = 'Clip';
$string['tool_select'] = 'Select';
$string['toolbar'] = 'Document tools';
$string['tools'] = 'Tools';
$string['ttslang'] = 'Language (e.g. de-DE, fr-FR)';
$string['ttstext'] = 'Text to speak';
$string['unlinkspread'] = 'Unlink double page';
$string['unprotect'] = 'Remove protection';
$string['uploadaudio'] = 'Upload audio file';
$string['uploadimage'] = 'Upload image';
$string['websnippet'] = 'Web snippet';
$string['withsolutions'] = 'Teacher copy with solutions';
$string['zoom'] = 'Zoom';
