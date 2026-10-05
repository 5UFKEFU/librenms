# Disk graph grouping — 2026-10-05

Source: `master` at `175e9fb1e8`, on top of the deployed image `librenms-5uf:health-items-0bc61d5d57` (`0bc61d5d57`).

## Changes

`device_diskio_bits` and `device_diskio_ops` (device graphs and the API `graph` endpoint) take three optional filters so a server with many disks can be read in groups:

- `disk_scope`: `all` (default), `physical` (whole disks only), `partitions` (partitions only).
- `disk`: one disk name (`^[A-Za-z0-9._-]{1,64}$`); draws that disk and its partitions.
- `disk_direction`: `both` (default), `read`, `write`.

## Deployment

Overlay of `LibreNMS/Data/Graphing/DiskGraphScope.php`, `LibreNMS/Data/Graphing/GraphParameters.php`, `LibreNMS/Util/Graph.php`, `includes/html/api_functions.inc.php` and `includes/html/graphs/device/diskio_common.inc.php` (owned `1000:1000`, 644). Before building, all five running files matched `0bc61d5d57` on both hosts. Only the web service was switched; no database migration.

| | FEB (`lnms.feb.sg`) | 5UF (`libernms.5ufkefu.com`) |
| --- | --- | --- |
| Image | `librenms-5uf:disk-groups-175e9fb1e8` | `librenms-5uf:disk-groups-175e9fb1e8` |
| Service | `web` in `/opt/librenms/compose.yaml` (line 47) | `librenms` in `/opt/librenms/compose.yml` (line 38) |
| Compose backup | `/opt/librenms/compose.yaml.bak-disk-groups-20261005060608` | `/opt/librenms/compose.yml.bak-disk-groups-20261005060843` |

Verified on both hosts: all six variants (all, physical, partitions, one disk, read, write + physical) render without errors; on FEB, `disk=nvme0n1` draws nvme0n1 and its five partitions and `disk_direction=read` draws only the read series. Login 200 and no web log errors.

## Rollback

No schema change. Restore the compose backup (image `librenms-5uf:health-items-0bc61d5d57`) and run `docker compose up -d --no-deps <service>`.
