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
 * This file is responsible for producing the downloadable versions of a coursesat
 * module.
 *
 * @package   mod_coursesat
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once ("../../config.php");

// Check that all the parameters have been provided.

$id    = required_param('id', PARAM_INT);    // Course Module ID
$type  = optional_param('type', 'xls', PARAM_ALPHA);
$group = optional_param('group', 0, PARAM_INT);

if (! $cm = get_coursemodule_from_id('coursesat', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
    throw new \moodle_exception('coursemisconf');
}

$context = context_module::instance($cm->id);

$PAGE->set_url('/mod/coursesat/download.php', array('id'=>$id, 'type'=>$type, 'group'=>$group));

require_login($course, false, $cm);
require_capability('mod/coursesat:download', $context) ;

if (! $coursesat = $DB->get_record("coursesat", array("id"=>$cm->instance))) {
    throw new \moodle_exception('invalidcoursesatid', 'coursesat');
}

$params = array(
    'objectid' => $coursesat->id,
    'context' => $context,
    'courseid' => $course->id,
    'other' => array('type' => $type, 'groupid' => $group)
);
$event = \mod_coursesat\event\report_downloaded::create($params);
$event->trigger();

/// Check to see if groups are being used in this coursesat

$groupmode = groups_get_activity_groupmode($cm);   // Groups are being used

if ($groupmode and $group) {
    $users = get_users_by_capability($context, 'mod/coursesat:participate', '', '', '', '', $group, null, false);
} else {
    $users = get_users_by_capability($context, 'mod/coursesat:participate', '', '', '', '', '', null, false);
    $group = false;
}

// The order of the questions
$order = explode(",", $coursesat->questions);

// Get the actual questions from the database
$questions = $DB->get_records_list("coursesat_questions", "id", $order);

// Get an ordered array of questions
$orderedquestions = array();

$virtualscales = false;
foreach ($order as $qid) {
    $orderedquestions[$qid] = $questions[$qid];
    // Check if this question is using virtual scales
    if (!$virtualscales && $questions[$qid]->type < 0) {
        $virtualscales = true;
    }
}
$nestedorder = array();//will contain the subquestions attached to the main questions
$preparray = array();

foreach ($orderedquestions as $qid=>$question) {
    //$orderedquestions[$qid]->text = get_string($question->text, "coursesat");
    if (!empty($question->multi)) {
        $actualqids = explode(",", $questions[$qid]->multi);
        foreach ($actualqids as $subqid) {
            if (!empty($orderedquestions[$subqid]->type)) {
                $orderedquestions[$subqid]->type = $questions[$qid]->type;
            }
        }
    } else {
        $actualqids = array($qid);
    }
    if ($virtualscales && $questions[$qid]->type < 0) {
        $nestedorder[$qid] = $actualqids;
    } else if (!$virtualscales && $question->type >= 0) {
        $nestedorder[$qid] = $actualqids;
    } else {
        //todo andrew this was added by me. Is it correct?
        $nestedorder[$qid] = array();
    }
}

$reversednestedorder = array();
foreach ($nestedorder as $qid=>$subqidarray) {
    foreach ($subqidarray as $subqui) {
        $reversednestedorder[$subqui] = $qid;
    }
}

//need to get info on the sub-questions from the db and merge the arrays of questions
$allquestions = array_merge($questions, $DB->get_records_list("coursesat_questions", "id", array_keys($reversednestedorder)));

//array_merge() messes up the keys so reinstate them
$questions = array();
foreach($allquestions as $question) {
    $questions[$question->id] = $question;

    //while were iterating over the questions get the question text
    $questions[$question->id]->text = get_string($questions[$question->id]->text, "coursesat");
}
unset($allquestions);

// Get and collate all the results in one big array
if (! $coursesatanswers = $DB->get_records("coursesat_answers", array("coursesat"=>$coursesat->id), "time ASC")) {
    throw new \moodle_exception('cannotfindanswer', 'coursesat');
}

$results = array();

foreach ($coursesatanswers as $coursesatanswer) {
    if (!$group || isset($users[$coursesatanswer->userid])) {
        //$questionid = $reversednestedorder[$coursesatanswer->question];
        $questionid = $coursesatanswer->question;
        if (!array_key_exists($coursesatanswer->userid, $results)) {
            $results[$coursesatanswer->userid] = array('time'=>$coursesatanswer->time);
        }
        $results[$coursesatanswer->userid][$questionid]['answer1'] = $coursesatanswer->answer1;
        $results[$coursesatanswer->userid][$questionid]['answer2'] = $coursesatanswer->answer2;
    }
}

// Output the file as a valid ODS spreadsheet if required
$coursecontext = context_course::instance($course->id);
$courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));

if ($type == "ods") {
    require_once("$CFG->libdir/odslib.class.php");

/// Calculate file name
    $downloadfilename = clean_filename(strip_tags($courseshortname.' '.format_string($coursesat->name, true))).'.ods';
/// Creating a workbook
    $workbook = new MoodleODSWorkbook("-");
/// Sending HTTP headers
    $workbook->send($downloadfilename);
/// Creating the first worksheet
    $myxls = $workbook->add_worksheet(core_text::substr(strip_tags(format_string($coursesat->name,true)), 0, 31));

    $header = array("coursesatid","coursesatname","userid","firstname","lastname","email","idnumber","time", "notes");
    $col=0;
    foreach ($header as $item) {
        $myxls->write_string(0,$col++,$item);
    }

    foreach ($nestedorder as $key => $nestedquestions) {
        foreach ($nestedquestions as $key2 => $qid) {
            $question = $questions[$qid];
            if ($question->type == "0" || $question->type == "1" || $question->type == "3" || $question->type == "-1")  {
                $myxls->write_string(0,$col++,"$question->text");
            }
            if ($question->type == "2" || $question->type == "3")  {
                $myxls->write_string(0,$col++,"$question->text (preferred)");
            }
        }
    }

//      $date = $workbook->addformat();
//      $date->set_num_format('mmmm-d-yyyy h:mm:ss AM/PM'); // ?? adjust the settings to reflect the PHP format below

    $row = 0;
    foreach ($results as $user => $rest) {
        $col = 0;
        $row++;
        if (! $u = $DB->get_record("user", array("id"=>$user))) {
            throw new \moodle_exception('invaliduserid');
        }

        $myxls->write_string($row,$col++,$coursesat->id);
        $myxls->write_string($row,$col++,strip_tags(format_text($coursesat->name,true)));
        $myxls->write_string($row,$col++,$user);
        $myxls->write_string($row,$col++,$u->firstname);
        $myxls->write_string($row,$col++,$u->lastname);
        $myxls->write_string($row,$col++,$u->email);
        $myxls->write_string($row,$col++,$u->idnumber);
        $myxls->write_string($row,$col++, userdate($results[$user]["time"], "%d-%b-%Y %I:%M:%S %p") );;

        foreach ($nestedorder as $key => $nestedquestions) {
            foreach ($nestedquestions as $key2 => $qid) {
                $question = $questions[$qid];
                if ($question->type == "0" || $question->type == "1" || $question->type == "3" || $question->type == "-1")  {
                    $myxls->write_string($row,$col++, $results[$user][$qid]["answer1"] );
                }
                if ($question->type == "2" || $question->type == "3")  {
                    $myxls->write_string($row, $col++, $results[$user][$qid]["answer2"] );
                }
            }
        }
    }
    $workbook->close();

    exit;
}

// Output the file as a valid Excel spreadsheet if required

if ($type == "xls") {
    require_once("$CFG->libdir/excellib.class.php");

/// Calculate file name
    $downloadfilename = clean_filename(strip_tags($courseshortname.' '.format_string($coursesat->name,true))).'.xls';
/// Creating a workbook
    $workbook = new MoodleExcelWorkbook("-");
/// Sending HTTP headers
    $workbook->send($downloadfilename);
/// Creating the first worksheet
    $myxls = $workbook->add_worksheet(core_text::substr(strip_tags(format_string($coursesat->name,true)), 0, 31));

    $header = array("id_encuesta","nombre_encuesta","id_usuario","nombre","apellido","email","numero_identificacion","fecha_hora");
    $col=0;
    foreach ($header as $item) {
        $myxls->write_string(0,$col++,$item);
    }

    foreach ($nestedorder as $key => $nestedquestions) {
        foreach ($nestedquestions as $key2 => $qid) {
            $question = $questions[$qid];

            if ($question->type == "0" || $question->type == "1" || $question->type == "3" || $question->type == "-1")  {
                $myxls->write_string(0,$col++,"$question->text");
            }
            if ($question->type == "2" || $question->type == "3")  {
                $myxls->write_string(0,$col++,"$question->text (preferred)");
            }
        }
    }

    $row = 0;
    foreach ($results as $user => $rest) {
        $col = 0;
        $row++;
        if (! $u = $DB->get_record("user", array("id"=>$user))) {
            throw new \moodle_exception('invaliduserid');
        }
        $myxls->write_string($row,$col++,$coursesat->id);
        $myxls->write_string($row,$col++,strip_tags(format_text($coursesat->name,true)));
        $myxls->write_string($row,$col++,$user);
        $myxls->write_string($row,$col++,$u->firstname);
        $myxls->write_string($row,$col++,$u->lastname);
        $myxls->write_string($row,$col++,$u->email);
        $myxls->write_string($row,$col++,$u->idnumber);
        $myxls->write_string($row,$col++, userdate($results[$user]["time"], "%d-%b-%Y %I:%M:%S %p") );

        foreach ($nestedorder as $key => $nestedquestions) {
            foreach ($nestedquestions as $key2 => $qid) {
                $question = $questions[$qid];
                if (($question->type == "0" || $question->type == "1" || $question->type == "3" || $question->type == "-1")
                    && array_key_exists($qid, $results[$user]) ){
                $myxls->write_string($row,$col++, $results[$user][$qid]["answer1"] );
            }
                if (($question->type == "2" || $question->type == "3")
                    && array_key_exists($qid, $results[$user]) ){
                $myxls->write_string($row, $col++, $results[$user][$qid]["answer2"] );
            }
        }
    }
    }
    $workbook->close();

    exit;
}

// Otherwise, return the text file.

// Print header to force download

header("Content-Type: application/download\n");

$downloadfilename = clean_filename(strip_tags($courseshortname.' '.format_string($coursesat->name,true)));
header("Content-Disposition: attachment; filename=\"$downloadfilename.txt\"");

// Print names of all the fields

echo "id_encuesta    nombre_encuesta    id_usuario    nombre    apellido    email    numero_identificacion    fecha_hora    ";

foreach ($nestedorder as $key => $nestedquestions) {
    foreach ($nestedquestions as $key2 => $qid) {
        $question = $questions[$qid];
    if ($question->type == "0" || $question->type == "1" || $question->type == "3" || $question->type == "-1")  {
        echo "$question->text    ";
    }
    if ($question->type == "2" || $question->type == "3")  {
         echo "$question->text (preferred)    ";
    }
}
}
echo "\n";

// Print all the lines of data.
foreach ($results as $user => $rest) {
    if (! $u = $DB->get_record("user", array("id"=>$user))) {
        throw new \moodle_exception('invaliduserid');
    }
    echo $coursesat->id."\t";
    echo strip_tags(format_string($coursesat->name,true))."\t";
    echo $user."\t";
    echo $u->firstname."\t";
    echo $u->lastname."\t";
    echo $u->email."\t";
    echo $u->idnumber."\t";
    echo userdate($results[$user]["time"], "%d-%b-%Y %I:%M:%S %p")."\t";

    foreach ($nestedorder as $key => $nestedquestions) {
        foreach ($nestedquestions as $key2 => $qid) {
            $question = $questions[$qid];

            if ($question->type == "0" || $question->type == "1" || $question->type == "3" || $question->type == "-1")  {
                echo $results[$user][$qid]["answer1"]."    ";
            }
            if ($question->type == "2" || $question->type == "3")  {
                echo $results[$user][$qid]["answer2"]."    ";
            }
        }
    }
    echo "\n";
}

exit;
