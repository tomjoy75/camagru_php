# AI-Assisted Development Workflow

This document describes the standard workflow used to develop projects
with AI assistance (Cursor, ChatGPT, etc.).

Goals: - Maintain architectural clarity - Break the project into small
testable units - Guide the AI with structured context

**Stack-specific guidance** (e.g. web server, MVC, HTTP/curl tests):  
`docs/WORKFLOW-addendum-web-server.md` — use together with this file when it applies.

---

# 1. Project Context

Before starting implementation, gather the core context.

## Inputs

- Project subject / specification
- Constraints (language, framework, architecture)
- Expected features

## Suggested folder structure

docs/  
├── subject.txt  
├── WORKFLOW.md  
├── product_spec.md  
├── architecture.md  
├── feature_tree.md  
└── specs/

---

# 2. Product Specification

Describe **what the product is supposed to do** at a high level.

### AI Prompt

```
Read the project subject @subject.txt.

Write a product specification including:

- project goal
- core features
- user flows
- constraints
```

## Output

docs/product_spec.md

## Example structure

# Product Specification

Goal  
...  
Describe the objective of the application.

Core Features

- area 1
- area 2
- area 3

Constraints 

- language 
- frameworks 
- security requirements

---

# 3. Architecture Definition

Define **how the system will be built** for **your** stack (CLI, mobile, web API, monolith, etc.).

### AI Prompt

```
Read docs/product_spec.md.

Propose a software architecture appropriate to this project, including:

- major components or modules and their responsibilities
- boundaries (what talks to what)
- data or state (persistence, files, remote APIs) where relevant
- how users or callers interact with the system (UI, CLI, HTTP, etc.)

Adapt naming to the stack (e.g. handlers, packages, screens). Do not assume MVC unless the project uses it.
```

## Output

docs/architecture.md

## Example

Keep examples in **architecture.md** aligned with the real stack. For a **web MVC-style** monolith, you can use the sample layout in `docs/WORKFLOW-addendum-web-server.md`.

---

# 4. Feature Tree

Break the product into **modules, features, and subfeatures**.

### AI Prompt

```
Read docs/product_spec.md and docs/architecture.md. If a codebase already exists, consider it.
Generate a hierarchical feature tree.

Structure it like:

Product
→ modules
→ features
→ subfeatures

Optional focus (examples):

- onboarding or auth flows
- primary user journeys
- integrations or background work
```

## Output

docs/feature_tree.md

## Example

Product  
│  
├── Module A  
│ ├── feature 1  
│ └── feature 2  
└── Module B  
    └── feature 3

(Replace with your real modules and features in **feature_tree.md**.)

---

# 4.5 Backlog Projection (optional but recommended)

Once the feature tree is stable enough, project it into a lightweight issue backlog.

Goal:

- make the remaining work visible at project level
- create a simple kanban view (Todo / Doing / Done, optional Waiting)
- keep a link between product structure and implementation flow

## Rules

- Create issues at the **subfeature** level only.
- Do **not** create issues for modules.
- Do **not** create issues for top-level features unless there is a strong reason.
- Keep backlog issues lightweight:
  - title
  - short description
  - parent module / feature
  - labels
  - initial status
- Do **not** add full specs, implementation plans, or detailed test plans at this stage.
- Use the backlog for visibility, not for detailed design.

## Recommended issue format

- Title format: `[Module] Subfeature`
- Description: short operational description (2 to 4 lines max)
- Labels:
  - one required module label
  - optional secondary labels if useful
- Initial status:
  - `Todo` for not started
  - `Doing` for the current active issue
  - `Done` for already implemented items
  - optional `Waiting` only if genuinely useful

## Relationship with the feature tree

- `feature_tree.md` remains the source of truth for product structure.
- GitHub issues are a projected operational backlog derived from the feature tree.
- The feature tree keeps the hierarchy.
- The issue board gives visibility and progress tracking.

## Important note

Backlog projection is intentionally lightweight.
Detailed design happens later, when a specific issue becomes active in the implementation workflow.

### AI Prompt

```
Read docs/feature_tree.md.

Produce a lightweight backlog projection as **one issue per subfeature** only.

Rules:
- Do **not** create issues for modules.
- Do **not** create issues for top-level features unless the tree gives a strong reason (say so briefly if you make an exception).
- Keep each item lightweight: title, short description (2–4 lines max), parent module / feature, suggested labels, initial status (Todo / Doing / Done, optional Waiting).
- Use title format: `[Module] Subfeature`
- Group output **by module**.
- Do **not** write code. Do **not** modify any files yet—the output is for copy/paste into your issue tracker only.
```

## Optional: publish backlog to issue tracker

Once the lightweight backlog is **validated**, you may **publish** it to your issue tracker / kanban tool so the whole team sees status at a glance.

Typical sequence (adapt names to your tool):

1. Create **labels** first (e.g. module tags, small secondary tags).
2. **Create issues** from the backlog items.
3. **Add** those issues to a **board or project** view.
4. Set **initial statuses** (e.g. Todo / Doing / Done, optional Waiting).

This step is **optional** but useful when you want global visual tracking.

### AI Prompt

```
You are given the **validated lightweight backlog** (titles, descriptions, labels, initial statuses—e.g. from §4.5, pasted by the user).

1. Inspect the **current environment** (shell, available CLIs, API tokens, browser-only, nothing)—report briefly what can be automated versus manual.
2. If automation is possible: propose **ordered steps** to create missing labels, create issues, attach them to the right board/project, and set initial statuses—without naming a specific vendor unless the user’s context already implies one.
3. If automation is **not** available: give the **most efficient fallback** (e.g. minimal copy/paste batches, CSV export/import if their tool supports it, or a short checklist for the UI).
4. Do **not** modify application source or project docs unless the user explicitly asks. Do **not** assume a particular host (GitHub, GitLab, Jira, etc.)—stay tool-agnostic unless the user specifies one.
5. Wait for explicit user confirmation before performing any action that changes their tracker or repository (if you have permission to run commands).

Keep the answer concise and practical.
```

---

# 5. Feature Selection

Select the **next smallest logical unit** to implement.

### AI Prompt

```
Use the current GitHub issue backlog and project board for this repository, together with:
- docs/feature_tree.md
- docs/ARCHITECT.md
- docs/WORKFLOW.md

Important:
In this repository, the architecture reference is docs/ARCHITECT.md.
Do not assume docs/architecture.md.

Goal:
Recommend the next issue to move from Todo to Doing.

Selection criteria:
- smallest logical and testable next unit
- preferably already present as an open GitHub issue
- should build naturally on what is already done
- should not depend on multiple unfinished features
- should fit the current MVC architecture and workflow
- prefer visible product value
- avoid optional/later items unless clearly justified

Your task:
1. inspect the current GitHub issues and project statuses
2. identify the best next candidate issue to activate
3. explain briefly why
4. list 2 or 3 alternative candidates
5. mention any dependency or sequencing concern
6. if your best candidate is smaller than an existing issue, say so explicitly

Do not write code.
Do not modify files.
Do not change issue statuses yet.
```

---

# 5-bis. Scope sanity check (before branch)

Before creating a branch, do a quick scope check on the selected issue from §5. If it is too broad for one clean implementation cycle, propose a lightweight split and pick the smallest testable slice to implement next.

### AI Prompt

```
We are between §5 (Feature Selection) and §5-ter (Create branch).

Input:
- the currently selected issue from §5
- current backlog context (issues/dependencies), if available

Task:
1. Assess whether the selected issue is appropriately scoped for one implementation cycle.
2. If scope is too broad, propose a practical split into smaller sub-features/issues.
3. Recommend the best next smallest testable slice to implement now.
4. Keep rationale brief and implementation-oriented.

Constraints:
- Do not write code.
- Do not modify files.
- Do not create branches yet.
- Keep this step lightweight and practical.
```

---

# 5-ter. Create branch (from active issue)

Once you know **which** issue you are implementing (§5), create the **feature branch** from the tracker so all later commits land on the right line of work.

**Typical approach (no AI prompt):** use your host’s workflow from the issue itself—for example GitHub’s **Create a branch** (or linked branch) on the issue page. That keeps the branch name and the issue tied together without a separate Cursor step.

**If you use Git only:** after the remote branch exists, `git fetch` and check it out locally with the same name your team agreed on.

This step is **intentionally early**: branch right after selection, then write the spec, plan, and tests on that branch (§6 onward).

---

# 6. Feature Specification

```
Create a spec file for the feature.

## File

docs/specs/`<feature_name>`.md

## Template

# Feature: `<name>`

Goal Describe the objective.

Behavior Describe expected behavior.

Constraints Technical or security constraints.

Success Criteria How we know the feature works.
```

---

# 7. Implementation Plan

Break the feature into **small implementation steps** and append the plan to the spec file.

### AI Prompt

```
Read docs/specs/<feature_name>.md.

Break the feature into small implementation steps.

Rules:
- Each step should be small and independently testable.
- The plan should contain between 3 and 7 steps.
- Each step should require less than ~30 lines of code.
- Avoid explanations; only list steps.

Append the result in the same file (docs/specs/<feature_name>.md) under a new section titled "## Implementation Plan".

Example output format:

1 add entry point or wiring
2 validate inputs
3 implement core behavior
4 persist or integrate side effects
5 expose result (response, UI, exit code, etc.)

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)
```

---

NB. Before implementing a step, make sure you understand what it does.
If unclear, ask the AI to explain the step before coding.

### Example prompt (explain a step)

```
Explain step <n> of the implementation plan in simple terms without writing code.
```
or
```
Explain each step of the implementation plan in simple terms without writing code and giving an example.
```
---

# 7.4 Generate test plan (AI)

Append a **test plan** to the spec **after** the implementation plan and **before** the §7.5 gate. The gate reviews whether that plan is sufficient, so it must already exist.

### AI Prompt

````
Read docs/specs/<feature_name>.md.

Generate tests before implementing features.

Include:
- success cases
- failure cases
- edge cases

Keep the test case list concise.

After the test case list, append **runnable verification** appropriate to the project, for example:

- shell commands, a small script, **or** unit/integration test names and how to run them  
- for **HTTP** features: `curl` bash blocks — use the full template and conventions in **`docs/WORKFLOW-addendum-web-server.md`** (§ Test plan — HTTP with curl)

The output structure should include:

**Test cases**  
(list: success / failure / edge cases)

**Execute tests** (and setup blocks if needed)  
```bash
# or other runnable checks per stack
```

Keep the output concise. Do not modify other sections of the spec file.

Append the result in the same file (docs/specs/<feature_name>.md) under a new section titled "## Tests".
````

---

# 7.5 Spec-driven implementation gate (AI)

**Prerequisite:** the spec already contains a test section from **§7.4** (e.g. `## Tests`). This gate checks that it (and the rest of the spec) is **ready for implementation**.

This section is a **pre-implementation checkpoint**. The goal is to verify the feature is **ready to implement** before any **application source code** changes. It **blocks premature coding**: the AI may **produce** the discovery summary and artifact review below, but **whether to implement** stays an **explicit user decision** (e.g. after you say “go ahead”).

**Light reuse / duplication audit (part of this gate):** Before implementation, do a **quick** scan of the codebase—not a refactor pass. Note whether the feature would **duplicate** logic that already exists, whether **existing code** partly covers the need, and whether a **small, local** simplification (e.g. a private helper in the same class or file) would remove **obvious** duplication. Use this only to **inform** the planned change: do **not** turn the gate into broad cleanup, do **not** introduce speculative abstractions or new layers “for reuse,” and defer larger deduplication to a **dedicated** issue or to the optional **§15** pass unless the current feature spec already includes that work.

### AI Prompt

```
We are at the §7.5 spec-driven implementation gate.

Read:
- docs/ARCHITECT.md
- docs/WORKFLOW.md
- docs/WORKFLOW-addendum-web-server.md (use sections that apply to this project; treat as optional if irrelevant)
- docs/specs/<feature_name>.md

Then provide:

1. **Files to read** — each path with a one-line reason.
2. **Files to modify** — each path with a one-line reason; if unknown, say so instead of guessing.
3. **Files to create** — each path with a one-line reason; if none, say none.
4. **Feature spec** — confirm whether the spec exists and is **sufficient** for implementation (goal, behavior, constraints, success criteria). If not, list gaps.
5. **Implementation plan** — confirm whether a plan in the spec is **sufficient**. If not, list gaps.
6. **Test plan** — confirm whether a test plan with runnable checks is **sufficient** (per WORKFLOW §7.4 and the web addendum where applicable). If not, list gaps.
7. **Scope creep** — note obvious ways the work could grow beyond the current spec.
8. **Architecture** — note concerns relative to docs/ARCHITECT.md and the Core Principles in docs/WORKFLOW.md.
9. **Reuse / duplication (light)** — in a few short bullets: could this feature **duplicate** logic that already exists? Is there existing code to **reuse or extend**? Would a **small, local** helper (or equivalent) remove **clear** duplication while staying proportionate to this spec? If nothing obvious, say so. **Do not** propose speculative abstractions, new shared layers, or wide refactors here; if bigger cleanup is warranted, say so briefly and point to a **dedicated** issue or **§15** instead.

Do **not** write application code. Do **not** modify any files. Wait for my explicit confirmation before implementation.
```

---

# 8. Implementation Loop

**Start coding only after §7.5 confirmation** (when the task includes application changes).

After this high-level loop, the concrete execution flow is: §9 → §10 → §11 → §12.

Workflow for each step:

Feature selection (§5)/
↓/
Create branch from issue (§5-bis)/
↓/
Feature Spec/
↓/
Implementation Plan/
↓/
Generate test plan (§7.4)/
↓/
Spec-driven gate — user confirms (§7.5)/
↓/
Understand Steps/
↓/
Enrich active issue (tracker)
↓/
Implement steps (commit per step)/
↓/
Execute spec tests + update issue (§11)/
↓/
Fix if needed/
↓/
Validate; update tracker; close issue; merge branch

## Active issue enrichment

When a feature is selected for implementation:

- if no issue exists yet, create one
- if the issue already exists in the backlog, update it instead of creating a duplicate

At this stage, the active issue may be enriched with:

- link or reference to the feature spec
- short implementation checklist
- short test checklist
- current status / notes if useful

The backlog issue becomes the active implementation issue.

This keeps:

- the feature tree as product structure
- the spec as detailed source of truth
- the issue as execution and tracking support

§8 is the **conceptual** layer. The **branch** should already exist (**§5-bis**). After **§7.4** and **§7.5**, use **§9 Active issue update** for the **copy/paste prompt** that syncs the tracker issue with the spec.

If you did not use a host-linked branch name, a minimal local equivalent is still:

git checkout -b feature/

Commit example:

feat(scope): short description of change

---

# 9. Active issue update (tracker)

The **test plan** is produced in **§7.4** and reviewed at **§7.5**; this section only syncs the tracker with the spec.

This is the **execution** step for the tracker: **§8 Active issue enrichment** explains *why* and *what* to add; **this section** is where you run the operational prompt once the spec (including tests) is ready **after** the §7.5 gate.

Align the **tracker issue** with the spec:  

- If **no** issue exists yet for this unit of work, **create** one using the prompt below.  
- If an issue **already** exists (e.g. from backlog projection in §4.5), **update** it—add or refresh the body—instead of opening a duplicate.

### AI Prompt

```

Read docs/specs/<feature_name>.md (including "## Implementation Plan" and "## Tests" if present) and docs/WORKFLOW.md.

Use the spec file as the source of truth, including:

- the feature description
- the implementation plan
- the test section, whether it is named "## Tests", "## Test plan", or similar

Prepare an **active issue update** for this feature:

- If there is not yet a tracker issue for this subfeature: draft a **new** issue (title + body) with a short overview, a **spec reference** (path), an **implementation checklist** from the plan, and a **test checklist** from the Tests section—keep each list concise.
- If a backlog issue already exists: draft **updated** body sections or a clear before/append pattern so one issue remains the single source in the tracker—**no duplicate issues**.

Do **not** write application code. Do **not** modify repository files. Wait for explicit user confirmation before any implementation or application code changes.

```

```
update the issue
```

## Branch (already done)

Create the working branch **right after feature selection** (**§5-bis**), usually from the issue in GitHub (or equivalent)—there is **no** dedicated Cursor prompt for this step. If you skipped §5-bis, do it before §10 (and keep the same branch for §11).

---

# 10. Implement active feature

The feature is ready to implement on the **active feature branch**. Use the **spec**, **implementation plan**, **test plan**, and **active tracker issue** as the execution references. Keep work inside the **agreed scope**; do not widen the feature without explicit approval.

### AI Prompt

```
We are implementing the active feature (WORKFLOW §10).

Read:
- docs/specs/<feature_name>.md
- docs/WORKFLOW.md
- docs/WORKFLOW-addendum-web-server.md (sections that apply to this project; skip what does not apply)
- docs/ARCHITECT.md
- the active tracker issue for this feature (paste link, title/body, or export—whatever your session allows)

Assumptions—confirm briefly; if any fail, stop and say what is missing:

- Feature spec exists for this unit of work
- Implementation plan exists in the spec
- Test plan with runnable checks exists in the spec (§7.4)
- Active tracker issue is already updated for implementation
- Feature branch is created and checked out (**§5-bis**)

Phase A — do not change application code yet:

1. Restate the implementation **scope** in a few lines (in scope / explicitly out of scope).
2. List **files to modify** (path + one-line reason each).
3. List **files to create** (path + one-line reason each).
4. Wait for my explicit confirmation (e.g. “implement now”).

Phase B — after I confirm: implement strictly within scope, following the plan and ARCHITECT.md / WORKFLOW Core Principles. Run the spec’s checks as you go; fix only within scope if something fails.
```

```
Implement now (and don't commit)
```

---

# 11. Test execution and issue update (AI)

After **§10** implementation, run the checks defined in the feature spec and record outcomes before **§12 Complete the feature**. Treat `## Tests` (or equivalent) as the script: automate what the environment allows, isolate what must be done manually, then sync the **active GitHub issue** with a concise test report.

### AI Prompt

```
We are at WORKFLOW §11 — test execution and tracker update (after §10 implementation).

Read:
- docs/specs/<feature_name>.md (focus on "## Tests", "## Test plan", or equivalent runnable / verification blocks)
- docs/WORKFLOW-addendum-web-server.md (only if the spec’s tests are HTTP/curl or web-specific)

Goals:

1. **Locate tests** — Find the spec’s test section and any embedded commands, steps, or success criteria tied to it.

2. **Execute faithfully** — Run automated checks yourself when possible (e.g. shell blocks, curl scripts), using the **exact** commands from the spec. If the environment blocks something (no server, wrong port, missing DB, tool absent), say what you ran instead and what differed—do not pretend a check ran.

3. **Split automated vs manual** —
   - **Automated:** what you executed in this session and the outcome (pass / fail / skipped + reason).
   - **Manual / browser:** checks you cannot fully run here (UI-only flows, device-specific behavior, production-only, etc.).

4. **Manual checklist for the developer** — For each manual item: numbered steps, **expected result**, and how it maps to the spec (e.g. criterion S3).

5. **Summary** — Short table or bullet list: passed | failed | skipped | needs manual validation | blocking issues (if any).

6. **GitHub issue** — Draft text to **add or edit** on the **active issue** for this feature: what was tested, what passed, what failed, what still needs manual validation, and any blocker. Keep it concise and paste-ready (or note the exact UI steps to add a comment if you cannot use the API or CLI).

Rules:
- Do **not** change application code unless the user explicitly asks to fix a failing test.
- If tests fail, state facts and suspected area; do not silently widen scope.
```
```
MaJ Issue checkboxes with those results
```
---

# 12. Complete the feature

When **§10** implementation, **§11** spec-based test execution (and any **fix / re-test** you need) are finished, **close the feature** in a controlled way:

1. **Validate** — Check the work against the feature spec and the **test plan** (runnable checks from the spec’s Tests section, §7.4; align with **§11** outcomes). Record **passed**, **failed**, and **deferred** items.
2. **Update the active tracker issue** — Add a short validation summary (outcomes, deferrals) if not already covered by **§11**.
3. **Board status** — Set the item to **Done** (or your tool’s equivalent) when scope matches what was agreed; otherwise keep it in **Doing** / **Waiting** until it does.
4. **Close the issue** — When your process says the unit of work is finished, **close** it in the tracker so lists stay accurate (many tools still show closed items on a **Done** column).
5. **Commit** — Commit any remaining changes on the feature branch with clear messages.
6. **Merge** — Merge into your main integration branch per your team’s rules.
7. **Optional** — Capture deferred checks or light tech debt (e.g. a short note on the issue, in the spec, or a small **Post-feature cleanup** section in the spec if you use one).

Then continue with **§13 Iteration Cycle** for the next unit of work.

---

# 13. Iteration Cycle

Repeat the cycle:

feature_tree  
↓  
select smallest unit (§5)  
↓  
create branch from issue (§5-bis)  
↓  
feature spec  
↓  
implementation plan  
↓  
generate test plan (§7.4)  
↓  
confirm (§7.5 gate)  
↓  
code (§10)  
↓  
execute spec tests + issue update (§11)  
↓  
fix / re-test if needed

---

# 14. Project Evolution

As the project grows:

- update feature_tree.md
- update architecture.md
- add new specs in docs/specs/
- when a substantial set of features is **done**, consider the optional **§15** cleanup / simplification pass (deduplication and readability **without** behavior changes)

These documents serve as **AI context sources**.

---

# 15. Final cleanup / simplification pass (optional)

When a **substantial** portion of planned work is **complete**, schedule a **single**, **controlled** pass over the codebase—not something to run during every feature. **Goal:** simplify, deduplicate, and improve readability **without** changing behavior (same tests/specs, same user-visible outcomes).

**Focus:** repeated logic; **small** private-helper opportunities; dead or unnecessary branches; code that became heavier than needed; **MVC responsibility drift** (e.g. business rules in views, HTTP/session concerns buried in services).

**Rules:** no broad redesign—stay proportional. If cleanup is **large or risky**, open a **dedicated** cleanup/refactor **issue** (or a small set of issues) instead of mixing it into an unrelated feature. **Record** outcomes where your process allows (e.g. tracker comment, short checklist, or a **Post-feature cleanup** note in a spec) and add **follow-up** issues when the pass surfaces work that should not be done in one go.

Afterward, continue only with remaining product work or move to release/handoff per your team’s process.

### AI Prompt

```
We are at WORKFLOW §15 — final cleanup / simplification pass (optional). A substantial portion of planned work is assumed **complete**; this is a **readiness / audit** step, not normal feature implementation.

Read:
- docs/ARCHITECT.md
- docs/WORKFLOW.md (especially **Core Principles** and §15)
- docs/WORKFLOW-addendum-web-server.md (only if this project is web/MVC and sections apply)
- the codebase (controllers, services, repositories, views, routing, and relevant assets)

Audit the codebase for:
- **Repeated logic** and **obvious** opportunities for **small, local** private helpers (same file or class), not new frameworks or layers
- **Dead or unnecessary** branches/paths
- Code that became **heavier than needed** for what it does
- **MVC responsibility drift** (e.g. business rules in views, HTTP/session concerns buried in services, persistence leaking where it should not)

Distinguish clearly:
- **Small, safe simplifications** — low risk, **no user-visible behavior change**, reasonable to do in one short pass
- **Larger or riskier refactors** — scope, coupling, or uncertainty warrants a **dedicated** cleanup/refactor **issue** (or issues), not “while we’re here” in this audit

Preferences (in order): **deletion**, **consolidation**, **simplification**. **Do not** add new abstractions unless they remove clear duplication and stay minimal.

Avoid:
- **Broad redesign** or **speculative** architecture
- Changing **user-visible** behavior, APIs, or semantics
- Rewriting **stable** code without a **strong**, audit-backed reason

Produce a practical report with:
1. **Short audit summary** (what you looked at, overall posture)
2. **Files / areas** most concerned (paths or modules)
3. **Recommended safe cleanup actions** (concrete, behavior-preserving)
4. **Defer to follow-up issues** (larger/risky items—title-worthy bullets)

If **no meaningful** cleanup is justified, say so **clearly** and briefly why.

Do **not** modify repository files unless the user explicitly asks you to implement cleanup after this audit.
```

---

# Core Principles

- Smallest testable unit first
- Follow **docs/architecture.md** for this project’s layering and boundaries
- Keep orchestration at edges thin; keep domain or core logic cohesive and testable
- Isolate persistence and external I/O behind clear boundaries when the architecture calls for it
- Architecture documents guide AI decisions

Treat the implementation plan as the source of truth. If you need to change it, update the plan first (e.g. with AI), then implement. Do not redefine steps ad hoc during implementation.

For **web MVC-style** projects, also apply the profile in `docs/WORKFLOW-addendum-web-server.md` (Core Principles — MVC web profile).

---

# Final workflow

Product Spec  
↓  
Architecture  
↓  
Feature Tree  
↓  
Select Feature (§5)  
↓  
Create branch from issue (§5-bis)  
↓  
Feature Spec  
↓  
Implementation Plan  
↓  
Generate test plan (§7.4)  
↓  
User confirms implementation (§7.5 gate)  
↓  
Understand Steps  
↓  
Enrich active issue (tracker)  
↓  
Implement Steps (commit per step) (§10)  
↓  
Execute spec tests + update issue (§11)  
↓  
Fix / re-test if needed  
↓  
Validate; update tracker; close issue; merge branch (§12)  
↓  
(Optional when the project is near complete: final cleanup / simplification — §15)

```

```

