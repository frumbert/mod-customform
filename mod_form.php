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
 * Page configuration form
 *
 * @package mod_customform
 * @copyright  2022 tim st clair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/customform/locallib.php');
require_once($CFG->libdir.'/filelib.php');

class mod_customform_mod_form extends moodleform_mod {
    function definition() {
        global $CFG, $DB;

        $mform = $this->_form;

        $config = get_config('customform');

        //-------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'48'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('editor', 'customform', get_string('feedback', 'customform'), array('rows' => 5), customform_get_editor_options($this->context));
        $mform->addRule('customform', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'url', get_string('url'), array('size'=>'48'));
        $mform->setType('url', PARAM_URL);
        $mform->addRule('url', null, 'required', null, 'client');
        $mform->addRule('url', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $handler = mod_customform\customfield\mod_handler::create();
        $categories = $handler->get_categories_with_fields();
        $options = [];
        foreach ($categories as $key => $value) {
            $options[$key] = $value->get('name');
        }

        $mform->addElement('select', 'category', get_string('category', 'customform'), $options);
        $mform->addRule('category', null, 'required', null);

        //-------------------------------------------------------
        $this->standard_coursemodule_elements();

        //-------------------------------------------------------
        $this->add_action_buttons();

        //-------------------------------------------------------
        // $mform->addElement('hidden', 'revision');
        // $mform->setType('revision', PARAM_INT);
        // $mform->setDefault('revision', 1);
    }

    /**
     * Enforce defaults here.
     *
     * @param array $defaultvalues Form defaults
     * @return void
     **/
    public function data_preprocessing(&$defaultvalues) {
        if ($this->current->instance) {
            $draftitemid = file_get_submitted_draft_itemid('customform');
            $defaultvalues['customform']['format'] = $defaultvalues['feedbackformat'];
            $defaultvalues['customform']['text']   = file_prepare_draft_area(
                                                        $draftitemid,
                                                        $this->context->id,
                                                        'mod_customform',
                                                        'feedback',
                                                        0,
                                                        customform_get_editor_options($this->context),
                                                        $defaultvalues['feedback']);
            $defaultvalues['customform']['itemid'] = $draftitemid;
        }


    }
}

