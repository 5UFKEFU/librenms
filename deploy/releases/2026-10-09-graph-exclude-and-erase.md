# Graph time windows: hide and permanently clear — 2026-10-09

Source: `master` at `1f6f96de43` (hide from `f3ed51113c`).

## Changes

- `exclude=start-end,...` (unix seconds, at most 10 windows) on any graph API request. `LibreNMS/Data/Graphing/GraphExclusion` rewrites each `DEF:x=...` into `DEF:x__raw=...` plus a `CDEF` that is UNKN inside the windows. The windows are not drawn, don't stretch the scale, and are left out of the legend statistics. Stored data is unchanged. Hooked in `LibreNMS/Util/Graph::get`.
- POST to a graph's API URL, with `erase_start`/`erase_end` in the body, permanently clears that window in every RRD file the graph's DEFs read.
  - The work is done by `LibreNMS/Data/Store/RrdRangeEraser`:
    1. flush rrdcached for the file;
    2. `rrdtool dump`;
    3. set every row whose consolidation interval overlaps the window to NaN, in all RRAs;
    4. `rrdtool restore` to a temporary file and rename it over the original.
  - The original file is first copied to `/data/febnms-rrd-backups/<YYYYmmdd-HHMMSS>/<relative path>`.
  - The POST routes are in the `can:update,Device` group and cover generic, health, port, service and application graphs. Results are logged ("FebNMS cleared graph data").
- The FebNMS app (build 100) uses both from a drag selection in expanded graphs.

## Deployment

- 5UF: `svc-retries-1f6f96de43` and `dispatcher-svc-retries-1f6f96de43`. RRD files are local at `/data/rrd`.
- FEB: the same tags. RRD files live in the rrdcached container (`./rrd/db`). The web service gained the volume `./rrd/db:/data/rrdcached`, which has the same uid 10000. `RrdRangeEraser` uses `RRD_LOCAL_DIR`, default `/data/rrdcached`, when rrdcached is configured.
- Compose backups are named `*.bak-svc-<commit>`. Logins returned 200 on both hosts.

Verified:
- Hide: a 6-hour window of CPU on sg1-pc01 was left blank.
- Clear: on copies of real files on both hosts, 64 rows were cleared, a backup was made, the file was replaced, and the copies were then removed.
- API: a POST with a future window returned per-file results with nothing cleared, using a temporary token that was deleted afterwards.

## Restore

To undo a clear, copy the file back from `/data/febnms-rrd-backups/...`. On FEB the backup sits in the web container's `/data`, and the live file is `./rrd/db/...` on the host. Flush rrdcached for that file first.

## Rollback

Switch both compose files back to the `*-818abfb316` tags. On FEB, also remove the `./rrd/db:/data/rrdcached` volume from `web`, then recreate the containers.
