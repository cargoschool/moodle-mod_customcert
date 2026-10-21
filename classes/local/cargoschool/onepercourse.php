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
 * CargoSchool (mod F): one certificate per student per enrolment in a course.
 *
 * A course can hold several certificate activities (old and new editions, one per client,
 * the single LIVE certificate). Without this rule the issue task issues and emails every one
 * of them to the same student. With the setting customcert/cargoschoolonepercourse (on by
 * default) the task does not issue a certificate to a student who already has a certificate
 * of another activity of the same course issued since the start of his current enrolment.
 * A student enrolled again for a new cycle (new enrolment start) can get the new certificate.
 *
 * @package    mod_customcert
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class onepercourse {
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
     * Start of the user's current enrolment in the course: the latest start among the active
     * enrolments (start date, or creation when there is no start date); 0 without enrolments.
     *
     * @param int $courseid
     * @param int $userid
     * @return int
     */
    public static function enrolment_start(int $courseid, int $userid): int {
        global $DB;
        $sql = "SELECT MAX(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END)
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid AND ue.userid = :userid
                   AND ue.status = :uestatus AND e.status = :estatus";
        return (int)$DB->get_field_sql($sql, ['courseid' => $courseid, 'userid' => $userid,
            'uestatus' => ENROL_USER_ACTIVE, 'estatus' => ENROL_INSTANCE_ENABLED]);
    }

    /**
     * The other certificate already issued to the user in the same course for this enrolment, if any.
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
            'since' => self::enrolment_start($courseid, $userid)];
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
