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
 * Publishing settings inside the studio (excerpt, asset bank).
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class publish_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'tab', 'publish');
        $mform->setType('tab', PARAM_ALPHA);

        $mform->addElement('text', 'pagerange', get_string('pagerange', 'mod_buchbinder'), ['size' => 20]);
        $mform->setType('pagerange', PARAM_TEXT);
        $mform->addHelpButton('pagerange', 'pagerange', 'mod_buchbinder');
        $mform->addElement('advcheckbox', 'enablereflow', get_string('enablereflow', 'mod_buchbinder'));
        $mform->addElement('advcheckbox', 'enableprint', get_string('enableprint', 'mod_buchbinder'));
        $mform->addElement('advcheckbox', 'ismaster', get_string('ismaster', 'mod_buchbinder'));
        $mform->addHelpButton('ismaster', 'ismaster', 'mod_buchbinder');
        $this->add_action_buttons(false);
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
