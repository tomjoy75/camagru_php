# Step 7: Implement Feature

**Source:** WORKFLOW.md §7 (Implementation Plan) + §8 (Implementation Loop)

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

1. create route  
2. validate input  
3. call service  
4. store data  
5. return response  

---

## 7b – Understand a step (before coding it)

If a step is unclear:

```
Explain step <n> of the implementation plan in simple terms without writing code.
```

---

## 7c – Implementation loop

1. Feature spec + Implementation plan (from spec file)  
2. Understand each step before coding  
3. Generate test cases (step 6)  
4. Create issue + branch: `git checkout -b feature/<feature_name>`  
5. Implement steps (commit per step), e.g. `feat(editor): add upload endpoint`  
6. Run tests  
7. Fix if needed  
8. Close issue & merge  

**Core rule:** Treat the implementation plan as the source of truth. If you need to change it, update the plan first, then implement. Do not redefine steps ad hoc during implementation.
