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

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/feedback/editpdf/fpdi/fpdi_pdf_parser.php');

define("ONE", 1);
define("NUMBER_OF_EACH_RUN", 1000);

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

class scan_assignments extends \core\task\scheduled_task
{
    /**
     * {@inheritDoc}
     * @see \core\task\scheduled_task::get_name()
     */
    public function get_name() {
        return get_string('task_scan_assignments', 'tool_corruptpdfdetector');
    }

    /**
     * {@inheritDoc}
     * @see \core\task\task_base::execute()
     */
    public function execute() {
        global $DB;

        // Get the last submission id that had been checked in last run.
        $lastrun = $DB->get_records_sql('SELECT lastsubmissionid
                                               FROM {tool_pdfdetect_runs}
                                           ORDER BY runtime
                                               DESC LIMIT :one', ['one' => ONE]);

        $lastsubmitid = 0;
        if ($lastrun) {
            $lastsubmitid = end($lastrun)->lastsubmissionid;
        }
        // Fetch all assignment submissions updated after last run that has detected badly converted assignment submission.
        $records = $DB->get_records_sql('SELECT *
                                               FROM {assign_submission}
                                              WHERE id > :id
                                           ORDER BY timecreated
                                                ASC LIMIT :num', ['id' => $lastsubmitid, 'num' => NUMBER_OF_EACH_RUN]);
        $detectednum = 0;
        $run = new \stdClass();

        if (count($records) > 0) { // If we still have not finished all submission check.
            $run->lastsubmissionid = end($records)->id;
            foreach ($records as $submission) {
                $detectedsubmission = $this->detected_submission($submission);
                if ($detectedsubmission != null) {
                    $pdfwitherror = $this->check_submission_combined_pdf($submission);
                    $detected = $DB->get_record('tool_pdfdetect_assigns', array('submissionid' => $submission->id));
                    $detected->submitted = $submission->timemodified;
                    if ($pdfwitherror != null) {
                        $detected->detected = $pdfwitherror->detected;
                    } else {
                        $detected->fixed = true;
                    }
                    $DB->update_record('tool_pdfdetect_assigns', $detected);
                } else {
                    $pdfwitherror = $this->check_submission_combined_pdf($submission);
                    if ($pdfwitherror != null) {
                        $detectednum++;
                        $DB->insert_record('tool_pdfdetect_assigns', $pdfwitherror);
                    }
                }
            }
        } else {
            // Reset to the top of the submission list.
            $run->lastsubmissionid = 0;
        }
        $run->runtime = time();
        $run->detectednumber = $detectednum;
        $DB->insert_record('tool_pdfdetect_runs', $run);
    }

    private function get_pdf_file_for_assignment($assignment, $submission) {
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

    private function detected_submission($submission) {
        global $DB;

        $params = ['submissionid' => $submission->id];
        $select = $DB->sql_compare_text('submissionid') . ' = ' . $DB->sql_compare_text(':submissionid');
        $detectedsubmission = $DB->get_record_select('tool_pdfdetect_assigns', $select, $params);

        if (!empty($detectedsubmission)) {
            return $detectedsubmission;
        }

        return null;
    }

    private function check_submission_combined_pdf($submission) {
        global $DB;

        $cm = \get_coursemodule_from_instance('assign', $submission->assignment, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $assignment = new \assign($context, null, null);

        $pdf = $this->get_pdf_file_for_assignment($assignment, $submission);
        $user = $DB->get_record('user', array('id' => $submission->userid), '*', MUST_EXIST);

        if ($pdf) {
            try {
                $tmppdfpath = $pdf->copy_content_to_temp();
                $fpdf = new \fpdi_pdf_parser($tmppdfpath);
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
                $pdfwitherror->fixed = false;

                return $pdfwitherror;
            }
            unlink($tmppdfpath);
            return null;
        }
    }
}
