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

namespace mod_customcert;

use mod_customcert\local\cargoschool\onepercourse;

/**
 * CargoSchool (mod F): one certificate per student per enrolment in a course.
 *
 * @package    mod_customcert
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\local\cargoschool\onepercourse
 * @covers     \mod_customcert\task\issue_certificates_task
 */
final class cargoschool_onepercourse_test extends \advanced_testcase {
    /**
     * A certificate sent to a company address, with one element.
     *
     * @param int $courseid
     * @param string $name
     * @return \stdClass
     */
    protected function add_certificate(int $courseid, string $name): \stdClass {
        global $DB;
        $customcert = $this->getDataGenerator()->create_module(
            'customcert',
            ['course' => $courseid, 'name' => $name, 'emailothers' => 'azienda@example.com']
        );
        $template = new template($DB->get_record('customcert_templates', ['id' => $customcert->templateid]));
        $pageid = $template->add_page();
        $DB->insert_record('customcert_elements', (object)['pageid' => $pageid, 'name' => 'Nome',
            'element' => 'studentname', 'data' => '', 'font' => 'times', 'fontsize' => 12, 'colour' => '#000000',
            'posx' => 10, 'posy' => 10, 'width' => 0, 'refpoint' => 0, 'alignment' => 'L', 'sequence' => 1,
            'timecreated' => time(), 'timemodified' => time()]);
        return $customcert;
    }

    /**
     * Run the issue task.
     */
    protected function run_task(): void {
        set_config('certificate_offset', 0, 'customcert');
        (new task\issue_certificates_task())->execute();
    }

    /**
     * The single certificate added to a course with an old certificate already issued.
     */
    public function test_no_second_certificate(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $sink = $this->redirectEmails();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $newstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $old = $this->add_certificate($course->id, 'ATTESTATO 2025');
        $DB->insert_record('customcert_issues', (object)['userid' => $student->id, 'customcertid' => $old->id,
            'code' => 'OLD1', 'emailed' => 1, 'timecreated' => time()]);
        // The old certificate is no longer sent: only the new one is processed.
        $DB->set_field('customcert', 'emailothers', '', ['id' => $old->id]);

        $new = $this->add_certificate($course->id, 'Attestato unico');
        $this->assertTrue(onepercourse::blocks($new->id, $course->id, $student->id));
        $this->assertFalse(onepercourse::blocks($new->id, $course->id, $newstudent->id));
        $this->assertSame((int)$old->id, (int)onepercourse::other_issue($new->id, $course->id, $student->id)->customcertid);

        $this->run_task();
        $this->assertFalse($DB->record_exists('customcert_issues', ['customcertid' => $new->id, 'userid' => $student->id]));
        $this->assertTrue($DB->record_exists('customcert_issues', ['customcertid' => $new->id, 'userid' => $newstudent->id]));

        // Enrolled again for a new cycle: the old certificate belongs to the previous enrolment.
        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $DB->set_field('user_enrolments', 'timestart', time() + 10, ['enrolid' => $enrol->id, 'userid' => $student->id]);
        $this->assertFalse(onepercourse::blocks($new->id, $course->id, $student->id));

        // Back to the same enrolment, rule switched off: issued as in the official plugin.
        $DB->set_field('user_enrolments', 'timestart', 0, ['enrolid' => $enrol->id, 'userid' => $student->id]);
        $DB->set_field('user_enrolments', 'timecreated', 1, ['enrolid' => $enrol->id, 'userid' => $student->id]);
        $this->assertTrue(onepercourse::blocks($new->id, $course->id, $student->id));
        set_config('cargoschoolonepercourse', 0, 'customcert');
        $this->run_task();
        $this->assertTrue($DB->record_exists('customcert_issues', ['customcertid' => $new->id, 'userid' => $student->id]));
        $sink->close();
    }
}
