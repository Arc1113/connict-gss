---
name: visual-qa
description: Perform browser-based visual QA for Graduation Stage System routes and UI changes. Use when frontend work requires exact viewport inspection, state screenshots, overflow and wrapping checks, focus and contrast review, reduced-motion verification, or evidence for phase acceptance.
---

# Visual QA

Never claim visual verification without opening the real route in a browser.

## Workflow

1. Read `docs/ui/ui-testing-checklist.md`,
   `docs/ui/visual-review-checklist.md`, and the affected screen specification.
2. Start the compiled application and required local services.
3. Open the route with the correct authenticated role.
4. Exercise every current-phase state using realistic data. Do not fake a
   business result in production code for a screenshot.
5. Inspect each exact viewport from `docs/ui/responsive-rules.md`.
6. Check hierarchy, alignment, spacing, typography, contrast, focus, target
   sizes, overflow, name/program wrapping, optional-content gaps, loading,
   recovery, and reduced motion.
7. Capture evidence with route, state, and viewport in the filename.
8. Fix high-impact visible defects, rerun affected checks, and reinspect.
9. Report exactly which routes, states, and dimensions were inspected and which
   limitations remain.

## Audience safety

For `/stage`, explicitly confirm that no navigation, operator control, QR token,
student number, debug output, connection label, technical warning, or scrollbar
is visible.

## Integrity

- Browser automation may prepare development fixtures but must not alter
  production workflow or bypass PostgreSQL authority.
- Do not use a build-only result as visual evidence.
- Do not add an external screenshot or analytics service.
- Preserve screenshots only when they provide review value and contain no
  sensitive production data.
