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
 * CargoSchool: the reservation (mod_reservation) a certificate refers to.
 *
 * One place for the rules shared by the CargoSchool elements of LIVE courses
 * (reservationdates, datediff, resevationteachername, resevationsignature):
 *
 *  - only requests that are not cancelled count (rr.timecancelled = 0): an absent
 *    student has the request cancelled (mod_reservation, CargoSchool mod A);
 *  - among them the most recent session wins (r.timestart), considering only the
 *    sessions started before the certificate was issued (a PDF downloaded again
 *    later must not take the dates of a newer booking in the same course);
 *  - for the instructor, sessions without teachers are skipped and the first teacher
 *    of the session wins; elements that hide the director ask instead for the first
 *    teacher who is not the director (the director only when he is the only one).
 *
 * If mod_reservation is not installed every method answers "nothing".
 *
 * @package    mod_customcert
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reservation {
    /** @var array|null Director user ids, cached while the setting does not change. */
    protected static $directorids = null;

    /** @var string|null Setting the cached director ids were computed from. */
    protected static $directorsetting = null;

    /** @var bool|null Whether mod_reservation tables exist, cached for the request. */
    protected static $available = null;

    /**
     * Is mod_reservation installed?
     *
     * @return bool
     */
    public static function is_available(): bool {
        global $DB;

        if (self::$available === null) {
            self::$available = $DB->get_manager()->table_exists('reservation')
                && $DB->get_manager()->table_exists('reservation_request');
        }
        return self::$available;
    }

    /**
     * The most recent session of the user in the course, absences and cancellations excluded.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $before only sessions started before this time (0 = no limit)
     * @return \stdClass|null fields: requestid, reservationid, timestart, timeend, teachers, grade
     */
    public static function get_session(int $userid, int $courseid, int $before = 0): ?\stdClass {
        global $DB;

        if (!self::is_available() || $userid <= 0 || $courseid <= 0) {
            return null;
        }

        $beforesql = $before > 0 ? 'AND r.timestart <= :before' : '';
        $sql = "SELECT rr.id AS requestid, r.id AS reservationid, r.timestart, r.timeend, r.teachers, rr.grade
                  FROM {reservation_request} rr
                  JOIN {reservation} r ON r.id = rr.reservation
                 WHERE rr.userid = :userid
                   AND r.course = :courseid
                   AND rr.timecancelled = 0
                   $beforesql
              ORDER BY r.timestart DESC, rr.timecreated DESC, rr.id DESC";
        $params = ['userid' => $userid, 'courseid' => $courseid, 'before' => $before];
        $records = $DB->get_records_sql($sql, $params, 0, 1);

        return $records ? reset($records) : null;
    }

    /**
     * Start and end of the session (end = start when the reservation has no end).
     *
     * @param int $userid
     * @param int $courseid
     * @param int $before only sessions started before this time (0 = no limit)
     * @return array|null [start, end] timestamps, or null without a session
     */
    public static function get_dates(int $userid, int $courseid, int $before = 0): ?array {
        $session = self::get_session($userid, $courseid, $before);
        if (!$session || empty($session->timestart)) {
            return null;
        }
        $start = (int)$session->timestart;
        $end = !empty($session->timeend) ? (int)$session->timeend : $start;
        if ($end < $start) {
            $end = $start;
        }
        return [$start, $end];
    }

    /**
     * The instructor of the user's session in the course.
     *
     * @param int $userid
     * @param int $courseid
     * @param bool $skipdirector prefer a teacher who is not the director (elements that hide the director)
     * @param int $before only sessions started before this time (0 = no limit)
     * @return \stdClass|null user record, or null when no instructor can be found
     */
    public static function get_teacher(int $userid, int $courseid, bool $skipdirector = false, int $before = 0): ?\stdClass {
        global $DB;

        if (!self::is_available() || $userid <= 0 || $courseid <= 0) {
            return null;
        }

        $notempty = $DB->sql_isnotempty('reservation', 'r.teachers', false, false);
        $beforesql = $before > 0 ? 'AND r.timestart <= :before' : '';
        $sql = "SELECT rr.id, r.teachers
                  FROM {reservation_request} rr
                  JOIN {reservation} r ON r.id = rr.reservation
                 WHERE rr.userid = :userid
                   AND r.course = :courseid
                   AND rr.timecancelled = 0
                   AND $notempty
                   $beforesql
              ORDER BY r.timestart DESC, rr.timecreated DESC, rr.id DESC";
        $params = ['userid' => $userid, 'courseid' => $courseid, 'before' => $before];
        $records = $DB->get_records_sql($sql, $params, 0, 1);
        if (!$records) {
            return null;
        }
        $record = reset($records);

        $director = null;
        foreach (explode(',', (string)$record->teachers) as $teacherid) {
            $teacherid = (int)trim($teacherid);
            if ($teacherid <= 0) {
                continue;
            }
            $teacher = $DB->get_record('user', ['id' => $teacherid, 'deleted' => 0]);
            if (!$teacher) {
                continue;
            }
            if (!$skipdirector || !self::is_director($teacherid)) {
                return $teacher;
            }
            if ($director === null) {
                $director = $teacher;
            }
        }

        return $director;
    }

    /**
     * Reference time of a certificate: when it was issued to the user, otherwise now.
     *
     * A certificate PDF is generated again at every download; with this limit an old
     * certificate keeps the session it was issued for.
     *
     * @param int $pageid page of the certificate template the element belongs to
     * @param int $userid
     * @return int
     */
    public static function reference_time(int $pageid, int $userid): int {
        global $DB;

        $sql = "SELECT MIN(ci.timecreated)
                  FROM {customcert_pages} p
                  JOIN {customcert} c ON c.templateid = p.templateid
                  JOIN {customcert_issues} ci ON ci.customcertid = c.id
                 WHERE p.id = :pageid AND ci.userid = :userid";
        $issued = (int)$DB->get_field_sql($sql, ['pageid' => $pageid, 'userid' => $userid]);

        return $issued > 0 ? $issued : time();
    }

    /**
     * Is this user the school director (setting customcert/cargoschooldirectors)?
     *
     * @param int $userid
     * @return bool
     */
    public static function is_director(int $userid): bool {
        return in_array($userid, self::get_director_ids(), true);
    }

    /**
     * User ids of the usernames written in the setting customcert/cargoschooldirectors.
     *
     * @return int[]
     */
    public static function get_director_ids(): array {
        global $DB, $CFG;

        $setting = get_config('customcert', 'cargoschooldirectors');
        if ($setting === false) {
            // Never saved: the default of settings.php.
            $setting = 'g.proietti@cargoschool.com';
        }
        $setting = (string)$setting;
        if (self::$directorids !== null && self::$directorsetting === $setting) {
            return self::$directorids;
        }

        self::$directorids = [];
        self::$directorsetting = $setting;
        $usernames = [];
        foreach (preg_split('/[\s,;]+/', (string)$setting) as $username) {
            $username = \core_text::strtolower(trim($username));
            if ($username !== '') {
                $usernames[$username] = $username;
            }
        }
        if ($usernames) {
            [$insql, $params] = $DB->get_in_or_equal(array_values($usernames), SQL_PARAMS_NAMED);
            $params['mnethostid'] = $CFG->mnet_localhost_id;
            $ids = $DB->get_fieldset_select(
                'user',
                'id',
                "deleted = 0 AND mnethostid = :mnethostid AND username $insql",
                $params
            );
            self::$directorids = array_map('intval', $ids);
        }

        return self::$directorids;
    }

    /**
     * Forget the cached values (tests, long CLI runs after a setting change).
     */
    public static function reset_caches(): void {
        self::$directorids = null;
        self::$directorsetting = null;
        self::$available = null;
    }
}
