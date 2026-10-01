# 5UF deployment alignment — 2026-10-01

The 5UF deployment at `libernms.5ufkefu.com` now uses the same application image as `lnms.feb.sg`:

- LibreNMS version: `26.9.1`.
- Source revision: `24e4996ab631d7403f41bf43b86aefabff5159bc` (upstream through `e490aaea1150e7c879c6b22d1a04304f8a3687d6`).
- Image: `librenms-5uf:upstream-e490aaea11`.
- Image ID: `sha256:f5d40ee0ec83093738da1626260ca8bda27c80269583224e8cd572328ecec067`.

The exact image was exported from the reference deployment and imported on 5UF. The mobile dashboard controller, API routes, and Composer lockfile hashes matched the source revision before deployment.

## Container roles

All four application containers now use the image above:

| Container | Role |
| --- | --- |
| `librenms` | Web UI and API |
| `librenms_dispatcher` | Scheduled discovery, polling, service checks and alerting |
| `librenms_syslogng` | Syslog receiver |
| `librenms_snmptrapd` | SNMP trap receiver |

Redis, MariaDB and the mail relay retain their previous images. There are seven running containers in total. Persistent mounts and existing configuration were preserved.

Previously, Web used `librenms-custom:traffic-colors-20260925-v2`; the three sidecars used `librenms-custom:realtime-cache-20260824T021000Z`. The authenticated mobile dashboard endpoint returned HTTP 404 before the upgrade.

## Validation

- Mobile dashboard tests: 4 tests, 10 assertions passed.
- Traffic style, disk scope and metric service tests: 20 tests, 106 assertions passed.
- Database migrations completed successfully before starting the sidecars.
- Authenticated public dashboard GET → PUT of the existing layout → GET succeeded with identical layout content and four preserved modules.
- Public devices API returned 37 devices.
- Existing three dashboards, 50 widgets and 12 alert rules remained present.
- Public summary, realtime and graph-list endpoints passed for a monitored Linux device; traffic history returned a valid SVG.
- All four application containers were running with the same image ID and zero restarts.
- Dispatcher logs confirmed completed polling after the upgrade and the database's latest poll timestamp advanced.

## Recovery artifacts

On the 5UF host, `/opt/librenms/backups/aligned-20261001/` holds the database dump, shared configuration archive, original Compose/environment files and original image IDs. A final backup was taken after stopping the four application containers. These files contain private configuration and must remain on the host with restricted permissions.

`/opt/librenms/releases/aligned-20261001/` contains the imported image archive and deployment/check scripts. Original images were retained. A rollback must account for the database migrations, including API token migration; do not simply start old containers against the upgraded schema. Stop application containers, restore the matching database/configuration backup and original Compose file, then start and verify the old deployment.
