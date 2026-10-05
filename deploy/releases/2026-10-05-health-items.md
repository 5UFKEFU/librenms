# Per-disk I/O and per-item health graphs — 2026-10-05

Source: `master` at `0bc61d5d57`, on top of the deployed image `librenms-5uf:graph-timegrid` (`7e133c1981`).

## Changes

- `GET devices/{device}/health` also lists `device_diskio` when the device has disks, and `GET devices/{device}/health/device_diskio` lists each disk (`sensor_id` = `diskio_id`, `desc` = disk name). The app shows per-disk I/O as the web health page does.
- `GET devices/{device}/graphs/health/{type}/{id}` draws the item's own graph: `processor_usage`, `mempool_usage`, `storage_usage`, `diskio_bits` (`device_diskio`), `diskio_ops` (`device_diskio_ops`). These were drawn as `sensor_*` graphs before, which read sensor rows with the item's id. Sensor classes are unchanged.
- `graphs/health/device_diskio` without an id draws the device-wide disk throughput.

## Deployment

Overlay of `includes/html/api_functions.inc.php` and the new `LibreNMS/Data/Graphing/HealthItemGraph.php` (owned `1000:1000`, 644). Before building, the running `api_functions.inc.php` matched `7e133c1981` on both hosts. Only the web service was switched; no database migration.

| | FEB (`lnms.feb.sg`) | 5UF (`libernms.5ufkefu.com`) |
| --- | --- | --- |
| Image | `librenms-5uf:health-items-0bc61d5d57` | `librenms-5uf:health-items-0bc61d5d57` |
| Service | `web` in `/opt/librenms/compose.yaml` (line 47) | `librenms` in `/opt/librenms/compose.yml` (line 38) |
| Compose backup | `/opt/librenms/compose.yaml.bak-health-items-20261005052940` | `/opt/librenms/compose.yml.bak-health-items-20261005053059` |

Verified on FEB (device 2): the health list includes `device_diskio` with 26 disks; per-disk throughput and IOPS, one processor, one storage volume and the device-wide disk graph all render. Login 200 (internal and public) and no web log errors on both hosts; the app lists and draws per-disk and per-processor graphs on 5UF.

## Rollback

No schema change. Restore the compose backup (image `librenms-5uf:graph-timegrid`) and run `docker compose up -d --no-deps <service>`.
