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

use tool_corruptpdfdetector\task\fix_assignments;
/**
 * Unit tests for the fix_assignments scheduled task.
 *
 * @covers    \tool_corruptpdfdetector\task\fix_assignments
 * @package   tool_corruptpdfdetector
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class task_fix_assignments_test extends \advanced_testcase {
    /**
     * Helper: insert a minimal record into tool_corruptpdfdetector_assigns.
     *
     * @param string $contenthash SHA-1 hash stored in the filename column.
     * @param bool $fixed Whether the record is already fixed.
     * @return int Inserted record ID.
     */
    private function insert_assigns_record(string $contenthash, bool $fixed = false): int {
        global $DB;

        $record = (object)[
            'assignid'     => 1,
            'submissionid' => random_int(1, 99999),
            'coursename'   => 'Test course',
            'assignname'   => 'Test assignment',
            'userfullname' => 'Test User',
            'email'        => 'test@example.com',
            'filename'     => $contenthash,
            'message'      => 'Corrupt PDF detected',
            'submitted'    => time(),
            'detected'     => time(),
            'fixed'        => (int) $fixed,
        ];

        return $DB->insert_record('tool_corruptpdfdetector_assigns', $record);
    }

    /**
     * Helper: insert a minimal record into the files table with filename = 'combined.pdf'.
     *
     * @param string $contenthash Content hash matching the filename column in assigns.
     * @param string $filearea 'combined' or 'partial'.
     * @return int Inserted record ID.
     */
    private function insert_file_record(string $contenthash, string $filearea = 'combined'): int {
        global $DB;

        $record = (object)[
            'contenthash'     => $contenthash,
            'pathnamehash'    => sha1(uniqid('', true)),
            'contextid'       => \context_system::instance()->id,
            'component'       => 'assignfeedback_editpdf',
            'filearea'        => $filearea,
            'itemid'          => 1,
            'filepath'        => '/',
            'filename'        => 'combined.pdf',
            'userid'          => 0,
            'filesize'        => 12345,
            'mimetype'        => 'application/pdf',
            'status'          => 0,
            'source'          => null,
            'author'          => null,
            'license'         => null,
            'timecreated'     => time(),
            'timemodified'    => time(),
            'sortorder'       => 0,
            'referencefileid' => null,
        ];

        return $DB->insert_record('files', $record);
    }

    /**
     * Verify that get_name() returns the expected localised string.
     */
    public function test_task_name(): void {
        $task = new fix_assignments();
        $this->assertEquals(
            get_string('task_fix_assignments', 'tool_corruptpdfdetector'),
            $task->get_name()
        );
    }

    /**
     * execute() should complete silently when there are no unfixed records.
     */
    public function test_execute_does_nothing_when_table_is_empty(): void {
        global $DB;
        $this->resetAfterTest();

        $task = new fix_assignments();
        $task->execute();

        $this->assertEquals(0, $DB->count_records('tool_corruptpdfdetector_assigns'));
    }

    /**
     * execute() should mark every unfixed record as fixed after running.
     */
    public function test_execute_marks_unfixed_records_as_fixed(): void {
        global $DB;
        $this->resetAfterTest();

        $id1 = $this->insert_assigns_record(sha1('content1'), false);
        $id2 = $this->insert_assigns_record(sha1('content2'), false);

        $task = new fix_assignments();
        $task->execute();

        $record1 = $DB->get_record('tool_corruptpdfdetector_assigns', ['id' => $id1]);
        $record2 = $DB->get_record('tool_corruptpdfdetector_assigns', ['id' => $id2]);

        $this->assertEquals(1, $record1->fixed, 'First record should be marked fixed.');
        $this->assertEquals(1, $record2->fixed, 'Second record should be marked fixed.');
    }

    /**
     * execute() should delete the combined.pdf file (filearea = 'combined') for each unfixed record.
     */
    public function test_execute_deletes_combined_pdf_file(): void {
        global $DB;
        $this->resetAfterTest();

        $contenthash = sha1('combinedcontent');
        $this->insert_assigns_record($contenthash, false);
        $fileid = $this->insert_file_record($contenthash, 'combined');

        $this->assertTrue(
            $DB->record_exists('files', ['id' => $fileid]),
            'File record should exist before task runs.'
        );

        $task = new fix_assignments();
        $task->execute();

        $this->assertFalse(
            $DB->record_exists('files', ['id' => $fileid]),
            'Combined PDF file record should be deleted after task runs.'
        );
    }

    /**
     * execute() should also delete the partial combined.pdf file (filearea = 'partial').
     */
    public function test_execute_deletes_partial_pdf_file(): void {
        global $DB;
        $this->resetAfterTest();

        $contenthash = sha1('partialcontent');
        $this->insert_assigns_record($contenthash, false);
        $fileid = $this->insert_file_record($contenthash, 'partial');

        $task = new fix_assignments();
        $task->execute();

        $this->assertFalse(
            $DB->record_exists('files', ['id' => $fileid]),
            'Partial PDF file record should be deleted after task runs.'
        );
    }

    /**
     * execute() must NOT delete files unrelated to the corrupt submission
     * (different contenthash, or a different filename in the same filearea).
     */
    public function test_execute_does_not_delete_unrelated_files(): void {
        global $DB;
        $this->resetAfterTest();

        $contenthash = sha1('targetcontent');
        $otherhash = sha1('othercontent');
        $this->insert_assigns_record($contenthash, false);

        // File with a different contenthash – must be preserved.
        $differenthashfileid = $this->insert_file_record($otherhash);

        // File with the right hash but a different filename – must be preserved.
        $differentnamerecord = (object)[
            'contenthash'     => $contenthash,
            'pathnamehash'    => sha1(uniqid('', true)),
            'contextid'       => \context_system::instance()->id,
            'component'       => 'assignfeedback_editpdf',
            'filearea'        => 'combined',
            'itemid'          => 1,
            'filepath'        => '/',
            'filename'        => 'other.pdf',
            'userid'          => 0,
            'filesize'        => 100,
            'mimetype'        => 'application/pdf',
            'status'          => 0,
            'source'          => null,
            'author'          => null,
            'license'         => null,
            'timecreated'     => time(),
            'timemodified'    => time(),
            'sortorder'       => 0,
            'referencefileid' => null,
        ];
        $differentnamefileid = $DB->insert_record('files', $differentnamerecord);

        $task = new fix_assignments();
        $task->execute();

        $this->assertTrue(
            $DB->record_exists('files', ['id' => $differenthashfileid]),
            'File with a different hash should not be deleted.'
        );
        $this->assertTrue(
            $DB->record_exists('files', ['id' => $differentnamefileid]),
            'File with a different filename should not be deleted.'
        );
    }

    /**
     * execute() should leave already-fixed records (fixed = 1) completely untouched.
     */
    public function test_execute_skips_already_fixed_records(): void {
        global $DB;
        $this->resetAfterTest();

        $contenthash = sha1('fixedcontent');
        $fixedid = $this->insert_assigns_record($contenthash, true);
        $fileid = $this->insert_file_record($contenthash);

        $task = new fix_assignments();
        $task->execute();

        $this->assertTrue(
            $DB->record_exists('files', ['id' => $fileid]),
            'File for an already-fixed record should not be deleted.'
        );

        $record = $DB->get_record('tool_corruptpdfdetector_assigns', ['id' => $fixedid]);
        $this->assertEquals(1, $record->fixed);
    }

    /**
     * execute() processes unfixed records while leaving already-fixed records intact.
     */
    public function test_execute_mixed_fixed_and_unfixed_records(): void {
        global $DB;
        $this->resetAfterTest();

        $unfixedhash = sha1('unfixedcontent');
        $fixedhash = sha1('alreadyfixedcontent');

        $unfixedid = $this->insert_assigns_record($unfixedhash);
        $fixedid = $this->insert_assigns_record($fixedhash, true);

        $unfixedfileid = $this->insert_file_record($unfixedhash);
        $fixedfileid = $this->insert_file_record($fixedhash);

        $task = new fix_assignments();
        $task->execute();

        // Unfixed record – should now be fixed, its file deleted.
        $unfixedrecord = $DB->get_record('tool_corruptpdfdetector_assigns', ['id' => $unfixedid]);
        $this->assertEquals(1, $unfixedrecord->fixed, 'Unfixed record should now be marked fixed.');
        $this->assertFalse(
            $DB->record_exists('files', ['id' => $unfixedfileid]),
            'File for the unfixed record should be deleted.'
        );

        // Already-fixed record – unchanged, file retained.
        $fixedrecord = $DB->get_record('tool_corruptpdfdetector_assigns', ['id' => $fixedid]);
        $this->assertEquals(1, $fixedrecord->fixed, 'Already-fixed record should remain fixed.');
        $this->assertTrue(
            $DB->record_exists('files', ['id' => $fixedfileid]),
            'File for the already-fixed record should not be deleted.'
        );
    }
}
