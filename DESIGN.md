---
name: PingSites Status
description: Status page pubblica — control room quieta, semaforo deciso
colors:
  paper: "#f8fafc"
  surface: "#ffffff"
  ink: "#0f172a"
  ink-muted: "#64748b"
  ink-secondary: "#475569"
  ink-faint: "#94a3b8"
  rule: "#e2e8f0"
  hairline: "#f1f5f9"
  chip-idle: "#f1f5f9"
  chip-idle-text: "#475569"
  chip-idle-hover: "#e2e8f0"
  chip-active: "#0f172a"
  chip-active-text: "#ffffff"
  signal-ok: "#059669"
  signal-ok-soft: "#d1fae5"
  signal-ok-ink: "#047857"
  signal-ok-bar: "#10b981"
  signal-warn: "#d97706"
  signal-warn-soft: "#fef3c7"
  signal-warn-ink: "#b45309"
  signal-warn-wash: "#fffbeb"
  signal-warn-rule: "#fde68a"
  signal-down: "#dc2626"
  signal-down-soft: "#fee2e2"
  signal-down-ink: "#b91c1c"
  signal-down-wash: "#fef2f2"
  signal-down-rule: "#fecaca"
  signal-down-bar: "#ef4444"
  link: "#2563eb"
  link-hover: "#1e40af"
  chart-line: "#3b82f6"
typography:
  display:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.875rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "normal"
  headline:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.25
  title:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: 1.4
  body:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 400
    lineHeight: 1.4
rounded:
  full: "9999px"
  xl: "0.75rem"
  lg: "0.5rem"
spacing:
  chip-x: "0.75rem"
  chip-y: "0.25rem"
  card: "1.5rem"
  stat: "1.25rem"
  page-x: "1rem"
  page-y: "2rem"
  stack: "2rem"
  gutter: "1rem"
components:
  chip-filter-active:
    backgroundColor: "{colors.chip-active}"
    textColor: "{colors.chip-active-text}"
    rounded: "{rounded.full}"
    padding: "0.25rem 0.75rem"
    typography: "{typography.body}"
  chip-filter-idle:
    backgroundColor: "{colors.chip-idle}"
    textColor: "{colors.chip-idle-text}"
    rounded: "{rounded.full}"
    padding: "0.25rem 0.75rem"
    typography: "{typography.body}"
  chip-filter-idle-hover:
    backgroundColor: "{colors.chip-idle-hover}"
    textColor: "{colors.chip-idle-text}"
  status-operational:
    backgroundColor: "{colors.signal-ok-soft}"
    textColor: "{colors.signal-ok-ink}"
    rounded: "{rounded.full}"
    padding: "0.25rem 0.75rem"
  status-maintenance:
    backgroundColor: "{colors.signal-warn-soft}"
    textColor: "{colors.signal-warn-ink}"
    rounded: "{rounded.full}"
    padding: "0.25rem 0.75rem"
  status-down:
    backgroundColor: "{colors.signal-down-soft}"
    textColor: "{colors.signal-down-ink}"
    rounded: "{rounded.full}"
    padding: "0.25rem 0.75rem"
  status-unknown:
    backgroundColor: "{colors.chip-idle}"
    textColor: "{colors.chip-idle-text}"
    rounded: "{rounded.full}"
    padding: "0.25rem 0.75rem"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.xl}"
    padding: "{spacing.card}"
  card-incident:
    backgroundColor: "{colors.signal-down-wash}"
    textColor: "{colors.signal-down-ink}"
    rounded: "{rounded.xl}"
    padding: "{spacing.card}"
  card-maintenance:
    backgroundColor: "{colors.signal-warn-wash}"
    textColor: "{colors.signal-warn-ink}"
    rounded: "{rounded.xl}"
    padding: "{spacing.card}"
  stat-card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.xl}"
    padding: "{spacing.stat}"
  link:
    textColor: "{colors.link}"
    typography: "{typography.body}"
  link-hover:
    textColor: "{colors.link-hover}"
---

# Design System: PingSites Status

## Overview

**Creative North Star: "The Quiet Control Room"**

La status page pubblica è una sala operativa chiara: carta, inchiostro, e un semaforo che parla solo quando serve. Lo sfondo resta quieto; chip di filtro e badge di stato sono decisi — fill pieni, contrasto alto, nessuna decorazione intorno.

Il cliente deve leggere lo stato in pochi secondi. Personalità: fattuale, tabellare, italiana. Filosofia: peso sul segnale, non sulla chrome. Anti-riferimento confermato: né dashboard SaaS (sidebar, KPI rainbow, illustration, gradient hero) né clone Statuspage/Atlassian (logo enorme, illustrazioni, footer da marketing).

Questo file documenta **solo la status page pubblica** (`resources/views/status/*`). L’admin Filament è un vernacolo separato e non mescola token con questa pagina.

**Key Characteristics:**
- Carta fredda e inchiostro ardesia; il colore esiste per lo stato.
- Chip e badge a pill, fill pieno, contrasto alto.
- Card con bordo + ombra minima; wash rosso/ambra solo per allarme e manutenzione.
- Tabella come oggetto primario; numeri in forma tabulare.
- Link blu operativo, non un accento di brand.

## Colors

Inchiostro ardesia per struttura; segnale verde/ambra/rosso per lo stato; blu solo per navigazione.

### Primary
- **Inchiostro ardesia** (`ink`): testo principale, titolo pagina, chip filtro attivo. È la voce della control room, non un accento.
- **Carta fredda** (`paper`) e **superficie** (`surface`): pagina e card. Occupano la maggior parte dello schermo di proposito.

### Secondary
- **Link blu operativo** (`link` / `link-hover`): “Dettaglio”, back-link, linea del grafico. Mai fill di sezione.

### Tertiary
- **Segnale OK / warn / down**: unico colore semantico. Testo di overall status, uptime, badge, wash di incidenti e manutenzioni, barre timeline. Non usare questi verdi/ambra/rossi per chrome.

### Neutral
- **Inchiostro spento** (`ink-muted`, `ink-secondary`, `ink-faint`): meta, thead, timestamp, footnote, empty.
- **Riga / capello** (`rule`, `hairline`): bordo card, divisori tabella.

### Named Rules
**The Signal Privilege Rule.** Verde, ambra e rosso appaiono solo su stato, uptime, incidenti, manutenzioni e timeline. Se un elemento non comunica salute, resta ardesia.

**The One Voice Rule.** Il blu è solo navigazione. Non diventa brand fill, hero o bottone primario.

## Typography

**Display Font:** UI sans di sistema (`ui-sans-serif, system-ui, sans-serif`)
**Body Font:** stessa famiglia
**Label/Mono Font:** numeri con `tabular-nums` sui conteggi filtro; niente mono dedicato

**Character:** una sola sans di sistema, peso 400/600/700. Gerarchia per peso e misura, non per famiglia. Le view pubbliche non caricano Instrument Sans (quella è solo nel tema Vite, non usato qui).

### Hierarchy
- **Display** (700, 1.875rem): titolo pagina o nome servizio.
- **Headline** (700, 1.5rem): KPI sulla pagina dettaglio (disponibilità, ms).
- **Title** (600, 1.25rem): intestazioni di sezione (Servizi, Incidenti, Tempi di risposta).
- **Body** (400, 0.875rem): celle tabella, chip, link. Overall status in header è 1.125rem / 500-equivalent (`text-lg`) sopra questa scala.
- **Label** (400, 0.75rem): URL sotto il nome, footnote, assi timeline, caption KPI.

### Named Rules
**The One Sans Rule.** Nessun serif, nessun display font, nessun logo wordmark sulla pagina pubblica.

## Layout

Colonna unica `max-w-5xl` (64rem) centrata, padding pagina `1rem` / `2rem`, stack verticale `2rem`. Header a tutta larghezza su superficie, bordo inferiore `rule`. Main: card impilate.

Tabella servizi: colonne progressive — Ultimo controllo da `sm`, Risposta da `md`, Disponibilità da `lg`. Filtri: wrap a pill; da `lg` stato e pubblicazione sulla stessa riga.

Dettaglio servizio: griglia KPI 1 → 3 colonne da `sm`. Grafico alto `16rem`. Timeline disponibilità: barra unica a segmenti flex.

**The Five-Second Scan Rule.** Titolo + overall status in header; tabella o KPI subito sotto. Niente hero, sidebar o footer di prodotto.

## Elevation & Depth

Ibrida: le card a riposo hanno bordo `rule` e ombra ambient minima. Il wash tonale (rosso/ambra) è lo strato di allarme, non un’ombra più forte.

### Shadow Vocabulary
- **Ambient card** (`box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05)`): card bianche e stat. Non usarla sui pannelli incidente/manutenzione (lì parla il wash).

### Named Rules
**The Tone-Not-Lift Rule.** Allarme = wash + bordo del segnale. Non alzare la card con un’ombra più pesante.

## Shapes

Rettangoli morbidi per contenitori (`rounded-xl`, 0.75rem). Pill complete per filtri e badge (`rounded-full`). Timeline: `rounded-lg` (0.5rem). Bordi 1px, mai tratteggiati.

**The Pill Means State Rule.** `rounded-full` è riservato a filtro e badge di stato. Le card non diventano capsule.

## Components

### Buttons
Non esistono bottoni submit. L’azione primaria è un **link testuale** (`link` → `link-hover`, 0.875rem / 500).

### Chips
- **Filtro attivo:** fill `chip-active`, testo bianco, pill, padding `0.25rem 0.75rem`.
- **Filtro idle:** fill `chip-idle`, testo `chip-idle-text`; hover `chip-idle-hover`.
- **Badge stato:** stesso involucro pill; fill soft del segnale + inchiostro del segnale. Quattro valori: operational / maintenance / down / unknown.

### Cards / Containers
- **Card standard:** superficie, bordo `rule`, `rounded-xl`, padding `1.5rem`, ombra ambient.
- **Stat:** stesso involucro, padding `1.25rem`.
- **Incidente attivo:** wash down, bordo `signal-down-rule`, niente ombra.
- **Manutenzione:** wash warn, bordo `signal-warn-rule`, niente ombra.

### Inputs / Fields
Nessun campo form sulla pagina pubblica. I filtri sono link-chip, non `<select>`.

### Navigation
Header senza nav di prodotto. Back-link blu. Filtri come chip, `aria-label` su ciascun gruppo.

### Status table (signature)
Tabella `text-sm`, thead `ink-muted`, divisori `hairline`. Nome servizio in medium; URL `label` troncato. Uptime colorato per soglia (≥99 OK, ≥95 warn, <95 down). Empty: una riga, `ink-muted`.

### Uptime timeline (signature)
Barra `2rem`, segmenti flex: `signal-ok-bar` / `signal-down-bar`. Dettaglio solo in `title`.

## Do's and Don'ts

### Do:
- **Do** tenere carta + inchiostro come default e spendere il colore solo sullo stato.
- **Do** usare pill piene e contrasto alto su chip/badge.
- **Do** far leggere header + tabella (o KPI) in un colpo, colonna `max-w-5xl`.
- **Do** usare wash rosso/ambra per incidenti e manutenzioni, non per sezioni neutre.

### Don't:
- **Don't** introdurre sidebar, gradient hero, illustration o KPI rainbow.
- **Don't** copiare Statuspage/Atlassian (logo grande, mascotte, footer marketing).
- **Don't** mescolare token Filament/admin in questa pagina.
- **Don't** usare Instrument Sans qui: la pagina pubblica è system UI sans.
- **Don't** inventare un bottone primario colorato: l’azione resta un link blu.
