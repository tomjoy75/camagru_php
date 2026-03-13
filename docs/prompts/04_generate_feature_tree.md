# Step 4: Generate Feature Tree

**Source:** WORKFLOW.md §4 – Feature Tree

**Output:** `docs/feature_tree.md`

---

## Prompt

```
Read docs/product_spec.md and docs/architecture.md. If a codebase already exists, consider it.
Generate a hierarchical feature tree.

Structure it like:

Product
→ modules
→ features
→ subfeatures

Optional focus:

- authentication
- editor
- gallery
- comments
- likes
- notifications
```

## Example

```
Product
├── Module: Authentication
│   ├── Feature: register
│   ├── Feature: login
│   └── Feature: logout
├── Module: Editor
│   ├── upload photo
│   ├── select sticker
│   ├── compose image
│   └── save image
├── Module: Gallery
│   ├── view images
│   ├── pagination
│   └── delete image
…
```
