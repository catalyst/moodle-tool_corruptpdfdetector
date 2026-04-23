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

namespace tool_corruptpdfdetector\table;

use html_table;
use html_table_cell;
use html_table_row;
use moodle_url;
use html_writer;

/**
 * Corrupt pdf assignment list.
 *
 * @package    tool_corruptpdfdetector
 * @author     John Yao <johnyao@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignments extends html_table {
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->attributes['class'] = 'admintable generaltable';

        $headers = [
            get_string('course'),
            get_string('assignmentheader', 'tool_corruptpdfdetector'),
            get_string('userfullnameheader', 'tool_corruptpdfdetector'),
            get_string('email'),
            get_string('reasonheader', 'tool_corruptpdfdetector'),
            get_string('submittedheader', 'tool_corruptpdfdetector'),
            get_string('detectedheader', 'tool_corruptpdfdetector'),
            get_string('fixedheader', 'tool_corruptpdfdetector'),
        ];
        $data = [];

        $records = $this->get_detected_assignments();

        foreach ($records as $record) {
            $cm = get_coursemodule_from_instance('assign', $record->assignid);
            $assignurl = new moodle_url('/mod/assign/view.php', [
                'id' => $cm->id,
                'action' => 'grader',
            ]);

            $linktoassign = html_writer::link($assignurl, $record->assignname);

            $row = new html_table_row([
                new html_table_cell($record->coursename),
                new html_table_cell($linktoassign),
                new html_table_cell($record->userfullname),
                new html_table_cell($record->email),
                new html_table_cell($record->message),
                new html_table_cell(userdate($record->submitted, '%Y-%m-%d %H:%M:%S', 99, false, false)),
                new html_table_cell(userdate($record->detected, '%Y-%m-%d %H:%M:%S', 99, false, false)),
                new html_table_cell($record->fixed ? 'Yes' : 'No'),
            ]);

            $data[] = $row;
        }

        $this->head = $headers;
        $this->data = $data;
    }

    /**
     * Obtain an array of all the currently detected assignments.
     *
     * @return array
     */
    private function get_detected_assignments() {
        global $DB;

        $records = $DB->get_records('tool_pdfdetect_assigns', [], 'detected ASC');

        return $records;
    }

    /**
     * Calculates the percentage of assignments with unresolved issues.
     * This method determines the proportion of submissions with detected, unresolved issues
     * out of the total number of submissions as a percentage.
     * If there are no submissions or no unresolved issues, it returns "0" as the percentage.
     *
     * @return string The percentage of unresolved issues, formatted as a string with a "%" sign.
     */
    public function get_percentage() {
        global $DB;

        $submissioncount = $DB->count_records('assign_submission');
        $filecount = $DB->count_records_select(
            'tool_pdfdetect_assigns',
            'fixed = ?',
            [false],
            'COUNT(DISTINCT submissionid)'
        );

        if ($filecount === 0 || $submissioncount === 0) {
            return '0';
        }
        return round($filecount * 100 / $submissioncount) . '%';
    }
}
