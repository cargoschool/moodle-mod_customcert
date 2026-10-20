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
 * CargoSchool (mod E): who would receive a certificate if "Issue only to who completed the
 * certificate activity" were turned off. Read only: nothing is issued or sent.
 *
 * php mod/customcert/cli/cargoschool_owncompletion.php [--course=ID] [--users] [--all]
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
    ['course' => 0, 'users' => false, 'all' => false, 'help' => false],
    ['c' => 'course', 'u' => 'users', 'a' => 'all', 'h' => 'help']
);

if ($options['help'] || $unrecognised) {
    echo "Studenti che riceverebbero un certificato se si togliesse la spunta
\"Emetti solo a chi ha completato l'attività certificato\" (customcert/cargoschoolowncompletion).
Sola lettura: non emette né invia nulla.

Opzioni:
  --course=ID   solo un corso
  --users       elenca anche gli studenti (id, username, nome)
  --all         mostra anche i certificati con 0 studenti e quelli che il task salta
  -h, --help    questo aiuto

Esempio:
  php mod/customcert/cli/cargoschool_owncompletion.php
  php mod/customcert/cli/cargoschool_owncompletion.php --course=23 --users
";
    exit(0);
}

\core\session\manager::set_user(get_admin());
$rows = \mod_customcert\local\cargoschool\owncompletion::backlog(!empty($options['users']), (int)$options['course']);

$total = 0;
$shown = 0;
echo "certificato_id;corso_id;corso;certificato;da_inviare;gia_emessi;note\n";
foreach ($rows as $row) {
    $total += $row->count;
    if (!$options['all'] && ($row->count === 0)) {
        continue;
    }
    $shown++;
    $note = $row->processed ? $row->reason : 'saltato dal task: ' . $row->reason;
    echo implode(';', [$row->customcertid, $row->courseid, $row->course, $row->name, $row->count, $row->issued, $note]), "\n";
    if (!empty($options['users']) && $row->userids) {
        foreach (
            $DB->get_records_list(
                'user',
                'id',
                $row->userids,
                'lastname, firstname',
                'id, username, firstname, lastname'
            ) as $user
        ) {
            echo "    {$user->id};{$user->username};{$user->lastname} {$user->firstname}\n";
        }
    }
}
echo "\nCertificati elencati: $shown. Studenti che riceverebbero un certificato togliendo la spunta: $total.\n";
echo "Impostazione attuale: " . (\mod_customcert\local\cargoschool\owncompletion::enabled()
    ? "accesa (comportamento 4.4.13)" : "SPENTA (comportamento 4.4.16)") . "\n";
if ($total === 0) {
    echo "Nessuno: si può togliere la spunta senza invii arretrati.\n";
}
