# Device Setup Checklist

Status: Phase 11 software browser rehearsal verified; physical devices pending

Use these checklists after the server deployment is prepared. The approved event
layout is three laptops: server, scanner, and stage.

Phase 11 used separate authenticated browser profiles to simulate the approved
roles and routes. It did not attach the final USB scanner, projector/LED, venue
audio, router, or three separate laptops. Complete this checklist physically
before marking the event GO.

## Approved browser baseline

Use a current Chromium-based browser, such as Google Chrome or Chromium. The
browser must have:

- JavaScript enabled;
- cookies enabled;
- local storage enabled;
- zoom at 100%;
- no extensions that modify forms, audio, fullscreen, or keyboard input;
- notifications disabled;
- password-manager popups suppressed where practical.

The ceremony runtime is local HTTP unless a separate approved local TLS setup is
implemented and tested.

## Browser session strategy

- Server/admin device: administrator or technical operator account.
- Scanner laptop: scanner-operator account in its own browser profile.
- Stage laptop: stage operator or approved stage-display session in its own
  browser profile.
- Stage-control: stage-operator account on a protected non-audience device.

Do not share one browser profile between incompatible roles. Log out and back in
if a device was used for the wrong role.

Before the final rehearsal, an administrator must create one named local account
per operator under `/admin/users`, assign only the required role, verify the
12-character-or-longer password offline, and deactivate unused rehearsal
accounts. Do not place administrator credentials on the audience display
profile. Technical operators should enter administration through their visible
Logs or Backups links; they are not authorized for the readiness page.

## Server laptop

Preparation:

1. Connect to the dedicated event router, preferably wired.
2. Confirm fixed/reserved server IP.
3. Disable sleep, screen lock during ceremony, and automatic operating-system
   updates.
4. Connect reliable power; use a UPS where available.
5. Confirm Docker is installed and images/dependencies are ready offline.
6. Confirm production assets exist.
7. Confirm backups are stored outside public web directories.
8. Run:

   ```bash
   bash scripts/gss-start.sh --env-file .env.production --skip-migrate
   bash scripts/gss-health-check.sh --env-file .env.production
   ```

9. Keep terminal windows away from any audience-facing display.

## Scanner laptop

Setup:

1. Connect to the event router.
2. Open the server URL, for example `http://SERVER_LAN_IP:8000/login`.
3. Log in as the scanner operator.
4. Open `/scanner`.
5. Confirm the QR input receives focus automatically.
6. Connect the USB 2D QR scanner.
7. Confirm the scanner is in HID keyboard mode.
8. Confirm the keyboard layout matches the laptop.
9. Confirm the scanner sends an Enter/carriage-return suffix.
10. Test a valid QR code.
11. Test an invalid QR code.
12. Test a duplicate QR code.
13. Test rapid scans without double submission.
14. Keep browser zoom at 100%.
15. Disable sleep, notifications, and extension popups.
16. Keep manual keyboard entry available as fallback.

Emergency recovery:

```text
Refresh /scanner
Log back in if the session expired
Confirm input focus
Resume scanning
```

## Stage laptop

Setup:

1. Connect to the event router, preferably wired.
2. Open the server URL and log in with the approved stage-display/stage operator
   account.
3. Open `/stage`.
4. Activate browser audio with the page’s audio enable control.
5. Select the correct operating-system audio output.
6. Connect projector or LED display.
7. Set the output resolution, preferably 1920×1080.
8. Enter browser fullscreen.
9. Hide browser UI and mouse pointer.
10. Disable sleep, screen saver, notifications, and update popups.
11. Verify holding screen.
12. Verify an approved audio announcement.
13. Verify missing-audio fallback does not expose technical details.
14. Refresh `/stage` and confirm HTTP state recovery.
15. Restart Reverb in a controlled test and confirm safe reconnect.

Keep admin screens, terminals, queue details, QR tokens, and technical logs away
from the audience display.

## Preventing browser chrome and pointer exposure

- Use fullscreen mode.
- Move the pointer to a screen edge or blank non-projected area.
- Disable notification banners.
- Close unrelated tabs.
- Keep the operating-system dock/taskbar hidden where practical.
- Do not mirror the server laptop terminal to the projector.

## Stage-control placement

Preferred: optional fourth control device, or server laptop operated away from
the audience.

Within the three-laptop constraint:

1. Use the server laptop for `/stage-control` if the server operator can safely
   control stage flow without exposing terminals or admin screens.
2. If the server laptop is not practical, use a separate protected browser
   profile/window on the scanner laptop.
3. Do not place `/stage-control` on the projected stage laptop.

## Audio-device preparation

1. Prefer wired audio over Bluetooth.
2. Connect the stage laptop to the venue mixer or speaker input.
3. Select the correct OS output device.
4. Disable notification sounds and system sounds where practical.
5. Disable audio enhancements that alter volume unexpectedly.
6. Calibrate volume with an approved sample announcement.
7. Test replay from stage control.
8. Confirm missing-audio fallback.
9. Keep human-announcer backup ready.

Do not claim venue audio certification until the physical venue system is tested.

## Power preparation

- Server laptop connected to reliable power.
- Router connected to reliable power; UPS recommended.
- Stage and scanner laptops connected to adapters.
- Extension cables taped or routed safely.
- Batteries charged before doors open.
- Sleep disabled.
- Lid-close behavior set to do nothing or keep awake where appropriate.
- Document server reboot recovery and router reboot recovery.

## Time and logging

- Set all devices to Asia/Manila.
- Confirm server clock before ceremony.
- Confirm scanner and stage clocks are reasonably aligned.
- Do not require internet time sync during operation.
- If time is corrected mid-event, note it in the operational log because report
  timestamps may jump.

## Physical verification status

This checklist must be physically completed on the event devices before claiming
venue readiness. Local single-machine browser checks are useful but do not
certify projector, audio mixer, router, USB scanner, or laptop power behavior.
