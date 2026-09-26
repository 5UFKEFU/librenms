# Realtime overview metrics

`GET /api/v0/devices/:hostname/metrics/realtime?refresh=1` retains its existing authentication, authorization and cached response behavior. A refresh schedules one SNMP snapshot after the response. Subsequent requests return the completed sample; clients must use each metric's `sampled_at`, not their request time.

The additive fields in `metrics` are:

- `cpu.user` (including nice), `cpu.system`, `cpu.idle`, `cpu.steal`: percentages over the same counter window as `cpu.value` and `io_wait.value`. Unsupported steal is null.
- `processor_count`: discovered logical processors, or null.
- `load`: `available`, `one`, `five`, `fifteen` (load averages, not percentages), plus sample time.
- `memory.used_bytes`, `total_bytes`, `cached_bytes`, `swap_used_bytes`: bytes; unsupported optional counters are null.
- `disk`: availability, sample time/window, disk count, total `read_bytes_per_second`, `write_bytes_per_second`, `read_iops`, `write_iops`, and optional average `utilization_percent`. `devices` includes the same rates per selected disk.

Disk rates need two snapshots at least three seconds apart. Identical counters correctly yield zero. Counter resets invalidate rates; a decreasing system uptime clears all baselines. Whole physical disks are preferred, excluding partitions and stacked RAID/cache aliases to avoid double counting. Up to 16 disks are sampled. Optional IOPS/busy counters do not block byte rates. Busy time is microseconds and is converted to a percentage; it is not latency.

Older devices and servers can omit the new data. Clients should retain the existing CPU/memory/network fallback and show unavailable indicators instead of invented zero values. Disabling realtime keeps the existing polling path; these additions do not require continuous background SNMP sampling.

## Overview without realtime sampling

`GET /api/v0/devices/:hostname/metrics/summary` is a permission-checked, read-only snapshot of the poller's database and recent RRD values. It returns `status`, `device_id`, `hostname`, `sampled_at` and `metrics` using the realtime metric shape. It never initiates SNMP. Responses are cached for 30 seconds. CPU states require aligned timestamps; Load is scaled from the stored UCD values; disk RRD datasets are already bytes/second and operations/second. Missing data remains unavailable, while recorded zero rates remain zero. Disk aggregation uses the same physical-disk selection as realtime sampling and reports `disk_count` and `partial`. Disk sample time is the oldest contributing sample. RRD data outside the existing recent-sample window is not presented as current.

Storage capacity is separate from disk I/O. The mobile overview shows used/total capacity of the root filesystem `/`, not the sum of all physical disks.
