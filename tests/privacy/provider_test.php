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
 * Data provider tests.
 *
 * @package    mod_coursesat
 * @category   test
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_coursesat\privacy;

defined('MOODLE_INTERNAL') || die();
global $CFG;

use core_privacy\tests\provider_testcase;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use mod_coursesat\privacy\provider;

require_once($CFG->dirroot . '/mod/coursesat/lib.php');

/**
 * Data provider testcase class.
 *
 * @package    mod_coursesat
 * @category   test
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends provider_testcase {

    public function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $PAGE->get_renderer('core');

        // coursesat module is disabled by default, enable it for testing.
        $manager = \core_plugin_manager::resolve_plugininfo_class('mod');
        $manager::enable_plugin('coursesat', 1);
    }

    public function test_get_contexts_for_userid(): void {
        $dg = $this->getDataGenerator();

        $c1 = $dg->create_course();
        $c2 = $dg->create_course();
        $cm1a = $dg->create_module('coursesat', ['template' => 1, 'course' => $c1]);
        $cm1b = $dg->create_module('coursesat', ['template' => 2, 'course' => $c1]);
        $cm1c = $dg->create_module('coursesat', ['template' => 2, 'course' => $c1]);
        $cm2a = $dg->create_module('coursesat', ['template' => 1, 'course' => $c2]);
        $cm2b = $dg->create_module('coursesat', ['template' => 1, 'course' => $c2]);
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();

        $this->create_answer($cm1a->id, 1, $u1->id);
        $this->create_answer($cm1a->id, 1, $u2->id);
        $this->create_answer($cm1b->id, 1, $u2->id);
        $this->create_answer($cm2a->id, 1, $u1->id);
        $this->create_analysis($cm2b->id, $u1->id);
        $this->create_analysis($cm1c->id, $u2->id);

        $contextids = provider::get_contexts_for_userid($u1->id)->get_contextids();
        $this->assertCount(3, $contextids);
        $this->assertTrue(in_array(\context_module::instance($cm1a->cmid)->id, $contextids));
        $this->assertTrue(in_array(\context_module::instance($cm2a->cmid)->id, $contextids));
        $this->assertTrue(in_array(\context_module::instance($cm2b->cmid)->id, $contextids));

        $contextids = provider::get_contexts_for_userid($u2->id)->get_contextids();
        $this->assertCount(3, $contextids);
        $this->assertTrue(in_array(\context_module::instance($cm1a->cmid)->id, $contextids));
        $this->assertTrue(in_array(\context_module::instance($cm1b->cmid)->id, $contextids));
        $this->assertTrue(in_array(\context_module::instance($cm1c->cmid)->id, $contextids));
    }

    /**
     * Test for provider::test_get_users_in_context().
     */
    public function test_get_users_in_context(): void {
        $dg = $this->getDataGenerator();
        $component = 'mod_coursesat';

        $c1 = $dg->create_course();
        $c2 = $dg->create_course();
        $cm1a = $dg->create_module('coursesat', ['template' => 1, 'course' => $c1]);
        $cm1b = $dg->create_module('coursesat', ['template' => 2, 'course' => $c1]);
        $cm2 = $dg->create_module('coursesat', ['template' => 1, 'course' => $c2]);
        $cm1acontext = \context_module::instance($cm1a->cmid);
        $cm1bcontext = \context_module::instance($cm1b->cmid);
        $cm2context = \context_module::instance($cm2->cmid);

        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $bothusers = [$u1->id, $u2->id];
        sort($bothusers);

        $this->create_answer($cm1a->id, 1, $u1->id);
        $this->create_answer($cm1b->id, 1, $u1->id);
        $this->create_answer($cm1b->id, 1, $u2->id);
        $this->create_answer($cm2->id, 1, $u2->id);
        $this->create_analysis($cm2->id, $u1->id);

        // Cm1a should only contain u1.
        $userlist = new \core_privacy\local\request\userlist($cm1acontext, $component);
        provider::get_users_in_context($userlist);

        $this->assertCount(1, $userlist);
        $this->assertEquals([$u1->id], $userlist->get_userids());

        // Cm1b should contain u1 and u2 (both have answers).
        $userlist = new \core_privacy\local\request\userlist($cm1bcontext, $component);
        provider::get_users_in_context($userlist);

        $this->assertCount(2, $userlist);
        $actual = $userlist->get_userids();
        sort($actual);
        $this->assertEquals($bothusers, $actual);

        // Cm2 should contain u1 (analysis) and u2 (answer).
        $userlist = new \core_privacy\local\request\userlist($cm2context, $component);
        provider::get_users_in_context($userlist);

        $this->assertCount(2, $userlist);
        $actual = $userlist->get_userids();
        sort($actual);
        $this->assertEquals($bothusers, $actual);
    }

    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $dg = $this->getDataGenerator();

        $c1 = $dg->create_course();
        $cm1a = $dg->create_module('coursesat', ['template' => 1, 'course' => $c1]);
        $cm1b = $dg->create_module('coursesat', ['template' => 2, 'course' => $c1]);
        $cm1c = $dg->create_module('coursesat', ['template' => 2, 'course' => $c1]);
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();

        $this->create_answer($cm1a->id, 1, $u1->id);
        $this->create_answer($cm1a->id, 1, $u2->id);
        $this->create_answer($cm1b->id, 1, $u2->id);
        $this->create_answer($cm1c->id, 1, $u1->id);
        $this->create_analysis($cm1a->id, $u1->id);
        $this->create_analysis($cm1b->id, $u1->id);
        $this->create_analysis($cm1a->id, $u2->id);
        $this->create_analysis($cm1c->id, $u2->id);

        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1c->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1b->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1b->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1c->id]));

        // Deleting the course does nothing.
        provider::delete_data_for_all_users_in_context(\context_course::instance($c1->id));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1c->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1b->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1b->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1c->id]));

        provider::delete_data_for_all_users_in_context(\context_module::instance($cm1c->cmid));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertFalse($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1c->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1b->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1b->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertFalse($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1c->id]));

        provider::delete_data_for_all_users_in_context(\context_module::instance($cm1a->cmid));
        $this->assertFalse($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertFalse($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1c->id]));
        $this->assertFalse($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1b->id]));
        $this->assertFalse($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1b->id]));
        $this->assertFalse($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertFalse($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1c->id]));
    }

    public function test_delete_data_for_user(): void {
        global $DB;
        $dg = $this->getDataGenerator();

        $c1 = $dg->create_course();
        $cm1a = $dg->create_module('coursesat', ['template' => 1, 'course' => $c1]);
        $cm1b = $dg->create_module('coursesat', ['template' => 2, 'course' => $c1]);
        $cm1c = $dg->create_module('coursesat', ['template' => 2, 'course' => $c1]);
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();

        $this->create_answer($cm1a->id, 1, $u1->id);
        $this->create_answer($cm1a->id, 1, $u2->id);
        $this->create_answer($cm1b->id, 1, $u2->id);
        $this->create_answer($cm1c->id, 1, $u1->id);
        $this->create_analysis($cm1a->id, $u1->id);
        $this->create_analysis($cm1b->id, $u1->id);
        $this->create_analysis($cm1a->id, $u2->id);
        $this->create_analysis($cm1c->id, $u2->id);

        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1c->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1b->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1b->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1c->id]));

        provider::delete_data_for_user(new approved_contextlist($u1, 'mod_coursesat', [
            \context_course::instance($c1->id)->id,
            \context_module::instance($cm1a->cmid)->id,
            \context_module::instance($cm1b->cmid)->id,
        ]));
        $this->assertFalse($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1c->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1b->id]));
        $this->assertFalse($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertFalse($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1b->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1c->id]));
    }

    /**
     * Test for provider::delete_data_for_users().
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $dg = $this->getDataGenerator();
        $component = 'mod_coursesat';

        $c1 = $dg->create_course();
        $cm1a = $dg->create_module('coursesat', ['template' => 1, 'course' => $c1]);
        $cm1b = $dg->create_module('coursesat', ['template' => 2, 'course' => $c1]);
        $cm1c = $dg->create_module('coursesat', ['template' => 2, 'course' => $c1]);
        $cm1acontext = \context_module::instance($cm1a->cmid);
        $cm1bcontext = \context_module::instance($cm1b->cmid);

        $u1 = $dg->create_user();
        $u2 = $dg->create_user();

        $this->create_answer($cm1a->id, 1, $u1->id);
        $this->create_answer($cm1a->id, 1, $u2->id);
        $this->create_analysis($cm1a->id, $u1->id);
        $this->create_analysis($cm1a->id, $u2->id);
        $this->create_answer($cm1b->id, 1, $u2->id);
        $this->create_analysis($cm1b->id, $u1->id);
        $this->create_answer($cm1c->id, 1, $u1->id);
        $this->create_analysis($cm1c->id, $u2->id);

        // Confirm data exists before deletion.
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1c->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1b->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1b->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1c->id]));

        // Ensure only approved user data is deleted.
        $approveduserids = [$u1->id];
        $approvedlist = new approved_userlist($cm1acontext, $component, $approveduserids);
        provider::delete_data_for_users($approvedlist);

        $this->assertFalse($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertFalse($DB->record_exists('coursesat_analysis', ['userid' => $u1->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1a->id]));

        $approveduserids = [$u1->id, $u2->id];
        $approvedlist = new approved_userlist($cm1bcontext, $component, $approveduserids);
        provider::delete_data_for_users($approvedlist);

        $this->assertFalse($DB->record_exists('coursesat_answers', ['coursesat' => $cm1b->id]));
        $this->assertFalse($DB->record_exists('coursesat_analysis', ['coursesat' => $cm1b->id]));

        $this->assertTrue($DB->record_exists('coursesat_answers', ['userid' => $u1->id, 'coursesat' => $cm1c->id]));
        $this->assertTrue($DB->record_exists('coursesat_analysis', ['userid' => $u2->id, 'coursesat' => $cm1c->id]));
    }

    public function test_export_data_for_user(): void {
        global $DB;
        $dg = $this->getDataGenerator();

        $templates = $DB->get_records_menu('coursesat', array('template' => 0), 'name', 'name, id');

        $c1 = $dg->create_course();
        $s1a = $dg->create_module('coursesat', ['template' => $templates['attlsname'], 'course' => $c1]);
        $s1b = $dg->create_module('coursesat', ['template' => $templates['ciqname'], 'course' => $c1]);
        $s1c = $dg->create_module('coursesat', ['template' => $templates['collesapname'], 'course' => $c1]);
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();

        $s1actx = \context_module::instance($s1a->cmid);
        $s1bctx = \context_module::instance($s1b->cmid);
        $s1cctx = \context_module::instance($s1c->cmid);

        $this->answer_coursesat($s1a, $u1, $c1, $s1actx);
        $this->answer_coursesat($s1b, $u1, $c1, $s1bctx);
        $this->create_analysis($s1a->id, $u1->id, 'Hello,');

        $this->answer_coursesat($s1a, $u2, $c1, $s1actx);
        $this->answer_coursesat($s1c, $u2, $c1, $s1cctx);
        $this->create_analysis($s1b->id, $u2->id, 'World!');

        provider::export_user_data(new approved_contextlist($u1, 'mod_coursesat', [$s1actx->id, $s1bctx->id, $s1cctx->id]));

        $data = writer::with_context($s1actx)->get_data([]);
        $this->assertNotEmpty($data);
        $this->assert_exported_answers($data->answers, $u1, $s1a);
        $data = writer::with_context($s1actx)->get_related_data([], 'coursesat_analysis');
        $this->assertEquals('Hello,', $data->notes);

        $data = writer::with_context($s1bctx)->get_data([]);
        $this->assertNotEmpty($data);
        $this->assert_exported_answers($data->answers, $u1, $s1b);
        $data = writer::with_context($s1bctx)->get_related_data([], 'coursesat_analysis');
        $this->assertEmpty($data);

        $data = writer::with_context($s1cctx)->get_data([]);
        $this->assertEmpty($data);
        $data = writer::with_context($s1cctx)->get_related_data([], 'coursesat_analysis');
        $this->assertEmpty($data);

        writer::reset();
        provider::export_user_data(new approved_contextlist($u2, 'mod_coursesat', [$s1actx->id, $s1bctx->id, $s1cctx->id]));

        $data = writer::with_context($s1actx)->get_data([]);
        $this->assertNotEmpty($data);
        $this->assert_exported_answers($data->answers, $u2, $s1a);
        $data = writer::with_context($s1actx)->get_related_data([], 'coursesat_analysis');
        $this->assertEmpty($data);

        $data = writer::with_context($s1bctx)->get_data([]);
        $this->assertEmpty($data);
        $data = writer::with_context($s1bctx)->get_related_data([], 'coursesat_analysis');
        $this->assertEquals('World!', $data->notes);

        $data = writer::with_context($s1cctx)->get_data([]);
        $this->assertNotEmpty($data);
        $this->assert_exported_answers($data->answers, $u2, $s1c);
        $data = writer::with_context($s1cctx)->get_related_data([], 'coursesat_analysis');
        $this->assertEmpty($data);
    }

    /**
     * Answer a coursesat in a predictable manner.
     *
     * @param stdClass $coursesat The coursesat.
     * @param stdClass $user The user.
     * @param stdClass $course The course.
     * @param context_module $context The module context.
     * @return void
     */
    protected function answer_coursesat($coursesat, $user, $course, \context_module $context) {
        global $USER;

        $userid = $user->id;
        $questions = coursesat_get_questions($coursesat);
        $answer = function(&$answers, $q) use ($userid) {
            $key = 'q' . ($q->type == 2 ? 'P' : '') . $q->id;

            if ($q->type < 1) {
                $a = "A:{$q->id}:{$userid}";
                $answers[$key] = $a;

            } else if ($q->type < 3) {
                $options = explode(',', get_string($q->options, 'mod_coursesat'));
                $answers[$key] = ($q->id + $userid) % count($options) + 1;

            } else {
                $options = explode(',', get_string($q->options, 'mod_coursesat'));
                $answers["q{$q->id}"] = ($q->id + $userid) % count($options) + 1;
                $answers["qP{$q->id}"] = ($q->id + $userid + 1) % count($options) + 1;
            }

        };

        foreach ($questions as $q) {
            if ($q->type < 0) {
                continue;
            } else if ($q->type > 0 && $q->multi) {
                $subquestions = coursesat_get_subquestions($q);
                foreach ($subquestions as $sq) {
                    $answer($answers, $sq);
                }
            } else {
                $answer($answers, $q);
            }
        }

        $origuser = $USER;
        $this->setUser($user);
        coursesat_save_answers($coursesat, $answers, $course, $context);
        $this->setUser($origuser);
    }

    /**
     * Assert the answers provided to a coursesat.
     *
     * @param array $answers The answers.
     * @param object $user The user.
     * @param object $coursesat The coursesat.
     * @return void
     */
    protected function assert_exported_answers($answers, $user, $coursesat) {
        global $DB;

        $userid = $user->id;
        $questionids = explode(',', $coursesat->questions);
        $topquestions = $DB->get_records_list('coursesat_questions', 'id', $questionids, 'id');
        $questions = [];

        foreach ($topquestions as $q) {
            if ($q->type < 0) {
                continue;
            } else if ($q->type > 0 && $q->multi) {
                $questionids = explode(',', $q->multi);
                $subqs = $DB->get_records_list('coursesat_questions', 'id', $questionids, 'id');
            } else {
                $subqs = [$q];
            }
            foreach ($subqs as $sq) {
                $questions[] = $sq;
            }
        }

        $this->assertCount(count($questions), $answers);

        $answer = reset($answers);
        foreach ($questions as $question) {
            $qtype = $question->type;
            $question = coursesat_translate_question($question);
            $options = $qtype > 0 ? explode(',', $question->options) : '-';
            $this->assertEquals($question->text, $answer['question']['text']);
            $this->assertEquals($question->shorttext, $answer['question']['shorttext']);
            $this->assertEquals($question->intro, $answer['question']['intro']);
            $this->assertEquals($options, $answer['question']['options']);

            if ($qtype < 1) {
                $this->assertEquals("A:{$question->id}:{$userid}", $answer['answer']['actual']);

            } else if ($qtype == 1 || $qtype == 2) {
                $chosen = ($question->id + $userid) % count($options);
                $key = $qtype == 1 ? 'actual' : 'preferred';
                $this->assertEquals($options[$chosen], $answer['answer'][$key]);

            } else {
                $chosen = ($question->id + $userid) % count($options);
                $this->assertEquals($options[$chosen], $answer['answer']['actual']);
                $chosen = ($question->id + $userid + 1) % count($options);
                $this->assertEquals($options[$chosen], $answer['answer']['preferred']);
            }

            // Grab next answer, if any.
            $answer = next($answers);
        }

    }

    /**
     * Create analysis.
     *
     * @param int $coursesatid The coursesat ID.
     * @param int $userid The user ID.
     * @param string $notes The nodes.
     * @return stdClass
     */
    protected function create_analysis($coursesatid, $userid, $notes = '') {
        global $DB;
        $record = (object) [
            'coursesat' => $coursesatid,
            'userid' => $userid,
            'notes' => $notes
        ];
        $record->id = $DB->insert_record('coursesat_analysis', $record);
        return $record;
    }

    /**
     * Create answer.
     *
     * @param int $coursesatid The coursesat ID.
     * @param int $questionid The question ID.
     * @param int $userid The user ID.
     * @param string $answer1 The first answer field.
     * @param string $answer2 The second answer field.
     * @return stdClass
     */
    protected function create_answer($coursesatid, $questionid, $userid, $answer1 = '', $answer2 = '') {
        global $DB;
        $record = (object) [
            'coursesat' => $coursesatid,
            'question' => $questionid,
            'userid' => $userid,
            'answer1' => $answer1,
            'answer2' => $answer2,
            'time' => time()
        ];
        $record->id = $DB->insert_record('coursesat_answers', $record);
        return $record;
    }

}
