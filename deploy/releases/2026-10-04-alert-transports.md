# Alert transport and operation API — 2026-10-04

Source: `master` at `bf9b6940eb`, on top of the deployed `26041b0e35` (image `librenms-5uf:cacti-fix-26041b0e35`).

## Changes

- `GET`/`POST`/`DELETE /api/v0/alert/transports` and `GET /api/v0/alert/operations`, `POST`/`DELETE /api/v0/alert/operations/{id}/transports` (see `doc/API/Alerts.md`). The FebNMS app uses them to register its push webhook.
- `me/mobile-dashboard` routes removed (layouts now live in the FebNMS account service).

## Deployment

Overlay image built on each host from `routes/api.php` and the two new controllers; the Dockerfile also deletes `bootstrap/cache/routes-v7.php`, which the base image bakes in at build time and which would otherwise hide the new routes. Only the web service was switched.

| | FEB (`librenms-web-1`, via JumpServer) | 5UF (`librenms`, `root@172.237.80.65`) |
| --- | --- | --- |
| Image | `librenms-5uf:alert-transports-bf9b6940eb` | `librenms-5uf:alert-transports-bf9b6940eb` |
| Service | `web` in `/opt/librenms/compose.yaml` (line 47) | `librenms` in `/opt/librenms/compose.yml` (line 38) |
| Compose backup | `compose.yaml.bak-alert-transports-*` | `compose.yml.bak-alert-transports-*` |
| Build context | `/opt/librenms/releases/alert-transports-bf9b6940eb` | `/opt/librenms/releases/alert-transports-bf9b6940eb` |

Verified after switching on both hosts: `route:list` shows the six `api/v0/alert/*` routes and no `mobile-dashboard` route, the route cache file is gone, login 200, no errors in the web logs.

## Rollback

No schema change. Restore the compose backup (or set the web service image back to `librenms-5uf:cacti-fix-26041b0e35`) and `docker compose up -d --no-deps <service>`.
