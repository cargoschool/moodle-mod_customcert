# mod_customcert — modifiche CargoSchool

Versione: **4.4.16-cargoschool.1** (`2024042228`), basata sulla release ufficiale **4.4.16**
(`2024042228`, tag `v4.4.16` di https://github.com/mdjnelson/moodle-mod_customcert, ramo `MOODLE_404_STABLE`,
che è quello usato su Moodle 4.5).

Precedente: 4.4.13-cargoschool3 (`2024042224`), basata sulla 4.4.13 ufficiale (`2024042221`).

## Regola sui numeri di versione

Il numero di versione del modulo resta **sempre uguale a quello ufficiale** della release di partenza.
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
| `customcertelement_resevationteachername` — *Nome docente della prenotazione* | 1.1.0 (`2026102000`) | Nome del docente della sessione. Nuova opzione **Nascondi quando il docente è il direttore** (attiva negli elementi nuovi; quelli esistenti la ricevono solo se la si spunta). |
| `customcertelement_resevationsignature` — *Firma docente della prenotazione* | 1.1.0 (`2026102000`) | Firma del docente della sessione. Nuove opzioni **Nascondi quando il docente è il direttore** e **Alpha channel** (opacità, 0–1, come l'elemento *Immagine* ufficiale). Le immagini delle firme si scaricano solo da chi può gestire i certificati del corso (fino alla 1.0 erano pubbliche). |

Il refuso `resevation` nei nomi dei componenti resta: rinominarli farebbe perdere gli elementi già salvati.

La firma del direttore è l'elemento *Immagine* ufficiale, che ha già **Alpha channel**.

## Certificato unico per i corsi LIVE

- *Instructor* (Testo) + *Nome docente della prenotazione* e *Firma docente della prenotazione*, entrambi con *Nascondi quando il docente è il direttore*;
- *School Director G.Proietti* (Testo) + firma del direttore (*Immagine*), sempre presenti;
- *Course duration:* (Testo) + *Date dell'aula (prenotazione)*;
- *Expiring Date:* (Testo) + *Data + 2 anni (scadenza)* con *Fine dell'aula (prenotazione)* e formato *July 2018*.

Quando il docente è il direttore restano la scritta *Instructor*, *School Director G.Proietti* e una sola firma.

## Come si rifà il fork su una nuova release ufficiale

1. Si parte dal tag ufficiale e si copiano gli elementi CargoSchool in `element/`.
2. Si riapplicano le mod A-D (le differenze sono piccole, cercare `CargoSchool` nei file).
3. `version.php`: stessa `version` dell'ufficiale, `release` = ufficiale + `-cargoschool.N`.
4. Si confrontano i passi di `db/upgrade.php` con la versione installata.
