# Windows runtime inputs

Release CI stages signed, version-pinned Windows x64 runtime inputs under `distribution/windows/runtime-input/` before running `Build-ConnictGssRelease.ps1`. They are intentionally not committed. Every file must be present in `runtime-input/runtime-manifest.json`, with a verified SHA-256 and official source URL; the build fails closed otherwise. Start from `runtime-manifest.example.json`.

Required layout:

```text
runtime/
├── frankenphp/
│   ├── frankenphp.exe
│   └── php.exe
├── postgres/
│   └── bin/          # initdb, pg_ctl, psql, createdb, pg_dump
├── tts/
│   ├── tts-server.exe # PyInstaller bundle with MP3 and phonemizer extensions
│   ├── models/        # pinned Kokoro model cache
│   └── licenses/      # generated dependency and model license inventory
└── winsw/
    └── WinSW-x64.exe
```

The GitHub Windows runner creates this entire tree before packaging. The TTS
payload must be fully offline: no package installation, model download, or
cloud credential is permitted at client installation or ceremony time.
