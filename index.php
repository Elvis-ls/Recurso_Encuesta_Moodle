<?php

    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id', PARAM_INT);    // Course Module ID

    $PAGE->set_url('/mod/coursesat/index.php', array('id'=>$id));

    if (!$course = $DB->get_record('course', array('id'=>$id))) {
        throw new \moodle_exception('invalidcourseid');
    }

    require_course_login($course);
    $PAGE->set_pagelayout('incourse');

    $params = array(
        'context' => context_course::instance($course->id),
        'courseid' => $course->id
    );
    $event = \mod_coursesat\event\course_module_instance_list_viewed::create($params);
    $event->trigger();

    $strcoursesats = get_string("modulenameplural", "coursesat");
    $strname = get_string("name");
    $strstatus = get_string("status");
    $strdone  = get_string("done", "coursesat");
    $strnotdone  = get_string("notdone", "coursesat");

    $PAGE->navbar->add($strcoursesats);
    $PAGE->set_title($strcoursesats);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    if (!$PAGE->has_secondary_navigation()) {
        echo $OUTPUT->heading($strcoursesats);
    }

    if (! $coursesats = get_all_instances_in_course("coursesat", $course)) {
        notice(get_string('thereareno', 'moodle', $strcoursesats), "../../course/view.php?id=$course->id");
    }

    $usesections = course_format_uses_sections($course->format);

    $table = new html_table();

    if ($usesections) {
        $strsectionname = get_string('sectionname', 'format_'.$course->format);
        $table->head  = array ($strsectionname, $strname, $strstatus);
    } else {
        $table->head  = array ($strname, $strstatus);
    }

    $currentsection = '';

    foreach ($coursesats as $coursesat) {
        if (isloggedin() and coursesat_already_done($coursesat->id, $USER->id)) {
            $ss = $strdone;
        } else {
            $ss = $strnotdone;
        }
        $printsection = "";
        if ($usesections) {
            if ($coursesat->section !== $currentsection) {
                if ($coursesat->section) {
                    $printsection = get_section_name($course, $coursesat->section);
                }
                if ($currentsection !== "") {
                    $table->data[] = 'hr';
                }
                $currentsection = $coursesat->section;
            }
        }
        //Calculate the href
        if (!$coursesat->visible) {
            //Show dimmed if the mod is hidden
            $tt_href = "<a class=\"dimmed\" href=\"view.php?id=$coursesat->coursemodule\">".format_string($coursesat->name,true)."</a>";
        } else {
            //Show normal if the mod is visible
            $tt_href = "<a href=\"view.php?id=$coursesat->coursemodule\">".format_string($coursesat->name,true)."</a>";
        }

        if ($usesections) {
            $table->data[] = array ($printsection, $tt_href, "<a href=\"view.php?id=$coursesat->coursemodule\">$ss</a>");
        } else {
            $table->data[] = array ($tt_href, "<a href=\"view.php?id=$coursesat->coursemodule\">$ss</a>");
        }
    }

    echo "<br />";
    echo html_writer::table($table);
    echo $OUTPUT->footer();


