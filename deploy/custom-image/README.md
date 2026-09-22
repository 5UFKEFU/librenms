# LibreNMS custom API image

This image keeps the official LibreNMS container runtime and adds the sensor
threshold API and current device metrics implemented in this fork. The base
image is pinned by digest so the deployed application is reproducible.

`GET /api/v0/devices/{hostname}/metrics/io-wait` returns the latest normalized
I/O wait percentage calculated from matching UCD CPU RRD samples. The response
also reports availability, sample time, raw wait rate, and related graph name.

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
