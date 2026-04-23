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

namespace tool_corruptpdfdetector\task;

/**
 * Task to fix assignments.
 *
 * @package    tool_corruptpdfdetector
 * @author     Ilya Tregubov <ilyatregubov@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fix_assignments extends \core\task\scheduled_task {

    /**
     * {@inheritDoc}
     * @see \core\task\scheduled_task::get_name()
     */
    public function get_name() {
        return get_string('task_fix_assignments', 'tool_corruptpdfdetector');
    }

    /**
     * {@inheritDoc}
     * @see \core\task\task_base::execute()
     */
    public function execute() {
        global $DB;

        // Fetch all broken assignment submissions.
        $records = $DB->get_recordset('tool_pdfdetect_assigns', ['fixed' => false]);

        foreach ($records as $submission) {
            $contenthash = $submission->filename;
            $params['contenthash'] = $contenthash;
            $DB->delete_records_select('files',
                "contenthash = :contenthash AND filename = 'combined.pdf'
                        AND (filearea = 'combined' OR filearea = 'partial')",
                $params);
            $submission->fixed = true;
            $DB->update_record('tool_pdfdetect_assigns', $submission);
        }
        $records->close();
    }

}
