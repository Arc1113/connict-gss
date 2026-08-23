# Code signing policy

Connict GSS publishes Windows release artifacts only from the public source
repository through the checked-in GitHub Actions release workflow. Release
artifacts must be traceable to a Git commit, built on a GitHub-hosted runner,
verified against the pinned runtime checksums, and approved before signing.

Free code signing provided by SignPath.io, certificate by SignPath Foundation.

> **Enrollment status:** SignPath Foundation enrollment is in progress. Until
> approval and workflow integration are complete, locally built or unsigned
> Windows installers are development artifacts and must not be represented as
> production-signed releases.

## Signed artifacts

The signing policy covers the Windows installer and all first-party executable
code shipped inside it, including the launcher, offline TTS application, and
PowerShell operational scripts. Bundled third-party runtime files retain their
upstream identity and are checksum-verified; they are signed by this project
only when the upstream license and signing policy permit it. A release is not
eligible for publication unless every runtime code path is accepted by Windows
Smart App Control on a clean machine. The final installer is signed only after
its payload has been signed and verified. Every published installer is
accompanied by a SHA-256 checksum.

## Build and approval controls

- Source and builds originate from `Arc1113/connict-gss` on GitHub.
- GitHub-hosted runners build the complete artifact from the referenced commit.
- Third-party runtime inputs are pinned and checksum-verified before signing.
- Pull requests from contributors who cannot commit directly require review.
- A designated approver reviews every production signing request.
- Repository and SignPath accounts used by the release team require
  multi-factor authentication.
- Signing keys remain in SignPath-managed hardware security modules and are not
  copied into the repository or GitHub Actions secrets.

## Team roles

- Committer and reviewer: [@Arc1113](https://github.com/Arc1113)
- Signing approver: [@Arc1113](https://github.com/Arc1113)

Additional maintainers must be listed here before receiving a signing role.
The author of a change should not approve its production signing request when
another designated approver is available.

## Privacy

The runtime privacy commitment is documented in [PRIVACY.md](PRIVACY.md).

## Reporting compromised releases

Do not install an artifact whose signature or published SHA-256 checksum fails
verification. Report suspected signing misuse or a compromised release through
the repository's private security-reporting feature. Maintainers will suspend
signing, remove affected downloads, notify SignPath, and publish remediation
details when a release cannot be trusted.
