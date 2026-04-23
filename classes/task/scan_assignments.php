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

use stdClass;
use assign;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/feedback/editpdf/fpdi/autoload.php');

define("ONE", 1);
define("NUMBER_OF_EACH_RUN", 1000);

/**
 * Task to scan assignments.
 *
 * @package    tool_corruptpdfdetector
 * @author     John Yao <johnyao@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scan_assignments extends \core\task\scheduled_task {
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
                                              WHERE userid > 0
                                                AND id > :id
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
                    $detected = $DB->get_record('tool_pdfdetect_assigns', ['submissionid' => $submission->id]);
                    $detected->submitted = $submission->timemodified;
                    if ($pdfwitherror != null) {
                        $detected->detected = $pdfwitherror->detected;
                        $detected->filename = $pdfwitherror->filename;
                        $detected->message = $pdfwitherror->message;
                        $detected->submitted = $pdfwitherror->submitted;
                        $detected->fixed = false;
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

    /**
     * Retrieves the PDF file associated with a given assignment and submission.
     * This method fetches the combined or partial PDF file created for grading
     * a specific user's submission to an assignment, based on the grading context.
     *
     * @param assign $assignment The assignment instance from which the PDF file is retrieved.
     * @param stdClass $submission The submission object containing details such as userid and attempt number.
     *
     * @return \stored_file|null The stored file instance representing the PDF file, or null if no file exists.
     */
    private function get_pdf_file_for_assignment(assign $assignment, stdClass $submission) {
        $grade = $assignment->get_user_grade($submission->userid, true, $submission->attemptnumber);
        $contextid = $assignment->get_context()->id;
        $component = 'assignfeedback_editpdf';
        $itemid = $grade->id;
        $fs = get_file_storage();
        $pdfarea = 'combined';
        $pdfname = 'combined.pdf';
        $filepath = '/';

        $file = $fs->get_file($contextid, $component, $pdfarea, $itemid, $filepath, $pdfname);
        if ($file) {
            return $file;
        } else {
            return $fs->get_file($contextid, $component, 'partial', $itemid, $filepath, $pdfname);
        }
    }

    /**
     * Detects a previously recorded submission in the 'tool_pdfdetect_assigns' table.
     *
     * @param stdClass $submission The submission object containing the ID to look up in the database.
     *
     * @return stdClass|null The detected submission record if found, or null if no matching record exists.
     */
    private function detected_submission(stdClass $submission): ?stdClass {
        global $DB;

        $params = ['submissionid' => $submission->id];
        $select = $DB->sql_compare_text('submissionid') . ' = ' . $DB->sql_compare_text(':submissionid');
        $detectedsubmission = $DB->get_record_select('tool_pdfdetect_assigns', $select, $params);

        if (!empty($detectedsubmission)) {
            return $detectedsubmission;
        }

        return null;
    }

    /**
     * Validates the combined PDF of a submission by checking its structure and metadata.
     * Ensures the PDF has a correct header, body, and can be parsed without errors.
     *
     * @param stdClass $submission The submission object containing assignment and user details.
     *
     * @return stdClass|null An object with details of the PDF error and context if validation fails,
     *                       or null if no issues are found.
     */
    private function check_submission_combined_pdf(stdClass $submission): ?stdClass {
        global $DB;

        $cm = \get_coursemodule_from_instance('assign', $submission->assignment, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $assignment = new \assign($context, null, null);

        $pdf = $this->get_pdf_file_for_assignment($assignment, $submission);
        $user = $DB->get_record('user', ['id' => $submission->userid], '*', MUST_EXIST);

        if ($pdf) {
            try {
                $tmppdfpath = $pdf->copy_content_to_temp();
                // Confirm, that PDF has correct header which can be parsed to get the version.
                $source = fopen($tmppdfpath, 'r');
                $stream = new \setasign\Fpdi\PdfParser\StreamReader($source, true);
                $parser = new \setasign\Fpdi\PdfParser\PdfParser($stream);
                $parser->getPdfVersion();
                // Confirm, that PDF has correct body.
                $reader = new \setasign\Fpdi\PdfReader\PdfReader($parser);
                $reader->getPageCount();
                // If no exception has been thrown the file has a correct structure.
                unset($stream);
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
