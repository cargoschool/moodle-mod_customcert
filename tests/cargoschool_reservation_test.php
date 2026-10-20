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

use mod_customcert\local\cargoschool\reservation;

/**
 * CargoSchool: tests of the reservation rules and of the elements of LIVE courses.
 *
 * @package    mod_customcert
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\local\cargoschool\reservation
 * @covers     \customcertelement_reservationdates\element
 * @covers     \customcertelement_resevationteachername\element
 * @covers     \customcertelement_resevationsignature\element
 * @covers     \customcertelement_datediff\element
 */
final class cargoschool_reservation_test extends \advanced_testcase {
    /**
     * Skip when mod_reservation is not installed.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        reservation::reset_caches();
        if (!reservation::is_available()) {
            $this->markTestSkipped('mod_reservation is not installed.');
        }
    }

    /**
     * A reservation of the course, in the database.
     *
     * @param int $courseid
     * @param int $start
     * @param int $end
     * @param array $teacherids
     * @return int reservation id
     */
    protected function add_reservation(int $courseid, int $start, int $end, array $teacherids): int {
        global $DB;
        return $DB->insert_record('reservation', (object)[
            'course' => $courseid, 'name' => 'Aula', 'intro' => '', 'introformat' => 1,
            'teachers' => implode(',', $teacherids), 'location' => 'Virtual Class',
            'timestart' => $start, 'timeend' => $end, 'grade' => 100, 'timeopen' => 0, 'timeclose' => 0,
            'note' => 0, 'maxrequest' => 0, 'overbook' => 0, 'showrequest' => 0, 'parent' => 0, 'mailed' => 0,
            'completionreserved' => 0, 'timemodified' => time(), 'showseats' => 0, 'dualgrade' => 0,
            'grade1label' => '', 'grade2label' => '',
        ]);
    }

    /**
     * A request of a user.
     *
     * @param int $reservationid
     * @param int $userid
     * @param bool $absent cancelled as the "Absent" button does
     * @return int
     */
    protected function add_request(int $reservationid, int $userid, bool $absent = false): int {
        global $DB;
        return $DB->insert_record('reservation_request', (object)[
            'reservation' => $reservationid, 'userid' => $userid, 'timecreated' => time(),
            'timecancelled' => $absent ? time() : 0, 'teacher' => 0, 'grade' => $absent ? -1 : 90,
            'timegraded' => 0, 'mailed' => 0, 'eventid' => 0,
        ]);
    }

    /**
     * Timestamp of a day at 9:00 in Rome.
     *
     * @param string $date Y-m-d
     * @param string $time
     * @return int
     */
    protected function rome(string $date, string $time = '09:00'): int {
        return (new \DateTime($date . ' ' . $time, new \DateTimeZone('Europe/Rome')))->getTimestamp();
    }

    /**
     * The most recent session wins; absences do not count.
     */
    public function test_session_rule(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();

        $this->assertNull(reservation::get_session($student->id, $course->id));

        $old = $this->add_reservation($course->id, $this->rome('2026-09-29'), $this->rome('2026-10-02', '17:00'), [$teacher->id]);
        $this->add_request($old, $student->id);
        $new = $this->add_reservation($course->id, $this->rome('2026-10-12'), $this->rome('2026-10-15', '17:00'), [$teacher->id]);
        $absentrequest = $this->add_request($new, $student->id, true);

        // Absent in the new session: the old one counts.
        $this->assertEquals($old, reservation::get_session($student->id, $course->id)->reservationid);

        // Present in the new session: the new one counts.
        $this->add_request($new, $student->id);
        $this->assertEquals($new, reservation::get_session($student->id, $course->id)->reservationid);
        $this->assertSame(
            [$this->rome('2026-10-12'), $this->rome('2026-10-15', '17:00')],
            reservation::get_dates($student->id, $course->id)
        );

        // Another course does not count.
        $other = $this->getDataGenerator()->create_course();
        $this->assertNull(reservation::get_dates($student->id, $other->id));
        $this->assertNotEmpty($absentrequest);
    }

    /**
     * The director is the instructor only when he is the only one.
     */
    public function test_teacher_and_director(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $director = $this->getDataGenerator()->create_user(['username' => 'g.proietti@cargoschool.com',
            'firstname' => 'G.', 'lastname' => 'Proietti']);
        $teacher = $this->getDataGenerator()->create_user(['firstname' => 'María Elizabeth', 'lastname' => 'Patino Segovia']);

        // Default setting: g.proietti@cargoschool.com.
        $this->assertTrue(reservation::is_director($director->id));
        $this->assertFalse(reservation::is_director($teacher->id));

        $r = $this->add_reservation(
            $course->id,
            $this->rome('2026-10-12'),
            $this->rome('2026-10-15'),
            [$director->id, $teacher->id]
        );
        $this->add_request($r, $student->id);
        // Elements that hide the director look for another teacher; the others keep the first one, as before.
        $this->assertEquals($teacher->id, reservation::get_teacher($student->id, $course->id, true)->id);
        $this->assertEquals($director->id, reservation::get_teacher($student->id, $course->id)->id);

        $course2 = $this->getDataGenerator()->create_course();
        $r2 = $this->add_reservation($course2->id, $this->rome('2026-10-12'), $this->rome('2026-10-15'), [$director->id]);
        $this->add_request($r2, $student->id);
        $this->assertEquals($director->id, reservation::get_teacher($student->id, $course2->id)->id);

        // Elements: with the option the name is empty for the director, without it is printed.
        $hide = new \customcertelement_resevationteachername\element((object)[
            'id' => 0, 'pageid' => 0, 'name' => 'Docente', 'element' => 'resevationteachername',
            'data' => json_encode(['hidedirector' => 1]), 'font' => 'times', 'fontsize' => 12, 'colour' => '#000000',
            'posx' => 0, 'posy' => 0, 'width' => 0, 'refpoint' => 1, 'alignment' => 'L',
        ]);
        $this->assertSame('', $hide->get_teacher_name($student->id, $course2->id));
        $this->assertSame(fullname($teacher), $hide->get_teacher_name($student->id, $course->id));

        $legacy = new \customcertelement_resevationteachername\element((object)[
            'id' => 0, 'pageid' => 0, 'name' => 'Docente', 'element' => 'resevationteachername',
            'data' => json_encode(['name' => 'Docente', 'font' => 'times']), 'font' => 'times', 'fontsize' => 12,
            'colour' => '#000000', 'posx' => 0, 'posy' => 0, 'width' => 0, 'refpoint' => 1, 'alignment' => 'L',
        ]);
        $this->assertSame(fullname($director), $legacy->get_teacher_name($student->id, $course2->id));
        // Legacy element, session with director and teacher: still the first one (the director).
        $this->assertSame(fullname($director), $legacy->get_teacher_name($student->id, $course->id));

        // Another username in the setting: the old director is a teacher like the others.
        set_config('cargoschooldirectors', "someone.else\n", 'customcert');
        reservation::reset_caches();
        $this->assertFalse(reservation::is_director($director->id));
        $this->assertSame(fullname($director), $hide->get_teacher_name($student->id, $course2->id));
    }

    /**
     * A certificate downloaded again keeps the session it was issued for.
     */
    public function test_reference_time(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $first = $this->add_reservation($course->id, $this->rome('2026-09-29'), $this->rome('2026-10-02'), []);
        $this->add_request($first, $student->id);
        $later = $this->add_reservation($course->id, $this->rome('2027-03-01'), $this->rome('2027-03-04'), []);
        $this->add_request($later, $student->id);

        // Without limit the most recent one; with the issue date the first one.
        $this->assertSame($this->rome('2027-03-01'), reservation::get_dates($student->id, $course->id)[0]);
        $issued = $this->rome('2026-10-03');
        $this->assertSame($this->rome('2026-09-29'), reservation::get_dates($student->id, $course->id, $issued)[0]);

        // The reference time is the issue of the certificate of the page, otherwise now.
        $cert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $template = new template($DB->get_record('customcert_templates', ['id' => $cert->templateid]));
        $pageid = $template->add_page();
        $this->assertEqualsWithDelta(time(), reservation::reference_time($pageid, $student->id), 5);
        $DB->insert_record('customcert_issues', (object)['userid' => $student->id, 'customcertid' => $cert->id,
            'code' => 'X1', 'emailed' => 0, 'timecreated' => $issued]);
        $this->assertSame($issued, reservation::reference_time($pageid, $student->id));
    }

    /**
     * Text of the dates element.
     */
    public function test_dates_text(): void {
        $class = \customcertelement_reservationdates\element::class;

        $this->assertSame(
            'from October 12 to October 15, 2026 - 32 hours',
            $class::format_text($this->rome('2026-10-12'), $this->rome('2026-10-15', '17:00'), '32', 'Europe/Rome')
        );
        $this->assertSame(
            'from September 29 to October 2, 2026 - 24 hours',
            $class::format_text($this->rome('2026-09-29'), $this->rome('2026-10-02'), '24', 'Europe/Rome')
        );
        $this->assertSame(
            'from December 29, 2026 to January 2, 2027 - 32 hours',
            $class::format_text($this->rome('2026-12-29'), $this->rome('2027-01-02'), '32', 'Europe/Rome')
        );
        $this->assertSame(
            'on October 12, 2026 - 1 hour',
            $class::format_text($this->rome('2026-10-12'), $this->rome('2026-10-12', '18:00'), '1', 'Europe/Rome')
        );
        $this->assertSame(
            'on October 12, 2026',
            $class::format_text($this->rome('2026-10-12'), 0, '', 'Europe/Rome')
        );
        $this->assertSame(
            'from October 12 to October 15, 2026 - 32 ore e 30 minuti',
            $class::format_text($this->rome('2026-10-12'), $this->rome('2026-10-15'), '32 ore e 30 minuti', 'Europe/Rome')
        );
        // Midnight in Rome is still the same day, even if UTC is the day before.
        $this->assertSame(
            'on October 12, 2026',
            $class::format_text($this->rome('2026-10-12', '00:00'), $this->rome('2026-10-12', '00:30'), '', 'Europe/Rome')
        );
    }

    /**
     * Hours from the course custom field.
     */
    public function test_hours_from_course_field(): void {
        $category = $this->getDataGenerator()->create_custom_field_category(['name' => 'IATA']);
        $this->getDataGenerator()->create_custom_field(['categoryid' => $category->get('id'), 'type' => 'text',
            'shortname' => 'iata_course_duration', 'name' => 'Durata']);
        $course = $this->getDataGenerator()->create_course(['customfield_iata_course_duration' => '32']);
        $student = $this->getDataGenerator()->create_user();
        $r = $this->add_reservation($course->id, $this->rome('2026-10-12'), $this->rome('2026-10-15'), []);
        $this->add_request($r, $student->id);

        $element = new \customcertelement_reservationdates\element((object)[
            'id' => 0, 'pageid' => 0, 'name' => 'Durata', 'element' => 'reservationdates',
            'data' => json_encode(['showhours' => 1, 'hoursfield' => 'iata_course_duration']), 'font' => 'times',
            'fontsize' => 12, 'colour' => '#000000', 'posx' => 0, 'posy' => 0, 'width' => 0, 'refpoint' => 1,
            'alignment' => 'L',
        ]);
        $this->assertSame('32', $element->get_hours($course->id));
        $this->assertSame('from October 12 to October 15, 2026 - 32 hours', $element->get_text($student->id, $course->id));

        $nohours = new \customcertelement_reservationdates\element((object)[
            'id' => 0, 'pageid' => 0, 'name' => 'Durata', 'element' => 'reservationdates',
            'data' => json_encode(['showhours' => 0, 'hoursfield' => 'iata_course_duration']), 'font' => 'times',
            'fontsize' => 12, 'colour' => '#000000', 'posx' => 0, 'posy' => 0, 'width' => 0, 'refpoint' => 1,
            'alignment' => 'L',
        ]);
        $this->assertSame('from October 12 to October 15, 2026', $nohours->get_text($student->id, $course->id));

        // No session: nothing.
        $other = $this->getDataGenerator()->create_user();
        $this->assertSame('', $element->get_text($other->id, $course->id));
    }

    /**
     * Opacity values of the signature.
     */
    public function test_signature_alpha(): void {
        $class = \customcertelement_resevationsignature\element::class;
        $this->assertSame(1.0, $class::clean_alpha(null));
        $this->assertSame(1.0, $class::clean_alpha('abc'));
        $this->assertSame(0.5, $class::clean_alpha('0.5'));
        $this->assertSame(0.0, $class::clean_alpha(-3));
        $this->assertSame(1.0, $class::clean_alpha(7));
    }
}
