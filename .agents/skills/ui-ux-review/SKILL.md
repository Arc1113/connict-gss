---
name: ui-ux-review
description: Review implemented Graduation Stage System screens for hierarchy, operational usability, accessibility, responsive consistency, content quality, audience safety, and generic AI-generated UI patterns. Use after frontend implementation or when diagnosing a screen-quality problem.
---

# UI/UX Review

Review the real implemented screen, not source code alone.

## Workflow

1. Read `AGENTS.md`, the route specification, design system, UX/content rules,
   accessibility rules, and responsive rules.
2. Run the application and open the authenticated route.
3. Exercise the primary workflow and relevant failure/recovery states with
   realistic long and missing content.
4. Inspect:
   - primary hierarchy and action clarity;
   - operational recovery and prevention of mistakes;
   - keyboard/focus/live-region behavior;
   - content specificity and terminology;
   - responsive wrapping/overflow;
   - visual-system consistency;
   - audience privacy/safety;
   - generic AI/SaaS decoration.
5. Rank findings:
   - critical: wrong action/data, inaccessible operation, sensitive exposure;
   - high: workflow ambiguity, unreadable/overflowing content, unsafe recovery;
   - medium: inconsistency or avoidable friction;
   - low: polish with little operational effect.
6. Recommend the smallest defensible correction that stays in phase.
7. Reopen and reinspect the corrected state.

## Rules

- Do not redesign working backend contracts to fix presentation.
- Do not recommend future-phase controls.
- Do not count compilation as visual review.
- Cite the actual route, state, viewport, and visible evidence for every finding.
- Record unresolved limitations honestly in `CHANGELOG_UI.md` or the phase report.
