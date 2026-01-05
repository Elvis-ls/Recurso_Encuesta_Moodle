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

namespace mod_coursesat;

/**
 * Genarator tests class for mod_coursesat.
 *
 * @package    mod_coursesat
 * @category   test
 * @copyright  2013 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generator_test extends \advanced_testcase {

    /**
     * Setup testcase.
     */
    public function setUp(): void {
        parent::setUp();
        // coursesat module is disabled by default, enable it for testing.
        $manager = \core_plugin_manager::resolve_plugininfo_class('mod');
        $manager::enable_plugin('coursesat', 1);
    }

    public function test_create_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse($DB->record_exists('coursesat', array('course' => $course->id)));
        $coursesat = $this->getDataGenerator()->create_module('coursesat', array('course' => $course));
        $records = $DB->get_records('coursesat', array('course' => $course->id), 'id');
        $this->assertEquals(1, count($records));
        $this->assertTrue(array_key_exists($coursesat->id, $records));

        $params = array('course' => $course->id, 'name' => 'Another coursesat');
        $coursesat = $this->getDataGenerator()->create_module('coursesat', $params);
        $records = $DB->get_records('coursesat', array('course' => $course->id), 'id');
        $this->assertEquals(2, count($records));
        $this->assertEquals('Another coursesat', $records[$coursesat->id]->name);
    }

    public function test_create_instance_with_template(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $templates = $DB->get_records_menu('coursesat', array('template' => 0), 'name', 'id, name');
        $firsttemplateid = key($templates);

        // By default coursesat is created with the first available template.
        $coursesat = $this->getDataGenerator()->create_module('coursesat', array('course' => $course));
        $record = $DB->get_record('coursesat', array('id' => $coursesat->id));
        $this->assertEquals($firsttemplateid, $record->template);

        // coursesat can be created specifying the template id.
        $tmplid = array_search('ciqname', $templates);
        $coursesat = $this->getDataGenerator()->create_module('coursesat', array('course' => $course,
            'template' => $tmplid));
        $record = $DB->get_record('coursesat', array('id' => $coursesat->id));
        $this->assertEquals($tmplid, $record->template);

        // coursesat can be created specifying the template name instead of id.
        $coursesat = $this->getDataGenerator()->create_module('coursesat', array('course' => $course,
            'template' => 'collesaname'));
        $record = $DB->get_record('coursesat', array('id' => $coursesat->id));
        $this->assertEquals(array_search('collesaname', $templates), $record->template);

        // coursesat can not be created specifying non-existing template id or name.
        try {
            $this->getDataGenerator()->create_module('coursesat', array('course' => $course,
                'template' => 87654));
            $this->fail('Exception about non-existing numeric template is expected');
        } catch (\Exception $e) {}
        try {
            $this->getDataGenerator()->create_module('coursesat', array('course' => $course,
                'template' => 'nonexistingcode'));
            $this->fail('Exception about non-existing string template is expected');
        } catch (\Exception $e) {}
    }
}
