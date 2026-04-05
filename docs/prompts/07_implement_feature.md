# Step 7: Implement Feature

**Source:** WORKFLOW.md §7 (plan) + §7.4 (tests) + §7.5 (gate) + §8 (loop) + §9 (tracker) + §10–§12 (implement, test+issue, complete)

---

## Gate — before writing any application code

Use this (or equivalent) **first**. Do not edit application source until the user confirms.

```
You are implementing a feature. Do not modify application code yet.

1. Discovery summary: list files to read, modify, and create (one-line why each). Say if unknown.

2. Produce or confirm in docs/specs/<feature_name>.md:
   - feature spec (goal, behavior, constraints, success criteria)
   - implementation plan (§7-style steps)
   - test plan (success / failure / edge; runnable checks per WORKFLOW §7.4; for HTTP see docs/WORKFLOW-addendum-web-server.md)

3. Stop and wait for explicit confirmation (e.g. "go ahead") before any code edits.

4. Do not widen scope without stating it and getting approval.

5. Follow docs/architecture.md and WORKFLOW Core Principles; smallest change; no extra abstractions unless clearly needed.

6. Optional: add "## Post-feature cleanup / tech debt" to the spec when relevant.

Full detail: docs/WORKFLOW.md §7.5 — web/MVC/HTTP specifics: docs/WORKFLOW-addendum-web-server.md
```

**After the user confirms**, use §7a–§7c below.

---

## 7a – Implementation plan (append to spec)

Run this *before* coding to add steps to the spec:

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

Example format:

1. add entry point or wiring  
2. validate inputs  
3. implement core behavior  
4. persist or side effects  
5. expose result  

(Web route–driven plans: `docs/WORKFLOW-addendum-web-server.md`.)

---

## 7b – Understand a step (before coding it)

If a step is unclear:

```
Explain step <n> of the implementation plan in simple terms without writing code.
```

---

## 7c – Implementation loop

1. Feature spec + implementation plan + test plan in spec (WORKFLOW §7.4)  
2. User confirmation (WORKFLOW §7.5)  
3. Understand each step before coding  
4. Active issue update (WORKFLOW §9); branch per §5-bis if not already  
5. Implement steps (WORKFLOW §10; commit per step), e.g. `feat(editor): add upload endpoint`  
6. Execute spec tests + update issue (WORKFLOW §11)  
7. Fix / re-test if needed  
8. Close issue & merge (WORKFLOW §12)  

**Core rule:** Treat the implementation plan as the source of truth. If you need to change it, update the plan first, then implement. Do not redefine steps ad hoc during implementation.
