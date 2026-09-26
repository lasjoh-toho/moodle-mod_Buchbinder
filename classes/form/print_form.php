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
 * Eco print options.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class print_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $layouts = [];
        foreach (\mod_buchbinder\local\imposition::layouts() as $layout) {
            $layouts[$layout] = get_string('layout_' . $layout, 'mod_buchbinder');
        }
        $mform->addElement('select', 'layout', get_string('printlayout', 'mod_buchbinder'), $layouts);
        $mform->setDefault('layout', \mod_buchbinder\local\imposition::LAYOUT_2UP);
        $mform->addHelpButton('layout', 'printlayout', 'mod_buchbinder');

        $mform->addElement('text', 'range', get_string('printrange', 'mod_buchbinder'), ['size' => 20]);
        $mform->setType('range', PARAM_TEXT);
        $mform->addHelpButton('range', 'printrange', 'mod_buchbinder');

        $mform->addElement('advcheckbox', 'inksaver', get_string('inksaver', 'mod_buchbinder'));
        $mform->setDefault('inksaver', 1);
        $mform->addHelpButton('inksaver', 'inksaver', 'mod_buchbinder');

        if (!empty($this->_customdata['cansolutions'])) {
            $mform->addElement('advcheckbox', 'withsolutions', get_string('withsolutions', 'mod_buchbinder'));
        }
        $this->add_action_buttons(false, get_string('createpdf', 'mod_buchbinder'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!\mod_buchbinder\local\page_range::is_valid($data['range'] ?? '')) {
            $errors['range'] = get_string('errorpagerange', 'mod_buchbinder');
        }
        return $errors;
    }
}
