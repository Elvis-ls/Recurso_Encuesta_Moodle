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
 * This file is responsible for displaying the coursesat
 *
 * @package   mod_coursesat
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id = required_param('id', PARAM_INT);    // Course Module ID.

if (! $cm = get_coursemodule_from_id('coursesat', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}

$cm = cm_info::create($cm);

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    throw new \moodle_exception('coursemisconf');
}

$PAGE->set_url('/mod/coursesat/view.php', array('id' => $id));
require_login($course, false, $cm);
$context = context_module::instance($cm->id);

require_capability('mod/coursesat:participate', $context);

if (! $coursesat = $DB->get_record("coursesat", array("id" => $cm->instance))) {
    throw new \moodle_exception('invalidcoursesatid', 'coursesat');
}

if (! $template = $DB->get_record("coursesat", array("id" => $coursesat->template))) {
    throw new \moodle_exception('invalidtmptid', 'coursesat');
}

$showscales = ($template->name != 'ciqname');

// Check the coursesat hasn't already been filled out.
$coursesatalreadydone = coursesat_already_done($coursesat->id, $USER->id);
if ($coursesatalreadydone) {
    // Trigger course_module_viewed event and completion.
    coursesat_view($coursesat, $course, $cm, $context, 'graph');
} else {
    coursesat_view($coursesat, $course, $cm, $context, 'form');
}

$strcoursesat = get_string("modulename", "coursesat");
$PAGE->set_title($coursesat->name);
$PAGE->set_heading($course->fullname);
// No need to show the description if the coursesat is done and a graph page is to be shown.
if ($coursesatalreadydone && $showscales) {
    $PAGE->activityheader->set_description('');
} else {
    // If the coursesat has empty description, display the default one.
    $trimmedintro = trim($coursesat->intro);
    if (empty($trimmedintro)) {
        $tempo = $DB->get_field("coursesat", "intro", array("id" => $coursesat->template));
        $PAGE->activityheader->set_description(get_string($tempo, "coursesat"));
    }
}
$PAGE->add_body_class('limitedwidth');

echo $OUTPUT->header();

// Check to see if groups are being used in this coursesat.
if ($groupmode = groups_get_activity_groupmode($cm)) {   // Groups are being used.
    $currentgroup = groups_get_activity_group($cm);
} else {
    $currentgroup = 0;
}
$groupingid = $cm->groupingid;

if (has_capability('mod/coursesat:readresponses', $context) or ($groupmode == VISIBLEGROUPS)) {
    $currentgroup = 0;
}

if (!$cm->visible) {
    notice(get_string("activityiscurrentlyhidden"));
}

if (!is_enrolled($context)) {
    echo $OUTPUT->notification(get_string("guestsnotallowed", "coursesat"));
}

if ($coursesatalreadydone) {
    $numusers = coursesat_count_responses($coursesat->id, $currentgroup, $groupingid);
    if ($showscales) {
        // Ensure that graph.php will allow the user to see the graph.
        if (has_capability('mod/coursesat:readresponses', $context) || !$groupmode || groups_is_member($currentgroup)) {

            echo $OUTPUT->box(get_string("coursesatcompleted", "coursesat"));
            echo $OUTPUT->box(get_string("peoplecompleted", "coursesat", $numusers));

            echo '<div class="resultgraph">';
            coursesat_print_graph("id=$cm->id&amp;sid=$USER->id&amp;group=$currentgroup&amp;type=student.png");
            echo '</div>';
        } else {
            echo $OUTPUT->box(get_string("coursesatcompletednograph", "coursesat"));
            echo $OUTPUT->box(get_string("peoplecompleted", "coursesat", $numusers));
        }

    } else {

        echo $OUTPUT->spacer(array('height' => 30, 'width' => 1), true);  // Should be done with CSS instead.

        $questions = coursesat_get_questions($coursesat);
        foreach ($questions as $question) {

            if ($question->type == 0 or $question->type == 1) {
                if ($answer = coursesat_get_user_answer($coursesat->id, $question->id, $USER->id)) {
                    $table = new html_table();
                    $table->head = array(get_string($question->text, "coursesat"));
                    $table->align = array ("left");
                    $table->data[] = array(s($answer->answer1));// No html here, just plain text.
                    echo html_writer::table($table);
                    echo $OUTPUT->spacer(array('height' => 30, 'width' => 1), true);
                }
            }
        }
    }

    echo $OUTPUT->footer();
    exit;
}

echo "<form method=\"post\" action=\"save.php\" id=\"coursesatform\">";
echo '<div>';
echo "<input type=\"hidden\" name=\"id\" value=\"$id\" />";
echo "<input type=\"hidden\" name=\"sesskey\" value=\"".sesskey()."\" />";

echo '<div>'. get_string('allquestionrequireanswer', 'coursesat'). '</div>';

// Get all the major questions in order.
$questions = coursesat_get_questions($coursesat);

global $qnum;  // TODO: ugly globals hack for coursesat_print_*().
$qnum = 0;
foreach ($questions as $question) {

    if ($question->type >= 0) {

        $question = coursesat_translate_question($question);

        if ($question->multi) {
            coursesat_print_multi($question);
        } else {
            coursesat_print_single($question);
        }
    }
}

if (!is_enrolled($context)) {
    echo '</div>';
    echo "</form>";
    echo $OUTPUT->footer();
    exit;
}

$PAGE->requires->js_call_amd('mod_coursesat/validation', 'ensureRadiosChosen', array('coursesatform'));

echo '<br />';
echo '<input type="submit" class="btn btn-primary" value="'. get_string("submit"). '" />';
echo '</div>';
echo "</form>";

echo $OUTPUT->footer();
