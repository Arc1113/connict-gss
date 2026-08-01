# Remaining UX remediation coverage inventory

Prepared before remediation browser execution on 2026-08-01. The target remains the isolated QA database `graduation_stage_system_full_qa_20260801` at `http://127.0.0.1:8002`.

| Finding | Interaction and expected result | Off-happy-path coverage | Required evidence |
| --- | --- | --- | --- |
| L-01 invalid-login focus | Submit invalid credentials by button and Enter. The associated error remains visible, the password is cleared, and focus plus selection return to the email field for immediate correction. | Repeat an invalid submission; submit with an unknown email as well as a wrong password. | Active-element assertion, axe result, 1366x768 screenshot. |
| L-02 scanner fallback height | Expand **QR will not scan? Use controlled lookup**. The lookup appears as a bounded desktop tray without increasing document height or moving the scanner input/Process button; the lookup field receives focus. Closing it returns focus to the USB scanner input. | Search with fewer than two characters; search with no result; populate enough results to require tray-local scrolling; resize while open. | Before/after document-height measurement, element bounds, keyboard-focus assertions, 1366x768 and 1536x864 screenshots. |
| L-03 assisted-automatic readability | At 1366x768, the status cell states **Running** or **Paused**, explains scan behavior, and exposes a full-size Pause/Resume action without clipping. Clicking each action persists and visibly updates the state. | Double-click while a request is pending; pause with an empty queue; refresh while paused and verify persistence. | Bounding-box/overflow assertions, pause/resume HTTP responses, axe result, running and paused screenshots. |
| L-04 administrator navigation fit | At 1536x864, every authorized destination—including **Connect devices**—is visible above the fixed keyboard-shortcuts footer without first scrolling the sidebar. All links retain usable pointer and keyboard targets. | Check 1366x768; expand and collapse navigation; zoom-independent DOM bounds at the supported default scale. | Navigation/footer bounds, `scrollHeight <= clientHeight`, keyboard activation, 1536x864 screenshot. |
| M-01 Reverb recovery | Keep scanner, stage control, and audience clients open; stop Reverb, observe explicit disconnected/syncing status, restart it, then require all clients to reconnect and reconcile PostgreSQL-authoritative state without refresh. | Issue an HTTP-backed ceremony command during the outage; repeat a second restart; dispatch browser offline/online while recovery is pending. | Connection-state timestamps, HTTP response, unchanged page navigation IDs, Reverb/app logs, before/outage/recovered screenshots. |

## Regression gates

- No new browser console errors, failed application requests outside deliberate negative paths, JavaScript exceptions, or persistent WebSocket errors.
- Axe returns zero violations on the changed login, scanner, stage-control, and administrator screens.
- Scanner, stage control, and audience retain their critical controls at 1366x768, 1536x864, and 1920x1080.
- Functional assertions precede the separate visual inspection pass; screenshots are reviewed after state and geometry checks.

## Execution result

| Finding | Result |
| --- | --- |
| L-01 | Passed button, Enter, repeated-error, password-clear, email-focus, screenshot, and axe checks. |
| L-02 | Passed open/close focus, no-result search, bounded-tray, and unchanged document-height checks at 1366x768, 1536x864, and 1920x1080. |
| L-03 | Passed readable running/paused text, in-bounds action, one-POST double-click lock, refresh persistence, resume, screenshot, and axe checks. |
| L-04 | Passed without navigation overflow at 1366x768 and 1536x864; **Connect devices** remained above the footer with its 34px target intact. |
| M-01 | Passed two Reverb restarts. All original documents reconnected without refresh; first recovery took 10.1 seconds, and offline-to-online scanner recovery took 2.3 seconds. |
| Follow-up L-05 | Dashboard workspace links were converted from invalid ARIA list roles to native list semantics; axe returned zero. |
