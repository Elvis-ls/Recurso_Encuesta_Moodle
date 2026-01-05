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
 * This file is responsible for saving the results of a users coursesat and displaying
 * the final message.
 *
 * @package   mod_coursesat
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('lib.php');


// Make sure this is a legitimate posting

    if (!$formdata = data_submitted() or !confirm_sesskey()) {
        throw new \moodle_exception('cannotcallscript');
    }

    $id = required_param('id', PARAM_INT);    // Course Module ID

    if (! $cm = get_coursemodule_from_id('coursesat', $id)) {
        throw new \moodle_exception('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        throw new \moodle_exception('coursemisconf');
    }

    $PAGE->set_url('/mod/coursesat/save.php', array('id'=>$id));
    require_login($course, false, $cm);

    $context = context_module::instance($cm->id);
    require_capability('mod/coursesat:participate', $context);

    if (! $coursesat = $DB->get_record("coursesat", array("id"=>$cm->instance))) {
        throw new \moodle_exception('invalidcoursesatid', 'coursesat');
    }

    $strcoursesatsaved = get_string('coursesatsaved', 'coursesat');

    $PAGE->set_title($strcoursesatsaved);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($coursesat->name));

    if (coursesat_already_done($coursesat->id, $USER->id)) {
        notice(get_string("alreadysubmitted", "coursesat"), get_local_referer(false));
        exit;
    }

    coursesat_save_answers($coursesat, $formdata, $course, $context);

// Print the page and finish up.

    notice(get_string("thanksforanswers","coursesat", $USER->firstname), "$CFG->wwwroot/course/view.php?id=$course->id");

    exit;



