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

namespace mod_coursesat\backup;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . "/phpunit/classes/restore_date_testcase.php");

/**
 * Restore date tests.
 *
 * @package    mod_coursesat
 * @copyright  2017 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class restore_date_test extends \restore_date_testcase {

    public function test_restore_dates(): void {
        global $DB;

        list($course, $coursesat) = $this->create_course_and_module('coursesat');
        $context = \context_module::instance($coursesat->cmid);

        // Build our questions and responses array.
        $realquestions = array();
        $questions = coursesat_get_questions($coursesat);
        $i = 5;
        foreach ($questions as $q) {
            if ($q->type > 0) {
                if ($q->multi) {
                    $subquestions = coursesat_get_subquestions($q);
                    foreach ($subquestions as $sq) {
                        $key = 'q' . $sq->id;
                        $realquestions[$key] = $i % 5 + 1;
                        $i++;
                    }
                } else {
                    $key = 'q' . $q->id;
                    $realquestions[$key] = $i % 5 + 1;
                    $i++;
                }
            }
        }
        coursesat_save_answers($coursesat, $realquestions, $course, $context);
        // We do not want second differences to fail our test because of execution delays.
        $DB->set_field('coursesat_answers', 'time', $this->startdate);

        // Do backup and restore.
        $newcourseid = $this->backup_and_restore($course);
        $newcoursesat = $DB->get_record('coursesat', ['course' => $newcourseid]);
        $this->assertFieldsNotRolledForward($coursesat, $newcoursesat, ['timecreated', 'timemodified']);

        $answers = $DB->get_records('coursesat_answers', ['coursesat' => $newcoursesat->id]);
        foreach ($answers as $answer) {
            $this->assertEquals($this->startdate, $answer->time);
        }
    }
}
