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

use mod_customcert\local\cargoschool\owncompletion;

/**
 * CargoSchool (mod E): automatic issuing and the own completion of the certificate activity.
 *
 * @package    mod_customcert
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\local\cargoschool\owncompletion
 * @covers     \mod_customcert\task\issue_certificates_task
 */
final class cargoschool_owncompletion_test extends \advanced_testcase {
    /**
     * Course with completion, certificate "view to complete" sent to a company address, two students.
     *
     * @return array [$customcert, $viewed, $notviewed]
     */
    protected function setup_course(): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $CFG->enablecompletion = 1;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $customcert = $this->getDataGenerator()->create_module('customcert', [
            'course' => $course->id, 'emailothers' => 'azienda@example.com',
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
        ]);
        // A certificate without elements is never processed: add one.
        $template = new template($DB->get_record('customcert_templates', ['id' => $customcert->templateid]));
        $pageid = $template->add_page();
        $DB->insert_record('customcert_elements', (object)['pageid' => $pageid, 'name' => 'Nome',
            'element' => 'studentname', 'data' => '', 'font' => 'times', 'fontsize' => 12, 'colour' => '#000000',
            'posx' => 10, 'posy' => 10, 'width' => 0, 'refpoint' => 0, 'alignment' => 'L', 'sequence' => 1,
            'timecreated' => time(), 'timemodified' => time()]);

        $viewed = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $notviewed = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $cm = get_coursemodule_from_instance('customcert', $customcert->id);
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm, $viewed->id);

        return [$customcert, $viewed, $notviewed];
    }

    /**
     * Setting on (default): only who completed the activity; off: everybody, as the official 4.4.16.
     */
    public function test_task_and_backlog(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$customcert, $viewed, $notviewed] = $this->setup_course();

        $this->assertTrue(owncompletion::enabled());
        $rows = owncompletion::backlog(true, (int)$customcert->course);
        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]->count);
        $this->assertSame([(int)$notviewed->id], $rows[0]->userids);

        $sink = $this->redirectEmails();
        (new task\issue_certificates_task())->execute();
        $this->assertTrue($DB->record_exists('customcert_issues', ['customcertid' => $customcert->id, 'userid' => $viewed->id]));
        $this->assertFalse($DB->record_exists(
            'customcert_issues',
            ['customcertid' => $customcert->id, 'userid' => $notviewed->id]
        ));

        set_config('cargoschoolowncompletion', 0, 'customcert');
        $this->assertFalse(owncompletion::enabled());
        set_config('certificate_offset', 0, 'customcert');
        (new task\issue_certificates_task())->execute();
        $this->assertTrue($DB->record_exists(
            'customcert_issues',
            ['customcertid' => $customcert->id, 'userid' => $notviewed->id]
        ));
        $this->assertSame(0, owncompletion::backlog(false, (int)$customcert->course)[0]->count);
        $sink->close();
    }
}
