# LibreNMS custom API image

This image keeps the official LibreNMS container runtime and adds the sensor
threshold API implemented in this fork. The base image is pinned by digest so
the deployed application is reproducible.

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
