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
 * Test data generator for tool_corruptpdfdetector.
 *
 * @package    tool_corruptpdfdetector
 * @category   test
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Data generator for tool_corruptpdfdetector.
 *
 * @package    tool_corruptpdfdetector
 * @category   test
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_corruptpdfdetector_generator extends testing_module_generator {
    /**
     * Create a detection record in tool_corruptpdfdetector_assigns.
     *
     * Accepted keys in $record:
     *   - assignid       (int,    default 0)
     *   - submissionid   (int,    default auto-incremented unique value)
     *   - coursename     (string, default '')
     *   - assignname     (string, default '')
     *   - userfullname   (string, default '')
     *   - email          (string, default '')
     *   - filename       (string, default sha1 of submissionid)
     *   - message        (string, default '')
     *   - submitted      (int,    default current time)
     *   - detected       (int,    default current time)
     *   - fixed          (int,    default 0)
     *
     * @param array|stdClass|null $record Detection record data.
     * @return stdClass Inserted record with id populated.
     */
    public function create_detection($record = null): stdClass {
        global $DB;

        $record = (object)(array) $record;

        if (!isset($record->assignid)) {
            $record->assignid = 0;
        }
        if (!isset($record->submissionid)) {
            $record->submissionid = $DB->get_field_sql(
                'SELECT COALESCE(MAX(submissionid), 0) + 1 FROM {tool_corruptpdfdetector_assigns}'
            );
        }
        if (!isset($record->coursename)) {
            $record->coursename = '';
        }
        if (!isset($record->assignname)) {
            $record->assignname = '';
        }
        if (!isset($record->userfullname)) {
            $record->userfullname = '';
        }
        if (!isset($record->email)) {
            $record->email = '';
        }
        if (!isset($record->filename)) {
            $record->filename = sha1((string) $record->submissionid);
        }
        if (!isset($record->message)) {
            $record->message = '';
        }
        if (!isset($record->submitted)) {
            $record->submitted = time();
        }
        if (!isset($record->detected)) {
            $record->detected = time();
        }
        if (!isset($record->fixed)) {
            $record->fixed = 0;
        }

        $record->id = $DB->insert_record('tool_corruptpdfdetector_assigns', $record);

        return $record;
    }
}
