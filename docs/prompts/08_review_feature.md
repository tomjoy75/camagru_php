# Step 8: Review Feature

**Source:** WORKFLOW.md §8 (Implementation Loop – Run tests / Fix) and §10–11 (Iteration, Project Evolution)

---

## Prompt

```
Read docs/specs/<feature_name>.md and the code that was just implemented.

Review the feature:

1. **Spec compliance** – Does the implementation match the spec (Goal, Behavior, Constraints, Success Criteria)?
2. **Implementation plan** – Were all steps completed? Any steps skipped or done out of order?
3. **Tests** – Do the tests in the spec pass? Are there gaps (e.g. missing failure or edge cases)?
4. **Architecture** – Controllers thin? Business logic in services? Views only render data? Repositories for persistence?
5. **Security** – Input validation, escaping in views, no path traversal or trust of client-provided paths?

If anything is missing or wrong, list concrete fixes. Do not rewrite the whole codebase; suggest minimal, targeted changes.
```

---

## After merge (project evolution)

- Update `docs/feature_tree.md` if the product scope or structure changed.
- Update `docs/architecture.md` if new controllers, services, or repositories were added.
- Keep `docs/specs/<feature_name>.md` as the record of what was built; these docs serve as **AI context sources** for future work.
