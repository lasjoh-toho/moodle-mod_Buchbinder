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
 * Content harvester: fetch a snippet from a web page.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class web_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'tab', 'import');
        $mform->setType('tab', PARAM_ALPHA);
        $mform->addElement('hidden', 'form', 'web');
        $mform->setType('form', PARAM_ALPHA);

        $mform->addElement('header', 'webheader', get_string('websnippet', 'mod_buchbinder'));
        $mform->addElement('text', 'url', get_string('sourceurl', 'mod_buchbinder'), ['size' => 60]);
        $mform->setType('url', PARAM_URL);
        $mform->addRule('url', null, 'required', null, 'client');
        $mform->addElement('text', 'selector', get_string('selector', 'mod_buchbinder'), ['size' => 30]);
        $mform->setType('selector', PARAM_TEXT);
        $mform->addHelpButton('selector', 'selector', 'mod_buchbinder');
        $mform->addElement('select', 'pagestyle', get_string('pagestyle', 'mod_buchbinder'), [
            'standard' => get_string('pagestyle_standard', 'mod_buchbinder'),
            'tufte' => get_string('pagestyle_tufte', 'mod_buchbinder'),
        ]);
        $mform->addHelpButton('pagestyle', 'pagestyle', 'mod_buchbinder');
        $mform->addElement('text', 'author', get_string('sourceauthor', 'mod_buchbinder'), ['size' => 60]);
        $mform->setType('author', PARAM_TEXT);
        $this->add_action_buttons(false, get_string('harvest', 'mod_buchbinder'));
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
        if (!preg_match('#^https?://#i', $data['url'] ?? '')) {
            $errors['url'] = get_string('invalidurl', 'error');
        }
        return $errors;
    }
}
