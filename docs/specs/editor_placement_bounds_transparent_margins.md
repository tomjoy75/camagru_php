# Feature: Editor placement bounds ignore transparent sticker margins

Goal

Align editor sticker **placement limits**, **auto-fit**, **rotation bounding box**, and **preview overlay geometry** with the sticker’s **visually opaque content**, not the full PNG rectangle. Many sticker assets include large transparent padding; today both the client (`editor_sticker_placement.js`, using `naturalWidth` / `naturalHeight`) and the server (`ImageComposeService`, using full GD image dimensions) treat the whole file as the drawable extent, so users cannot move the visible graphic as close to the base edges as expected and preview vs. compose can feel inconsistent with what they see.

Behavior

- Define a **content bounding box** for each sticker: the smallest axis-aligned rectangle that contains all pixels whose alpha is above a defined threshold (e.g. not fully transparent). Stickers with no such pixels fall back to current full-image behavior or a documented safe default.
- **Fit-to-base**, **user scale**, **rotation AABB**, and **position clamping** (`x` / `y` maxima) use dimensions derived from this content box (or from the image after an equivalent crop-to-content step), so the “object” being bounded is what the user perceives, not invisible margins.
- `**(x, y)` semantics** stay consistent end-to-end: whatever convention the server uses for the composed result (e.g. top-left of the same box used for clamping after scale/rotate) must match what the interactive preview computes, so submitting the form still matches the [Editor interactive sticker placement](editor_interactive_sticker_placement.md) contract—no silent drift between preview and `POST /editor/compose` output.
- The final composed PNG must still **preserve sticker alpha** and obey the existing rule that the **entire** drawn sticker layer stays inside the base (no overflow); only the **measured** extent for layout changes, not security or path rules.
- **Regression:** Stickers that are already tight (little or no transparent border) behave as today within normal rounding.

Constraints

- **Stack:** PHP standard library and **GD** on the server; **vanilla JS** on the client—no new frameworks or libraries.
- **Security:** Unchanged: stickers only via `StickerService`; no client-controlled paths; server remains authoritative for compose; validate `x` / `y` / scale / angle as today.
- **Architecture:** Composition and image math stay in the service layer; HTTP in the controller; views unchanged except any data attributes or inline config needed for the client (keep HTML-only views—prefer data from the controller if required).
- **Performance:** Content bounds may be computed once per sticker load / selection (client) and once per compose (server); avoid repeated full-image scans on every mousemove if implemented via scanning.

Success Criteria

- With a sticker file that has **obvious transparent margins**, the user can place the **visible** graphic measurably closer to the base edges than before, without the overlay “hitting” the clamp while the art still looks inset.
- For the same session base, sticker, scale, angle, and position, the **composed temp image** matches the **preview** alignment within the same tolerance as today (integer pixel rounding only).
- Stickers without meaningful transparency (or full-bleed art) show **no user-visible regression** in bounds or compose output.
- Manual check: drag to corners at multiple rotations and scale values; no server errors, no overflow past base bounds, alpha still correct in the output.

