# Changes maintained by this fork

This repository follows the upstream [LibreNMS project](https://github.com/librenms/librenms)
and carries a small set of changes needed by the native mobile client and the
current container deployment.

## Sensor threshold API

Authenticated users with permission to update a sensor can edit one sensor or
up to 500 sensors in a transaction:

- `PATCH /api/v0/resources/sensors/{sensor}` updates a single sensor.
- `PATCH /api/v0/resources/sensors` updates the sensor IDs supplied in
  `sensor_ids`.
- The API accepts the four critical/warning thresholds, `sensor_alert`, or a
  `reset` request.
- Threshold order is validated before saving. Missing/deleted sensors and
  unauthorized bulk items fail the request instead of applying a partial
  update.
- User-provided thresholds are marked custom so later discovery runs preserve
  them. Reset returns ownership of the thresholds to discovery.

The public request and response examples are documented in
[`doc/API/Devices.md`](doc/API/Devices.md).

## Temperature limit discovery

When hardware does not publish a low temperature threshold, LibreNMS no longer
guesses one from the first observed value. The previous `current - 10` default
could produce false low-temperature alerts after a server cooled down.

High temperature guessing remains unchanged. Explicit hardware limits and
user-customized limits are preserved. During later discovery, an old automatic
temperature pair is removed only when all of the following are true:

- discovery does not supply a low threshold;
- the thresholds are not user-customized;
- no warning thresholds exist; and
- the stored high/low pair has the old algorithm's distinctive 30-degree gap.

## Reproducible container image

[`deploy/custom-image/Dockerfile`](deploy/custom-image/Dockerfile) layers the
forked API and discovery code on the official LibreNMS image pinned by digest.
It is used for the web, dispatcher, syslog, and SNMP trap containers while the
database, Redis, shared `/data` volume, and published ports remain external and
unchanged. Build and deployment details are in
[`deploy/custom-image/README.md`](deploy/custom-image/README.md).

## Verification

The fork includes API authorization/validation coverage and focused tests for
temperature limit guessing, legacy-limit migration, explicit device limits,
and user-customized limits. Production deployment should always be preceded by
a database backup and followed by targeted discovery before running discovery
for every affected device.

## Upstream contributions

Reusable changes are submitted to upstream as focused pull requests rather
than including the downstream container packaging in an unrelated change:

- [librenms/librenms#20354](https://github.com/librenms/librenms/pull/20354)
  removes unsafe guessed low-temperature limits and migrates legacy guesses.
- [librenms/librenms#20355](https://github.com/librenms/librenms/pull/20355)
  adds the permission-checked single and bulk sensor threshold API.
