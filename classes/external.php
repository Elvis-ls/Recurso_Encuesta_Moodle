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
 * coursesat external API
 *
 * @package    mod_coursesat
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

use core_course\external\helper_for_get_mods_by_courses;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_external\util;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/coursesat/lib.php');

/**
 * coursesat external functions
 *
 * @package    mod_coursesat
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_coursesat_external extends external_api {

    /**
     * Describes the parameters for get_coursesats_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_coursesats_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of coursesats in a provided list of courses,
     * if no list is provided all coursesats that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of coursesats details
     * @since Moodle 3.0
     */
    public static function get_coursesats_by_courses($courseids = array()) {
        global $CFG, $USER, $DB;

        $returnedcoursesats = array();
        $warnings = array();

        $params = self::validate_parameters(self::get_coursesats_by_courses_parameters(), array('courseids' => $courseids));

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {
            list($courses, $warnings) = util::validate_courses($params['courseids'], $mycourses);

            // Get the coursesats in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $coursesats = get_all_instances_in_courses("coursesat", $courses);
            foreach ($coursesats as $coursesat) {
                $context = context_module::instance($coursesat->coursemodule);
                if (empty(trim($coursesat->intro))) {
                    $tempo = $DB->get_field("coursesat", "intro", array("id" => $coursesat->template));
                    $coursesat->intro = get_string($tempo, "coursesat");
                }

                // Entry to return.
                $coursesatdetails = helper_for_get_mods_by_courses::standard_coursemodule_element_values(
                        $coursesat, 'mod_coursesat', 'moodle/course:manageactivities', 'mod/coursesat:participate');

                if (has_capability('mod/coursesat:participate', $context)) {
                    $coursesatdetails['template']  = $coursesat->template;
                    $coursesatdetails['days']      = $coursesat->days;
                    $coursesatdetails['questions'] = $coursesat->questions;
                    $coursesatdetails['coursesatdone'] = coursesat_already_done($coursesat->id, $USER->id) ? 1 : 0;
                }

                if (has_capability('moodle/course:manageactivities', $context)) {
                    $coursesatdetails['timecreated']   = $coursesat->timecreated;
                    $coursesatdetails['timemodified']  = $coursesat->timemodified;
                }
                $returnedcoursesats[] = $coursesatdetails;
            }
        }
        $result = array();
        $result['coursesats'] = $returnedcoursesats;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_coursesats_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_coursesats_by_courses_returns() {
        return new external_single_structure(
            array(
                'coursesats' => new external_multiple_structure(
                    new external_single_structure(array_merge(
                       helper_for_get_mods_by_courses::standard_coursemodule_elements_returns(true),
                       [
                            'template' => new external_value(PARAM_INT, 'coursesat type', VALUE_OPTIONAL),
                            'days' => new external_value(PARAM_INT, 'Days', VALUE_OPTIONAL),
                            'questions' => new external_value(PARAM_RAW, 'Question ids', VALUE_OPTIONAL),
                            'coursesatdone' => new external_value(PARAM_INT, 'Did I finish the coursesat?', VALUE_OPTIONAL),
                            'timecreated' => new external_value(PARAM_INT, 'Time of creation', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'Time of last modification', VALUE_OPTIONAL),
                        ]
                    ), 'coursesats')
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function view_coursesat_parameters() {
        return new external_function_parameters(
            array(
                'coursesatid' => new external_value(PARAM_INT, 'coursesat instance id')
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $coursesatid the coursesat instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function view_coursesat($coursesatid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::view_coursesat_parameters(),
                                            array(
                                                'coursesatid' => $coursesatid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $coursesat = $DB->get_record('coursesat', array('id' => $params['coursesatid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($coursesat, 'coursesat');

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/coursesat:participate', $context);

        $viewed = coursesat_already_done($coursesat->id, $USER->id) ? 'graph' : 'form';

        // Trigger course_module_viewed event and completion.
        coursesat_view($coursesat, $course, $cm, $context, $viewed);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.0
     */
    public static function view_coursesat_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_questions_parameters() {
        return new external_function_parameters(
            array(
                'coursesatid' => new external_value(PARAM_INT, 'coursesat instance id')
            )
        );
    }

    /**
     * Get the complete list of questions for the coursesat, including subquestions.
     *
     * @param int $coursesatid the coursesat instance id
     * @return array of warnings and the question list
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function get_questions($coursesatid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_questions_parameters(),
                                            array(
                                                'coursesatid' => $coursesatid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $coursesat = $DB->get_record('coursesat', array('id' => $params['coursesatid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($coursesat, 'coursesat');

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/coursesat:participate', $context);

        $mainquestions = coursesat_get_questions($coursesat);

        foreach ($mainquestions as $question) {
            if ($question->type >= 0) {
                // Parent is used in subquestions.
                $question->parent = 0;
                $questions[] = coursesat_translate_question($question);

                // Check if the question has subquestions.
                if ($question->multi) {
                    $subquestions = coursesat_get_subquestions($question);
                    foreach ($subquestions as $sq) {
                        $sq->parent = $question->id;
                        $questions[] = coursesat_translate_question($sq);
                    }
                }
            }
        }

        $result = array();
        $result['questions'] = $questions;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.0
     */
    public static function get_questions_returns() {
        return new external_single_structure(
            array(
                'questions' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Question id'),
                            'text' => new external_value(PARAM_RAW, 'Question text'),
                            'shorttext' => new external_value(PARAM_RAW, 'Question short text'),
                            'multi' => new external_value(PARAM_RAW, 'Subquestions ids'),
                            'intro' => new external_value(PARAM_RAW, 'The question intro'),
                            'type' => new external_value(PARAM_INT, 'Question type'),
                            'options' => new external_value(PARAM_RAW, 'Question options'),
                            'parent' => new external_value(PARAM_INT, 'Parent question (for subquestions)'),
                        ), 'Questions'
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for submit_answers.
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function submit_answers_parameters() {
        return new external_function_parameters(
            array(
                'coursesatid' => new external_value(PARAM_INT, 'coursesat id'),
                'answers' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'key' => new external_value(PARAM_RAW, 'Answer key'),
                            'value' => new external_value(PARAM_RAW, 'Answer value')
                        )
                    )
                ),
            )
        );
    }

    /**
     * Submit the answers for a given coursesat.
     *
     * @param int $coursesatid the coursesat instance id
     * @param array $answers the coursesat answers
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function submit_answers($coursesatid, $answers) {
        global $DB, $USER;

        $params = self::validate_parameters(self::submit_answers_parameters(),
                                            array(
                                                'coursesatid' => $coursesatid,
                                                'answers' => $answers
                                            ));
        $warnings = array();

        // Request and permission validation.
        $coursesat = $DB->get_record('coursesat', array('id' => $params['coursesatid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($coursesat, 'coursesat');

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/coursesat:participate', $context);

        if (coursesat_already_done($coursesat->id, $USER->id)) {
            throw new moodle_exception("alreadysubmitted", "coursesat");
        }

        // Build the answers array. Data is cleaned inside the coursesat_save_answers function.
        $answers = array();
        foreach ($params['answers'] as $answer) {
            $key = $answer['key'];
            $answers[$key] = $answer['value'];
        }

        coursesat_save_answers($coursesat, $answers, $course, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.0
     */
    public static function submit_answers_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

}
