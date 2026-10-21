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

namespace mod_customcert\local\cargoschool;

/**
 * CargoSchool (mod F): one certificate per student in a course, within a window of months.
 *
 * A course can hold several certificate activities (old and new editions, one per client,
 * the single LIVE certificate). Without this rule the issue task issues and emails every one
 * of them to the same student. With the setting customcert/cargoschoolonepercourse (on by
 * default) the task does not issue a certificate to a student who already has a certificate
 * of another activity of the same course issued in the last customcert/cargoschoolonepercoursemonths
 * months (20 by default, 0 = ever). A student who takes the course again for the renewal, about
 * two years later, gets the new certificate.
 *
 * Until 4.4.16-cargoschool.4 the window started at the user's current enrolment: on the CargoSchool
 * sites enrolments are created again or their dates moved without a new cycle, so old certificates
 * looked like a previous cycle and the task issued duplicates (staging, 21/10/2026).
 *
 * @package    mod_customcert
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class onepercourse {
    /** Default window in months. */
    public const DEFAULT_MONTHS = 20;

    /**
     * Is the rule on (setting customcert/cargoschoolonepercourse, on when never saved)?
     *
     * @return bool
     */
    public static function enabled(): bool {
        $setting = get_config('customcert', 'cargoschoolonepercourse');
        return $setting === false || !empty($setting);
    }

    /**
     * Window in months (setting customcert/cargoschoolonepercoursemonths; 0 = ever).
     *
     * @return int
     */
    public static function months(): int {
        $setting = get_config('customcert', 'cargoschoolonepercoursemonths');
        if ($setting === false || $setting === '' || !is_numeric($setting)) {
            return self::DEFAULT_MONTHS;
        }
        return max(0, (int)$setting);
    }

    /**
     * Oldest issue date that still counts, 0 when every issue counts.
     *
     * @param int $now
     * @return int
     */
    public static function since(int $now = 0): int {
        $months = self::months();
        if ($months === 0) {
            return 0;
        }
        $date = new \DateTime('@' . ($now ?: time()));
        $date->modify('-' . $months . ' months');
        return $date->getTimestamp();
    }

    /**
     * The other certificate already issued to the user in the same course within the window, if any.
     *
     * @param int $customcertid the certificate about to be issued
     * @param int $courseid
     * @param int $userid
     * @return \stdClass|null issue id, customcertid, name, timecreated
     */
    public static function other_issue(int $customcertid, int $courseid, int $userid): ?\stdClass {
        global $DB;
        $sql = "SELECT ci.id, ci.customcertid, c.name, ci.timecreated
                  FROM {customcert_issues} ci
                  JOIN {customcert} c ON c.id = ci.customcertid
                 WHERE c.course = :courseid AND ci.userid = :userid AND ci.customcertid <> :customcertid
                   AND ci.timecreated >= :since
              ORDER BY ci.timecreated DESC, ci.id DESC";
        $params = ['courseid' => $courseid, 'userid' => $userid, 'customcertid' => $customcertid,
            'since' => self::since()];
        $records = $DB->get_records_sql($sql, $params, 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Must the task skip this user for this certificate?
     *
     * @param int $customcertid
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function blocks(int $customcertid, int $courseid, int $userid): bool {
        return self::enabled() && self::other_issue($customcertid, $courseid, $userid) !== null;
    }
}
