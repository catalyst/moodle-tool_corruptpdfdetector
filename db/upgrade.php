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
 * Upgrade logic.
 *
 * @package   tool_corruptpdfdetector
 * @copyright Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Performs data migrations and updates on upgrade.
 *
 * @param   integer   $oldversion
 * @return  boolean
 */
function xmldb_tool_corruptpdfdetector_upgrade($oldversion = 0) {
    global $CFG, $DB;

    require_once($CFG->libdir . '/db/upgradelib.php'); // Core Upgrade-related functions.

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2026042301) {
        // Rename table tool_pdfdetect_assigns to tool_corruptpdfdetector_assigns.
        $table = new xmldb_table('tool_pdfdetect_assigns');

        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'tool_corruptpdfdetector_assigns');
        }

        // Rename table tool_pdfdetect_runs to tool_corruptpdfdetector_runs.
        $table = new xmldb_table('tool_pdfdetect_runs');

        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'tool_corruptpdfdetector_runs');
        }

        // Corruptpdfdetector savepoint reached.
        upgrade_plugin_savepoint(true, 2026042301, 'tool', 'corruptpdfdetector');
    }

    return true;
}
