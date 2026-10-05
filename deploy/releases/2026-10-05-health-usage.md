# Usage in health item lists — 2026-10-05

Source: `master` at `a1b2c5ba0b`, on top of the deployed image `librenms-5uf:rule-timing-e027db4112` (`e027db4112`).

## Changes

`GET devices/{device}/health/device_processor`, `device_mempool` and `device_storage` list each item with its current usage (`processor_usage`, `mempool_perc`, `storage_perc`) beside `sensor_id` and `desc`. The FebNMS app averages a device's CPU, memory and storage from that one list. Before, it needed one more request per processor, up to eight in sequence, for each of the 37 devices behind "CPU Top 5".

## Deployment

Overlay of `includes/html/api_functions.inc.php` (owned `1000:1000`, 644). Before building, the running file matched `e027db4112` on both hosts. Only the web service was switched; no database migration.

| | FEB (`lnms.feb.sg`) | 5UF (`libernms.5ufkefu.com`) |
| --- | --- | --- |
| Image | `librenms-5uf:health-usage-a1b2c5ba0b` | `librenms-5uf:health-usage-a1b2c5ba0b` |
| Service | `web` in `/opt/librenms/compose.yaml` (line 47) | `librenms` in `/opt/librenms/compose.yml` (line 38) |
| Compose backup | `/opt/librenms/compose.yaml.bak-health-usage-20261005165525` | `/opt/librenms/compose.yml.bak-health-usage-20261005165612` |

Verified on 5UF: `devices/29/health/device_processor` returns its 20 processors, each with `processor_usage`. Login 200 and no web log errors on both hosts.

## Rollback

No schema change. Restore the compose backup (image `librenms-5uf:rule-timing-e027db4112`) and run `docker compose up -d --no-deps <service>`.
