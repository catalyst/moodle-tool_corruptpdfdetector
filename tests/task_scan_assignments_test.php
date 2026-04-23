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

namespace tool_corruptpdfdetector;

use tool_corruptpdfdetector\task\scan_assignments;

/**
 * Unit tests for the scan_assignments scheduled task.
 * These tests cover the database-interaction logic of the task without
 * requiring real PDF files or a fully configured assignment grading workflow.
 * The private detected_submission() method is exercised via reflection.
 *
 * @covers    \tool_corruptpdfdetector\task\scan_assignments
 * @package   tool_corruptpdfdetector
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class task_scan_assignments_test extends \advanced_testcase {
    /**
     * Insert a row into tool_corruptpdfdetector_runs.
     *
     * @param int $lastsubmissionid Last submission id stored by the run.
     * @param int|null $runtime UNIX timestamp; defaults to current time.
     *
     * @return int Inserted record ID.
     */
    private function insert_run_record(int $lastsubmissionid, ?int $runtime = null): int {
        global $DB;

        return $DB->insert_record('tool_corruptpdfdetector_runs', (object)[
            'lastsubmissionid' => $lastsubmissionid,
            'runtime'          => $runtime ?? time(),
            'detectednumber'   => 0,
        ]);
    }

    /**
     * Insert a row into tool_corruptpdfdetector_assigns.
     *
     * @param int $submissionid Submission id to store.
     * @param string $contenthash Optional content hash; derived from submissionid if omitted.
     *
     * @return int Inserted record ID.
     */
    private function insert_assigns_record(int $submissionid, string $contenthash = ''): int {
        global $DB;

        return $DB->insert_record('tool_corruptpdfdetector_assigns', (object)[
            'assignid'     => 1,
            'submissionid' => $submissionid,
            'coursename'   => 'Test course',
            'assignname'   => 'Test assignment',
            'userfullname' => 'Test User',
            'email'        => 'test@example.com',
            'filename'     => $contenthash ?: sha1((string) $submissionid),
            'message'      => 'Corrupt PDF detected',
            'submitted'    => time(),
            'detected'     => time(),
            'fixed'        => 0,
        ]);
    }

    /**
     * Invoke the private detected_submission() method via reflection.
     *
     * @param \tool_corruptpdfdetector\task\scan_assignments $task Task instance.
     * @param \stdClass $submission Minimal object with an `id` property.
     * @return \stdClass|null
     */
    private function call_detected_submission(
        scan_assignments $task,
        \stdClass $submission
    ): ?\stdClass {
        $method = new \ReflectionMethod(
            scan_assignments::class,
            'detected_submission'
        );

        $method->setAccessible(true);
        return $method->invoke($task, $submission);
    }

    /**
     * Verify that get_name() returns the expected localised string.
     */
    public function test_task_name(): void {
        $task = new scan_assignments();
        $this->assertEquals(
            get_string('task_scan_assignments', 'tool_corruptpdfdetector'),
            $task->get_name()
        );
    }

    /**
     * When there are no assign_submission rows and no prior run, execute() must
     * create exactly one run record with lastsubmissionid = 0.
     */
    public function test_execute_with_no_submissions_creates_initial_run_record(): void {
        global $DB;
        $this->resetAfterTest();

        $this->assertEquals(0, $DB->count_records('assign_submission'));
        $this->assertEquals(0, $DB->count_records('tool_corruptpdfdetector_runs'));

        $task = new scan_assignments();
        $task->execute();

        $runs = $DB->get_records('tool_corruptpdfdetector_runs');
        $this->assertCount(1, $runs, 'Exactly one run record should be created.');

        $run = reset($runs);
        $this->assertEquals(
            0,
            $run->lastsubmissionid,
            'lastsubmissionid should be 0 when no submissions exist.'
        );
        $this->assertEquals(
            0,
            $run->detectednumber,
            'detectednumber should be 0 when no submissions were scanned.'
        );
        $this->assertGreaterThan(
            0,
            $run->runtime,
            'runtime should be populated with a UNIX timestamp.'
        );
    }

    /**
     * Each call to execute() must produce its own run record; multiple runs accumulate.
     */
    public function test_execute_creates_a_new_run_record_on_each_invocation(): void {
        global $DB;
        $this->resetAfterTest();

        $task = new scan_assignments();
        $task->execute();
        $task->execute();

        $this->assertEquals(
            2,
            $DB->count_records('tool_corruptpdfdetector_runs'),
            'Two executions should produce two run records.'
        );
    }

    /**
     * When all submissions have already been processed (last run id is higher than
     * any current submission id), execute() must reset lastsubmissionid to 0.
     */
    public function test_execute_resets_last_submission_id_when_all_processed(): void {
        global $DB;
        $this->resetAfterTest();

        $this->insert_run_record(PHP_INT_MAX);

        $task = new scan_assignments();
        $task->execute();

        $runs = $DB->get_records('tool_corruptpdfdetector_runs', [], 'id DESC', '*', 0, 1);
        $latestrun = reset($runs);

        $this->assertEquals(
            0,
            $latestrun->lastsubmissionid,
            'lastsubmissionid should be reset to 0 when no new submissions are found.'
        );
    }

    /**
     * When multiple prior run records exist, execute() must use the one with the
     * most recent runtime (ORDER BY runtime DESC LIMIT 1) to determine the starting
     * submission id.  When that id is very high, no submissions are found and the
     * next run resets to lastsubmissionid = 0.
     */
    public function test_execute_selects_latest_run_by_runtime(): void {
        global $DB;
        $this->resetAfterTest();

        $oldtime = time() - 7200;
        $recenttime = time() - 60;

        $this->insert_run_record(10, $oldtime);
        $this->insert_run_record(PHP_INT_MAX, $recenttime);

        $task = new scan_assignments();
        $task->execute();

        $runs = $DB->get_records('tool_corruptpdfdetector_runs', [], 'id DESC', '*', 0, 1);
        $latestrun = reset($runs);

        $this->assertEquals(
            0,
            $latestrun->lastsubmissionid,
            'Should use the most recent run by runtime, resulting in a reset to 0.'
        );
    }

    /**
     * detected_submission() must return null for a submission id that has no matching
     * row in tool_corruptpdfdetector_assigns.
     */
    public function test_detected_submission_returns_null_for_unknown_submission(): void {
        $this->resetAfterTest();

        $task = new scan_assignments();
        $result = $this->call_detected_submission($task, (object)['id' => 99999]);

        $this->assertNull(
            $result,
            'detected_submission() should return null when submission is not in the assigns table.'
        );
    }

    /**
     * detected_submission() must return the matching record when the submission id
     * exists in tool_corruptpdfdetector_assigns.
     */
    public function test_detected_submission_returns_record_for_known_submission(): void {
        $this->resetAfterTest();

        $submissionid = 12345;
        $this->insert_assigns_record($submissionid);

        $task = new scan_assignments();
        $result = $this->call_detected_submission($task, (object)['id' => $submissionid]);

        $this->assertNotNull(
            $result,
            'detected_submission() should return a record when the submission id is found.'
        );
        $this->assertEquals(
            $submissionid,
            $result->submissionid,
            'Returned record should have the correct submissionid.'
        );
    }

    /**
     * detected_submission() must return null after the assigns record for a given
     * submission has been deleted (ensures there is no stale in-process caching).
     */
    public function test_detected_submission_returns_null_after_record_deleted(): void {
        global $DB;
        $this->resetAfterTest();

        $submissionid = 54321;
        $id = $this->insert_assigns_record($submissionid);

        $task = new scan_assignments();

        // Verify the record is found before deletion.
        $this->assertNotNull(
            $this->call_detected_submission($task, (object)['id' => $submissionid])
        );

        // Delete the record and verify it is no longer found.
        $DB->delete_records('tool_corruptpdfdetector_assigns', ['id' => $id]);

        $this->assertNull(
            $this->call_detected_submission($task, (object)['id' => $submissionid]),
            'detected_submission() should return null after the DB record is removed.'
        );
    }

    /**
     * detected_submission() must match on the submissionid column, not on the
     * assigns table primary key id.
     */
    public function test_detected_submission_matches_on_submissionid_not_primary_id(): void {
        $this->resetAfterTest();

        $this->insert_assigns_record(111);

        $task = new scan_assignments();

        // Looking up submissionid = 111 should succeed.
        $this->assertNotNull(
            $this->call_detected_submission($task, (object)['id' => 111])
        );

        // A different id that is not a submissionid in the table should return null.
        $this->assertNull(
            $this->call_detected_submission($task, (object)['id' => 222]),
            'detected_submission() must not confuse the primary key with submissionid.'
        );
    }
}
