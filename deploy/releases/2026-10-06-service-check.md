# Service checks on demand — 2026-10-06

Source: `master` at `0c95f8bfc3`, on top of the deployed image `librenms-5uf:health-usage-a1b2c5ba0b` (`a1b2c5ba0b`).

## Changes

- `POST services/{id}/check` (`can:update` Service) polls one saved service now and stores the result exactly as `check-services.php` would, then returns its status and message.
- `POST service-checks` (`can:create` Service) takes `device_id`, `type`, `ip`, `param`, runs the plugin with those unsaved settings and returns its status and output without writing anything.
- The poller's command building moved into `service_check_command()` in `includes/services.inc.php`, shared by both; `poll_service()` behaves as before.

The FebNMS app uses these for "Check now" and the editor's "Test" button.

## Deployment

Overlay of `includes/services.inc.php`, `routes/api.php` and the new `app/Api/Controllers/ServiceCheckController.php` (owned `1000:1000`). Before building, the two running files matched `a1b2c5ba0b` on both hosts. Only the web service was switched; no database migration, no route cache in the images.

| | FEB (`lnms.feb.sg`) | 5UF (`libernms.5ufkefu.com`) |
| --- | --- | --- |
| Image | `librenms-5uf:service-check-0c95f8bfc3` | `librenms-5uf:service-check-0c95f8bfc3` |
| Service | `web` in `/opt/librenms/compose.yaml` (line 47) | `librenms` in `/opt/librenms/compose.yml` (line 38) |
| Compose backup | `/opt/librenms/compose.yaml.bak-service-check-20261006042452` | `/opt/librenms/compose.yml.bak-service-check-20261006042358` |

Verified on 5UF: a dry run for device 21 (`check_http`) returned `HTTP OK … 2083 bytes`, and from the app a saved check ran in 0.4 s and a test with `-e 200,404` returned OK. Both route names resolve on FEB. Login 200 on both hosts.

The FEB dispatcher still runs `librenms-5uf:upstream-e490aaea11`; scheduled polling there is unchanged.

## Rollback

No schema change. Restore the compose backup (image `librenms-5uf:health-usage-a1b2c5ba0b`) and run `docker compose up -d --no-deps <service>`.
