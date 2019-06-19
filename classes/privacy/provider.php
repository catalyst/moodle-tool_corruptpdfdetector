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
 * Privacy Subsystem implementation for tool_corruppdfdetector.
 *
 * @package    tool_corruptpdfdetector
 * @copyright  2019 John Yao <johnyao@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_corruptpdfdetector\privacy;

use core_privacy\local\metadata\collection;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for tool_corruppdfdetector implementing null_provider.
 *
 * @copyright  2019 John Yao <johnyao@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider
{

    /**
     * Get the language string identifier with the component's language
     * file to explain why this plugin stores no data.
     *
     * @param   collection $collection
     * @return  collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'tool_pdfdetect_assigns',
            [
                'userid' => 'privacy:metadata:tool_pdfdetect_assigns:userid',
                'discussionid' => 'privacy:metadata:tool_pdfdetect_assigns:email',
                'preference' => 'privacy:metadata:tool_pdfdetect_assigns:userfullname',

            ],
            'privacy:metadata:tool_pdfdetect_assigns'
        );

        return $collection;
    }
}
