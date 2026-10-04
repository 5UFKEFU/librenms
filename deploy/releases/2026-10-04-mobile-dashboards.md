# Multiple mobile dashboards — 2026-10-04

Source: `master` at `ba5b0e6dd7`, on top of the deployed `94b6ab7494` (image `librenms-5uf:graph-display-94b6ab7494`).

## Changes

`me/mobile-dashboard` accepts layout version 5 with additional dashboards; version 4 layouts behave as before.

- New optional layout keys: `dashboards` (up to 20; each `manual` with `modules`, or `automatic` with `policy` and `order`), `defaultDashboard` and `primaryName`. `modules` stays the primary dashboard, which is all older apps read.
- A save that omits `dashboards` (an app that predates multiple dashboards) keeps the stored dashboards, default and primary name, so older apps cannot delete dashboards they do not show.
- Module ids must be unique across all dashboards.
- The legacy base64 copy in the Notes widget now carries only the primary dashboard (version ≤ 4), leaving the 64 KB settings budget to the full layout.

## Deployment

Overlay image built on each host from the one changed file, `app/Api/Controllers/MobileDashboardController.php` (owned `1000:1000`, 644, SHA-256 prefix `a94b4dbad1886ab3`). Before building, the running file on both hosts matched `94b6ab7494` (prefix `2ed8f4516136e5d5`).

Only the web service was switched; dispatcher and sidecars keep `librenms-5uf:upstream-e490aaea11`. No database migration.

| | FEB (`lnms.feb.sg`) | 5UF (`libernms.5ufkefu.com`) |
| --- | --- | --- |
| Image | `librenms-5uf:mobile-dashboards-ba5b0e6dd7` | `librenms-5uf:mobile-dashboards-ba5b0e6dd7` |
| Service | `web` in `/opt/librenms/compose.yaml` (line 47) | `librenms` in `/opt/librenms/compose.yml` (line 38) |
| Compose backup | `/opt/librenms/compose.yaml.bak-mobile-dashboards-20261004024420` | `/opt/librenms/compose.yml.bak-mobile-dashboards-20261004024538` |
| Build context | `/tmp/mobile-dashboards-ba5b0e6dd7` | `/opt/librenms/releases/mobile-dashboards-ba5b0e6dd7` |

Verified after switching: the deployed file hash matches, login 200 (internal and public), `GET /api/v0/me/mobile-dashboard` answers 401 without a token, and no errors in the web logs.

## Rollback

No schema change. Put the compose backup back (or set the web service image to `librenms-5uf:graph-display-94b6ab7494`) and run `docker compose up -d --no-deps <service>`. Layouts saved at version 5 stay readable by the previous controller's `show`, which returns the stored layout as is; older apps read only its `modules`.
