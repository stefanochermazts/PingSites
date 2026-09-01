# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Primary: clienti Devisia che aprono la status page pubblica (senza login) per capire se i propri siti sono operativi, in manutenzione o in problema.

Secondary: team interno Devisia (ops/dev) che in `/admin` crea i monitor, gestisce incidenti, destinatari email e pagine di stato. Non è il pubblico da progettare per primo.

## Product Purpose

PingSites controlla URL pubblici HTTP/HTTPS, conferma un incidente solo dopo fallimenti consecutivi, avvisa via email e pubblica lo stato su una status page per gruppo/cliente.

Successo: un cliente capisce lo stato dei propri servizi in pochi secondi; il team interno riceve alert veri, non rumore.

## Positioning

La status page è parte del servizio che Devisia offre al cliente, non un cruscotto interno accidentale. Il meccanismo che un prodotto generico di monitoring non copia è questo: monitoraggio interno dell’agenzia + pagina pubblica per-cliente, con destinatari e perimetro distinti per pagina.

Non è un SaaS multi-tenant, non è monitoring infrastrutturale (no ping, DNS, porte, heartbeat).

## Operating Context

- Admin Filament su `/admin` (IT), status pubbliche su `/status/{slug}`.
- Hosting Cloudways: cron, Supervisor, Redis (`checks`, `notifications`, `cleanup`), SMTP esterno (Elastic Email). Niente SMTP locale per gli alert.
- Lingua UI: italiano. Fuso: Europe/Rome.
- Un monitor = un URL. Un check = un controllo. Un incidente = problema confermato, non un singolo errore.
- Manutenzioni programmate sospendono gli alert e appaiono sulla status page.
- Destinatari email di down/recovery: per status page. Mittente e default tecnici restano globali.

## Capabilities and Constraints

In scope (confermato):

- monitor HTTP/HTTPS, check automatici e manuali, soglie down/recovery
- email down/recovery, log notifiche
- più status page, pubblicazione selettiva, filtri stato/pubblicazione
- manutenzioni, retention check e log
- import/sync URL da Cloudways

Fuori perimetro (non condizionare il design):

- clienti/billing/ruoli complessi, API pubblica
- Telegram/Teams/Slack/SMS, webhook, probe distribuiti
- dominio custom sulla status page, app mobile
- scraping o salvataggio HTML delle pagine controllate

Aperto: roadmap (PDF, altri canali, dominio custom) — non impegnata.

## Brand Commitments

- Admin: brand name `PingSites Monitor`.
- Status pubbliche: titolo per pagina (es. Devisia Status, Publimedia), non un brand prodotto unico.
- Tracce Devisia vincolanti dove già usate: mittente mail, user-agent `DevisiaMonitor/1.0 (+https://devisia.pro)`.
- Voce: italiana, fattuale, operativa (stati e orari, non marketing).

## Evidence on Hand

- Spec: `documents/analisi-funzionale.md`, `README.md`
- Superfici: `resources/views/status/*.blade.php`, pannello Filament
- Nessuna testimonial, caso cliente o claim di uptime da inventare. I dati reali sono check, incidenti e titoli pagina.

## Product Principles

1. Il cliente legge lo stato, non il monitoring: niente gergo interno sulla pagina pubblica.
2. Confermare prima di allarmare: soglie, recovery e manutenzioni battono la reattività rumorosa.
3. Ogni status page è un perimetro (monitor + destinatari + titolo) rivolto a un cliente o gruppo.
4. Restare piccoli: niente SaaS, canali extra o infrastruttura di monitoring finché non sono decisi.
5. Salvare solo evidenza tecnica minima (URL, esito, codice, tempo, errore, timestamp).

## Accessibility & Inclusion

WCAG 2.2 AA è richiesto, in particolare sulla status page pubblica (primario). L’admin Filament deve restare usabile e coerente, senza abbassare lo standard della pagina cliente.
