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
 * Edit the citation data of a source.
 *
 * @package    mod_buchbinder
 * @copyright  2026 Buchbinder contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'title', get_string('sourcetitle', 'mod_buchbinder'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addElement('text', 'author', get_string('sourceauthor', 'mod_buchbinder'), ['size' => 60]);
        $mform->setType('author', PARAM_TEXT);
        $mform->addElement('text', 'url', get_string('sourceurl', 'mod_buchbinder'), ['size' => 60]);
        $mform->setType('url', PARAM_URL);
        $mform->addElement('date_selector', 'timeaccessed', get_string('sourceaccessed', 'mod_buchbinder'));
        $mform->addElement('advcheckbox', 'showcitation', get_string('showcitation', 'mod_buchbinder'));
        $this->add_action_buttons();
    }
}
