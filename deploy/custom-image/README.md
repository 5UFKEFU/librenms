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

Service polling records `service_checked` after every plugin execution. The
services API also returns `service_check_interval`, allowing clients to show
the real last check time and the configured next-check estimate instead of
substituting the device poll timestamp.

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

## Traffic graph display preference

The image also includes the personal **Show incoming and outgoing traffic on the
same axis** switch in Preferences (English, Simplified Chinese and Traditional
Chinese). Its default is the existing global graph setting, normally off.
No database migration is required; it uses the existing user preference table.

For an existing installation with additional downstream patches, build a small
layer over its currently deployed image and copy only the changed graph,
preference controller, view and translation files. Keep the old image and a
backup of the Compose file, replace only the web service image, and run
`docker compose up -d --no-deps librenms`. This preserves the separate polling,
database and Redis services. Clear compiled views if applying files to an
already running web container. Verify both switch states and persistence after
reload before accepting the release.

When updating the graph API on an older customized image, retain the personal
preference UI too: `UserPreferencesController`, `user/preferences.blade.php`,
the three preference translations, and the shared graph renderers must ship
together. Verify the Preferences page renders its switch, both values persist,
and the default graph request follows that user's choice. API clients may
explicitly pass `traffic_same_axis=0` to retain the legacy mirrored rendering
regardless of the website preference. Same-axis multi-interface graphs use
separate interface colors, solid incoming strokes, and dashed outgoing strokes.

## Physical disk history

For `device_diskio_bits` and `device_diskio_ops`, API clients may request
`disk_scope=physical`. The response acknowledges it with
`X-Disk-Scope: physical`. Omit the parameter (or use `all`) to retain
the original graph. Each retained device has separate Read and Write labels.

The filter uses SNMP block-device names: whole SATA/SCSI, NVMe namespaces,
virtio/Xen guest disks, MMC and BSD whole disks are kept; partitions, md/dm,
bcache, loop and zram are excluded. It does not infer physical hardware behind
a RAID controller or hypervisor, and unknown device-name formats are excluded.
No RRD data, polling configuration, or database schema is changed.
