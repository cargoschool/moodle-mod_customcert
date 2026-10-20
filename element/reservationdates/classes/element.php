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

namespace customcertelement_reservationdates;

use mod_customcert\element_helper;
use mod_customcert\local\cargoschool\reservation;

/**
 * CargoSchool: dates of the student's session (mod_reservation) and hours of the course.
 *
 * Prints for example "from October 12 to October 15, 2026 - 32 hours". The dates are the
 * start and the end of the most recent session of the student in the course, absences
 * excluded (\mod_customcert\local\cargoschool\reservation::get_dates()); the hours are the
 * value of a course custom field (by default iata_course_duration). Meant for the LIVE
 * courses, which have no course completion date.
 *
 * @package    customcertelement_reservationdates
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {
    /** Default shortname of the course custom field with the hours. */
    public const DEFAULT_HOURSFIELD = 'iata_course_duration';

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        $mform->addElement('static', 'datesnote', '', get_string('datesnote', 'customcertelement_reservationdates'));

        $mform->addElement('advcheckbox', 'showhours', get_string('showhours', 'customcertelement_reservationdates'));
        $mform->setDefault('showhours', 1);

        $mform->addElement('text', 'hoursfield', get_string('hoursfield', 'customcertelement_reservationdates'), ['size' => 30]);
        $mform->setType('hoursfield', PARAM_ALPHANUMEXT);
        $mform->setDefault('hoursfield', self::DEFAULT_HOURSFIELD);
        $mform->addHelpButton('hoursfield', 'hoursfield', 'customcertelement_reservationdates');
        $mform->hideIf('hoursfield', 'showhours', 'notchecked');

        parent::render_form_elements($mform);
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param \stdClass $data the form data
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        $hoursfield = trim((string)($data->hoursfield ?? ''));
        return json_encode([
            'showhours' => empty($data->showhours) ? 0 : 1,
            'hoursfield' => $hoursfield !== '' ? $hoursfield : self::DEFAULT_HOURSFIELD,
        ]);
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        if (!empty($this->get_data())) {
            $settings = $this->get_settings();
            if ($mform->elementExists('showhours')) {
                $mform->getElement('showhours')->setValue($settings->showhours);
            }
            if ($mform->elementExists('hoursfield')) {
                $mform->getElement('hoursfield')->setValue($settings->hoursfield);
            }
        }
        parent::definition_after_data($mform);
    }

    /**
     * The settings of the element, with defaults.
     *
     * @return \stdClass showhours (int), hoursfield (string)
     */
    public function get_settings(): \stdClass {
        $data = json_decode((string)$this->get_data());
        $settings = new \stdClass();
        $settings->showhours = (is_object($data) && isset($data->showhours)) ? (int)$data->showhours : 1;
        $settings->hoursfield = (is_object($data) && !empty($data->hoursfield)) ? (string)$data->hoursfield
            : self::DEFAULT_HOURSFIELD;
        return $settings;
    }

    /**
     * The text printed for a user, '' when the user has no session in the course.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $before only sessions started before this time (0 = no limit)
     * @return string
     */
    public function get_text(int $userid, int $courseid, int $before = 0): string {
        $dates = reservation::get_dates($userid, $courseid, $before);
        if (!$dates) {
            return '';
        }
        return self::format_text($dates[0], $dates[1], $this->get_hours($courseid));
    }

    /**
     * The hours of the course, '' when they are not shown or not set.
     *
     * @param int $courseid
     * @return string
     */
    public function get_hours(int $courseid): string {
        $settings = $this->get_settings();
        if (empty($settings->showhours)) {
            return '';
        }
        return self::course_field_value($courseid, $settings->hoursfield);
    }

    /**
     * Value of a course custom field, as text.
     *
     * @param int $courseid
     * @param string $shortname
     * @return string
     */
    public static function course_field_value(int $courseid, string $shortname): string {
        if ($courseid <= 0 || $shortname === '') {
            return '';
        }
        $handler = \core_course\customfield\course_handler::create();
        foreach ($handler->get_instance_data($courseid, true) as $data) {
            if ($data->get_field()->get('shortname') === $shortname) {
                $value = $data->export_value();
                return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES, 'UTF-8'));
            }
        }
        return '';
    }

    /**
     * The text: dates of the session, then the hours.
     *
     * @param int $start start of the session
     * @param int $end end of the session (0 or before the start = one day)
     * @param string $hours hours of the course: a number gets "hour(s)", any other text is printed as it is
     * @param string|null $timezone timezone of the dates, by default the timezone of the site
     * @return string
     */
    public static function format_text(int $start, int $end, string $hours = '', ?string $timezone = null): string {
        $text = self::format_dates($start, $end, $timezone);
        $hours = self::format_hours($hours);
        if ($hours === '') {
            return $text;
        }
        return get_string(
            'dateswithhours',
            'customcertelement_reservationdates',
            (object)['dates' => $text, 'hours' => $hours]
        );
    }

    /**
     * The dates of the session, in the current language.
     *
     * @param int $start
     * @param int $end
     * @param string|null $timezone
     * @return string
     */
    public static function format_dates(int $start, int $end, ?string $timezone = null): string {
        if ($timezone === null) {
            $timezone = \core_date::get_server_timezone();
        }
        if ($end < $start) {
            $end = $start;
        }
        $tz = new \DateTimeZone(\core_date::normalise_timezone($timezone));
        $from = (new \DateTime('@' . $start))->setTimezone($tz);
        $to = (new \DateTime('@' . $end))->setTimezone($tz);

        $a = (object)[
            'startday' => $from->format('j'),
            'startmonth' => userdate($start, '%B', $timezone, false, false),
            'startyear' => $from->format('Y'),
            'endday' => $to->format('j'),
            'endmonth' => userdate($end, '%B', $timezone, false, false),
            'endyear' => $to->format('Y'),
        ];

        if ($from->format('Y-m-d') === $to->format('Y-m-d')) {
            $key = 'dates_oneday';
        } else if ($a->startyear === $a->endyear) {
            $key = 'dates_sameyear';
        } else {
            $key = 'dates_otheryear';
        }
        return get_string($key, 'customcertelement_reservationdates', $a);
    }

    /**
     * The hours: "32 hours", "1 hour", or the text of the field as it is.
     *
     * @param string $hours
     * @return string
     */
    public static function format_hours(string $hours): string {
        $hours = trim($hours);
        if ($hours === '') {
            return '';
        }
        $number = str_replace(',', '.', $hours);
        if (!is_numeric($number)) {
            return $hours;
        }
        $number = (float)$number;
        $shown = (floor($number) == $number) ? (string)(int)$number : format_float($number, -1);
        $key = ($number == 1) ? 'hours_one' : 'hours_many';
        return get_string($key, 'customcertelement_reservationdates', $shown);
    }

    /**
     * Example text for previews and the drag and drop editor.
     *
     * @param int $courseid
     * @return string
     */
    protected function get_preview_text(int $courseid): string {
        $start = usergetmidnight(time(), \core_date::get_server_timezone());
        $hours = $courseid ? $this->get_hours($courseid) : '';
        if ($hours === '' && !empty($this->get_settings()->showhours)) {
            $hours = '24';
        }
        return self::format_text($start, $start + 3 * DAYSECS, $hours);
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        $courseid = (int)element_helper::get_courseid($this->get_id());
        if ($preview) {
            $text = $this->get_preview_text($courseid);
        } else {
            $before = reservation::reference_time((int)$this->get_pageid(), (int)$user->id);
            $text = $this->get_text((int)$user->id, $courseid, $before);
        }
        if ($text === '') {
            // No session: nothing to print. Never call debugging() here, it would corrupt the PDF.
            return;
        }
        element_helper::render_content($pdf, $this, $text);
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     */
    public function render_html() {
        global $COURSE;
        $courseid = (!empty($COURSE->id) && $COURSE->id != SITEID) ? (int)$COURSE->id : 0;
        return element_helper::render_html_content($this, $this->get_preview_text($courseid));
    }
}
