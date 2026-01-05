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
 * Unit tests for mod_coursesat lib
 *
 * @package    mod_coursesat
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
namespace mod_coursesat;

defined('MOODLE_INTERNAL') || die();


/**
 * Unit tests for mod_coursesat lib
 *
 * @package    mod_coursesat
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
final class lib_test extends \advanced_testcase {

    /**
     * Prepares things before this test case is initialised
     * @return void
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/coursesat/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Setup testcase.
     */
    public function setUp(): void {
        parent::setUp();
        // coursesat module is disabled by default, enable it for testing.
        $manager = \core_plugin_manager::resolve_plugininfo_class('mod');
        $manager::enable_plugin('coursesat', 1);
    }

    /**
     * Test coursesat_view
     * @return void
     */
    public function test_coursesat_view(): void {
        global $CFG;

        $CFG->enablecompletion = 1;
        $this->resetAfterTest();

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $coursesat = $this->getDataGenerator()->create_module('coursesat', array('course' => $course->id),
                                                            array('completion' => 2, 'completionview' => 1));
        $context = \context_module::instance($coursesat->cmid);
        $cm = get_coursemodule_from_instance('coursesat', $coursesat->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        coursesat_view($coursesat, $course, $cm, $context, 'form');

        $events = $sink->get_events();
        // 2 additional events thanks to completion.
        $this->assertCount(3, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_coursesat\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodleurl = new \moodle_url('/mod/coursesat/view.php', array('id' => $cm->id));
        $this->assertEquals($moodleurl, $event->get_url());
        $this->assertEquals('form', $event->other['viewed']);
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
        // Check completion status.
        $completion = new \completion_info($course);
        $completiondata = $completion->get_data($cm);
        $this->assertEquals(1, $completiondata->completionstate);

    }

    /**
     * Test coursesat_order_questions
     */
    public function test_coursesat_order_questions(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $coursesat = $this->getDataGenerator()->create_module('coursesat', array('course' => $course->id));

        $orderedquestionids = explode(',', $coursesat->questions);
        $coursesatquestions = $DB->get_records_list("coursesat_questions", "id", $orderedquestionids);

        $questionsordered = coursesat_order_questions($coursesatquestions, $orderedquestionids);

        // Check one by one the correct order.
        for ($i = 0; $i < count($orderedquestionids); $i++) {
            $this->assertEquals($orderedquestionids[$i], $questionsordered[$i]->id);
        }
    }

    /**
     * Test coursesat_save_answers
     */
    public function test_coursesat_save_answers(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $coursesat = $this->getDataGenerator()->create_module('coursesat', array('course' => $course->id));
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

        $sink = $this->redirectEvents();
        coursesat_save_answers($coursesat, $realquestions, $course, $context);

        // Check the stored answers, they must match.
        $dbanswers = $DB->get_records_menu('coursesat_answers', array('coursesat' => $coursesat->id), '', 'question, answer1');
        foreach ($realquestions as $key => $value) {
            $id = str_replace('q', '', $key);
            $this->assertEquals($value, $dbanswers[$id]);
        }

        // Check events.
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_coursesat\event\response_submitted', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($coursesat->id, $event->other['coursesatid']);
    }

    public function test_coursesat_core_calendar_provide_event_action(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course();
        $coursesat = $this->getDataGenerator()->create_module('coursesat', array('course' => $course->id));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $coursesat->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_coursesat_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('view'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_coursesat_core_calendar_provide_event_action_for_user(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course();
        $coursesat = $this->getDataGenerator()->create_module('coursesat', array('course' => $course->id));

        // Create a student and enrol into the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $coursesat->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Now log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_coursesat_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('view'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_coursesat_core_calendar_provide_event_action_as_non_user(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course();
        $coursesat = $this->getDataGenerator()->create_module('coursesat', array('course' => $course->id));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $coursesat->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Log out the user and set force login to true.
        \core\session\manager::init_empty_session();
        $CFG->forcelogin = true;

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_coursesat_core_calendar_provide_event_action($event, $factory);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    public function test_coursesat_core_calendar_provide_event_action_already_completed(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $CFG->enablecompletion = 1;

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $coursesat = $this->getDataGenerator()->create_module('coursesat', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Get some additional data.
        $cm = get_coursemodule_from_instance('coursesat', $coursesat->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $coursesat->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_coursesat_core_calendar_provide_event_action($event, $factory);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    public function test_coursesat_core_calendar_provide_event_action_already_completed_for_user(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $CFG->enablecompletion = 1;

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $coursesat = $this->getDataGenerator()->create_module('coursesat', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Create 2 students and enrol them into the course.
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Get some additional data.
        $cm = get_coursemodule_from_instance('coursesat', $coursesat->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $coursesat->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed for the $student1.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm, $student1->id);

        // Now log in as $student2.
        $this->setUser($student2);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for $student1.
        $actionevent = mod_coursesat_core_calendar_provide_event_action($event, $factory, $student1->id);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    /**
     * Creates an action event.
     *
     * @param int $courseid The course id.
     * @param int $instanceid The instance id.
     * @param string $eventtype The event type.
     * @return bool|calendar_event
     */
    private function create_action_event($courseid, $instanceid, $eventtype) {
        $event = new \stdClass();
        $event->name = 'Calendar event';
        $event->modulename  = 'coursesat';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;
        $event->timestart = time();

        return \calendar_event::create($event);
    }

    /**
     * Test the callback responsible for returning the completion rule descriptions.
     * This function should work given either an instance of the module (cm_info), such as when checking the active rules,
     * or if passed a stdClass of similar structure, such as when checking the the default completion settings for a mod type.
     */
    public function test_mod_coursesat_completion_get_active_rule_descriptions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Two activities, both with automatic completion. One has the 'completionsubmit' rule, one doesn't.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 2]);
        $coursesat1 = $this->getDataGenerator()->create_module('coursesat', [
            'course' => $course->id,
            'completion' => 2,
            'completionsubmit' => 1,
        ]);
        $coursesat2 = $this->getDataGenerator()->create_module('coursesat', [
            'course' => $course->id,
            'completion' => 2,
            'completionsubmit' => 0,
        ]);
        $cm1 = \cm_info::create(get_coursemodule_from_instance('coursesat', $coursesat1->id));
        $cm2 = \cm_info::create(get_coursemodule_from_instance('coursesat', $coursesat2->id));

        // Data for the stdClass input type.
        // This type of input would occur when checking the default completion rules for an activity type, where we don't have
        // any access to cm_info, rather the input is a stdClass containing completion and customdata attributes, just like cm_info.
        $moddefaults = new \stdClass();
        $moddefaults->customdata = ['customcompletionrules' => ['completionsubmit' => 1]];
        $moddefaults->completion = 2;

        $activeruledescriptions = [get_string('completionsubmit', 'coursesat')];
        $this->assertEquals(mod_coursesat_get_completion_active_rule_descriptions($cm1), $activeruledescriptions);
        $this->assertEquals(mod_coursesat_get_completion_active_rule_descriptions($cm2), []);
        $this->assertEquals(mod_coursesat_get_completion_active_rule_descriptions($moddefaults), $activeruledescriptions);
        $this->assertEquals(mod_coursesat_get_completion_active_rule_descriptions(new \stdClass()), []);
    }
}
