# Windows Clean-Machine Acceptance

Run this procedure on a fresh Windows 10 or Windows 11 x64 virtual machine or laptop. Do not use a development machine, a device with Docker, PHP, Node.js, Composer, PostgreSQL, or an earlier Connict GSS installation.

## Before the test

1. Record the installer version, SHA-256, source commit, tester, Windows edition/build, and whether the device has a private LAN connection.
2. Verify the installer is code-signed and its SHA-256 matches the release record.
3. Disconnect Internet access after the installer and approved local-CA onboarding material have been copied to the device.

## Acceptance flow

1. Install `ConnictGSS-Setup-x.y.z.exe` as a local administrator.
2. Confirm the desktop/start-menu shortcut opens the Connict GSS setup wizard without a terminal window.
3. Complete first-run setup with a test institution and administrator password. Do not retain test credentials in the release record.
4. Confirm the launcher displays a private HTTPS server address and all core services (database, application, realtime, and queue) as ready.
5. From a second LAN device, import the exported local CA and open the HTTPS address. Verify sign-in, scanner, stage, and stage-control links.
6. Rehearse scanner → queue → stage. Temporarily stop/restart the Reverb service and confirm the client recovers state through HTTP.
7. Generate an announcement. If the TTS service is bundled, confirm both `af_heart` and `af_bella` produce playable speech; otherwise confirm the live-announcer/manual-audio fallback remains usable.
8. Use **Backup now**, verify the resulting backup, then export diagnostics. Confirm neither archive contains `runtime.env`, database data, announcement audio, or graduate media.
9. Re-run the installer as an upgrade. Confirm it creates a verified backup, preserves the existing institution, users, ceremony data, media, and configuration, and completes pending migrations.
10. Uninstall Connict GSS. Confirm program files, services, firewall rules, and shortcuts are removed while `C:\ProgramData\ConnictGSS` remains intact.

## Automated evidence

After first-run setup and after the upgrade, run PowerShell as administrator:

```powershell
& 'C:\Program Files\Connict GSS\scripts\Test-ConnictGssCleanMachine.ps1' -RequireTts
```

The script writes a JSON report under `C:\ProgramData\ConnictGSS\diagnostics\`. All required checks must pass. A missing or failed TTS service is non-blocking only when the release is explicitly approved without offline TTS; omit `-RequireTts` in that case.

Record the report, installer hash, source commit, and any manual-rehearsal evidence with the release candidate. Do not publish the installer if a required check, backup/upgrade rehearsal, or LAN-device workflow fails.
