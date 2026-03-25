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
Read docs/feature_tree.md and docs/architecture.md.

Identify the next smallest logical and testable unit to implement.

The unit must be:
- implementable in one development step
- testable independently
- not dependent on multiple unfinished features

Generate:
1. short feature specification
2. minimal implementation plan
3. main components or modules involved (per architecture.md)

Do not write code.
```

---

# 6. Feature Specification

Create a spec file for the feature.

## File

docs/specs/`<feature_name>`.md

## Template

# Feature: `<name>`

Goal Describe the objective.

Behavior Describe expected behavior.

Constraints Technical or security constraints.

Success Criteria How we know the feature works.

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

---

# 7.5 Spec-driven implementation gate (AI)

This section is a **pre-implementation checkpoint**. The goal is to verify the feature is **ready to implement** before any **application source code** changes. It **blocks premature coding**: the AI may **produce** the discovery summary and artifact review below, but **whether to implement** stays an **explicit user decision** (e.g. after you say “go ahead”).

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
6. **Test plan** — confirm whether a test plan with runnable checks is **sufficient** (per WORKFLOW §9 and the web addendum where applicable). If not, list gaps.
7. **Scope creep** — note obvious ways the work could grow beyond the current spec.
8. **Architecture** — note concerns relative to docs/ARCHITECT.md and the Core Principles in docs/WORKFLOW.md.

Do **not** write application code. Do **not** modify any files. Wait for my explicit confirmation before implementation.
```

---

# 8. Implementation Loop

**Start coding only after §7.5 confirmation** (when the task includes application changes).

After this high-level loop, the concrete execution flow is: §9 → §10 → §11.

Workflow for each step:

Feature Spec/
↓/
Implementation Plan/
↓/
Understand Steps/
↓/
Generate Test Cases/
↓/
Activate issue + Branch
↓/
Implement steps (commit per step)/
↓/
Run tests/
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

§8 is the **conceptual** layer. After the spec includes tests (**§9 Generate Tests**), use **§9 Active issue update** for the **copy/paste prompt** that syncs the tracker issue with the spec.

Recommended git workflow:

git checkout -b feature/

Commit example:

feat(scope): short description of change

---

# 9. Test cases and Issue Creation

## Generate Tests

### AI Prompt

```
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

Append the result in the same file (docs/specs/.md) under a new section titled "## Tests".

  
## Active issue update  
  
This is the **execution** step for the tracker: **§8 Active issue enrichment** explains *why* and *what* to add; **this subsection** is where you run the operational prompt once the spec (including tests) is ready.  
  
After tests are captured in the spec (§9 **Generate Tests**), align the **tracker issue** with the spec:  
  
- If **no** issue exists yet for this unit of work, **create** one using the prompt below.  
- If an issue **already** exists (e.g. from backlog projection in §4.5), **update** it—add or refresh the body—instead of opening a duplicate.

### AI Prompt

```

Read docs/specs/.md (including "## Implementation Plan" and "## Tests" if present) and docs/WORKFLOW.md.

Use the spec file as the source of truth, including:

- the feature description
- the implementation plan
- the test section, whether it is named "## Tests", "## Test plan", or similar

Prepare an **active issue update** for this feature:

- If there is not yet a tracker issue for this subfeature: draft a **new** issue (title + body) with a short overview, a **spec reference** (path), an **implementation checklist** from the plan, and a **test checklist** from the Tests section—keep each list concise.
- If a backlog issue already exists: draft **updated** body sections or a clear before/append pattern so one issue remains the single source in the tracker—**no duplicate issues**.

Do **not** write application code. Do **not** modify repository files. Wait for explicit user confirmation before any implementation or application code changes.

```

## Create branch

```

We are now at the "Create branch" step for feature .

Context:

- the tracker issue for this feature already exists
- the issue is now the active implementation issue

Task:

1. identify the corresponding GitHub issue
2. propose a clean local branch name based on the issue title/feature name
3. if possible in this environment, create the local git branch and switch to it
4. do not write application code yet

Rules:

- keep branch naming simple and consistent
- prefer a name like:
  feature/<feature_name>
  or
  feature/<issue-number>-<feature_name>
- report exactly what branch was created
- if branch creation cannot be automated here, give me the exact git command to run

```

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
- Test plan with runnable checks exists (§9)
- Active tracker issue is already updated for implementation
- Feature branch is created and checked out

Phase A — do not change application code yet:

1. Restate the implementation **scope** in a few lines (in scope / explicitly out of scope).
2. List **files to modify** (path + one-line reason each).
3. List **files to create** (path + one-line reason each).
4. Wait for my explicit confirmation (e.g. “implement now”).

Phase B — after I confirm: implement strictly within scope, following the plan and ARCHITECT.md / WORKFLOW Core Principles. Run the spec’s checks as you go; fix only within scope if something fails.
```

---

# 11. Complete the feature

When implementation and the **Run tests** / **Fix** loop are finished, **close the feature** in a controlled way:

1. **Validate** — Check the work against the feature spec and the **test plan** (runnable checks from §9). Record **passed**, **failed**, and **deferred** items.
2. **Update the active tracker issue** — Add a short validation summary (outcomes, deferrals).
3. **Board status** — Set the item to **Done** (or your tool’s equivalent) when scope matches what was agreed; otherwise keep it in **Doing** / **Waiting** until it does.
4. **Close the issue** — When your process says the unit of work is finished, **close** it in the tracker so lists stay accurate (many tools still show closed items on a **Done** column).
5. **Commit** — Commit any remaining changes on the feature branch with clear messages.
6. **Merge** — Merge into your main integration branch per your team’s rules.
7. **Optional** — Capture deferred checks or light tech debt (e.g. a short note on the issue, in the spec, or a small **Post-feature cleanup** section in the spec if you use one).

Then continue with **§12 Iteration Cycle** for the next unit of work.

---

# 12. Iteration Cycle

Repeat the cycle:

feature_tree  
↓  
select smallest unit  
↓  
feature spec  
↓  
implementation plan  
↓  
test plan  
↓  
confirm (§7.5)  
↓  
code  
↓  
tests

---

# 13. Project Evolution

As the project grows:

- update feature_tree.md
- update architecture.md
- add new specs in docs/specs/

These documents serve as **AI context sources**.

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
Select Feature  
↓  
Feature Spec  
↓  
Implementation Plan  
↓  
Understand Steps  
↓  
Generate Test Cases  
↓  
User confirms implementation (§7.5 gate)  
↓  
Activate issue + Branch  
↓  
Implement Steps (commit per step)  
↓  
Run Tests  
↓  
Fix  
↓  
Validate; update tracker; close issue; merge branch

```

```

