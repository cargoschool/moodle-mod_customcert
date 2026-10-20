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
 * CargoSchool (mod E): the own completion of the certificate activity in automatic issuing.
 *
 * Up to 4.4.13 the issue task skipped the users who had not completed the certificate activity
 * itself (for example "view the activity" or manual completion). 4.4.16 removed the check (#1020):
 * the task issues to everybody who meets the access restrictions. On the CargoSchool production
 * site that would send at once the certificates of every student who passed a course and never
 * opened the certificate. The setting customcert/cargoschoolowncompletion (on by default) keeps
 * the 4.4.13 behaviour; backlog() lists, certificate by certificate, the students who would receive
 * the certificate with the setting off, so that the certificates can be put in order first.
 *
 * @package    mod_customcert
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class owncompletion {
    /**
     * Is the 4.4.13 check on (setting customcert/cargoschoolowncompletion, on when never saved)?
     *
     * @return bool
     */
    public static function enabled(): bool {
        $setting = get_config('customcert', 'cargoschoolowncompletion');
        return $setting === false || !empty($setting);
    }

    /**
     * Has the user completed the certificate activity itself? Same code as the 4.4.13 task.
     *
     * @param \completion_info $completion
     * @param object $cm
     * @param int $userid
     * @return bool
     */
    public static function is_met(\completion_info $completion, object $cm, int $userid): bool {
        if (!$completion->is_enabled($cm)) {
            return true;
        }
        $data = $completion->get_data($cm, false, $userid);
        return in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
    }

    /**
     * Students who would get a new certificate if the check were off, certificate by certificate.
     *
     * Same selection as the issue task (all the certificates, not only one run), read only.
     * Only users without an issue are counted: those are the ones who would be issued and emailed.
     *
     * @param bool $withusers also return the user ids
     * @param int $courseid only this course (0 = all)
     * @return array of objects: customcertid, name, courseid, course, processed (bool), reason, count, issued, userids
     */
    public static function backlog(bool $withusers = false, int $courseid = 0): array {
        global $DB;

        $includeinnotvisiblecourses = (bool)get_config('customcert', 'includeinnotvisiblecourses');
        $lengthsql = $DB->sql_length('c.emailothers');
        $params = ['emailstudents' => 1, 'emailteachers' => 1];
        $where = "(c.emailstudents = :emailstudents OR c.emailteachers = :emailteachers OR $lengthsql >= 3)";
        if ($courseid) {
            $where .= " AND c.course = :courseid";
            $params['courseid'] = $courseid;
        }
        $sql = "SELECT c.id, c.name, c.course, c.requiredtime, c.emailstudents, c.issueautomatically, c.emailothers,
                       ct.contextid, co.shortname AS courseshortname, co.visible AS coursevisible,
                       cat.visible AS categoryvisible
                  FROM {customcert} c
                  JOIN {customcert_templates} ct ON c.templateid = ct.id
                  JOIN {course} co ON c.course = co.id
             LEFT JOIN {course_categories} cat ON co.category = cat.id
                 WHERE $where
              ORDER BY co.shortname, c.name";
        $result = [];
        foreach ($DB->get_records_sql($sql, $params) as $customcert) {
            $row = (object)[
                'customcertid' => (int)$customcert->id, 'name' => $customcert->name,
                'courseid' => (int)$customcert->course, 'course' => $customcert->courseshortname,
                'processed' => true, 'reason' => '', 'count' => 0, 'userids' => [],
                'issued' => $DB->count_records('customcert_issues', ['customcertid' => $customcert->id]),
            ];
            $result[] = $row;

            $cm = get_course_and_cm_from_instance($customcert->id, 'customcert', $customcert->course)[1];
            if (!$cm->visible) {
                $row->processed = false;
                $row->reason = 'attività nascosta';
                continue;
            }
            if (
                !$includeinnotvisiblecourses && (!$customcert->coursevisible
                    || ($customcert->categoryvisible !== null && !$customcert->categoryvisible))
            ) {
                $row->processed = false;
                $row->reason = 'corso o categoria nascosti';
                continue;
            }
            $autoissue = !empty($customcert->emailstudents) || !empty($customcert->issueautomatically)
                || trim((string)($customcert->emailothers ?? '')) !== '';
            if (!$autoissue) {
                $row->reason = 'nessuna emissione automatica';
                continue;
            }
            $completion = new \completion_info(get_course((int)$customcert->course));
            if (!$completion->is_enabled($cm)) {
                $row->reason = 'nessun completamento proprio';
                continue;
            }

            $cmcontext = \context_module::instance($cm->id);
            $userswithissue = get_users_by_capability($cmcontext, 'mod/customcert:receiveissue');
            $userswithview = get_users_by_capability($cmcontext, 'mod/customcert:view');
            $users = array_intersect_key($userswithissue, $userswithview);
            $users = (new \core_availability\info_module($cm))->filter_user_list($users);
            $issued = $DB->get_records_menu('customcert_issues', ['customcertid' => $customcert->id], '', 'userid, id');

            foreach ($users as $user) {
                if (!empty($user->suspended) || isset($issued[$user->id])) {
                    continue;
                }
                $usercm = get_fast_modinfo($customcert->course, $user->id)->instances['customcert'][$customcert->id];
                if (!$usercm->uservisible) {
                    continue;
                }
                if (
                    !empty($customcert->requiredtime) && \mod_customcert\certificate::get_course_time(
                        $customcert->course,
                        $user->id
                    ) < ($customcert->requiredtime * 60)
                ) {
                    continue;
                }
                if (self::is_met($completion, $usercm, (int)$user->id)) {
                    continue;
                }
                $row->count++;
                if ($withusers) {
                    $row->userids[] = (int)$user->id;
                }
            }
        }
        return $result;
    }
}
