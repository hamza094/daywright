Purpose

Concise design guardrails for the Daywright landing page so humans and the assistant deliver consistent sections fast.

How to use

- Follow **general rules** for structural decisions.
- Follow the **section checklist** when touching a specific area (Header, Hero, About, Features, Pricing, Subscription, Footer).
- The assistant implements only one section per approval cycle.

General rules

- Use semantic HTML with Bootstrap containers/grid for layout.
- Keep copy/layout clean, muted, and consistent; reuse SCSS variables from `resources/sass/_variables.scss` and scope landing styles to `.landing-*` selectors inside `resources/sass/_landing.scss` (imported by `app.scss`).
- Prefer `rem` units, provide alt text, maintain WCAG-friendly contrast, and place assets in `public/img/` via `asset()` helpers.
- The HTML and CSS must follow the project's current coding standard (class naming, SCSS structure, and file placement).
- Avoid unnecessary `<div>` wrappers — prefer semantic elements that accurately reflect content.
- Keep the design modern but simple; add subtle animations and tasteful button hover effects where they improve clarity and polish.
- Follow the SCSS variables in `resources/sass/_variables.scss`. In this project the primary brand variable is named `$color-secondary`; common neutral colors include `$color-grey: #999999` and `$color-light-grey: #e5e5ee`.
- When adding or updating landing styles, edit `resources/sass/main.scss` (or the project's main SCSS file). For each section, apply the user's section-specific instruction first, then follow the rules in this document.
- Class naming: do not use double underscores `__` or double hyphens `--` in class names; use a single underscore `_` separator only (e.g., `landing-hero_title`, not `landing-hero__title` or `landing-hero--title`).
- When i provide an image per section as inspiration, the assistant may adapt that design (layout, spacing, colors, and assets) to match the app's standards and sync it according to my app design.
- Section headings: except for the Hero, Navbar,Subscrbe and Footer, every section must use a consistent section-heading design (badge/heading/border pattern) and follow the project's heading styles.

Assistant workflow

1. Ask which section to handle next.
2. Share a short proposal: files to touch under `resources/views/`, `resources/sass/`, `resources/js/`; rationale; Blade/CSS snippets.
3. Wait for `approve` (or `revise`) before editing.
4. Apply only that section’s changes, then reply with the files touched, notable diffs, and accessibility checks.
5. Await `next` before moving on.

Auto-apply option

- If the user explicitly requests immediate implementation by including `implement now` or `auto-apply` in their instruction, the assistant may apply the proposed changes immediately without waiting for a separate `approve` message. When using `auto-apply`, the assistant must still:
  - apply a focused patch that only touches the section requested,
  - and respond after applying changes with the files modified, short diffs/snippets, and accessibility checks.

Approval keywords

- `approve` — implement proposed section changes.
- `revise` — adjust proposal before coding.
- `next` — move to another section after successful delivery.

Next step

- Tell the assistant which section to start with (e.g., `Hero`).
