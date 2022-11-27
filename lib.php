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
 * @package mod_customform
 * @copyright  2022 tim st clair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * List of features supported in Page module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function customform_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function customform_reset_userdata($data) {

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.

    return array();
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function customform_get_view_actions() {
    return array('view');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function customform_get_post_actions() {
    return array('update', 'add');
}

/**
 * Add page instance.
 * @param stdClass $data
 * @param mod_customcustomform_mod_customform $mform
 * @return int new page instance id
 */
function customform_add_instance($data, $mform = null) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    $cmid = $data->coursemodule;

    $data->timemodified = time();

    if ($mform) {
        $data->feedback       = $data->customform['text'];
        $data->feedbackformat = $data->customform['format'];
    }

    $data->id = $DB->insert_record('customform', $data);

    // we need to use context now, so we need to make sure all needed info is already in db
    $DB->set_field('course_modules', 'instance', $data->id, array('id'=>$cmid));
    $context = context_module::instance($cmid);

    if ($mform and !empty($data->customform['itemid'])) {
        $draftitemid = $data->customform['itemid'];
        $data->feedback = file_save_draft_area_files($draftitemid, $context->id, 'mod_customform', 'feedback', 0, customform_get_editor_options($context), $data->feedback);
        $DB->update_record('customform', $data);
    }

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'customform', $data->id, $completiontimeexpected);

    return $data->id;
}

/**
 * Update page instance.
 * @param object $data
 * @param object $mform
 * @return bool true
 */
function customform_update_instance($data, $mform) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    $cmid        = $data->coursemodule;
    $draftitemid = $data->customform['itemid'];

    $data->timemodified = time();
    $data->id           = $data->instance;

    $data->feedback       = $data->customform['text'];
    $data->feedbackformat = $data->customform['format'];

    $DB->update_record('customform', $data);

    $context = context_module::instance($cmid);
    if ($draftitemid) {
        $data->feedback = file_save_draft_area_files($draftitemid, $context->id, 'mod_customform', 'feedback', 0, customform_get_editor_options($context), $data->feedback);
        $DB->update_record('customform', $data);
    }

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'customform', $data->id, $completiontimeexpected);

    return true;
}

/**
 * Delete page instance.
 * @param int $id
 * @return bool true
 */
function customform_delete_instance($id) {
    global $DB;

    if (!$customform = $DB->get_record('customform', array('id'=>$id))) {
        return false;
    }

    $cm = get_coursemodule_from_instance('customform', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'customform', $id, null);

    // note: all context files are deleted automatically

    $DB->delete_records('customform', array('id'=>$customform->id));

    return true;
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 *
 * See {@link get_array_of_activities()} in course/lib.php
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info Info to customise main page display
 */
function customform_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    if (!$customform = $DB->get_record('customform', array('id'=>$coursemodule->instance),
            'id, name, feedback, feedbackformat, intro, introformat')) {
        return NULL;
    }

    $info = new cached_cm_info();
    $info->name = $customform->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('customform', $customform, $coursemodule->id, false);
    }

    return $info;
}


/**
 * Lists all browsable file areas
 *
 * @package  mod_customform
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function customform_get_file_areas($course, $cm, $context) {
    $areas = array();
    $areas['feedback'] = get_string('feedback', 'customform');
    return $areas;
}

/**
 * File browsing support for page module content area.
 *
 * @package  mod_customform
 * @category files
 * @param stdClass $browser file browser instance
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function customform_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG;

    if (!has_capability('moodle/course:managefiles', $context)) {
        // students can not peak here!
        return null;
    }

    $fs = get_file_storage();

    if ($filearea === 'feedback') {
        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_customform', 'feedback', 0, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_customform', 'feedback', 0);
            } else {
                // not found
                return null;
            }
        }
        require_once("$CFG->dirroot/mod/customform/locallib.php");
        return new customform_content_file_info($browser, $context, $storedfile, $urlbase, $areas[$filearea], true, true, true, false);
    }

    // note: customform_intro handled in file_browser automatically

    return null;
}

/**
 * Serves the page files.
 *
 * @package  mod_customform
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function customform_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);
    if (!has_capability('mod/customform:view', $context)) {
        return false;
    }

    if ($filearea !== 'feedback') {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);

}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function customform_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-customform-*'=>get_string('page-mod-customform-x', 'customform'));
    return $module_pagetype;
}

// /**
//  * Register the ability to handle drag and drop file uploads
//  * @return array containing details of the files / types the mod can handle
//  */
// function customform_dndupload_register() {
//     return array('types' => array(
//                      array('identifier' => 'text/html', 'message' => get_string('createpage', 'page')),
//                      array('identifier' => 'text', 'message' => get_string('createpage', 'page'))
//                  ));
// }

// /**
//  * Handle a file that has been uploaded
//  * @param object $uploadinfo details of the file / content that has been uploaded
//  * @return int instance id of the newly created mod
//  */
// function customform_dndupload_handle($uploadinfo) {
//     // Gather the required info.
//     $data = new stdClass();
//     $data->course = $uploadinfo->course->id;
//     $data->name = $uploadinfo->displayname;
//     $data->intro = '<p>'.$uploadinfo->displayname.'</p>';
//     $data->introformat = FORMAT_HTML;
//     if ($uploadinfo->type == 'text/html') {
//         $data->contentformat = FORMAT_HTML;
//         $data->content = clean_param($uploadinfo->content, PARAM_CLEANHTML);
//     } else {
//         $data->contentformat = FORMAT_PLAIN;
//         $data->content = clean_param($uploadinfo->content, PARAM_TEXT);
//     }
//     $data->coursemodule = $uploadinfo->coursemodule;

//     // Set the display options to the site defaults.
//     $config = get_config('page');
//     $data->display = $config->display;
//     $data->popupheight = $config->popupheight;
//     $data->popupwidth = $config->popupwidth;
//     $data->printheading = $config->printheading;
//     $data->printintro = $config->printintro;
//     $data->printlastmodified = $config->printlastmodified;

//     return customform_add_instance($data, null);
// }

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $page       page object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function customform_view($customform, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $customform->id
    );

    $event = \mod_customform\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('customform', $customform);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function customform_check_updates_since(cm_info $cm, $from, $filter = array()) {
    $updates = course_check_module_updates_since($cm, $from, array('url','category','feedback'), $filter);
    return $updates;
}

// /**
//  * This function receives a calendar event and returns the action associated with it, or null if there is none.
//  *
//  * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
//  * is not displayed on the block.
//  *
//  * @param calendar_event $event
//  * @param \core_calendar\action_factory $factory
//  * @return \core_calendar\local\event\entities\action_interface|null
//  */
// function mod_customcustomform_core_calendar_provide_event_action(calendar_event $event,
//                                                       \core_calendar\action_factory $factory, $userid = 0) {
//     global $USER;

//     if (empty($userid)) {
//         $userid = $USER->id;
//     }

//     $cm = get_fast_modinfo($event->courseid, $userid)->instances['page'][$event->instance];

//     $completion = new \completion_info($cm->get_course());

//     $completiondata = $completion->get_data($cm, false, $userid);

//     if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
//         return null;
//     }

//     return $factory->create_instance(
//         get_string('view'),
//         new \moodle_url('/mod/page/view.php', ['id' => $cm->id]),
//         1,
//         true
//     );
// }

/**
 * Given an array with a file path, it returns the itemid and the filepath for the defined filearea.
 *
 * @param  string $filearea The filearea.
 * @param  array  $args The path (the part after the filearea and before the filename).
 * @return array The itemid and the filepath inside the $args path, for the defined filearea.
 */
function mod_customcustomform_get_path_from_pluginfile(string $filearea, array $args) : array {
    // Page never has an itemid (the number represents the revision but it's not stored in database).
    array_shift($args);

    // Get the filepath.
    if (empty($args)) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    return [
        'itemid' => 0,
        'filepath' => $filepath,
    ];
}
