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
 * Create empty worksheets.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blank_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'tab', 'import');
        $mform->setType('tab', PARAM_ALPHA);
        $mform->addElement('hidden', 'form', 'blank');
        $mform->setType('form', PARAM_ALPHA);

        $mform->addElement('header', 'blankheader', get_string('blankpages', 'mod_buchbinder'));
        $options = [];
        foreach (\mod_buchbinder\local\blank_page::TEMPLATES as $template) {
            $options[$template] = get_string('template_' . $template, 'mod_buchbinder');
        }
        $mform->addElement('select', 'template', get_string('template', 'mod_buchbinder'), $options);
        $mform->addElement('text', 'count', get_string('numberofpages', 'mod_buchbinder'), ['size' => 3]);
        $mform->setType('count', PARAM_INT);
        $mform->setDefault('count', 1);
        $mform->addElement('advcheckbox', 'landscape', get_string('landscape', 'mod_buchbinder'));
        $this->add_action_buttons(false, get_string('addpages', 'mod_buchbinder'));
    }
}
