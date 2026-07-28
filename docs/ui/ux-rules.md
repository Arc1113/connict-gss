# UX Rules

Status: Phase 3A foundation  
Updated: 2026-07-28

## Core principles

1. Give each screen one primary job.
2. Put critical state before supporting context.
3. Prefer prevention and explicit recovery over vague warnings.
4. Preserve operator flow under time pressure and network interruption.
5. Keep audience presentation calm even when recovery is happening.
6. Persist business truth before displaying success.

## Information hierarchy

Classify information as critical, supporting, secondary, or optional. Remove or
de-emphasize anything that competes with the primary task.

For `/scanner`:

1. readiness and input;
2. current scan result;
3. ceremony, HTTP server, and live-update state;
4. last successful graduate;
5. concise recovery guidance.

For `/stage`:

1. graduate display name;
2. program;
3. honors/major when present;
4. approved ceremony identity;
5. optional approved photo.

## Operational behavior

- A scanner request is not successful until the HTTP response confirms it.
- Reverb state must not disable HTTP scanning.
- Prevent repeat submission while a request is pending.
- Restore scanner focus after processing without trapping Tab or stealing
  activation from links and buttons.
- Use the server-provided result code to select operator copy; do not alter the
  business rule in React.
- Tell the operator what to do next and whether rescanning is safe.
- Never add speculative retry loops or polling to hide an outage.

## Audience behavior

- Keep the last authoritative presentation visible during transient
  disconnection.
- Recover from PostgreSQL through HTTP on reconnect or version gap.
- Do not show connection labels, errors, stack traces, or recovery instructions
  to the audience.
- Use a neutral ceremony display while initial state is loading.
- Return to the approved idle display after clear.
- Missing photo, honors, or audio must not create an empty placeholder.

## Screen-state rule

Every implemented screen must define loading, empty, success, error, and
disconnected/recovered behavior where relevant. If the backend cannot produce a
planned state in the current phase, document it and do not fabricate workflow.

## Anti–AI-slop review

Reject layouts whose main structure depends on:

- equal cards for unrelated information;
- excessive rounded containers or shadows;
- decorative gradients/glows;
- generic analytics;
- duplicated descriptions;
- a pill for every label;
- unnecessary icons/animation;
- long operational instructions;
- components created only to appear “complete.”

## Risky or future actions

Do not add confirmation dialogs, undo, queue controls, manual lookup, replay,
pause, or administration actions until their approved phase defines behavior.
