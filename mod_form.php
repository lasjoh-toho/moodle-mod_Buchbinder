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
 * Activity settings form.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Activity settings form.
 */
class mod_buchbinder_mod_form extends moodleform_mod {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'publishing', get_string('publishing', 'mod_buchbinder'));
        $mform->addElement('text', 'pagerange', get_string('pagerange', 'mod_buchbinder'), ['size' => 20]);
        $mform->setType('pagerange', PARAM_TEXT);
        $mform->addHelpButton('pagerange', 'pagerange', 'mod_buchbinder');

        $mform->addElement('advcheckbox', 'enablereflow', get_string('enablereflow', 'mod_buchbinder'));
        $mform->setDefault('enablereflow', 1);
        $mform->addHelpButton('enablereflow', 'enablereflow', 'mod_buchbinder');

        $mform->addElement('advcheckbox', 'enableprint', get_string('enableprint', 'mod_buchbinder'));
        $mform->setDefault('enableprint', 1);

        $mform->addElement('advcheckbox', 'ismaster', get_string('ismaster', 'mod_buchbinder'));
        $mform->addHelpButton('ismaster', 'ismaster', 'mod_buchbinder');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
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
        if (!\mod_buchbinder\local\page_range::is_valid($data['pagerange'] ?? '')) {
            $errors['pagerange'] = get_string('errorpagerange', 'mod_buchbinder');
        }
        return $errors;
    }
}
