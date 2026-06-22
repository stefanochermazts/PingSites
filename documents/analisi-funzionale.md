Analisi funzionale — Web Monitor interno
Obiettivo

Realizzare una piccola applicazione interna per monitorare siti web pubblici.

L’applicazione deve permettere di inserire una lista di URL, controllarli periodicamente e segnalare quando uno di questi siti non risponde correttamente. Il sistema deve aprire un incidente quando il problema è confermato, inviare una email di avviso e chiudere automaticamente l’incidente quando il sito torna disponibile.

Il prodotto non deve essere pensato come SaaS per clienti esterni. Deve essere uno strumento interno, semplice, utile e mantenibile. Non servono multi-tenant, billing, gestione clienti, ruoli complessi o funzionalità avanzate di monitoring infrastrutturale.

La prima versione deve monitorare solo siti web pubblici HTTP/HTTPS.

L’obiettivo pratico è questo:

sapere se i siti monitorati sono online, quando sono andati giù, quando sono tornati disponibili e avere una status page pubblica ordinata.

Stack previsto

L’applicazione deve essere sviluppata in Laravel.

Per il backend amministrativo usare Filament.

L’applicazione sarà ospitata su Cloudways, dove sono disponibili cron, worker, database e gestione applicativa. La soluzione deve quindi restare compatibile con un ambiente Laravel tradizionale, senza introdurre architetture inutilmente complesse.

Il sistema deve usare:

Laravel
Filament
database MySQL/MariaDB
Laravel Scheduler
Laravel Queue
Redis, se disponibile
Supervisor per mantenere attivi i worker
provider email esterno per l’invio delle notifiche

Non usare SMTP locale del server per gli alert.

Perimetro della prima versione

La prima versione deve essere volutamente limitata.

L’applicazione deve controllare solo URL pubblici raggiungibili via HTTP o HTTPS. Non deve fare ping, non deve controllare porte TCP, non deve monitorare DNS, non deve eseguire heartbeat, non deve controllare servizi interni o reti private.

Le funzionalità incluse sono:

creazione e gestione dei monitor
controllo periodico degli URL
controllo del codice HTTP restituito
controllo del timeout
misurazione del tempo di risposta
controllo SSL di base
controllo opzionale di una keyword nella pagina
apertura automatica degli incidenti
chiusura automatica degli incidenti
invio email quando un sito va giù
invio email quando un sito torna online
dashboard interna
status page pubblica
manutenzioni programmate semplici
storico dei controlli
storico degli incidenti
cancellazione automatica dei check vecchi

Le funzionalità escluse dalla prima versione sono:

gestione clienti
multi-tenant
billing
API pubblica
notifiche Telegram, Teams, Slack o SMS
webhook
probe distribuiti
monitor DNS
monitor porte TCP
monitor ping
monitor cron/heartbeat
status page con dominio custom
app mobile

Queste funzionalità potranno essere aggiunte in futuro, ma non devono condizionare lo sviluppo iniziale.

Concetto generale

Il sistema ruota attorno a tre concetti principali.

Il primo è il monitor. Un monitor rappresenta un sito da controllare, per esempio https://www.devisia.pro.

Il secondo è il check. Un check è un singolo controllo eseguito in un determinato momento. Il sistema controlla il sito, misura il tempo di risposta, verifica il codice HTTP, controlla eventuali errori e salva l’esito.

Il terzo è l’incidente. Un incidente non coincide con un singolo errore. Un sito può fallire una volta per un problema temporaneo di rete, un timeout momentaneo o una risposta lenta. Per questo motivo il sistema deve aprire un incidente solo dopo più errori consecutivi.

La logica corretta è:

il sito viene controllato periodicamente;
se un controllo fallisce, il sistema registra l’errore;
se il sito fallisce per un numero configurato di volte consecutive, viene aperto un incidente;
quando l’incidente viene aperto, viene inviata una email;
il sistema continua a controllare il sito;
quando il sito torna disponibile per un numero configurato di controlli consecutivi, l’incidente viene chiuso;
quando l’incidente viene chiuso, viene inviata una email di recovery.

Questa logica serve a evitare falsi positivi e notifiche inutili.

Gestione dei monitor

Dal backend deve essere possibile creare e modificare i siti da monitorare.

La creazione di un monitor deve essere semplice. L’utente interno deve inserire almeno un nome e un URL. Tutti gli altri valori devono avere default ragionevoli.

Per ogni monitor devono essere configurabili:

nome interno
URL da controllare
stato attivo o sospeso
frequenza di controllo
timeout
codici HTTP considerati validi
gestione dei redirect
controllo SSL
keyword opzionale da cercare nella pagina
numero di fallimenti consecutivi necessari per aprire un incidente
numero di successi consecutivi necessari per chiudere un incidente
pubblicazione o meno nella status page
nome pubblico da mostrare nella status page
note interne

I valori di default devono essere adatti alla maggior parte dei casi:

controllo ogni 5 minuti
timeout di 10 secondi
codici validi: 200, 301, 302
redirect abilitati
controllo SSL abilitato
apertura incidente dopo 2 fallimenti consecutivi
chiusura incidente dopo 2 successi consecutivi

Quando un monitor viene creato, il sistema deve poter eseguire subito un primo controllo manuale, così da verificare se l’URL è corretto e raggiungibile.

Un monitor può trovarsi in diversi stati:

unknown, quando non è ancora stato controllato
online, quando risponde correttamente
down, quando ha un incidente aperto
maintenance, quando è dentro una finestra di manutenzione
paused, quando è sospeso manualmente

Il backend deve permettere di sospendere e riattivare un monitor. Un monitor sospeso non deve essere controllato e non deve generare incidenti.

Controllo periodico dei siti

Il controllo periodico deve essere gestito tramite Laravel Scheduler e Laravel Queue.

Il cron di sistema deve eseguire php artisan schedule:run ogni minuto. Lo scheduler non deve controllare direttamente i siti. Deve solo identificare quali monitor sono da controllare e inserire i job nella coda.

I worker devono prendere i job dalla coda ed eseguire i controlli HTTP/HTTPS.

Questo approccio è importante perché separa la pianificazione dall’esecuzione. Se in futuro i monitor aumentano, sarà possibile aumentare il numero di worker senza riscrivere la logica.

Ogni controllo deve:

verificare che il monitor sia attivo
verificare se è in corso una manutenzione programmata
eseguire la richiesta HTTP/HTTPS
rispettare il timeout configurato
seguire i redirect se abilitati
misurare il tempo di risposta
controllare il codice HTTP
verificare SSL/TLS se abilitato
cercare la keyword se configurata
classificare eventuali errori
salvare il risultato
aggiornare lo stato del monitor
valutare se aprire o chiudere un incidente

Il controllo non deve salvare il contenuto HTML della pagina. Deve salvare solo metadati tecnici: esito, codice HTTP, tempo di risposta, errore rilevato, data e ora del controllo.

Classificazione degli errori

Gli errori devono essere classificati in modo leggibile.

Non basta registrare “failed”. Il sistema deve distinguere almeno tra:

DNS non risolto
timeout
connessione rifiutata
errore SSL/TLS
codice HTTP non valido
errore HTTP 4xx
errore HTTP 5xx
redirect loop
keyword assente
risposta vuota
errore sconosciuto

Questa classificazione deve essere visibile nella scheda del monitor e nella scheda dell’incidente.

L’obiettivo è capire rapidamente il tipo di problema senza dover leggere log tecnici grezzi.

Esempi:

HTTP 500 — il server ha restituito un errore interno
Timeout — il sito non ha risposto entro 10 secondi
SSL error — il certificato non è valido o la connessione TLS non è riuscita
Keyword assente — la pagina risponde ma non contiene il testo atteso
Apertura e chiusura degli incidenti

Un incidente deve essere aperto automaticamente quando un monitor fallisce per un numero configurato di controlli consecutivi.

Per default, usare 2 fallimenti consecutivi.

Un singolo errore non deve aprire subito un incidente.

Quando viene aperto un incidente, il sistema deve salvare:

monitor interessato
data e ora apertura
causa iniziale
ultimo errore rilevato
numero di controlli falliti
stato dell’incidente
eventuale visibilità pubblica nella status page

Se un incidente è già aperto per quel monitor, il sistema non deve aprirne un altro. Deve invece aggiornare l’incidente esistente, aggiungendo gli eventi successivi alla sua timeline.

La chiusura deve avvenire automaticamente quando il monitor torna disponibile per un numero configurato di controlli consecutivi.

Per default, usare 2 successi consecutivi.

Quando l’incidente viene chiuso, il sistema deve salvare:

data e ora chiusura
durata complessiva
causa iniziale
ultimo esito positivo
invio della notifica di recovery

La scheda incidente deve mostrare una timeline leggibile degli eventi principali.

Esempio:

incidente aperto
email down inviata
controllo ancora fallito
sito tornato disponibile
incidente chiuso
email recovery inviata
Notifiche email

La prima versione deve inviare solo notifiche email.

Devono esistere due tipi di notifica:

email di down
email di recovery

Per semplicità, i destinatari possono essere configurati globalmente nelle impostazioni dell’applicazione o nel file .env. Non serve una gestione avanzata dei contatti.

L’email di down deve essere inviata quando viene aperto un incidente.

Deve contenere:

nome monitor
URL
data e ora del problema
errore rilevato
codice HTTP, se disponibile
numero di controlli falliti
link alla scheda interna del monitor o dell’incidente, se disponibile

Oggetto suggerito:

[Monitor] Sito non disponibile: {nome_monitor}

L’email di recovery deve essere inviata quando l’incidente viene chiuso.

Deve contenere:

nome monitor
URL
data e ora del ripristino
durata dell’incidente
causa iniziale
ultimo tempo di risposta rilevato

Oggetto suggerito:

[Monitor] Sito tornato online: {nome_monitor}

Ogni invio deve essere tracciato. Deve essere possibile vedere se una notifica è stata inviata correttamente o se il provider email ha restituito errore.

Dashboard interna

Il backend interno deve essere realizzato con Filament.

La dashboard deve dare una vista immediata dello stato generale.

Deve mostrare:

numero totale di monitor
numero di monitor online
numero di monitor down
numero di monitor in manutenzione
numero di monitor sospesi
numero di incidenti aperti
ultimo controllo eseguito
ultimi incidenti rilevati

La lista dei monitor deve mostrare almeno:

nome
URL
stato
ultimo controllo
ultimo codice HTTP
ultimo tempo di risposta
prossimo controllo previsto
pubblicazione nella status page
azioni disponibili

Le azioni principali devono essere:

creare monitor
modificare monitor
sospendere monitor
riattivare monitor
eseguire check manuale
vedere ultimi check
vedere incidenti collegati

La scheda del singolo monitor deve essere il punto operativo principale. Da lì deve essere possibile capire lo stato del sito, vedere l’ultimo errore, consultare gli ultimi controlli ed entrare negli incidenti.

Status page pubblica

La status page deve essere inclusa nella prima versione.

Deve essere una pagina pubblica, accessibile senza login, raggiungibile per esempio da:

/status

Per la prima versione basta una sola status page globale.

La status page deve mostrare solo i monitor esplicitamente pubblicati. Non tutti i monitor interni devono essere visibili pubblicamente.

Per ogni monitor pubblicato deve essere possibile definire un nome pubblico diverso dal nome interno.

Esempio:

Nome interno:

Devisia — Homepage produzione

Nome pubblico:

Sito Devisia

La status page deve mostrare:

titolo della pagina
stato generale
lista dei servizi pubblicati
stato di ogni servizio
eventuali incidenti aperti
eventuali manutenzioni in corso o programmate
storico recente degli incidenti
data ultimo aggiornamento

Lo stato generale deve essere calcolato automaticamente.

Regole:

se almeno un monitor pubblicato è down, mostrare “Problemi su uno o più servizi”
se almeno un monitor è in manutenzione, mostrare “Manutenzione in corso”
se tutti i monitor pubblicati sono online, mostrare “Tutti i servizi operativi”
se non ci sono dati, mostrare “Stato non disponibile”

La status page non deve esporre dati tecnici sensibili.

Non deve mostrare:

URL tecnici completi, salvo scelta esplicita futura
codici HTTP
errori grezzi
IP
dettagli SSL
note interne
informazioni infrastrutturali

Deve mostrare solo informazioni controllate e comprensibili.

Esempio:

Devisia Status

Tutti i servizi operativi

Sito Devisia — Operativo
AuditReady — Operativo
Hooplytics — Operativo

Ultimo aggiornamento: 22/06/2026 14:30

In caso di incidente:

Devisia Status

Problemi su uno o più servizi

Sito Devisia — Operativo
AuditReady — Problemi rilevati
Hooplytics — Operativo

Incidenti attivi

AuditReady
Problema rilevato alle 14:12. Sono in corso verifiche.

Gli incidenti pubblici devono avere un messaggio pubblico opzionale. Se il messaggio non è presente, mostrare un testo generico, senza dettagli tecnici.

Manutenzioni programmate

L’applicazione deve permettere di creare finestre di manutenzione.

Una manutenzione deve avere:

titolo
data e ora inizio
data e ora fine
monitor coinvolti
visibilità nella status page
messaggio pubblico opzionale
note interne

Durante una manutenzione:

i check continuano a essere eseguiti
i risultati vengono salvati
non vengono aperti incidenti automatici
non vengono inviate email di down
la status page può mostrare lo stato “manutenzione”

Questa funzione serve a evitare alert inutili durante interventi pianificati.

Per la prima versione non servono ricorrenze o calendari complessi. Basta creare finestre singole.

Check manuale

Dal backend deve essere possibile eseguire un check manuale su un monitor.

Il check manuale serve per verificare subito lo stato di un sito senza aspettare il prossimo controllo schedulato.

Il risultato deve essere salvato e mostrato nella scheda monitor.

Per semplicità, il check manuale non deve influenzare la logica automatica degli incidenti. Non deve aprire o chiudere incidenti, salvo decisione esplicita futura.

Validazione e sicurezza degli URL

L’applicazione deve accettare solo URL HTTP e HTTPS.

Devono essere rifiutati URL locali, privati o potenzialmente pericolosi.

Bloccare almeno:

localhost
127.0.0.1
0.0.0.0
IP privati
IP loopback
IP link-local
endpoint metadata cloud
schemi diversi da http:// e https://

Bloccare quindi anche:

10.0.0.0/8
172.16.0.0/12
192.168.0.0/16
169.254.169.254
IPv6 locali

Questa protezione è importante perché lo strumento non deve diventare un modo per interrogare servizi interni o reti private.

Il prodotto deve monitorare solo siti pubblici.

Retention dei dati

I check grezzi non devono essere conservati per sempre.

Per la prima versione:

conservare i check per 30 giorni
conservare gli incidenti senza scadenza
conservare gli eventi degli incidenti senza scadenza
conservare i log delle notifiche per 12 mesi

Prevedere un job notturno di pulizia che cancelli i check più vecchi della retention.

Il job di pulizia non deve cancellare incidenti, timeline o dati necessari per ricostruire la storia dei problemi.

Report base

Nella prima versione non serve generare PDF.

È sufficiente una vista interna con alcune informazioni sintetiche.

Per ogni monitor mostrare:

uptime ultimi 7 giorni
uptime ultimi 30 giorni
numero incidenti ultimi 30 giorni
downtime totale ultimi 30 giorni
tempo medio di risposta

Questi dati possono essere calcolati dai check disponibili. In futuro si potrà introdurre una tabella di aggregazione giornaliera per rendere i report più efficienti.

Il report PDF mensile è una funzione futura.

Impostazioni globali

Devono esistere impostazioni globali per governare il comportamento dell’applicazione.

Possono essere gestite tramite .env o tramite una sezione settings in Filament.

Impostazioni utili:

nome della status page
email destinatari alert
email mittente
frequenza default
timeout default
codici HTTP validi di default
numero default di fallimenti prima di incidente
numero default di successi prima di recovery
retention dei check
user-agent usato nei controlli

User-agent consigliato:

DevisiaMonitor/1.0 (+https://devisia.pro)
Queue e worker

Le attività devono essere separate in code.

Per la prima versione bastano tre code:

checks
notifications
cleanup

La coda checks deve contenere i controlli dei siti.

La coda notifications deve contenere gli invii email.

La coda cleanup deve contenere le attività di pulizia dati.

Su Cloudways configurare Supervisor per mantenere attivi i worker.

Comandi indicativi:

php artisan queue:work --queue=checks --timeout=30 --tries=2
php artisan queue:work --queue=notifications --timeout=60 --tries=3
php artisan queue:work --queue=cleanup --timeout=120 --tries=1

Se il server ha risorse sufficienti, si possono avviare due worker per la coda checks.

Distribuzione dei controlli nel tempo

I monitor non devono essere controllati tutti nello stesso istante.

Ogni monitor deve avere una data di prossimo controllo.

Lo scheduler deve selezionare i monitor con prossimo controllo scaduto e creare i relativi job.

Dopo aver schedulato un controllo, il sistema deve calcolare il successivo.

Quando viene creato un nuovo monitor, il primo controllo automatico può essere distribuito con un piccolo offset casuale. Questo evita che centinaia di monitor vengano controllati nello stesso secondo.

Esempio:

se un monitor ha frequenza 5 minuti, il suo controllo può essere distribuito in un punto qualsiasi di quei 5 minuti.

Questa scelta rende il sistema più stabile e riduce i picchi.

Privacy e contenuto delle pagine

Il sistema non deve fare scraping.

Il sistema non deve salvare il contenuto HTML delle pagine.

Il controllo keyword deve limitarsi a verificare se una stringa è presente nella risposta, senza archiviare la risposta stessa.

Salvare solo dati tecnici minimi:

URL
esito
codice HTTP
tempo risposta
errore
timestamp

Questo mantiene il prodotto semplice e riduce rischi inutili.

Ordine di sviluppo consigliato

Sviluppare prima il nucleo del prodotto.

Prima fase:

backend Filament
creazione monitor
check manuale
salvataggio risultato
visualizzazione ultimi check

Seconda fase:

scheduler
queue
worker automatici
aggiornamento stato monitor

Terza fase:

logica incidenti
apertura automatica
chiusura automatica
timeline incidente

Quarta fase:

email down
email recovery
log notifiche

Quinta fase:

status page pubblica
pubblicazione selettiva dei monitor
stato generale
incidenti visibili pubblicamente

Sesta fase:

maintenance window
sospensione alert durante manutenzione
stato manutenzione nella status page

Settima fase:

retention check
cleanup notturno
report base
Criteri di accettazione

La prima versione è completa quando posso:

accedere al backend
creare un monitor HTTP/HTTPS
eseguire un check manuale
vedere l’esito del check
lasciare che il sistema controlli automaticamente il sito
vedere lo storico dei check
vedere lo stato aggiornato del monitor
ricevere una email quando il sito va giù
ricevere una email quando il sito torna online
vedere un incidente aperto automaticamente dopo errori consecutivi
vedere un incidente chiuso automaticamente dopo recovery confermata
consultare la timeline dell’incidente
sospendere e riattivare un monitor
creare una manutenzione programmata
evitare alert durante la manutenzione
pubblicare alcuni monitor nella status page
vedere la status page pubblica senza login
vedere sulla status page lo stato dei servizi
vedere sulla status page gli incidenti aperti
cancellare automaticamente i check più vecchi della retention

Il prodotto non deve fare altro nella prima versione.

Roadmap futura

Dopo la prima versione si potranno valutare:

report PDF mensile
invio automatico dei report
notifiche Telegram
notifiche Teams
webhook
status page multiple
status page con slug personalizzato
dominio custom
controllo DNS
controllo scadenza dominio
controllo heartbeat
secondo probe esterno
integrazione con AuditReady
esportazione evidenze

Queste funzioni non devono essere sviluppate ora.

Sintesi finale

Costruire una Laravel app interna, ospitata su Cloudways, per monitorare siti web pubblici.

Il prodotto deve restare semplice.

Il cuore è:

monitor
check periodici
incidenti
email
status page

Non servono clienti, piani, fatturazione, API o notifiche avanzate.

La prima versione deve essere piccola ma affidabile. Deve controllare i siti, rilevare problemi reali, avvisare via email, mostrare lo stato interno e pubblicare una status page chiara.