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
$string['cargoschoolheading'] = 'CargoSchool';
$string['cargoschoolheading_desc'] = 'Modifiche CargoSchool, descritte in CARGOSCHOOL.md del plugin.';
$string['cargoschoolonepercourse'] = 'Un solo certificato per studente nel corso';
$string['cargoschoolonepercourse_desc'] = 'L\'emissione automatica non emette (né invia per mail) un certificato allo studente che ha già un certificato di un\'altra attività dello stesso corso, emesso negli ultimi mesi indicati sotto. Chi rifà il corso per il rinnovo, dopo quel periodo, riceve il certificato nuovo. Lo studente può comunque aprire da sé un\'attività certificato.';
$string['cargoschoolonepercoursemonths'] = 'Un certificato per corso: mesi';
$string['cargoschoolonepercoursemonths_desc'] = 'Un certificato di un\'altra attività dello stesso corso emesso negli ultimi mesi blocca quello nuovo. 0 = sempre, qualunque sia la data. Di serie 20: la certificazione dura 24 mesi e il rinnovo parte 3 mesi prima della scadenza.';
$string['cargoschoolowncompletion'] = 'Emetti solo a chi ha completato l\'attività certificato';
$string['cargoschoolowncompletion_desc'] = 'Come fino alla versione 4.4.13: l\'emissione automatica salta chi non ha completato l\'attività certificato stessa (per esempio "visualizza l\'attività" o completamento manuale). La versione 4.4.16 emette a tutti quelli che soddisfano le restrizioni d\'accesso: togliere la spunta solo dopo aver verificato, con il comando php mod/customcert/cli/cargoschool_owncompletion.php, che nessun certificato partirebbe a studenti che hanno superato il corso tempo fa.';
$string['sitelanguage'] = 'Lingua forzata dei certificati';
$string['sitelanguage_desc'] = 'Genera tutti i certificati del sito nella lingua scelta. I certificati che forzano già una lingua propria non cambiano. Con "Non forzare" ogni certificato usa la lingua del corso e poi quella dell\'utente.';
