# Windows Runtime Contract

Every Windows installer is assembled on a Windows x64 release runner from a verified runtime manifest. The installer never downloads tools, packages, models, or dependencies on a client device.

## Initial approved components

| Component | Initial release input | Verification |
| --- | --- | --- |
| Web/PHP runtime | FrankenPHP `v1.12.7`, `frankenphp-windows-x86_64.zip` | SHA-256 `52fb7d1d8ca785599189789f813dd5cd2c29892ed2eaa3fdaab07e938e551870`, matching the GitHub release asset digest published on 2026-08-23, from the [official release](https://github.com/php/frankenphp/releases/tag/v1.12.7) |
| Native runtime prerequisite | Microsoft Visual C++ Redistributable x64 `14.44.35211.0` | SHA-256 `cc0ff0eb1dc3f5188ae6300faef32bf5beeba4bdd6e8e445a9184072096b713b` and valid Microsoft Authenticode signature |
| Service wrapper | WinSW `v2.12.0`, `WinSW-x64.exe` | SHA-256 `05b82d46ad331cc16bdc00de5c6332c1ef818df8ceefcd49c726553209b3a0da` from the [official release](https://github.com/winsw/winsw/releases/tag/v2.12.0) |
| Database | PostgreSQL `16.15-1` x64 binary archive from EDB | SHA-256 `25e6fcdfb8caec38691bf461125e7564508760666f7b8e5dc6a5f0818f58f81e`; PostgreSQL 16 remains aligned with development/QA |
| TTS | CI-built Windows x64 offline Kokoro bundle | Build attestation, per-file SHA-256, model revision `f3ff3571791e39611d31c381e3a41a3af07b4987`, generated license inventory, and production license review |
| Launcher | .NET 8 self-contained Windows x64 publish | Authenticode-sign the output; no .NET installation on the client |

The FrankenPHP archive contains `frankenphp.exe`, `php.exe`, required PHP extensions, and dependent DLLs. Preserve the archive’s verified contents as one release unit; do not hand-copy individual DLLs.

PostgreSQL is sourced as a binary archive suitable for bundling, not by running the EDB interactive installer on a client. The [PostgreSQL Windows download page](https://www.postgresql.org/download/windows/) identifies EDB as the binary/archive supplier.

## Release admission requirements

1. The GitHub `windows-2022` runner stages files below the ignored `distribution/windows/runtime-input/` directory.
2. `Get-ConnictGssRuntimeInputs.ps1` downloads the approved FrankenPHP, PostgreSQL, WinSW, and Microsoft Visual C++ Redistributable releases, verifies their pinned hashes, and verifies the redistributable's Microsoft signature.
3. `Build-ConnictGssOfflineTts.ps1` produces the offline Windows speech server from pinned Python dependencies and the pinned Kokoro model revision, then exercises both supported voices without network access.
4. `Register-ConnictGssTtsRuntime.ps1` records the workflow run and source commit. `New-ConnictGssRuntimeManifest.ps1` then hashes every staged file; the release builder rejects missing, changed, or unlisted files.
5. The workflow installs the completed setup executable on the same clean Windows runner, validates every service and offline speech, and creates a verified backup before admitting the artifact.

## GitHub release workflow

The `.github/workflows/windows-release.yml` workflow is the only supported
release builder. It validates the application on Ubuntu, then builds and signs
the installer on `windows-2022` behind the protected `windows-release`
environment.

Pull-request and manually dispatched test builds require no private runtime
bundle and no signing secrets. They use an ephemeral self-signed certificate
that is trusted only on the CI validation runner. These are internal development
artifacts and are blocked by Smart App Control when copied to another machine.

Public Windows releases will use SignPath Foundation open-source signing after
the project is accepted and its GitHub workflow integration is configured. The
current PFX-based trusted mode remains a fail-closed compatibility path; it is
not the planned source of the SignPath certificate.

Configure these environment values before selecting `trusted` signing or
pushing a `vX.Y.Z` production tag:

| Type | Name | Purpose |
| --- | --- | --- |
| Variable | `WINDOWS_SIGNING_TIMESTAMP_URL` | HTTPS RFC 3161 timestamp service. |
| Secret | `WINDOWS_SIGNING_CERTIFICATE_BASE64` | Base64-encoded PFX used to Authenticode-sign the launcher and installer. |
| Secret | `WINDOWS_SIGNING_CERTIFICATE_PASSWORD` | Password for that PFX. |

Tag builds derive the installer version from the tag and always require the
trusted signing credentials. Before an artifact can be published, the Windows
runner silently installs it, completes first-run setup, checks PostgreSQL,
HTTPS, Reverb, the queue worker and offline TTS, creates a verified backup, and
uploads its validation evidence. Only then does the workflow upload the
installer plus its `.sha256` file and create the corresponding GitHub Release.
Manual dispatches use the same gates but upload a release-candidate artifact
without publishing a GitHub Release. Both paths fail closed when runtime
verification, code signing, installation, or health checks are incomplete.

Do not upgrade PostgreSQL across a major version in an in-place application upgrade. Treat it as a separately rehearsed migration with a verified restore path.
