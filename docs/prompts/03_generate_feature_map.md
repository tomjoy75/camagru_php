# Step 3: Generate Feature Map

**Source:** WORKFLOW.md §4 – Feature Tree (high-level pass)

**Output:** A concise map of modules and main features (one or two levels) to align with product spec and architecture. Can be a section in `docs/product_spec.md` or a short `docs/feature_map.md` if you want a separate artifact before the full tree.

---

## Prompt

```
Read docs/product_spec.md and docs/architecture.md.

Generate a concise feature map: list modules and their main features (no subfeatures yet).

Structure: Product → modules → features (one level only).

Optional focus: authentication, editor, gallery, comments, likes, notifications.
```

## Purpose

Use this to confirm scope and alignment before generating the full feature tree with subfeatures in step 4.
