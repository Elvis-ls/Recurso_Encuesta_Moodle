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
 * @package    mod_coursesat
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_coursesat_activity_task
 */

/**
 * Structure step to restore one coursesat activity
 */
class restore_coursesat_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('coursesat', '/activity/coursesat');
        if ($userinfo) {
            $paths[] = new restore_path_element('coursesat_answer', '/activity/coursesat/answers/answer');
            $paths[] = new restore_path_element('coursesat_analys', '/activity/coursesat/analysis/analys');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_coursesat($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.

        // insert the coursesat record
        $newitemid = $DB->insert_record('coursesat', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_coursesat_analys($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->coursesat = $this->get_new_parentid('coursesat');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('coursesat_analysis', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }

    protected function process_coursesat_answer($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->coursesat = $this->get_new_parentid('coursesat');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('coursesat_answers', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }

    protected function after_execute() {
        // Add coursesat related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_coursesat', 'intro', null);
    }
}
