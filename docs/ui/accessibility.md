# Accessibility Requirements

Status: Phase 3A foundation  
Updated: 2026-07-28

## Operator screens

- All functions must remain keyboard reachable in logical visual order.
- Use native labels, buttons, links, and form controls.
- Show a visible 3px focus indicator that is not clipped.
- Keep interactive targets at least 44×44px.
- Associate input help/error text with the input.
- Use text plus semantic color for state.
- Announce processing politely and confirmed results assertively only when
  urgency justifies it.
- Do not trap focus in the scanner input. Tab and Shift+Tab must work.
- Restore scanner focus after processing and browser-window return.
- Pending state must be exposed through disabled/read-only semantics and copy.

## Stage

- Graduate name and supporting text require high contrast and distance-readable
  sizing.
- Use semantic headings and a single clear landmark.
- Loading state should use `role="status"` without technical detail.
- Decorative elements must be hidden from assistive technology.
- Optional photos use empty alt text when the adjacent name fully identifies the
  graduate and the photo adds no content.
- Do not expose technical reconnect messages through visible or assertive
  audience announcements.

## Motion

Honor `prefers-reduced-motion: reduce`. Remove non-essential transitions and
animations. No state change may depend on motion alone.

## Content and contrast

- Normal text target: WCAG AA contrast.
- Large stage text must remain visibly distinct under projector conditions.
- Never rely on green/red alone.
- Avoid light gray essential text.
- Do not shrink long names below the documented stage minimum merely to prevent
  wrapping.

## Verification

For each affected screen:

- Tab through all controls and verify order.
- Verify scanner focus at load and after all result types.
- Inspect focus at every required operator viewport.
- Emulate reduced motion.
- Confirm live-region text matches the visual result.
- Inspect long content at 200% browser zoom when practical.
- Record any requirement deferred to the final Phase 11 accessibility review.
