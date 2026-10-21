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
 * CargoSchool: what the task "Issue certificates" would do at its next runs, without doing it.
 *
 * Same checks as the task (restrictions, own completion of mod E, one certificate per course of
 * mod F, required time, email retries), on every certificate at once. Read only: nothing is
 * issued or sent.
 *
 * php mod/customcert/cli/cargoschool_preview.php [--course=ID] [--summary]
 *
 * @package    mod_customcert
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/completionlib.php');

[$options, $unrecognised] = cli_get_params(
    ['course' => 0, 'summary' => false, 'help' => false],
    ['c' => 'course', 's' => 'summary', 'h' => 'help']
);

if ($options['help'] || $unrecognised) {
    echo "Cosa farebbe il task \"Emissione certificati\" ai prossimi giri, senza farlo.
Stessi controlli del task (restrizioni, completamento dell'attività certificato, un certificato
per corso, tempo minimo, invii da ripetere), su tutti i certificati insieme.
Sola lettura: non emette né invia nulla.

Opzioni:
  --course=ID   solo un corso
  --summary     una riga per certificato invece di una per studente
  -h, --help    questo aiuto

Azioni:
  EMETTE   nuovo certificato, poi inviato per email secondo le opzioni del certificato
  INVIA    certificato già emesso che il task invierebbe di nuovo (invio non riuscito o mai fatto)

Esempio:
  php mod/customcert/cli/cargoschool_preview.php --summary
  php mod/customcert/cli/cargoschool_preview.php --course=23
";
    exit(0);
}

\core\session\manager::set_user(get_admin());

$task = \core\task\manager::get_scheduled_task(\mod_customcert\task\issue_certificates_task::class);
$preview = (new \mod_customcert\task\issue_certificates_task())->preview((int)$options['course']);

$labels = ['issue' => 'EMETTE', 'email' => 'INVIA'];
$userids = array_unique(array_map(function ($row) {
    return $row->userid;
}, $preview));
$users = $userids ? $DB->get_records_list('user', 'id', $userids, '', 'id, username, firstname, lastname') : [];

$recipients = function (\stdClass $customcert): string {
    $to = [];
    if (!empty($customcert->emailstudents)) {
        $to[] = 'studente';
    }
    if (!empty($customcert->emailteachers)) {
        $to[] = 'docenti';
    }
    if (trim((string)($customcert->emailothers ?? '')) !== '') {
        $to[] = 'altri: ' . preg_replace('/\s+/', ' ', trim($customcert->emailothers));
    }
    return implode(', ', $to);
};

$counts = ['issue' => 0, 'email' => 0];
$bycert = [];
foreach ($preview as $row) {
    $counts[$row->action]++;
    $id = $row->customcert->id;
    if (!isset($bycert[$id])) {
        $bycert[$id] = (object)['customcert' => $row->customcert, 'issue' => 0, 'email' => 0];
    }
    $bycert[$id]->{$row->action}++;
}

if ($options['summary']) {
    echo "certificato_id;corso_id;corso;certificato;emette;invia;destinatari\n";
    foreach ($bycert as $id => $cert) {
        $c = $cert->customcert;
        echo implode(';', [$id, $c->courseid, $c->courseshortname, $c->templatename, $cert->issue, $cert->email,
            $recipients($c)]), "\n";
    }
} else {
    echo "azione;certificato_id;corso_id;corso;certificato;utente_id;username;studente;destinatari\n";
    foreach ($preview as $row) {
        $c = $row->customcert;
        $user = $users[$row->userid] ?? null;
        echo implode(';', [$labels[$row->action], $c->id, $c->courseid, $c->courseshortname, $c->templatename,
            $row->userid, $user ? $user->username : '', $user ? "{$user->lastname} {$user->firstname}" : '',
            $recipients($c)]), "\n";
    }
}

echo "\nCertificati coinvolti: " . count($bycert) . ". Nuove emissioni: {$counts['issue']}. "
    . "Invii di certificati già emessi: {$counts['email']}.\n";
echo "Task \"Emissione certificati\": " . ($task && $task->get_disabled() ? 'DISATTIVATO' : 'attivo') . ".\n";
echo "Un solo certificato per corso: " . (\mod_customcert\local\cargoschool\onepercourse::enabled()
    ? 'acceso (' . \mod_customcert\local\cargoschool\onepercourse::months() . ' mesi)' : 'SPENTO') . ". "
    . "Solo a chi ha completato l'attività certificato: "
    . (\mod_customcert\local\cargoschool\owncompletion::enabled() ? 'acceso' : 'SPENTO') . ".\n";
