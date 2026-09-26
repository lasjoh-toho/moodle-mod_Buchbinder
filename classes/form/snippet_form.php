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
 * Clipboard snippet or html page editing (with provenance data).
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class snippet_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);

        $mform->addElement(
            'editor',
            'content_editor',
            get_string('pagecontent', 'mod_buchbinder'),
            ['rows' => 20],
            $this->_customdata['editoroptions']
        );
        $mform->setType('content_editor', PARAM_RAW);
        $mform->addRule('content_editor', null, 'required');

        if (empty($this->_customdata['editing'])) {
            $mform->addElement('header', 'sourceheader', get_string('sourcemeta', 'mod_buchbinder'));
            $mform->addElement('text', 'title', get_string('sourcetitle', 'mod_buchbinder'), ['size' => 60]);
            $mform->setType('title', PARAM_TEXT);
            $mform->addElement('text', 'author', get_string('sourceauthor', 'mod_buchbinder'), ['size' => 60]);
            $mform->setType('author', PARAM_TEXT);
            $mform->addElement('text', 'url', get_string('sourceurl', 'mod_buchbinder'), ['size' => 60]);
            $mform->setType('url', PARAM_URL);
        }
        $this->add_action_buttons();
    }
}
