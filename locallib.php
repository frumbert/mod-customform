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
 * Private page module utility functions
 *
 * @package mod_customform
 * @copyright  2022 tim st clair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_customfield\api;

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/filelib.php");
require_once("$CFG->libdir/resourcelib.php");
require_once("$CFG->dirroot/mod/page/lib.php");

/**
 * File browsing support class
 */
class customform_content_file_info extends file_info_stored {
    public function get_parent() {
        if ($this->lf->get_filepath() === '/' and $this->lf->get_filename() === '.') {
            return $this->browser->get_file_info($this->context);
        }
        return parent::get_parent();
    }
    public function get_visible_name() {
        if ($this->lf->get_filepath() === '/' and $this->lf->get_filename() === '.') {
            return $this->topvisiblename;
        }
        return parent::get_visible_name();
    }
}

function customform_get_editor_options($context) {
    global $CFG;
    return array(
        'subdirs'=>1,
        'maxbytes'=>$CFG->maxbytes,
        'maxfiles'=>-1,
        'changeformat'=>1,
        'context'=>$context,
        'noclean'=>1,
        'trusttext'=>0
    );
}

function send_data_as_email($cm, $customform, $data) {
global $COURSE;

    $pluginconfig = get_config('customform');

    // convert MFORM post fields into HTML
    $output = [];
    $handler = mod_customform\customfield\mod_handler::create();
    $editablefields = $handler->get_editable_fields($cm->id);
    $fieldswithdata = api::get_instance_fields_data($editablefields, $cm->id);
    $formdata = customform_normalise_formdata($data);
    foreach ($fieldswithdata as $row) {
        $field = $row->get_field();
        $value = "";
        if ($customform->category == $field->get_category()->get('id')) {
            $label = $field->get('name');
            $config = $field->get('configdata');
            switch ($field->get('type')) {
                case "select":
                    $options = explode("\r\n",$config['options']);
                    $value = $options[$formdata[$field->get('shortname')]];
                    break;

                case "multiselect":
                    $options = explode("\r\n",$config['options']);
                    $values = [];
                    foreach ($formdata[$field->get('shortname')] as $item) {
                        $values[] = $options[$item];
                    }
                    $value = implode(', ', $values);
                    break;

                case "checkbox":
                    $value = ($formdata[$field->get('shortname')] == 0) ? get_string('no') : get_string('yes');
                    break;

                default: 
                    $value = $formdata[$field->get('shortname')];
            }
            $output[] = "<p><strong>{$label}</strong>: {$value}</p>";
        }
    }

    if ($pluginconfig->sendemail == '1') {
        $messagehtml = implode(PHP_EOL, $output);
        $messagetext = html_to_text($messagehtml);
        $subject = "Contact Form ({$COURSE->fullname})";

        $emailfrom = core_user::get_noreply_user();
        $emailto = core_user::get_support_user();
        if (!empty($pluginconfig->emailto)) {
            $emailto->firstname = '';
            $emailto->lastname = '';
            $emailto->email = $pluginconfig->emailto;
        }
        email_to_user($emailto, $emailfrom,
                    $subject,
                    $messagetext, $messagehtml);
    }
}

function customform_normalise_formdata($data) {
    $pairs = [];
    foreach ((array)$data as $key => $value) {
        $k = str_replace('customfield_','',$key,$count);
        $v = $value;
        if (strpos($k,"_editor")!==false) {
            $k = str_replace('_editor','',$k);
            $v = $value['text'];
        }
        $pairs[$k] = $v;
    }
    return $pairs;
}



function customform_submit_data($url, $data) {

    // $data_string = json_encode($data, JSON_NUMERIC_CHECK);
    $data_string = customform_normalise_formdata($data);

    if (empty($url)) return;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data_string)); // $entityBody = file_get_contents('php://input');
    // curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    //     'Content-Type: multipart/form-data',
    //     //'Content-Length: ' . strlen($data_string))
    // ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // not listening or recording result
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_exec($ch);

    return true;

}