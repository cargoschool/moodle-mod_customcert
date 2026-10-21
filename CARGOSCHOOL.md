# mod_customcert — modifiche CargoSchool

Versione: **4.4.16-cargoschool.6** (`2024042228`), basata sulla release ufficiale **4.4.16**
(`2024042228`, tag `v4.4.16` di https://github.com/mdjnelson/moodle-mod_customcert, ramo `MOODLE_404_STABLE`,
che è quello usato su Moodle 4.5).

Precedenti: 4.4.16-cargoschool.1, .2, .3, .4 e .5 (stesso numero: nessuna modifica al database); 4.4.13-cargoschool3 (`2024042224`), basata sulla 4.4.13 ufficiale (`2024042221`).

## Regola sui numeri di versione

Il numero di versione del modulo resta **sempre uguale a quello ufficiale** della release di partenza. Per questo Moodle non vede le release CargoSchool con lo stesso numero come aggiornamenti: dopo l'installazione si fa *Svuota tutte le cache* e si controllano le *Notifiche* per le impostazioni nuove.
La 4.4.13-cargoschool3 aveva `2024042224`, un numero che l'ufficiale ha usato più avanti: chi aggiorna da lì
salta i passi di upgrade ufficiali compresi fra `2024042221` e `2024042224`. È successo con il passo
`2024042222`, che aggiunge `customcert.issueautomatically`: senza il campo il task di emissione si ferma.
Le modifiche CargoSchool al modulo non toccano il database; gli elementi hanno numeri di versione propri.

**Non installare** customcert dal pulsante *Installa questo aggiornamento* di Moodle: sostituirebbe la
cartella con l'ufficiale, togliendo le modifiche e gli elementi qui sotto.

## Modifiche al modulo

| Mod | Dove | Cosa fa |
|---|---|---|
| A | `lib.php`, `settings.php`, `lang/en`, `lang/it`, `tests/lib_test.php` | **Lingua forzata per tutto il sito** (`customcert/language`): vale per i certificati che non forzano una lingua propria, prima della lingua del corso e di quella dell'utente. Dalla 4.4.13-cargoschool3. |
| B | `classes/task/issue_certificates_task.php` | **Emissione automatica anche con "Email others"** (modifica di Marco, dalla 4.4.13-cargoschool3): il task emette il certificato anche quando è compilato solo *Email others*. Dalla 4.4.14 l'ufficiale ha l'opzione per attività *Issue certificates automatically*, spenta di serie, che però vale solo se è attiva anche un'opzione di invio per email: la modifica resta per non dover riaprire ogni attività. Il test ufficiale `email_certificate_task_test::test_email_certificates_others_does_not_bulk_issue_to_students` fallisce per questo, ed è voluto. |
| C | `db/upgrade.php` | **Correzione dell'aggiornamento da `2024042224`**: aggiunge `customcert.issueautomatically` se manca (vedi la regola sopra). Innocua negli altri casi. |
| E | `classes/local/cargoschool/owncompletion.php`, `classes/task/issue_certificates_task.php`, `settings.php`, `cli/cargoschool_owncompletion.php`, `tests/cargoschool_owncompletion_test.php` | **Emissione solo a chi ha completato l'attività certificato** (impostazione `customcert/cargoschoolowncompletion`, *Emetti solo a chi ha completato l'attività certificato*, accesa di serie). Fino alla 4.4.13 il task saltava chi non aveva completato l'attività certificato stessa (*visualizza l'attività*, completamento manuale); la 4.4.16 ha tolto il controllo (#1020) ed emette a tutti quelli che soddisfano le restrizioni d'accesso. In produzione 84 certificati con completamento proprio e invio via mail avrebbero mandato in blocco i certificati di chi ha superato il corso anche anni fa. Con la spunta il comportamento è quello della 4.4.13. Il comando `php mod/customcert/cli/cargoschool_owncompletion.php [--course=ID] [--users] [--all]` (sola lettura) elenca, certificato per certificato, gli studenti che riceverebbero il certificato togliendo la spunta: quando dice 0 si può togliere. Con la spunta falliscono sei test ufficiali di #1020 (`email_certificate_task_test`, voluto). |
| F | `classes/local/cargoschool/onepercourse.php`, `classes/task/issue_certificates_task.php`, `settings.php`, `tests/cargoschool_onepercourse_test.php` | **Un solo certificato per studente nel corso** (impostazioni `customcert/cargoschoolonepercourse`, accesa, e `customcert/cargoschoolonepercoursemonths`, 20 mesi; dalla cargoschool.4, finestra in mesi dalla .5). Il task non emette (e non invia) un certificato a chi ha già un certificato di un'altra attività dello stesso corso emesso negli ultimi N mesi (0 = sempre). Chi rifà il corso per il rinnovo, circa due anni dopo, riceve il certificato nuovo. Nella .4 la finestra partiva dall'inizio dell'iscrizione attuale: su staging il 21/10/2026 il task ha emesso 28 doppioni perché le iscrizioni erano state ricreate o spostate dopo il certificato vecchio (es. certificati del 13-15/05/2025, iscrizione del 23/05/2025). Vale solo per l'emissione automatica: lo studente che apre da sé un'attività certificato lo genera comunque. Il comando della mod E non conta gli studenti che questa regola blocca. |
| G | `classes/task/issue_certificates_task.php`, `cli/cargoschool_preview.php`, `tests/cargoschool_preview_test.php` | **Anteprima del task di emissione** (dalla cargoschool.6). `php mod/customcert/cli/cargoschool_preview.php [--course=ID] [--summary]` elenca, senza emettere né inviare nulla, cosa farebbe il task *Emissione certificati*: **EMETTE** (nuovo certificato, poi inviato secondo le opzioni del certificato) o **INVIA** (certificato già emesso il cui invio non è riuscito o non è mai stato fatto), con i destinatari; in fondo lo stato del task (attivo o disattivato) e delle regole E ed F. Stessi controlli del task, su tutti i certificati insieme (il task ne guarda *certificatesperrun* per giro). Per riusare il codice il corpo di `execute()` è diviso in `get_customcerts()` (la query) e `process_customcert()` (il ciclo sugli studenti), con il parametro `$preview` che registra invece di emettere; nell'anteprima, come nel task, il primo certificato emesso in un corso blocca gli altri dello stesso corso (mod F). Comportamento del task invariato: falliscono gli stessi test ufficiali di prima. |
| D | `classes/local/cargoschool/reservation.php`, `settings.php` | **Prenotazione del certificato** (`mod_reservation`), regole condivise dagli elementi dei corsi LIVE, e impostazione **Direttore della scuola** (`customcert/cargoschooldirectors`, di serie `g.proietti@cargoschool.com`). Test `tests/cargoschool_reservation_test.php`. |

Regole della classe `\mod_customcert\local\cargoschool\reservation`:

- contano solo le richieste non annullate (`timecancelled = 0`): l'assente ha la richiesta annullata (mod A di `mod_reservation`);
- fra queste vince la **sessione più recente** (`reservation.timestart`), fra quelle iniziate prima dell'emissione del certificato: un PDF riscaricato più avanti non prende le date di una prenotazione successiva nello stesso corso;
- per il docente si saltano le sessioni senza docenti e vince il primo docente della sessione, come prima; negli elementi con *Nascondi quando il docente è il direttore* vince il primo che **non è il direttore**, e il direttore è il docente solo quando è l'unico;
- senza `mod_reservation` installato non si trova nulla e gli elementi non stampano nulla.

## Elementi CargoSchool

| Elemento | Versione | Cosa stampa |
|---|---|---|
| `customcertelement_datediff` — *Data + 2 anni (scadenza)* | 1.1.0 (`2026102000`) | Una data di partenza più due anni. Dalla 1.1.0 la data di partenza può essere **Fine dell'aula (prenotazione)**, per i corsi LIVE senza completamento. Costanti di classe al posto delle `define()` globali, che coincidevano con quelle dell'elemento *Date* ufficiale. |
| `customcertelement_reservationdates` — *Date dell'aula (prenotazione)* | 1.0.0 (`2026102000`), nuovo | Inizio e fine della sessione e ore del corso: *from October 12 to October 15, 2026 - 32 hours*; anni diversi *from December 29, 2026 to January 2, 2027 - 32 hours*; un giorno *on October 12, 2026 - 8 hours*. Ore dal campo del corso `iata_course_duration` (impostabile): un numero diventa *N hours*, un altro testo si stampa com'è, vuoto = niente ore. Mesi nella lingua del certificato, frasi modificabili come stringhe di lingua. |
| `customcertelement_reservationtext` — *Testo secondo la prenotazione* | 1.0.0 (`2026102001`), nuovo (dalla cargoschool.2) | Un testo stampato solo se vale la condizione scelta: *il docente non è il direttore*, *il docente è il direttore*, *la lezione è in aula (luogo compilato)*, *la lezione è in virtual class (luogo vuoto)*. `{LOCATION}` nel testo = luogo della prenotazione. Luogo vuoto = `''` oppure `'0'` (*Altro luogo* senza testo). Usi: *Instructor* accanto al nome e *Instructor* sopra il direttore; *Classroom* e *Virtual Class* nella stessa posizione. |
| `customcertelement_resevationteachername` — *Nome docente della prenotazione* | 1.1.0 (`2026102000`) | Nome del docente della sessione. Nuova opzione **Nascondi quando il docente è il direttore** (attiva negli elementi nuovi; quelli esistenti la ricevono solo se la si spunta). |
| `customcertelement_resevationsignature` — *Firma docente della prenotazione* | 1.1.0 (`2026102000`) | Firma del docente della sessione. Nuove opzioni **Nascondi quando il docente è il direttore** e **Alpha channel** (opacità, 0–1, come l'elemento *Immagine* ufficiale). Le immagini delle firme si scaricano solo da chi può gestire i certificati del corso (fino alla 1.0 erano pubbliche). |

Ogni elemento CargoSchool ha il suo repository (sottomodulo) `cargoschool/moodle-mod_customcert_<elemento>`.

Il refuso `resevation` nei nomi dei componenti resta: rinominarli farebbe perdere gli elementi già salvati.

La firma del direttore è l'elemento *Immagine* ufficiale, che ha già **Alpha channel**.

## Certificato unico per i corsi LIVE

- *Nome docente della prenotazione* e *Firma docente della prenotazione*, entrambi con *Nascondi quando il docente è il direttore*;
- *School Director G.Proietti* (Testo) + firma del direttore (*Immagine*), sempre presenti;
- *Instructor* (Testo secondo la prenotazione, *il docente non è il direttore*) accanto al nome del docente, e *Instructor* (*il docente è il direttore*) sopra *School Director G.Proietti*;
- *Examination venue:* (Testo) + *Classroom* (*la lezione è in aula*) e *Virtual Class* (*la lezione è in virtual class*), nella stessa posizione;
- *Course duration:* (Testo) + *Date dell'aula (prenotazione)*;
- *Expiring Date:* (Testo) + *Data + 2 anni (scadenza)* con *Fine dell'aula (prenotazione)* e formato *July 2018*.

Quando il docente è il direttore restano *Instructor* sopra *School Director G.Proietti* e una sola firma; il nome del direttore non si ripete.

## Come si rifà il fork su una nuova release ufficiale

1. Si parte dal tag ufficiale e si copiano gli elementi CargoSchool in `element/`.
2. Si riapplicano le mod A-F (le differenze sono piccole, cercare `CargoSchool` nei file).
3. `version.php`: stessa `version` dell'ufficiale, `release` = ufficiale + `-cargoschool.N`.
4. Si confrontano i passi di `db/upgrade.php` con la versione installata.
