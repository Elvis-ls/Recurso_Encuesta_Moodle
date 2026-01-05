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
 * Unit test for mod_coursesat searching.
 *
 * This is needed because the activity.php class overrides default behaviour.
 *
 * @package mod_coursesat
 * @category test
 * @copyright 2017 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursesat\search;

/**
 * Unit test for mod_coursesat searching.
 *
 * This is needed because the activity.php class overrides default behaviour.
 *
 * @package mod_coursesat
 * @category test
 * @copyright 2017 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class search_test extends \advanced_testcase {

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
    public function test_coursesat_indexing(): void {
        global $CFG;

        $this->resetAfterTest();

        require_once($CFG->dirroot . '/search/tests/fixtures/testable_core_search.php');
        \testable_core_search::instance();
        $area = \core_search\manager::get_search_area('mod_coursesat-activity');

        // Setup test data.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $coursesat1 = $generator->create_module('coursesat', ['course' => $course->id]);
        $coursesat2 = $generator->create_module('coursesat', ['course' => $course->id]);

        // Get all coursesats for indexing - note that there are special entries in the table with
        // course zero which should not be returned.
        $rs = $area->get_document_recordset();
        $this->assertEquals(2, iterator_count($rs));
        $rs->close();

        // Test specific context and course context.
        $rs = $area->get_document_recordset(0, \context_module::instance($coursesat1->cmid));
        $this->assertEquals(1, iterator_count($rs));
        $rs->close();
        $rs = $area->get_document_recordset(0, \context_course::instance($course->id));
        $this->assertEquals(2, iterator_count($rs));
        $rs->close();
    }
}
