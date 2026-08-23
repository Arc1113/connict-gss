# Privacy policy

Connict GSS is a local-network-only graduation presentation system. The
installed application does not transfer information to Internet services or
other external networked systems. It communicates only with devices and
services that an operator deliberately configures on the local ceremony
network.

## Information processed

An institution may enter ceremony, graduate, operator-account, queue,
presentation, audit, and locally generated or uploaded media information. That
information is stored in the institution's local PostgreSQL database and local
application storage. The institution operating the installation controls the
data and is responsible for its collection, retention, backups, user access,
and deletion.

## Network behavior

- Runtime services bind to the configured local machine or ceremony network.
- PostgreSQL remains local to the server laptop.
- The offline text-to-speech engine uses bundled models and does not download
  models or submit text to a cloud service.
- Connict GSS has no telemetry, advertising, analytics, CDN, or automatic
  update service.
- No information is transferred to another networked system unless an operator
  explicitly configures and initiates that local-network communication.

The source-build workflow accesses GitHub and the documented upstream package
sources to obtain pinned build dependencies. This build-time activity is not
part of an installed Connict GSS runtime and does not contain ceremony data.

## Logs and diagnostics

Operational logs and diagnostic exports remain local. Diagnostic exports are
designed to exclude credentials, QR token values, database/media/backup
contents, and raw logs that could contain institutional information. Operators
should still review an export before sharing it.

## Security reports

Report privacy or security problems through the repository's private
security-reporting feature rather than a public issue when the report could
contain sensitive details.
