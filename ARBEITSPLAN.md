# Arbeitsplan: Code Modernization (Szenario 1) — 4 Personen, 60 Minuten

## Context
Hackathon-Szenario 1 ("The Monolith"): Northwind Logistics läuft auf einem alten
System, niemand versteht es, der Vorstand will "Modernisierung". Wir beweisen, dass
es **sicher und schrittweise** (Strangler Fig) evolviert werden kann — kein Big-Bang-Rewrite.

> **WICHTIG — der Monolith existiert bereits.** Challenge 2 ("The Patient", Monolith
> generieren) ist **schon erledigt**: `monolith/` enthält den fertigen PHP-5-era-Monolith
> (mysql_*, God-Class `Northwind.php`, 5 MySQL-Trigger mit versteckter Logik, 3-fach
> initialisiertes BAV-Singleton). Wir **generieren nichts mehr** — der harte Blocker des
> alten Plans ist weg. Das verschiebt den kritischen Pfad: **alle 4 Spuren starten ab Minute 0**.

Entscheidungen des Teams (an den realen Monolith angepasst):
- **Legacy-Stack:** PHP-5-era Monolith mit **MySQL** (nicht SQLite — der Monolith nutzt
  `mysql_*` + echte MySQL-Trigger; das ist Teil des Patienten und bleibt so).
- **Modernisierung Richtung:** Strangler Fig — erste Extraktion eines Service mit sauberem API-Vertrag.
- **Erster Extraktions-Kandidat (Seam):** **Bank-Validation-Service** (BAV-Validierung) —
  NICHT Pricing. Begründung unten.

### Warum Bank-Validation der erste Seam ist (nicht Pricing)
Der Monolith hat **kein** Pricing-Modul. Der designierte, niedrigst-riskante erste Seam ist
durchgängig dokumentiert (`monolith/CLAUDE.md`, `monolith/classes/CLAUDE.md`):
- Klar abgegrenzt: alles was `malkusch/bav` aufruft (`classes/BankValidator.php`,
  BAV-Aufrufe in `Northwind::verarbeiteZahlung()`, `validateBankleitzahl()` in `helper.php`).
- Hoher Lehrwert: extrahiert den berüchtigten **3-fach überschriebenen `ConfigurationRegistry`-Singleton**.
- Keine FK-Verflechtung nach außen → ideal für den ersten "Cut".
- Der Service ist **ohne MySQL lauffähig** (BAV File-Backend) → trivial standalone testbar + in CI.

### Projekt-Ist-Zustand (was WIRKLICH im Repo liegt — Stand jetzt)
**Vorhanden (nicht neu anlegen):**
```
CLAUDE.md                         ← Projekt-Ebene (Root), bereits gepflegt
ARBEITSPLAN.md                    ← dieser Plan
bav/                              ← malkusch/bav-Library (Dependency, unverändert)
doc/initial context/              ← Challenge-Beschreibungen (01-code-modernization.md …)
.claude/my-plans/monolith-plan.md ← Generierungs-Plan des Monolithen (historisch)
monolith/
├── CLAUDE.md                     ← Verzeichnis-Ebene (Monolith-Kontext)
├── composer.json  config.php  init.php  index.php  helper.php
├── classes/  CLAUDE.md · Northwind.php · NorthwindDB.php · BankValidator.php
├── pages/    login, kunden, kunden_edit, artikel, artikel_edit, auftraege,
│             auftrag_neu, auftrag_detail, lieferungen, lieferung_detail,
│             rechnungen, rechnung_detail, rechnung_zahlung
├── includes/ header.php · footer.php · pagination.php
├── cron/     bav_update.php · mahnlauf.php · update_lieferstatus.php
├── sql/      schema.sql  (5 Trigger)
└── tests/    AuftragTest · BankValidatorTest · KundenTest · RechnungTest
```
> Die Drei-Ebenen-`CLAUDE.md` (`CLAUDE.md`, `monolith/CLAUDE.md`, `monolith/classes/CLAUDE.md`)
> und die 4 Characterization-Tests existieren **bereits**. Challenge 2 ("The Patient") ist erledigt.

**Anzulegen (Hackathon-Output — existiert noch NICHT):**
```
bank-validation-service/   ← neuer extrahierter Service (Sibling von monolith/)
monolith/docs/             ← ADR-001/002/003
stories/                   ← User Stories
eval/                      ← Scorecard
agentic/                   ← Scouts (Coordinator + Verdicts)
.claude/hooks|skills/      ← PreToolUse-Hook + Custom-Skill/Slash-Command
README.md  presentation.html  RUNBOOK.md  run-tests.sh   ← in Wurzel
```

### Wonach bewertet wird (steuert diesen Plan)
**Claude liest in jedem Fall 3 Dateien — sie sind unser Pitch, NICHT die letzten 10 Minuten:**
`README.md` (die Story), `presentation.html` (5-Min-Pitch), `CLAUDE.md` (wie wir das Tool angelernt haben).
→ Diese 3 entstehen **durchgehend** ab Minute 0; jede Person liefert ihren Abschnitt sofort nach Fertigstellung.

> **Hinweis CLAUDE.md:** Die Drei-Ebenen-`CLAUDE.md` **existiert bereits** (Repo-Root,
> `monolith/`, `monolith/classes/`). Wir schreiben sie nicht neu — wir **erweitern** sie und
> ergänzen die Service-Ebene (`bank-validation-service/CLAUDE.md`) + die "prefer new service"-Regel.

**5 Kategorien, auf die wir bewusst hinarbeiten:**
1. **Most production-ready** — "Montag an Ops übergebbar" (Cutover-Runbook, CI, How-to-Run)
2. **Best architecture thinking** — ADRs, Diagramme, Entscheidungen, die altern (Map-ADR, Hook-vs-Prompt-ADR)
3. **Best testing** — adversariell, Edge Cases, **Evals** (Characterization-Suite + Scorecard-Eval)
4. **Best product work** — Stories, die überzeugen (User Stories mit scharfen Akzeptanzkriterien)
5. **Most inventive Claude Code use** — Subagents, Hooks, Skills (Scouts, PreToolUse-Hook, Custom-Skill/Slash-Command)

---

## Bewertungs-Mapping (jede Kategorie hat einen Owner + ein konkretes Artefakt)
| Kategorie | Artefakt im Repo | Challenge | Owner |
|---|---|---|---|
| Most production-ready | `bank-validation-service/` läuft, CI grün, `RUNBOOK.md` (Cutover + Rollback) | 5, 8 | **P3** |
| Best architecture thinking | `monolith/docs/ADR-001-strangler-fig-bank-validation.md` + Diagramm, `ADR-002-anti-corruption-layer.md`, `ADR-003-trigger-explizit-machen.md`, Fence-ADR | 3, 6 | **P1** |
| Best testing | Characterization-Suite (`monolith/tests/`, Bugs gepinnt, Edge Cases) + `eval/scorecard` (False-Confidence-Rate) | 4, 7 | **P2** |
| Best product work | `stories/` — User Stories + scharfe Akzeptanzkriterien, Stakeholder-Konflikte explizit | 1 | **P4** |
| Most inventive Claude Code | Scouts-Subagents, PreToolUse-Hook (Fence), Custom-Skill + Slash-Command für die Extraktions-Playbook | 6, 9 | **P4 + P1** |
| **Pflicht-Docs (durchgehend)** | `README.md`, `presentation.html`, `CLAUDE.md` (erweitern) | — | **alle, P4 stitcht** |

> Prinzip: **Tiefe schlägt Breite.** Jede Kategorie wird mindestens dünn erreicht (Pflicht),
> dann gehen wir dort in die Tiefe, wo Zeit bleibt (Priorität siehe unten).
> **Zeit-Bonus:** Da der Monolith schon steht, haben wir ~13 Min mehr für Tiefe als geplant.

---

## Rollen (jede Person besitzt eine Bewertungs-Achse + ihren Doc-Abschnitt)
- **P1 – Architect:** Monolith **verstehen** (Patient lesen, nicht bauen) → Map-ADR + Diagramm + Trigger-ADR + Fence-ADR (Hook vs. Prompt). *Owner: Architecture.*
- **P2 – Tester/Quality:** Vorhandene Characterization-Tests **grün bekommen + adversariell härten** + Scorecard-Eval. *Owner: Testing.*
- **P3 – Dev/Platform:** **Bank-Validation-Service** extrahieren (Cut) + CI-Lauf + Cutover-Runbook. *Owner: Production-ready.*
- **P4 – PM/Agentic:** User Stories + Agentic Scouts + Custom-Skill/Slash-Command; **erweitert/stitcht README/Presentation/CLAUDE.md**. *Owner: Product + Inventive.*

---

## Gemeinsame Festlegungen (alle vor Start, ~2 Min, async im Chat)
- **Domäne:** Northwind Logistics — Auftrag/Lieferung/Rechnung + SEPA-Zahlungseinzug.
- **Erster Seam:** **Bank-Validation** (siehe Begründung oben). Grep-Targets stehen fest:
  `isValidBankAccount`, `isValidBIC`, `isValidBank`, `BankValidator`, `validateBankleitzahl`.
  Betroffene Dateien (bereits identifiziert): `classes/BankValidator.php`, `classes/Northwind.php`,
  `helper.php`, `pages/rechnung_zahlung.php`, `pages/kunden_edit.php`, `pages/auftrag_neu.php`.
- **Repo-Struktur — siehe "Projekt-Ist-Zustand" oben.** Kurz:
  - *Vorhanden:* `monolith/` (PHP-Monolith, MySQL) inkl. `monolith/tests/` (4 Characterization-Tests) und Drei-Ebenen-`CLAUDE.md`.
  - *Anzulegen:* `bank-validation-service/` (Sibling, ACL-Regel aus Root-`CLAUDE.md`), `monolith/docs/` (ADRs), `stories/`, `eval/`, `agentic/`, `.claude/hooks|skills`, sowie `README.md` / `presentation.html` / `RUNBOOK.md` / `run-tests.sh` in der Wurzel.
- **Commit-Disziplin:** oft committen (Commit-History ist Bewertungs-Evidenz).
- **Doc-Regel:** Wer ein Artefakt fertig hat, schreibt **sofort** 3–5 Sätze in README + eine Slide → kein Doc-Stau am Ende.
- **ACL-Regel (hart):** Neuer Service darf `malkusch/bav` direkt nutzen, aber **NICHT** aus
  `monolith/classes/` importieren und **keine** Monolith-Feldnamen (z.B. `bankleitzahl`,
  `kontonummer` aus `kunden`) in seiner öffentlichen API leaken.

---

## Infrastruktur-Entscheidung (NEU — wegen MySQL statt SQLite)
Der Monolith braucht MySQL + die BAV-Bundesbank-Datei. Das ist ein realer Setup-Aufwand:
- **MySQL via Docker** sofort in Block 0 hochziehen (`docker run mysql`, `schema.sql` einspielen, Mini-Seed).
- Falls Docker hakt: **Charakterisierungs-Tests, die DB brauchen, als "läuft mit MySQL" dokumentieren**
  und den Fokus auf den **Bank-Validation-Service** legen — der braucht **kein** MySQL und liefert
  trotzdem den grünen Contract-Test-Lauf (Beweis von Challenge 5).
- BAV-Datei: `cron/bav_update.php` bzw. BAV-eigene Testdaten (`bav/tests/data/`) als Quelle.

---

## Zeitplan (60 Minuten, 4 Spuren parallel — Docs laufen DURCHGEHEND mit)

### Block 0 — Setup (0–5 Min)
- **Alle:** Domäne + Seam (Bank-Validation) bestätigen, Ordner `bank-validation-service/`, `stories/`, `eval/`, `agentic/`, `.claude/` anlegen.
- **P3:** **MySQL via Docker** starten, `monolith/sql/schema.sql` einspielen, Mini-Seed (1 Kunde, 1 Auftrag, 1 Rechnung). → Voraussetzung für P2s Test-Lauf.
- **P4:** `CLAUDE.md` (Root) um "prefer `bank-validation-service` für Bank-Checks"-Regel **erweitern**;
  `README.md` aus Template + `presentation.html`-Gerüst (Titel-, Architektur-, Test-, Demo-, Inventive-Slide als Platzhalter).

### Block 1 — Verstehen + parallele Vorbereitung (5–18 Min)
> Kein Generieren mehr. P1 **liest** den Patienten statt ihn zu bauen → kürzer, daher früher Map-Start.
- **P1:** Patienten **kartieren** — `monolith/CLAUDE.md` + Code lesen, Seams + Trigger + Global-State verifizieren (3-fach `ConfigurationRegistry`, Storno-Lagerbestand-Bug, Trigger-3 Status 4 statt 5, doppelte BLZ-Validierung). Map-ADR-Entwurf beginnen.
- **P2:** Vorhandene `monolith/tests/` **laufen lassen** gegen Docker-MySQL + BAV-Daten; rot/grün-Status feststellen; Runner-Skript `run-tests.sh` + **CI-Stub** (GitHub Action) anlegen.
- **P3:** `bank-validation-service/`-Skeleton + API-Contract (`POST /validate/bank-account` → `{blz,kto}` → `{valid:bool}`, `POST /validate/bic` → `{bic}` → `{valid:bool}`) — **bewusst KEINE** Monolith-Feldnamen (Anti-Corruption).
- **P4:** `stories/` — 2–3 **User Stories** mit scharfen Akzeptanzkriterien (z.B. "SEPA-Lastschrift nur mit BAV-valider Bankverbindung") + **dokumentierter Stakeholder-Konflikt** (Vertrieb will schnelle Anlage vs. Buchhaltung will harte Validierung — nicht glattgebügelt). → speist später Tests & Product-Slide.

### Block 2 — Kern-Arbeit parallel (18–42 Min)
- **P1:** **Map-ADR** (`docs/ADR-001-strangler-fig-bank-validation.md`) — Seams aus `classes/CLAUDE.md` benennen, nach **Extraktions-Risiko** ranken (Bank NIEDRIG → Artikel → Kunden → Order → Invoice SEHR HOCH), "Was wir bewusst NICHT tun" + **ASCII/Mermaid-Diagramm**. Danach **`ADR-003-trigger-explizit-machen.md`** (5 Trigger → explizite Events, inkl. Storno-Bug) und **Fence-ADR** (`ADR-002-anti-corruption-layer.md`): warum harte Grenze = Hook, Präferenz = Prompt.
- **P2:** **Characterization-Tests härten** — vorhandene Tests grün, dann **konkrete Bugs pinnen**: Storno lässt Lagerbestand falsch (`tr_auftrag_bestaetigt` ohne Gegen-Trigger), `tr_lieferung_zugestellt` setzt Auftrag-Status **4 statt 5**, doppelte BLZ-Validierung (Format-gültige aber nicht-existente BLZ passiert `helper.validateBankleitzahl`), 3-fach `ConfigurationRegistry`-Überschreibung. **Edge Cases adversariell**, Fehlermeldungen sagen präzise *was* sich änderte. Danach **Scorecard-Eval** (`eval/`): Golden-Set "correct seam" (Bank) vs. "incorrect seam" (z.B. Invoice zuerst), Metrik **False-Confidence-Rate**.
- **P3:** **Cut** — BAV-Logik nach `bank-validation-service/` extrahieren, sauberer API-Vertrag; `BankValidator.php` ruft per HTTP-Client den Service auf / Monolith läuft weiter. **+ Contract-Test.** Danach **PreToolUse-Hook** (`.claude/`) der Schreibzugriffe von `bank-validation-service/` nach `monolith/classes/` (und umgekehrte Feldnamen-Leaks) blockt (Fence, deterministisch).
- **P4:** **Agentic Scouts (9)** — Coordinator + ein Task-Subagent pro Seam aus der Map (Bank, Artikel, Kunden, Order, Invoice); jeder bewertet Risiko (Kopplung, Testabdeckung, Datenmodell-Verflechtung, Geschäftskritikalität), gibt **strukturiertes Verdict** zurück; Scope **explizit** je Task übergeben (Subagents erben keinen Kontext). Coordinator → Ranking. **+ Custom-Skill/Slash-Command** für die Extraktions-Playbook (Inventive). Startet, sobald Map-Entwurf steht.

### Block 3 — Integration, grüner Lauf & Production-Polish (42–54 Min)
- **P2 + P3:** **Ein gemeinsamer Testlauf** — Characterization-Suite (Monolith, MySQL) **+** Contract-Test (Service) **grün auf demselben Commit** (hartes Kriterium von Challenge 5); in CI sichtbar. Notfalls: Contract-Test des Service als verlässlicher grüner Kern (kein MySQL nötig).
- **P3:** **`RUNBOOK.md`** — Cutover-Schritte (Feature-Flag: Monolith ruft Service vs. alten `BankValidator`), Rollback-Trigger, Entscheidungsbaum ("3-Uhr-nachts-tauglich"). 1× gedanklich durchspielen.
- **P4:** agent-Ranking vs. menschliche Map vergleichen — Übereinstimmungen/Abweichungen + **Warum** → in ADR/README.
- **Alle:** jeweils Doc-Abschnitt + Slide finalisieren (kein Stau).

### Block 4 — Pitch-Schliff & Abschluss (54–60 Min)
- **P4:** `README.md` finalisieren (What We Built, Challenges-Tabelle mit Status, Key Decisions → ADR-Links, How to Run, If We Had More Time, How We Used Claude Code); `presentation.html` als kohärenten 5-Min-Pitch durchgehen.
- **Alle:** finaler Commit + Sanity-Check der **3 Pflicht-Dateien**: Versteht Claude allein daraus, *was* wir bauten, *warum* es zählt, *wie weit* wir kamen und *wie* wir das Tool angelernt haben?

---

## Abhängigkeiten (kritischer Pfad — ohne Generierungs-Blocker)
```
P3 Docker-MySQL ──> P2 Tests laufen ──> P2 Pin (Bugs) ──┐
                                                          ├─> Grüner Lauf (Block3) ──> P3 Runbook
P3 Service-Skeleton ──> P3 Cut + Contract-Test ──────────┘
P1 Patient lesen ──> P1 Map ──> P4 Scouts ──> Ranking-Vergleich
Docs (README/Presentation/CLAUDE.md) laufen DURCHGEHEND parallel zu allem.
```
**Neuer harter Blocker:** Docker-MySQL-Setup (P3, Block 0) — alles DB-abhängige Testen hängt daran.
Mitigation: Der Bank-Validation-Service ist DB-frei → liefert den grünen Lauf auch ohne MySQL.

## Priorität bei Zeitnot (so erreichen wir trotzdem jede Kategorie)
1. **Pflicht-Docs sauber** (README + presentation.html + CLAUDE.md) — ohne sie zählt der Rest nicht.
2. **Grüner Lauf:** Contract-Test des Service (DB-frei, sicher) + so viel Characterization wie MySQL erlaubt.
3. **Map-ADR + Diagramm** (Architecture). **User Stories** (Product). **Scouts** (Inventive).
4. *Tiefe-Stretch:* Scorecard-Eval, Fence-Hook + ADR, Trigger-ADR, Runbook, Custom-Skill — je nach Restzeit (mehr Puffer als geplant, da kein Generieren).

## Deliverables / Definition of Done
- [ ] **README.md / presentation.html / CLAUDE.md** vollständig & kohärent (Pflicht; CLAUDE.md erweitert).
- [x] `monolith/` Monolith vorhanden, bewusst "hässlich" (inkl. Trigger-Logik) — **bereits erfüllt**.
- [ ] Characterization-Suite (`monolith/tests/`) **+** Contract-Test (`bank-validation-service/`) **grün auf einem Commit** (in CI).
- [ ] `bank-validation-service/` läuft, sauberer API-Vertrag, keine Monolith-Feldnamen geleakt.
- [ ] `monolith/docs/ADR-001` (Map + Diagramm), `ADR-002` (Anti-Corruption/Fence: Hook vs. Prompt), `ADR-003` (Trigger explizit).
- [ ] `stories/` User Stories + Akzeptanzkriterien + Stakeholder-Konflikt.
- [ ] `eval/` Scorecard mit False-Confidence-Rate · `agentic/` Scouts-Ranking + Vergleich.
- [ ] `.claude/` PreToolUse-Hook + Custom-Skill/Slash-Command · `RUNBOOK.md` (Cutover + Rollback).

## Risiken / Puffer
- **MySQL-Setup frisst Zeit** (statt früher SQLite) → Docker zuerst in Block 0; DB-freier Service als Fallback für den grünen Lauf.
- **BAV-Datei fehlt/nicht beschreibbar** → BAV-Testdaten aus `bav/tests/data/` nutzen; `cron/bav_update.php` als Quelle.
- Plan ist ambitioniert, aber **entspannter als zuvor** (kein Generieren) → Prioritätenliste oben strikt befolgen, Restzeit in Tiefe stecken.
- Grüner Lauf hakt → Contract-Test des Service minimal halten (ein Happy-Path-Fall, DB-frei).
- Doc-Stau vermeiden → "fertig = sofort dokumentieren"-Regel einhalten.

---

## Verifikation (wie wir prüfen, dass es funktioniert)
1. `docker`-MySQL läuft mit `monolith/sql/schema.sql`; `php -S localhost:8000 -t monolith/` startet den Monolith; eine Seite (z.B. `index.php?page=kunden_edit`) lädt.
2. `./run-tests.sh` (lokal + CI) führt Characterization- (`monolith/tests/`, MySQL) **und** Contract-Test (`bank-validation-service/`) aus → grün, derselbe Commit-Hash.
3. `bank-validation-service/` separat startbar (kein MySQL); ein Test schlägt **laut** fehl, falls ein Monolith-Feldname (`bankleitzahl`, `kontonummer`) in der Service-API auftaucht (Anti-Corruption-Forcing-Function). PreToolUse-Hook blockt einen Cross-Boundary-Write deterministisch.
4. Scorecard-Eval läuft und gibt eine Zahl (False-Confidence-Rate) aus.
5. Scouts-Coordinator gibt strukturiertes Ranking aus (Bank NIEDRIG … Invoice SEHR HOCH); Vergleich mit Map-ADR dokumentiert.
6. **Pitch-Test:** Liest man nur README + presentation.html + CLAUDE.md, ist Was/Warum/Wie-weit/Wie-angelernt klar.
