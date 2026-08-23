# Usability refactor baseline

Captured from the isolated PostgreSQL browser environment on 2026-08-21 before the usability workflow refactor.

Each viewport contains the required workflow states:

- home and admin readiness
- ceremony list and detail
- graduate list
- import source, column mapping, and row review
- voice generation and pronunciation rules
- backups and device connections
- scanner, Stage Control, and Stage Display

The PNG files are intentionally ignored by Git as local visual-QA evidence. Regenerate them with the temporary Playwright capture runner described in the execution playbook if a fresh comparison is required.

Business behavior was frozen for this baseline. The application was exercised on the existing release-candidate branch with the seeded admin, scanner, and stage operator accounts.
