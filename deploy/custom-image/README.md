# LibreNMS custom API image

This image keeps the official LibreNMS container runtime and adds the sensor
threshold API and current device metrics implemented in this fork. The base
image is pinned by digest so the deployed application is reproducible.

`GET /api/v0/devices/{hostname}/metrics/io-wait` returns the latest normalized
I/O wait percentage calculated from matching UCD CPU RRD samples. The response
also reports availability, sample time, raw wait rate, and related graph name.

`GET /api/v0/devices/{hostname}/metrics/live` performs a short, device-scoped
SNMP sample for current CPU, memory, I/O wait, and aggregate network rates. It
does not run a full poll or update the regular polling schedule.

`GET /api/v0/devices/{hostname}/metrics/realtime` returns the latest cached
sample immediately. Add `?refresh=1` while a client has real-time mode enabled;
the response is sent before an on-demand SNMP counter snapshot runs. CPU uses a
short raw-tick window, network uses a 10-30 second 64-bit counter window, and
unchanged agent counters never overwrite the last valid value.

Build from the repository root:

```bash
docker build \
  --build-arg VCS_REF="$(git rev-parse HEAD)" \
  -f deploy/custom-image/Dockerfile \
  -t librenms-custom:sensor-threshold-api .
```

Use the resulting image for the `librenms`, `dispatcher`, `syslogng`, and
`snmptrapd` services. Database, Redis, shared `/data` storage, and published
ports remain unchanged.
