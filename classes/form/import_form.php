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

namespace mod_buchbinder\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Upload of PDF, Word, HTML, image scans and comic archives.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $maxbytes = $this->_customdata['maxbytes'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'tab', 'import');
        $mform->setType('tab', PARAM_ALPHA);

        $mform->addElement('header', 'importheader', get_string('importfiles', 'mod_buchbinder'));
        $mform->addElement('filemanager', 'files', get_string('files'), null, [
            'subdirs' => 0,
            'maxbytes' => $maxbytes,
            'maxfiles' => 20,
            'accepted_types' => \mod_buchbinder\local\importer::ACCEPTED,
        ]);
        $mform->addHelpButton('files', 'importfiles', 'mod_buchbinder');
        $mform->addRule('files', null, 'required');

        $mform->addElement('advcheckbox', 'startright', get_string('startright', 'mod_buchbinder'));
        $mform->setDefault('startright', 1);
        $mform->addHelpButton('startright', 'startright', 'mod_buchbinder');
        $mform->addElement('advcheckbox', 'chop', get_string('cleanup_chop', 'mod_buchbinder'));
        $mform->setDefault('chop', 1);
        $mform->addElement('advcheckbox', 'deskew', get_string('cleanup_deskew', 'mod_buchbinder'));
        $mform->addElement('advcheckbox', 'shadow', get_string('cleanup_shadow', 'mod_buchbinder'));
        $mform->addElement('advcheckbox', 'split', get_string('cleanup_split', 'mod_buchbinder'));
        $mform->addHelpButton('split', 'cleanup_split', 'mod_buchbinder');

        $mform->addElement('header', 'sourceheader', get_string('sourcemeta', 'mod_buchbinder'));
        $mform->setExpanded('sourceheader', false);
        $mform->addElement('text', 'title', get_string('sourcetitle', 'mod_buchbinder'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addElement('text', 'author', get_string('sourceauthor', 'mod_buchbinder'), ['size' => 60]);
        $mform->setType('author', PARAM_TEXT);
        $mform->addElement('text', 'url', get_string('sourceurl', 'mod_buchbinder'), ['size' => 60]);
        $mform->setType('url', PARAM_URL);

        $this->add_action_buttons(false, get_string('import', 'mod_buchbinder'));
    }
}
