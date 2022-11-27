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
 * Page module version information
 *
 * @package mod_customform
 * @copyright  2022 tim st clair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/customform/lib.php');
require_once($CFG->dirroot.'/mod/customform/locallib.php');
require_once($CFG->libdir.'/completionlib.php');

$id      = optional_param('id', 0,PARAM_INT); // Course Module ID
$cmid = optional_param('cmid',0,PARAM_INT); // course module id

if ($cmid > 0) $id = $cmid;

if (!$cm = get_coursemodule_from_id('customform', $id)) {
    print_error('invalidcoursemodule');
}
$customform = $DB->get_record('customform', array('id'=>$cm->instance), '*', MUST_EXIST);

$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

$options = [];

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/customform:view', $context);

// Completion and trigger events.
customform_view($customform, $course, $cm, $context);

$PAGE->set_url('/mod/customform/view.php', array('id' => $cm->id));

$PAGE->set_title($course->shortname.': '.$customform->name);
$PAGE->set_heading($customform->name);
$PAGE->set_activity_record($customform);

echo $OUTPUT->header();

// Display any activity information (eg completion requirements / dates).
$cminfo = cm_info::create($cm);
$completiondetails = \core_completion\cm_completion_details::get_instance($cminfo, $USER->id);
$activitydates = \core\activity_dates::get_dates_for_module($cminfo, $USER->id);
echo $OUTPUT->activity_information($cminfo, $completiondetails, $activitydates);

if (!empty($options['printintro'])) {
    if (trim(strip_tags($customform->intro))) {
        echo $OUTPUT->box_start('mod_introbox', 'pageintro');
        echo format_module_intro('customform', $customform, $cm->id);
        echo $OUTPUT->box_end();
    }
}

$submitted = false;

$args = array(
    'cm' => $cm,
    'customform' => $customform,
    'modcontext' => $context
);
$viewform = new mod_customform_view_form(null, $args);

// ($viewform->is_cancelled())
if ($data = $viewform->get_data()) {

    $data->sesskey = sesskey(); // get_data just removed it, but we want it
    $submitted = customform_submit_data($customform->url, $data);

    $feedback = file_rewrite_pluginfile_urls($customform->feedback, 'pluginfile.php', $context->id, 'mod_customform', 'feedback', 0);
    $formatoptions = new stdClass;
    $formatoptions->noclean = true;
    $formatoptions->overflowdiv = true;
    $formatoptions->context = $context;
    $feedback = format_text($feedback, $customform->feedbackformat, $formatoptions);

    if ($feedback && $submitted) {
        echo $OUTPUT->box($feedback, "generalbox customfield-feedback");
    }

} else {

    $viewform->display();

}

echo $OUTPUT->footer();
