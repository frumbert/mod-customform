<?php

// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * File containing the form definition to post in the forum.
 *
 * @package   mod_customform
 * @copyright 2022 tim st clair
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/formslib.php');

/**
 * Class to view customfields.
 *
 * @package   mod_customform
 * @copyright 2022 tim st clair
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_customform_view_form extends moodleform {

   /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        $mform =& $this->_form;

        $cm = $this->_customdata['cm'];
        $customform = $this->_customdata['customform'];
        $modcontext = $this->_customdata['modcontext'];
        $category = $customform->category; // the category inside the form definition to display

        // Add custom fields to the form.
        $handler = mod_customform\customfield\mod_handler::create();
        $handler->set_parent_context($modcontext);
        $handler->instance_form_definition_for_category($mform, $cm->id, $category);

        $mform->addElement('hidden', 'cmid', $cm->id);
        $mform->setType('cmid', PARAM_INT);
        $mform->disable_form_change_checker();

        $mform->addElement('submit', 'submitbutton', get_string('submit'));

        // We don't store/populate instance data: Let the form just be its default build
        // $handler->instance_form_before_set_data($customform);
        // $this->set_data($customform);

    }

    /**
     * Fill in the current page data for this instance.
     */
    function definition_after_data() {
        global $USER, $COURSE;

        $mform = $this->_form;
        $instid = $mform->getElementValue('cmid');

        // Tweak the form with values provided by custom fields in use.
        $handler  = mod_customform\customfield\mod_handler::create();
        $handler->instance_form_definition_after_data($mform, empty($instid) ? 0 : $instid);
    }


    /**
     * Form validation
     *
     * @param array $data data from the form.
     * @param array $files files uploaded.
     * @return array of errors.
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Add the custom fields validation.
        $handler = mod_customform\customfield\mod_handler::create();
        $errors  = array_merge($errors, $handler->instance_form_validation($data, $files));

        return $errors;
    }
}
