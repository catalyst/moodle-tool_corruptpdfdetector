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
 * Task to scan assignments.
 *
 * @package    tool_corruptpdfdetector
 * @author     John Yao <johnyao@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_corruptpdfdetector\task;

require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/feedback/editpdf/fpdi/fpdi_pdf_parser.php');

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

class scan_assignments extends \core\task\scheduled_task
{
    /**
     * {@inheritDoc}
     * @see \core\task\scheduled_task::get_name()
     */
    public function get_name()
    {
        return get_string('task_scan_assignments', 'tool_corruptpdfdetector');
    }

    /**
     * {@inheritDoc}
     * @see \core\task\task_base::execute()
     */
    public function execute()
    {
        global $DB;

        // This could reduce a lot of submission records being compared and we assume there is at least one corrupt pdf found in last run.
        $lastdetected = $DB->get_field_sql('SELECT MAX(detected) FROM {tool_pdfdetect_assigns}');

        //Should only apply after installation
        if (!$lastdetected) {
            $lastdetected = 0;
        }
        // Fetch all assignment submissions updated after last run that has detected badly converted assignment submission.
        $records = $DB->get_records_sql('SELECT * FROM {assign_submission} WHERE timemodified > :timemodified', ['timemodified' => $lastdetected]);

        foreach ($records as $submission) {

            $detected_submission = $this->detected_submission($submission);
            if ($detected_submission != null) {
                $pdfwitherror = $this->check_submission_combined_pdf($submission);
                if ($pdfwitherror != null) {
                    $DB->update_record('tool_pdfdetect_assigns', $pdfwitherror);
                } else {
                    $DB->delete_records('tool_pdfdetect_assigns', array('submissionid' => $submission->id));
                }
            } else {
                $pdfwitherror = $this->check_submission_combined_pdf($submission);
                if ($pdfwitherror != null) {
                    $DB->insert_record('tool_pdfdetect_assigns', $pdfwitherror);
                }
            }
        }
    }

    private function get_pdf_file_for_assignment($assignment, $submission)
    {
        $grade = $assignment->get_user_grade($submission->userid, true, $submission->attemptnumber);
        $contextid = $assignment->get_context()->id;
        $component = 'assignfeedback_editpdf';
        $itemid = $grade->id;
        $fs = get_file_storage();
        $pdfarea = 'combined';
        $pdfname = 'combined.pdf';
        $filepath = '/';

        return $fs->get_file($contextid, $component, $pdfarea, $itemid, $filepath, $pdfname);
    }

    private function detected_submission($submission)
    {
        global $DB;

        $params = ['submissionid' => $submission->id];
        $select = $DB->sql_compare_text('submissionid') . ' = ' . $DB->sql_compare_text(':submissionid');
        $detectedsubmission = $DB->get_record_select('tool_pdfdetect_assigns', $select, $params);

        if (!empty($detectedsubmission)) {
            return $detectedsubmission;
        }

        return null;
    }

    private function check_submission_combined_pdf($submission)
    {
        global $DB;

        $cm = \get_coursemodule_from_instance('assign', $submission->assignment, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $assignment = new \assign($context, null, null);

        $pdf = $this->get_pdf_file_for_assignment($assignment, $submission);
        $user = $DB->get_record('user', array('id' => $submission->userid), '*', MUST_EXIST);

        if ($pdf) {
            try {
                $tmp_pdf_path = $pdf->copy_content_to_temp();
                $fpdf = new \fpdi_pdf_parser($tmp_pdf_path);
                unset($fpdf);
            } catch (\Exception $e) {
                $pdfwitherror = new \stdClass();
                $pdfwitherror->assignid = $submission->assignment;
                $pdfwitherror->submissionid = $submission->id;
                $pdfwitherror->coursename = $assignment->get_course()->fullname;
                $pdfwitherror->assignname = $assignment->get_instance()->name;
                $pdfwitherror->userfullname = $user->firstname . ' ' . $user->lastname;
                $pdfwitherror->email = $user->email;
                $pdfwitherror->filename = $pdf->get_contenthash();
                $pdfwitherror->message = $e->getMessage();
                $pdfwitherror->submitted = $submission->timemodified;
                $pdfwitherror->detected = time();

                return $pdfwitherror;
            }
            unlink($tmp_pdf_path);
            return null;
        }
    }
}
