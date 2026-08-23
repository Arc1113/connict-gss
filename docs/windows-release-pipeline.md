# Windows Release Pipeline

The Windows installer is built from an exact, clean Git commit, never by copying a developer workstation. Development can remain entirely on Ubuntu: GitHub Actions supplies the Windows 2022 runner, PHP 8.3, Node.js 22, .NET 8, Python 3.12 x64, and Inno Setup. None of those build tools are required on the client laptop.

## Prepare the verified runtime

The workflow downloads and checksum-verifies the pinned FrankenPHP, PostgreSQL,
WinSW, and Microsoft Visual C++ Redistributable inputs. It additionally verifies
the redistributable's Microsoft Authenticode signature:

```powershell
./distribution/windows/scripts/Get-ConnictGssRuntimeInputs.ps1
```

It then builds the offline TTS runtime from pinned Python packages and the pinned Kokoro model revision:

```powershell
./distribution/windows/scripts/Build-ConnictGssOfflineTts.ps1 -PythonCommand python
```

The workflow generates a license inventory, records the workflow run and source commit as provenance, and generates the per-file runtime manifest. Review the generated license evidence before distributing a production build.

```powershell
./distribution/windows/scripts/Register-ConnictGssTtsRuntime.ps1 `
  -Version 'kokoro-0.9.4-f3ff3571' `
  -SourceUrl 'https://github.com/OWNER/REPOSITORY/actions/runs/RUN_ID' `
  -LicenseRecord 'generated inventory reviewed for production release' `
  -Verification 'GitHub-Actions-attestation:RUN_ID; source-commit:COMMIT_SHA'

./distribution/windows/scripts/New-ConnictGssRuntimeManifest.ps1
```

The builder verifies every staged file against the manifest and refuses a
runtime missing FrankenPHP, PHP, PostgreSQL, WinSW, the Visual C++
Redistributable, the generated license inventory, or offline TTS.

## Validate and package

The workflow runs the repository-prescribed PHP tests, Pint check, frontend lint, and production build before packaging. It uses a clean checkout because the production Composer install removes development packages from that checkout.

```powershell
pwsh distribution/windows/scripts/Build-ConnictGssRelease.ps1 -Version 1.0.0
```

The expected artifact is:

```text
distribution\windows\installer\Output\ConnictGSS-Setup-1.0.0.exe
```

The [Windows release workflow](../.github/workflows/windows-release.yml)
validates every pull request on Linux and creates an internal test-signed
installer on a GitHub Windows runner. Those ephemeral self-signed artifacts are
not public releases and are not accepted by Smart App Control on other
machines. Production signing is being enrolled through SignPath Foundation as
documented in the [code signing policy](../CODE_SIGNING_POLICY.md). A `vX.Y.Z`
tag must remain fail-closed until that trusted signing path and clean-machine
validation are complete.

Install only the generated setup executable on a separate clean Windows VM and
complete the [clean-machine acceptance procedure](windows-clean-machine-qa.md).
Record the installer SHA-256 and source commit with the release artifact. Verify
all first-party executable code and every runtime code path under Smart App
Control before broad distribution.
