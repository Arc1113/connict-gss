# Event-Day Checklist

Use this checklist on the actual ceremony network and hardware.

## Before opening doors

- [ ] Server laptop connected to reliable power.
- [ ] Scanner laptop connected to reliable power.
- [ ] Stage laptop connected to reliable power.
- [ ] Dedicated router/switch powered and stable.
- [ ] Server IP matches `.env.production`, bookmarks, and operator instructions.
- [ ] Docker deployment started with production assets.
- [ ] `bash scripts/gss-health-check.sh --env-file .env.production` passes.
- [ ] Current backup exists and has passed disposable restore verification.
- [ ] Browser profiles are separated by role.
- [ ] Administrator account is available to the lead operator.
- [ ] Scanner operator is logged in only on the scanner laptop.
- [ ] Stage operator/control account is logged in only on the control device.
- [ ] Audience stage is logged in and opened only on the projected display.

## Scanner station

- [ ] USB scanner is in HID keyboard mode.
- [ ] Scanner sends Enter/carriage return after each QR.
- [ ] `/scanner` input is focused.
- [ ] Valid printed QR produces accepted feedback.
- [ ] Invalid QR produces actionable rejection feedback.
- [ ] Duplicate QR is rejected clearly.
- [ ] Rapid scan attempts do not double-submit.
- [ ] Manual lookup fallback is available if a card cannot be decoded.

## Stage-control station

- [ ] `/stage-control` shows the active ceremony.
- [ ] Waiting queue is visible and ordered.
- [ ] Present next works.
- [ ] Present selected works only after intentional operator action.
- [ ] Complete current clears the stage.
- [ ] Skip and return behave as expected.
- [ ] Holding screen can be activated and restored.
- [ ] Automatic pause/resume behavior is understood by operators.
- [ ] Missing-audio fallback says live announcer is required.

## Audience stage

- [ ] `/stage` is fullscreen.
- [ ] Browser chrome, taskbar, pointer, notifications, and terminal windows are
      not visible to the audience.
- [ ] Stage text is readable from the audience area.
- [ ] Long names and programs wrap cleanly.
- [ ] No QR token, student number, queue detail, operator control, or technical
      warning appears on the audience display.
- [ ] Audio output device is correct.
- [ ] Approved audio plays at acceptable venue loudness.
- [ ] Live announcer fallback is rehearsed for missing or rejected audio.

## Failure rehearsal

- [ ] Refresh `/scanner`; input focus returns.
- [ ] Refresh `/stage-control`; queue recovers from PostgreSQL.
- [ ] Refresh `/stage`; current presentation or idle state recovers.
- [ ] Restart Reverb; browser state recovers through HTTP refresh/reconnect.
- [ ] Restart app service; browsers recover after refresh.
- [ ] Confirm response plan if PostgreSQL becomes unavailable.
- [ ] Confirm response plan if a wrong graduate is presented.
- [ ] Confirm response plan if projector/audio fails.

## Final sign-off

- [ ] Lead operator confirms ceremony data is final.
- [ ] Technical operator confirms health check and backup.
- [ ] Scanner operator confirms physical scanner readiness.
- [ ] Stage operator confirms control workflow.
- [ ] Audio/stage operator confirms venue output.
- [ ] Administrator confirms no active unresolved blockers.
- [ ] Final readiness marked GO only after the physical rehearsal passes.
