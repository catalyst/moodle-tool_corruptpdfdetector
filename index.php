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
 * Corrupt pdf assignment list.
 *
 * @package    tool_corruptpdfdetector
 * @author     John Yao <johnyao@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_capability('moodle/site:config', context_system::instance());
admin_externalpage_setup('tool_corruptpdfdetector');

$assignments = new tool_corruptpdfdetector\table\assignments;

echo $OUTPUT->header();
echo html_writer::tag('h1', get_string('h1_current', 'tool_corruptpdfdetector'));
echo html_writer::table($assignments);
echo html_writer::empty_tag('br');
echo html_writer::tag('p', get_string('error_percentage', 'tool_corruptpdfdetector') . $assignments->get_percentage());
echo $OUTPUT->footer();
