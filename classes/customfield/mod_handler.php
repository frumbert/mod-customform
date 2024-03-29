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
 * Course handler for custom fields
 *
 * @package     mod_customform
 * @copyright   2022 tim st clair
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_customform\customfield;

defined('MOODLE_INTERNAL') || die;

use core_customfield\api;
use core_customfield\field_controller;

/**
 * Course handler for custom fields
 *
 * @package     mod_customform
 * @copyright   2022 tim st clair
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_handler extends \core_customfield\handler {

    /**
     * @var mod_handler
     */
    static protected $singleton;

    /**
     * @var \context
     */
    protected $parentcontext;

    /** @var int Field is displayed in the course listing, visible to everybody */
    const VISIBLETOALL = 2;
    /** @var int Field is displayed in the course listing but only for teachers */
    const VISIBLETOTEACHERS = 1;
    /** @var int Field is not displayed in the course listing */
    const NOTVISIBLE = 0;

    /**
     * Returns a singleton
     *
     * @param int $itemid
     * @return \core_mod\customfield\mod_handler
     */
    public static function create(int $itemid = 0) : \core_customfield\handler {
        if (static::$singleton === null) {
            self::$singleton = new static(0);
        }
        return self::$singleton;
    }

    /**
     * Run reset code after unit tests to reset the singleton usage.
     */
    public static function reset_caches(): void {
        if (!PHPUNIT_TEST) {
            throw new \coding_exception('This feature is only intended for use in unit tests');
        }

        static::$singleton = null;
    }

    /**
     * The current user can configure custom fields on this component.
     *
     * @return bool true if the current can configure custom fields, false otherwise
     */
    public function can_configure() : bool {
        return has_capability('moodle/course:configurecustomfields', $this->get_configuration_context());
    }

    /**
     * The current user can edit custom fields on the given course.
     *
     * @param field_controller $field
     * @param int $instanceid id of the course to test edit permission
     * @return bool true if the current can edit custom fields, false otherwise
     */
    public function can_edit(field_controller $field, int $instanceid = 0) : bool {
        if ($instanceid) {
            $context = $this->get_instance_context($instanceid);
            return (!$field->get_configdata_property('locked') ||
                    has_capability('moodle/course:changelockedcustomfields', $context));
        } else {
            $context = $this->get_parent_context();
            return (!$field->get_configdata_property('locked') ||
                guess_if_creator_will_have_course_capability('moodle/course:changelockedcustomfields', $context));
        }
    }

    /**
     * The current user can view custom fields on the given course.
     *
     * @param field_controller $field
     * @param int $instanceid id of the course to test edit permission
     * @return bool true if the current can edit custom fields, false otherwise
     */
    public function can_view(field_controller $field, int $instanceid) : bool {
        $visibility = $field->get_configdata_property('visibility');
        if ($visibility == self::NOTVISIBLE) {
            return false;
        } else if ($visibility == self::VISIBLETOTEACHERS) {
            return has_capability('moodle/course:update', $this->get_instance_context($instanceid));
        } else {
            return true;
        }
    }

    /**
     * Sets parent context for the course
     *
     * This may be needed when course is being created, there is no course context but we need to check capabilities
     *
     * @param \context $context
     */
    public function set_parent_context(\context $context) {
        $this->parentcontext = $context;
    }

    /**
     * Returns the parent context for the course
     *
     * @return \context
     */
    protected function get_parent_context() : \context {
        global $PAGE;
        if ($this->parentcontext) {
            return $this->parentcontext;
        } else if ($PAGE->context && $PAGE->context instanceof \context_coursecat) {
            return $PAGE->context;
        }
        return \context_system::instance();
    }

    /**
     * Context that should be used for new categories created by this handler
     *
     * @return \context the context for configuration
     */
    public function get_configuration_context() : \context {
        return \context_system::instance();
    }

    /**
     * URL for configuration of the fields on this handler.
     *
     * @return \moodle_url The URL to configure custom fields for this component
     */
    public function get_configuration_url() : \moodle_url {
        return new \moodle_url('/mod/customform/customfield.php');
    }

    /**
     * Returns the context for the data associated with the given instanceid.
     *
     * @param int $instanceid id of the record to get the context for
     * @return \context the context for the given record
     */
    public function get_instance_context(int $instanceid = 0) : \context {
        if ($instanceid > 0) {
            return \context_module::instance($instanceid);
        } else {
            return \context_system::instance();
        }
    }

    /**
     * Allows to add custom controls to the field configuration form that will be saved in configdata
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_definition(\MoodleQuickForm $mform) {
        $mform->addElement('header', 'mod_handler_header', get_string('customfieldsettings', 'core_course'));
        $mform->setExpanded('mod_handler_header', true);

        // If field is locked.
        $mform->addElement('selectyesno', 'configdata[locked]', get_string('customfield_islocked', 'core_course'));
        $mform->addHelpButton('configdata[locked]', 'customfield_islocked', 'core_course');

        // Field data visibility.
        $visibilityoptions = [self::VISIBLETOALL => get_string('customfield_visibletoall', 'core_course'),
            self::VISIBLETOTEACHERS => get_string('customfield_visibletoteachers', 'core_course'),
            self::NOTVISIBLE => get_string('customfield_notvisible', 'core_course')];
        $mform->addElement('select', 'configdata[visibility]', get_string('customfield_visibility', 'core_course'),
            $visibilityoptions);
        $mform->addHelpButton('configdata[visibility]', 'customfield_visibility', 'core_course');
    }

    /**
     * Creates or updates custom field data.
     *
     * @param \restore_task $task
     * @param array $data
     */
    public function restore_instance_data_from_backup(\restore_task $task, array $data) {
        $courseid = $task->get_courseid();
        $context = $this->get_instance_context($courseid);
        $editablefields = $this->get_editable_fields($courseid);
        $records = api::get_instance_fields_data($editablefields, $courseid);
        $target = $task->get_target();
        $override = ($target != \backup::TARGET_CURRENT_ADDING && $target != \backup::TARGET_EXISTING_ADDING);

        foreach ($records as $d) {
            $field = $d->get_field();
            if ($field->get('shortname') === $data['shortname'] && $field->get('type') === $data['type']) {
                if (!$d->get('id') || $override) {
                    $d->set($d->datafield(), $data['value']);
                    $d->set('value', $data['value']);
                    $d->set('valueformat', $data['valueformat']);
                    $d->set('contextid', $context->id);
                    $d->save();
                }
                return;
            }
        }
    }

    /**
     * Set up page customfield/edit.php
     *
     * @param field_controller $field
     * @return string page heading
     */
    public function setup_edit_page(field_controller $field) : string {
        global $CFG, $PAGE;
        require_once($CFG->libdir.'/adminlib.php');

        $title = parent::setup_edit_page($field);
        admin_externalpage_setup('mod_customform');
        $PAGE->navbar->add($title);
        return $title;
    }

   /**
     * Adds custom fields to instance editing form
     *
     * @param \MoodleQuickForm $mform
     * @param int $instanceid id of the instance, can be null when instance is being created
     * @param int $category id of the instance, can be null when instance is being created
     */
    public function instance_form_definition_for_category(\MoodleQuickForm $mform, int $instanceid = 0, $category = 0) {

        $editablefields = $this->get_editable_fields($instanceid);
        $fieldswithdata = api::get_instance_fields_data($editablefields, $instanceid);
        foreach ($fieldswithdata as $data) {
            if ($category == $data->get_field()->get_category()->get('id')) {

                $data->instance_form_definition($mform);
                $this->replace_default_values($mform, $data);
                $field = $data->get_field()->to_record();
                if (strlen($field->description)) {
                    // Add field description.
                    $context = $this->get_configuration_context();
                    $value = file_rewrite_pluginfile_urls($field->description, 'pluginfile.php',
                        $context->id, 'core_customfield', 'description', $field->id);
                    $value = format_text($value, $field->descriptionformat, ['context' => $context]);
                    $mform->addElement('static', 'customfield_' . $field->shortname . '_static', '', $value);
                }
            }
        }
    }

    // match these form default values at RUNTIME to replace with live data (pre-populate form fields)
    private function replace_default_values(&$mform, $data) {
    global $USER, $COURSE, $CFG, $DB;
        $find = [
            'VALUE:USER:USERNAME',
            'VALUE:USER:FIRSTNAME',
            'VALUE:USER:LASTNAME',
            'VALUE:USER:EMAIL',
            'VALUE:USER:FULLNAME',
            'VALUE:USER:FULLNAMEALT',
            'VALUE:COURSE:FULLNAME',
            'VALUE:COURSE:SHORTNAME',
            'VALUE:SITE:WWWROOT',
        ];
        $replace = [
            $USER->username,
            $USER->firstname,
            $USER->lastname,
            $USER->email,
            fullname($USER, false),
            fullname($USER, true),
            $COURSE->fullname,
            $COURSE->shortname,
            $CFG->wwwroot
        ];

        $field = $data->get_field();
        $config = $field->get('configdata');
        if (!array_key_exists('defaultvalue', $config)) return;
        $default = $config['defaultvalue'];

        $value = str_replace($find,$replace,$default, $count);

        if ($count === 0 && strpos($default, 'VALUE:PREF:')!==false) {
            list(,,$prefname) = explode(':',$default);
            $value = ''; // clear out the pref-name even if it's not found
            if ($record = $DB->get_record('user_preferences',['userid'=>$USER->id,'name'=>$prefname])) {
                $value = $record->value;
            }
            $count = 1;
        }

        if ($count > 0) {
            $elementname = $data->get_form_element_name();
            $mform->setDefault($elementname, $value);
        }
    }

    /**
     * Validates the given data for custom fields, used in moodleform validation() function
     * Category is required to limit validation to just the selected category for this form instance
     * validation is initiated from $mform->get_data()
     *
     * Example:
     *   public function validation($data, $files, $category) {
     *     $errors = [];
     *     // .... check other fields.
     *     $errors = array_merge($errors, $handler->instance_form_validation($data, $files));
     *     return $errors;
     *   }
     *
     * @param array $data
     * @param array $files
     * @param int $category - the selected category for this form display
     * @return array validation errors
     */
    public function instance_form_validation(array $data, array $files, $category = 0) {
        $instanceid = empty($data['id']) ? 0 : $data['id'];
        $editablefields = $this->get_editable_fields($instanceid);
        $fields = api::get_instance_fields_data($editablefields, $instanceid);
        $errors = [];
        foreach ($fields as $formfield) {
            if ($category > 0 && $category == $formfield->get_field()->get_category()->get('id')) {
                $errors += $formfield->instance_form_validation($data, $files);
            }
        }
        return $errors;
    }

}
