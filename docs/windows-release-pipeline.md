# Windows Release Pipeline

The Windows installer is built from an exact, clean Git commit, never by copying a developer workstation. Use a dedicated Windows 10/11 x64 release runner with Git, PHP 8.3 and Composer, Node.js 22, the .NET 8 SDK, Python 3.12 x64, and Inno Setup (`iscc.exe` on `PATH`). These are release-runner dependencies only and are not required on the client laptop.

## Prepare the verified runtime

From PowerShell at the repository root, download the pinned FrankenPHP, PostgreSQL, and WinSW inputs:

```powershell
./distribution/windows/scripts/Get-ConnictGssRuntimeInputs.ps1
```

Build the offline TTS runtime using separately verified Windows x64 LAME, eSpeak-NG, and license inputs:

```powershell
./distribution/windows/scripts/Build-ConnictGssOfflineTts.ps1 `
  -LameExecutable 'C:\verified-runtime\lame.exe' `
  -EspeakDirectory 'C:\verified-runtime\espeak-ng' `
  -LicenseDirectory 'C:\verified-runtime\licenses'
```

Publish the TTS bundle to the approved internal release registry, record its immutable SHA-256/attestation, and register that truthful provenance:

```powershell
./distribution/windows/scripts/Register-ConnictGssTtsRuntime.ps1 `
  -Version '1.0.0' `
  -SourceUrl 'https://release-registry.example/connict-gss-tts-1.0.0.zip' `
  -LicenseRecord 'licenses reviewed in release record GSS-TTS-1.0.0' `
  -Verification 'archive-sha256:REPLACE_WITH_APPROVED_HASH; release-attestation:GSS-TTS-1.0.0'

./distribution/windows/scripts/New-ConnictGssRuntimeManifest.ps1
```

Do not use the example URL or placeholder verification text in a real release. The builder verifies every staged file against the manifest and refuses a runtime missing FrankenPHP, PHP, PostgreSQL, WinSW, or offline TTS.

## Validate and package

Run the full repository-prescribed PHP tests, Pint check, frontend lint, production build, and browser rehearsal before packaging. Use a clean checkout for the packaging step because the production Composer install removes development packages from that checkout:

```powershell
pwsh distribution/windows/scripts/Build-ConnictGssRelease.ps1 -Version 1.0.0
```

The expected artifact is:

```text
distribution\windows\installer\Output\ConnictGSS-Setup-1.0.0.exe
```

The manually dispatched [Windows release workflow](../.github/workflows/windows-release.yml) validates the application on Linux, then packages on Windows only after the caller provides an HTTPS URL and SHA-256 for an approved `runtime-input` ZIP. The ZIP must place `runtime-manifest.json`, `runtime-provenance.json`, and the `frankenphp/`, `postgres/`, `tts/`, and `winsw/` directories at its root. The workflow uploads an artifact for QA; it does not publish an unsigned installer.

Install only the generated setup executable on a separate clean Windows VM and complete the [clean-machine acceptance procedure](windows-clean-machine-qa.md). Record the installer SHA-256 and source commit with the release artifact. Code-sign the launcher and installer before broad distribution.
