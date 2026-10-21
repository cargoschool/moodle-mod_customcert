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

/**
 * CargoSchool: the preview of the issue task does what the task does, without doing it.
 *
 * @package    mod_customcert
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\task\issue_certificates_task
 */
final class cargoschool_preview_test extends \advanced_testcase {
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
     * Preview, then the real run: same users, and the preview changes nothing.
     */
    public function test_preview_matches_task(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $sink = $this->redirectEmails();
        set_config('useadhoc', 0, 'customcert');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $other = $gen->create_course();
        $old = $this->add_certificate($course->id, 'ATTESTATO 2025');
        $new = $this->add_certificate($course->id, 'Attestato unico');
        $elsewhere = $this->add_certificate($other->id, 'Altro corso');
        $a = $gen->create_and_enrol($course, 'student');
        $b = $gen->create_and_enrol($course, 'student');
        $c = $gen->create_and_enrol($other, 'student');
        // A: already has the old certificate (mod F blocks the new one); its sending failed, so it is sent again.
        $DB->insert_record('customcert_issues', (object)['userid' => $a->id, 'customcertid' => $old->id,
            'code' => 'OLD1', 'emailed' => 0, 'timecreated' => time() - 30 * DAYSECS]);

        $preview = (new task\issue_certificates_task())->preview();
        $found = array_map(function ($row) {
            return $row->action . ':' . $row->customcert->id . ':' . $row->userid;
        }, $preview);
        sort($found);
        // B gets one of the two certificates of the course: the first one issued blocks the other (mod F).
        $this->assertCount(3, $found);
        $this->assertContains("email:{$old->id}:{$a->id}", $found);
        $this->assertContains("issue:{$elsewhere->id}:{$c->id}", $found);
        $bcert = null;
        foreach ($preview as $row) {
            if ($row->action === 'issue' && $row->userid == $b->id) {
                $bcert = (int)$row->customcert->id;
            }
        }
        $this->assertContains($bcert, [(int)$old->id, (int)$new->id]);
        // Only one course.
        $this->assertCount(1, (new task\issue_certificates_task())->preview($other->id));
        // Nothing issued, nothing sent, offset untouched.
        $this->assertSame(1, $DB->count_records('customcert_issues'));
        $this->assertSame(0, $sink->count());
        $this->assertEmpty(get_config('customcert', 'certificate_offset'));

        // The real task issues to the same users.
        set_config('certificate_offset', 0, 'customcert');
        set_config('certificatesperrun', 0, 'customcert');
        (new task\issue_certificates_task())->execute();
        $this->assertTrue($DB->record_exists('customcert_issues', ['customcertid' => $bcert, 'userid' => $b->id]));
        $this->assertSame(1, $DB->count_records_select(
            'customcert_issues',
            'userid = ? AND customcertid IN (?, ?)',
            [$b->id, $old->id, $new->id]
        ));
        $this->assertTrue($DB->record_exists('customcert_issues', ['customcertid' => $elsewhere->id, 'userid' => $c->id]));
        $this->assertFalse($DB->record_exists('customcert_issues', ['customcertid' => $new->id, 'userid' => $a->id]));
        $this->assertSame(3, $DB->count_records('customcert_issues'));
        $this->assertSame(1, (int)$DB->get_field('customcert_issues', 'emailed', ['code' => 'OLD1']));
        $this->assertEmpty((new task\issue_certificates_task())->preview());
        $sink->close();
    }
}
