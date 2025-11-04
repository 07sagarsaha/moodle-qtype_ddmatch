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
 * Serve question type files.
 *
 * @package   qtype_ddmatch
 * @category  files
 * @copyright 2007 DualCube
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serves question files for the Drag-and-Drop Matching question type.
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param context  $context The context.
 * @param string   $filearea The file area.
 * @param array    $args Additional arguments.
 * @param bool     $forcedownload Whether to force the download.
 * @param array    $options Additional options.
 * @return void
 */
function qtype_ddmatch_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');

    question_pluginfile(
        $course,
        $context,
        'qtype_ddmatch',
        $filearea,
        $args,
        $forcedownload,
        $options
    );
}
