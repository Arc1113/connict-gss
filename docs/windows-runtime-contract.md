# Windows Runtime Contract

Every Windows installer is assembled on a Windows x64 release runner from a verified runtime manifest. The installer never downloads tools, packages, models, or dependencies on a client device.

## Initial approved components

| Component | Initial release input | Verification |
| --- | --- | --- |
| Web/PHP runtime | FrankenPHP `v1.12.7`, `frankenphp-windows-x86_64.zip` | SHA-256 `d01e39f9aec75cf9522830e424d9f76f081c1d8b5ec4cc0897a7fdccc8391bb7` from the [official release](https://github.com/php/frankenphp/releases/tag/v1.12.7) |
| Service wrapper | WinSW `v2.12.0`, `WinSW-x64.exe` | SHA-256 `05b82d46ad331cc16bdc00de5c6332c1ef818df8ceefcd49c726553209b3a0da` from the [official release](https://github.com/winsw/winsw/releases/tag/v2.12.0) |
| Database | PostgreSQL `16.15-1` x64 binary archive from EDB | SHA-256 `25e6fcdfb8caec38691bf461125e7564508760666f7b8e5dc6a5f0818f58f81e`; PostgreSQL 16 remains aligned with development/QA |
| TTS | Windows x64 offline Kokoro bundle | Build attestation, full payload SHA-256, model revision `f3ff3571791e39611d31c381e3a41a3af07b4987`, and third-party license review |
| Launcher | .NET 8 self-contained Windows x64 publish | Authenticode-sign the output; no .NET installation on the client |

The FrankenPHP archive contains `frankenphp.exe`, `php.exe`, required PHP extensions, and dependent DLLs. Preserve the archive’s verified contents as one release unit; do not hand-copy individual DLLs.

PostgreSQL is sourced as a binary archive suitable for bundling, not by running the EDB interactive installer on a client. The [PostgreSQL Windows download page](https://www.postgresql.org/download/windows/) identifies EDB as the binary/archive supplier.

## Release admission requirements

1. Store all staged files below `distribution/windows/runtime-input/` outside source control.
   Run `Get-ConnictGssRuntimeInputs.ps1` on the Windows release runner to stage the approved FrankenPHP and WinSW inputs with their pinned hashes.
2. Supply `runtime-input/runtime-manifest.json`, based on `runtime-manifest.example.json` but with no placeholders.
3. Run `New-ConnictGssRuntimeManifest.ps1` after staging. It hashes every staged file and refuses unprovenanced TTS/model files. The release builder rejects both missing and unlisted runtime files.
   Register the TTS provenance first with `Register-ConnictGssTtsRuntime.ps1`; see [the offline TTS bundle contract](windows-offline-tts-bundle.md).
4. Build only after a clean Windows VM smoke test of the staged runtime.
5. Record runtime versions and hashes alongside the signed installer checksum.

Do not upgrade PostgreSQL across a major version in an in-place application upgrade. Treat it as a separately rehearsed migration with a verified restore path.
