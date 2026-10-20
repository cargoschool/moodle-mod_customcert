<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
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
 * Strings for component 'customcertelement_reservationdates', language 'en'.
 *
 * @package    customcertelement_reservationdates
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['dates_oneday'] = 'on {$a->startmonth} {$a->startday}, {$a->startyear}';
$string['dates_otheryear'] = 'from {$a->startmonth} {$a->startday}, {$a->startyear} to {$a->endmonth} {$a->endday}, {$a->endyear}';
$string['dates_sameyear'] = 'from {$a->startmonth} {$a->startday} to {$a->endmonth} {$a->endday}, {$a->endyear}';
$string['datesnote'] = 'Prints the start and the end of the student\'s session (most recent session, absences excluded), for example "from October 12 to October 15, 2026 - 32 hours". Nothing is printed for a student without a session in this course.';
$string['dateswithhours'] = '{$a->dates} - {$a->hours}';
$string['hours_many'] = '{$a} hours';
$string['hours_one'] = '{$a} hour';
$string['hoursfield'] = 'Course field with the hours';
$string['hoursfield_help'] = 'Shortname of the course custom field that contains the hours of the course. A number is printed as "32 hours"; any other text is printed as it is. If the field is empty, the hours are not printed.';
$string['pluginname'] = 'Reservation dates (session)';
$string['privacy:metadata'] = 'The Reservation dates plugin does not store any personal data.';
$string['showhours'] = 'Show the hours of the course';
