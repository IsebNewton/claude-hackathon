# Arbeitsplan: Code Modernization (Szenario 1) — 4 Personen, 60 Minuten

## Context
Hackathon-Szenario 1 ("The Monolith"): Northwind Logistics läuft auf einem alten
System, niemand versteht es, der Vorstand will "Modernisierung". Wir beweisen, dass
es **sicher und schrittweise** (Strangler Fig) evolviert werden kann — kein Big-Bang-Rewrite.

Entscheidungen des Teams:
- **Legacy-Stack:** PHP 5 Monolith (am schnellsten "hässlich genug" zu generieren).
- **Modernisierung Richtung:** Strangler Fig — erste Extraktion eines Service mit sauberem API-Vertrag.

### Wonach bewertet wird (steuert diesen Plan)
**Claude liest in jedem Fall 3 Dateien — sie sind unser Pitch, NICHT die letzten 10 Minuten:**
`README.md` (die Story), `presentation.html` (5-Min-Pitch), `CLAUDE.md` (wie wir das Tool angelernt haben).
→ Diese 3 entstehen **durchgehend** ab Minute 0; jede Person liefert ihren Abschnitt sofort nach Fertigstellung.

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
| Most production-ready | `services/pricing/` läuft, CI grün, `RUNBOOK.md` (Cutover + Rollback) | 5, 8 | **P3** |
| Best architecture thinking | `decisions/0001-decomposition.md` + Diagramm, `decisions/0002-fence-hook-vs-prompt.md` | 3, 6 | **P1** |
| Best testing | Characterization-Suite (Bugs gepinnt, Edge Cases) + `eval/scorecard` (False-Confidence-Rate) | 4, 7 | **P2** |
| Best product work | `stories/` — User Stories + scharfe Akzeptanzkriterien, Stakeholder-Konflikte explizit | 1 | **P4** |
| Most inventive Claude Code | Scouts-Subagents, PreToolUse-Hook (Fence), Custom-Skill + Slash-Command für die Extraktions-Playbook | 6, 9 | **P4 + P1** |
| **Pflicht-Docs (durchgehend)** | `README.md`, `presentation.html`, `CLAUDE.md` | — | **alle, P4 stitcht** |

> Prinzip: **Tiefe schlägt Breite.** Jede Kategorie wird mindestens dünn erreicht (Pflicht), dann gehen wir dort in die Tiefe, wo Zeit bleibt (Priorität siehe unten).

---

## Rollen (jede Person besitzt eine Bewertungs-Achse + ihren Doc-Abschnitt)
- **P1 – Architect:** Monolith (Patient) → Map-ADR + Diagramm + Fence-ADR (Hook vs. Prompt). *Owner: Architecture.*
- **P2 – Tester/Quality:** Characterization-Tests (adversariell) + Scorecard-Eval. *Owner: Testing.*
- **P3 – Dev/Platform:** Service-Extraktion (Cut) + CI-Lauf + Cutover-Runbook. *Owner: Production-ready.*
- **P4 – PM/Agentic:** User Stories + Agentic Scouts + Custom-Skill/Slash-Command; **stitcht README/Presentation/CLAUDE.md**. *Owner: Product + Inventive.*

---

## Gemeinsame Festlegungen (alle vor Start, ~2 Min, async im Chat)
- **Domäne:** Northwind Logistics — Order/Shipment-Management.
- **Erster Extraktions-Kandidat (Seam):** **Pricing/Quote** (Frachtpreis-Berechnung) — klar abgegrenzt, hoher Geschäftswert.
- **Repo-Struktur:**
  - `legacy/` — der PHP-5-Monolith · `services/pricing/` — neuer Service · `tests/` — Characterization + Contract
  - `stories/` — User Stories · `decisions/` — ADRs · `eval/` — Scorecard · `agentic/` — Scouts · `.claude/` — Hook, Skill, Slash-Command
  - Wurzel: `README.md`, `CLAUDE.md`, `presentation.html`, `RUNBOOK.md`
- **Commit-Disziplin:** oft committen (Commit-History ist Bewertungs-Evidenz).
- **Doc-Regel:** Wer ein Artefakt fertig hat, schreibt **sofort** 3–5 Sätze in README + eine Slide → kein Doc-Stau am Ende.

---

## Zeitplan (60 Minuten, 4 Spuren parallel — Docs laufen DURCHGEHEND mit)

### Block 0 — Setup (0–5 Min)
- **Alle:** `git init`, Ordnerstruktur, Domäne + Seam bestätigen.
- **P4:** Drei-Ebenen-`CLAUDE.md` anlegen (PHP-Konventionen, Testbefehl, "bevorzuge den neuen Pricing-Service für Preise");
  `README.md` aus Template + `presentation.html`-Gerüst (Titel-, Architektur-, Test-, Demo-, Inventive-Slide als Platzhalter).

### Block 1 — Patient + parallele Vorbereitung (5–18 Min)
- **P1:** Monolith generieren — bewusst "hässlich": `legacy/index.php`, inline-konkateniertes SQL, 1–2 God-Classes,
  Sessions in Globals, **Business-Logik in DB-Trigger** (Frachtzuschlag). SQLite + minimaler Seed, startbar via `php -S`. → **Blocker, daher zuerst.**
- **P2:** Test-Harness wählen (HTTP-Golden-Tests gegen `php -S` oder PHPUnit) + Runner-Skript + **CI-Stub** (GitHub Action).
- **P3:** `services/pricing/`-Skeleton + API-Contract (Endpunkt, JSON-Schema) — **bewusst NICHT** Monolith-Feldnamen übernehmen (Anti-Corruption).
- **P4:** `stories/` — 2–3 **User Stories** mit scharfen Akzeptanzkriterien + **dokumentierter Stakeholder-Konflikt** (Priorisierungsstreit, nicht glattgebügelt). → speist später Tests & Product-Slide.

### Block 2 — Kern-Arbeit parallel (18–42 Min)
- **P1:** **Map-ADR** (`0001`) — Seams benennen, nach **Extraktions-Risiko** ranken (nicht Größe), "Was wir bewusst NICHT tun" + **ASCII/Mermaid-Diagramm**. Danach **Fence-ADR** (`0002`): warum harte Grenze = Hook, Präferenz = Prompt.
- **P2:** **Characterization-Tests (Pin)** — verhaltens-fixierend inkl. Bugs, **Edge Cases adversariell**, Fehlermeldungen sagen präzise *was* sich änderte. Anschließend **Scorecard-Eval** (`eval/`): Golden-Set "correct seam" vs. "incorrect seam", Metrik **False-Confidence-Rate**.
- **P3:** **Cut** — Pricing-Logik nach `services/pricing/` extrahieren, sauberer API-Vertrag; Monolith ruft Service auf / läuft weiter. **+ Contract-Test.** Danach **PreToolUse-Hook** (`.claude/`) der Schreibzugriffe über die Service-Grenze blockt (Fence, deterministisch).
- **P4:** **Agentic Scouts (9)** — Coordinator + ein Task-Subagent pro Seam aus der Map; jeder bewertet Risiko (Kopplung, Testabdeckung, Datenmodell-Verflechtung, Geschäftskritikalität), gibt **strukturiertes Verdict** zurück; Scope **explizit** je Task übergeben. Coordinator → Ranking. **+ Custom-Skill/Slash-Command** für die Extraktions-Playbook (Inventive). Startet, sobald Map-Entwurf steht.

### Block 3 — Integration, grüner Lauf & Production-Polish (42–54 Min)
- **P2 + P3:** **Ein gemeinsamer Testlauf** — Characterization-Suite **+** Contract-Test **grün auf demselben Commit** (hartes Kriterium von Challenge 5); in CI sichtbar.
- **P3:** **`RUNBOOK.md`** — Cutover-Schritte, Rollback-Trigger, Entscheidungsbaum ("3-Uhr-nachts-tauglich"). 1× gedanklich durchspielen.
- **P4:** agent-Ranking vs. menschliche Map vergleichen — Übereinstimmungen/Abweichungen + **Warum** → in ADR/README.
- **Alle:** jeweils Doc-Abschnitt + Slide finalisieren (kein Stau).

### Block 4 — Pitch-Schliff & Abschluss (54–60 Min)
- **P4:** `README.md` finalisieren (What We Built, Challenges-Tabelle mit Status, Key Decisions → ADR-Links, How to Run, If We Had More Time, How We Used Claude Code); `presentation.html` als kohärenten 5-Min-Pitch durchgehen.
- **Alle:** finaler Commit + Sanity-Check der **3 Pflicht-Dateien**: Versteht Claude allein daraus, *was* wir bauten, *warum* es zählt, *wie weit* wir kamen und *wie* wir das Tool angelernt haben?

---

## Abhängigkeiten (kritischer Pfad)
```
P1 Patient ──> P2 Pin ──┐                         P1 Map ──> P4 Scouts ──> Ranking-Vergleich
             └> P3 Cut ──┴─> Grüner Lauf (Block3) ──> P3 Runbook
Docs (README/Presentation/CLAUDE.md) laufen DURCHGEHEND parallel zu allem.
```
P1s Monolith ist der einzige harte Blocker → zuerst, höchste Priorität.

## Priorität bei Zeitnot (so erreichen wir trotzdem jede Kategorie)
1. **Pflicht-Docs sauber** (README + presentation.html + CLAUDE.md) — ohne sie zählt der Rest nicht.
2. **Grüner Lauf:** Characterization + Contract-Test auf einem Commit (Production-ready + Testing).
3. **Map-ADR + Diagramm** (Architecture). **User Stories** (Product). **Scouts** (Inventive).
4. *Tiefe-Stretch:* Scorecard-Eval, Fence-Hook + ADR, Runbook, Custom-Skill — je nach Restzeit.

## Deliverables / Definition of Done
- [ ] **README.md / presentation.html / CLAUDE.md** vollständig & kohärent (Pflicht).
- [ ] `legacy/` Monolith startbar, bewusst "hässlich" (inkl. Trigger-Logik).
- [ ] Characterization-Suite **+** Contract-Test **grün auf einem Commit** (in CI).
- [ ] `services/pricing/` läuft, sauberer API-Vertrag, keine Monolith-Feldnamen geleakt.
- [ ] `decisions/0001` (Map + Diagramm) und `decisions/0002` (Fence: Hook vs. Prompt).
- [ ] `stories/` User Stories + Akzeptanzkriterien + Stakeholder-Konflikt.
- [ ] `eval/` Scorecard mit False-Confidence-Rate · `agentic/` Scouts-Ranking + Vergleich.
- [ ] `.claude/` PreToolUse-Hook + Custom-Skill/Slash-Command · `RUNBOOK.md` (Cutover + Rollback).

## Risiken / Puffer
- Monolith-Setup frisst Zeit → SQLite statt MySQL, minimaler Seed.
- Plan ist ambitioniert für 60 Min → **Prioritätenliste oben strikt befolgen**; Tiefe-Stretch ist optional.
- Grüner Lauf hakt → Contract-Test minimal halten (ein Happy-Path-Fall).
- Doc-Stau vermeiden → "fertig = sofort dokumentieren"-Regel einhalten.

---

## Verifikation (wie wir prüfen, dass es funktioniert)
1. `php -S localhost:8000 -t legacy/` startet den Monolith; Aufruf eines Pricing-Endpunkts liefert eine Antwort.
2. `./run-tests.sh` (lokal + CI) führt Characterization- **und** Contract-Tests aus → beide grün, derselbe Commit-Hash.
3. Service separat startbar; ein Test schlägt **laut** fehl, falls ein Monolith-Feldname in der Service-API auftaucht (Anti-Corruption-Forcing-Function). PreToolUse-Hook blockt einen Cross-Boundary-Write deterministisch.
4. Scorecard-Eval läuft und gibt eine Zahl (False-Confidence-Rate) aus.
5. Scouts-Coordinator gibt strukturiertes Ranking aus; Vergleich mit Map-ADR dokumentiert.
6. **Pitch-Test:** Liest man nur README + presentation.html + CLAUDE.md, ist Was/Warum/Wie-weit/Wie-angelernt klar.
