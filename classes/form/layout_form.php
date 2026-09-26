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
 * Editing form of a composed (layout) page.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class layout_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);
        $mform->addElement('hidden', 'template');
        $mform->setType('template', PARAM_ALPHA);

        $mform->addElement(
            'textarea',
            'source',
            get_string('layoutsource', 'mod_buchbinder'),
            ['rows' => 26, 'class' => 'bb-layout-source', 'spellcheck' => 'true']
        );
        $mform->setType('source', PARAM_RAW);
        $mform->addHelpButton('source', 'layoutsource', 'mod_buchbinder');

        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('savechanges')),
            $mform->createElement('submit', 'previewbutton', get_string('preview', 'mod_buchbinder')),
            $mform->createElement('cancel'),
        ];
        $mform->registerNoSubmitButton('previewbutton');
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }
}
