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
 * Strings for component 'customcertelement_reservationdates', language 'it'.
 *
 * @package    customcertelement_reservationdates
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['dates_oneday'] = 'il {$a->startday} {$a->startmonth} {$a->startyear}';
$string['dates_otheryear'] = 'dal {$a->startday} {$a->startmonth} {$a->startyear} al {$a->endday} {$a->endmonth} {$a->endyear}';
$string['dates_sameyear'] = 'dal {$a->startday} {$a->startmonth} al {$a->endday} {$a->endmonth} {$a->endyear}';
$string['datesnote'] = 'Stampa inizio e fine della sessione dello studente (la più recente, assenze escluse), per esempio "from October 12 to October 15, 2026 - 32 hours" in un certificato in inglese. Per uno studente senza sessione in questo corso non si stampa nulla.';
$string['dateswithhours'] = '{$a->dates} - {$a->hours}';
$string['hours_many'] = '{$a} ore';
$string['hours_one'] = '{$a} ora';
$string['hoursfield'] = 'Campo del corso con le ore';
$string['hoursfield_help'] = 'Nome breve (shortname) del campo personalizzato del corso che contiene le ore. Un numero si stampa come "32 hours"; qualsiasi altro testo si stampa così com\'è. Se il campo è vuoto le ore non si stampano.';
$string['pluginname'] = 'Date dell\'aula (prenotazione)';
$string['privacy:metadata'] = 'Il plugin Date dell\'aula non memorizza dati personali.';
$string['showhours'] = 'Mostra le ore del corso';
