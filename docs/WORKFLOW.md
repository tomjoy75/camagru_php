# AI-Assisted Development Workflow

This document describes the standard workflow used to develop projects
with AI assistance (Cursor, ChatGPT, etc.).

Goals: - Maintain architectural clarity - Break the project into small
testable units - Guide the AI with structured context

**Stack-specific guidance** (e.g. web server, MVC, HTTP/curl tests):  
`docs/WORKFLOW-addendum-web-server.md` — use together with this file when it applies.

------------------------------------------------------------------------

# 1. Project Context

Before starting implementation, gather the core context.

## Inputs

-   Project subject / specification
-   Constraints (language, framework, architecture)
-   Expected features

## Suggested folder structure

docs/ \
├── subject.txt \
├── WORKFLOW.md \
├── product_spec.md \
├── architecture.md \
├── feature_tree.md \
└── specs/

------------------------------------------------------------------------

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

Goal \
... \
Describe the objective of the application.

Core Features
- area 1
- area 2
- area 3

Constraints 
- language 
- frameworks 
- security requirements

------------------------------------------------------------------------

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

------------------------------------------------------------------------

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

Product \
│ \
├── Module A \
│ ├── feature 1 \
│ └── feature 2 \
└── Module B \
    └── feature 3

(Replace with your real modules and features in **feature_tree.md**.)

------------------------------------------------------------------------

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

------------------------------------------------------------------------

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

------------------------------------------------------------------------

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
```

Example output format:

1 add entry point or wiring
2 validate inputs
3 implement core behavior
4 persist or integrate side effects
5 expose result (response, UI, exit code, etc.)

(Web route–driven plans: see `docs/WORKFLOW-addendum-web-server.md`.)

------------------------------------------------------------------------

NB. Before implementing a step, make sure you understand what it does.
If unclear, ask the AI to explain the step before coding.

### Example prompt (explain a step)

```
Explain step <n> of the implementation plan in simple terms without writing code.
```

------------------------------------------------------------------------

# 7.5 Spec-driven implementation gate (AI)

Before changing **application source code** (whatever that means in this repo: services, UI, handlers, etc.):

1. **Discovery summary** — List files you plan to **read**, **modify**, and **create**, each with a one-line reason. If you are unsure, say so instead of guessing paths.

2. **Artifacts** — In the same planning pass, ensure there is (or produce):
   - a **feature spec** in `docs/specs/<feature_name>.md` (goal, behavior, constraints, success criteria as in §6);
   - an **implementation plan** in that spec (§7);
   - a **test plan** (success, failure, edge cases) and **runnable checks** per §9 (e.g. scripts, unit tests, or — for HTTP projects — curl blocks described in the web addendum).

3. **Confirmation** — **Do not edit application source files** until the user explicitly confirms (e.g. “go ahead”, “implement now”). If the user asked for documentation-only work, follow that scope. After confirmation, follow §8.

4. **Scope** — Do not widen the feature (extra surfaces, refactors, dependencies) without **stating the widening explicitly** and getting user approval.

5. **Architecture** — Follow **docs/architecture.md** and **Core Principles** below. Prefer the smallest change; avoid new abstractions unless clearly needed.

6. **Post-feature cleanup (optional)** — When useful, add to the feature spec a short section:  
   `## Post-feature cleanup / tech debt`  
   (bulleted follow-ups, known shortcuts only).

------------------------------------------------------------------------

# 8. Implementation Loop

**Start coding only after §7.5 confirmation** (when the task includes application changes).

Workflow for each step:

Feature Spec/
↓/
Implementation Plan/
↓/
Understand Steps/
↓/
Generate Test Cases/
↓/
Create Issue + Branch
↓/
Implement steps (commit per step)/
↓/
Run tests/
↓/
Fix if needed/
↓/
Close issue & merge

Recommended git workflow:

git checkout -b feature/<feature_name>

Commit example:

feat(scope): short description of change

------------------------------------------------------------------------

# 9. Test cases and Issue Creation

## Generate Tests

### AI Prompt

`````
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
`````

## Generate Github Issue

### AI Prompt

```
Read docs/specs/<feature_name>.md.

Generate a GitHub issue body for this feature.

Include:
- feature description
- implementation plan as checkboxes
- test cases as checkboxes
```

## Create branch

------------------------------------------------------------------------
# 10. Iteration Cycle

Repeat the cycle:

feature_tree\
↓\
select smallest unit\
↓\
feature spec\
↓\
implementation plan\
↓\
test plan\
↓\
confirm (§7.5)\
↓\
code\
↓\
tests

------------------------------------------------------------------------

# 11. Project Evolution

As the project grows:

-   update feature_tree.md
-   update architecture.md
-   add new specs in docs/specs/

These documents serve as **AI context sources**.

------------------------------------------------------------------------

# Core Principles

-   Smallest testable unit first
-   Follow **docs/architecture.md** for this project’s layering and boundaries
-   Keep orchestration at edges thin; keep domain or core logic cohesive and testable
-   Isolate persistence and external I/O behind clear boundaries when the architecture calls for it
-   Architecture documents guide AI decisions

Treat the implementation plan as the source of truth. If you need to change it, update the plan first (e.g. with AI), then implement. Do not redefine steps ad hoc during implementation.

For **web MVC-style** projects, also apply the profile in `docs/WORKFLOW-addendum-web-server.md` (Core Principles — MVC web profile).


------------------------------------------------------------------------

# Final workflow

Product Spec\
↓\
Architecture\
↓\
Feature Tree\
↓\
Select Feature\
↓\
Feature Spec\
↓\
Implementation Plan\
↓\
Understand Steps\
↓\
Generate Test Cases\
↓\
User confirms implementation (§7.5 gate)\
↓\
Create Issue + Branch\
↓\
Implement Steps (commit per step)\
↓\
Run Tests\
↓\
Fix\
↓\
Close Issue
