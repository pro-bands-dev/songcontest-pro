# Migration Notes v3.0.0-dev1

## Datenmigration

Bestehende Daten werden nicht gelöscht.

Bei Aktivierung:

- bestehende Artists → aktueller Default-Contest
- bestehende Votes → aktueller Default-Contest
- bestehende Jury Votes → aktueller Default-Contest
- bestehende Jury Preselects → aktueller Default-Contest
- IP-Geodaten werden aus `scp_votes` nach `scp_ip_cache` übernommen

## Neue Tabellen

- `scp_contests`
- `scp_ip_cache`
- `scp_logs`

## Neue Spalten

- `contest_id` in:
  - `scp_artists`
  - `scp_votes`
  - `scp_jury_votes`
  - `scp_jury_preselect`
