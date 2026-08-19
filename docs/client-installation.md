# Connict GSS Quick Start (Windows)

This guide is for the school laptop that will run Connict GSS during the ceremony. Install it only on that laptop; scanner, stage, and stage-control devices use their browsers.

1. Connect the server laptop to the dedicated ceremony network and plug it into power.
2. Run `ConnictGSS-Setup-x.y.z.exe` as an administrator.
3. Open the **Connict GSS** desktop shortcut.
4. On first run, enter the institution name and first administrator details. Use an administrator password with at least 12 characters.
5. Wait for the ready screen, then choose **Open Connict GSS**.
6. Use the displayed server address to connect other ceremony devices.

No PHP, Composer, Node.js, PostgreSQL, Docker, or terminal commands are required on the client laptop.

## Updates and uninstall

Run a newer `ConnictGSS-Setup-x.y.z.exe` to update. Connict GSS stops services, creates a verified backup, applies safe migrations, and then restarts. If a backup or migration fails, the update stops and preserves the existing data and logs.

Normal uninstall removes the application and shortcuts but intentionally retains `C:\ProgramData\ConnictGSS`, including ceremony data and backups. Do not delete that directory unless you have an explicit, verified institutional-data disposal decision.
