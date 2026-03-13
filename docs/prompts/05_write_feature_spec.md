# Step 5: Write Feature Spec

**Source:** WORKFLOW.md §5 (Feature Selection) + §6 (Feature Specification)

**Output:** `docs/specs/<feature_name>.md`

---

## 5a – Feature Selection prompt

Use this first to choose *which* feature to specify:

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
3. controllers/services involved

Do not write code.
```

---

## 5b – Feature spec file and template

**File:** `docs/specs/<feature_name>.md`

**Template:**

```markdown
# Feature: <name>

**Goal** – Describe the objective.

**Behavior** – Describe expected behavior.

**Constraints** – Technical or security constraints.

**Success Criteria** – How we know the feature works.
```

Fill this in for the chosen feature, then proceed to step 6 (Implementation Plan is added in step 7 or as part of the implementation plan prompt).
