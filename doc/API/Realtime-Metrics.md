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
