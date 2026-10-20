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
 * Italian strings of the CargoSchool additions (the rest comes from the Italian language pack).
 *
 * @package    mod_customcert
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cargoschooldirectors'] = 'Direttore della scuola (username)';
$string['cargoschooldirectors_desc'] = 'Username del direttore della scuola, uno per riga. Quando il docente della prenotazione è il direttore, gli elementi "Nome docente della prenotazione" e "Firma docente della prenotazione" non stampano nulla se hanno attiva l\'opzione "Nascondi quando il docente è il direttore": nome e firma del direttore sono già sul certificato.';
$string['cargoschoolheading'] = 'CargoSchool - corsi LIVE';
$string['cargoschoolheading_desc'] = 'Impostazioni usate dagli elementi CargoSchool che leggono la prenotazione del corso (mod_reservation).';
$string['sitelanguage'] = 'Lingua forzata dei certificati';
$string['sitelanguage_desc'] = 'Genera tutti i certificati del sito nella lingua scelta. I certificati che forzano già una lingua propria non cambiano. Con "Non forzare" ogni certificato usa la lingua del corso e poi quella dell\'utente.';
