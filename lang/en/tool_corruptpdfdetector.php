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
 * Corrupt pdf detector
 *
 * @package    tool_corruptpdfdetector
 * @author     John Yao <johnyao@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


$string['table_assign_headers'] = 'Detected Assignments';
$string['name'] = 'Corrupt PDF assignment Detetor';
$string['h1_current'] = 'Detected corrupt pdf submissions';
$string['lastrun'] = 'Last run';
$string['lastrundesc'] = 'Only compare the assignment submissions updated after this time';
$string['courseheader'] = 'Course';
$string['assignmentheader'] = 'Assignment';
$string['userfullnameheader'] = 'Student name';
$string['submittedheader'] = 'Last modified';
$string['reasonheader'] = 'Reason';
$string['pluginname'] = 'Corrupt pdf assignment finder';
$string['detectedheader'] = 'Detected';
$string['task_scan_assignments'] = 'Scan assignments task';
$string['privacy:metadata:tool_pdfdetect_assigns'] = 'Information about the assignment that found corrupt pdf file. This includes the course, assignment name, student name and email.';
$string['privacy:metadata:tool_pdfdetect_assigns:userid'] = 'The ID of the user with this assignment.';
$string['privacy:metadata:tool_pdfdetect_assigns:email'] = 'The email of the user who submits the assignment.';
$string['privacy:metadata:tool_pdfdetect_assigns:userfullname'] = 'The full name of the user who submits the assignment.';
