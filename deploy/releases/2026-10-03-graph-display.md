# Graph display options — 2026-10-03

Source: `master` at `94b6ab7494` (on top of the deployed `24e4996ab6` / image `librenms-5uf:upstream-e490aaea11`, ID `sha256:f5d40ee0…`).

## Changes

Optional graph API parameters; requests without them render as before.

- `timezone` (IANA name): API requests have no web session, so rrdtool labelled the time axis in the container's zone. The value sets `TZ` for the rrdtool process.
- `font_size` (6–16): larger rrdtool text for clients drawing large graphs.
- `traffic_style=cacti` (`device_bits`, `port_bits`): total of all interfaces, inbound as a green area `#00CF00`, outbound as a blue line `#002A97`, same axis, Now/Ave/Max/95th legend.

## Deployment

Overlay image built on each host from the six changed files (`LibreNMS/Data/Graphing/GraphParameters.php`, `LibreNMS/RRD/RrdProcess.php`, `includes/html/api_functions.inc.php`, `includes/html/graphs/{device,port}/bits.inc.php`, `includes/html/graphs/generic_cacti_traffic.inc.php`), owned `1000:1000`, files 644, directories unchanged (755). Before building, the five existing files in each running container matched `24e4996ab6` byte for byte.

Only the web service was switched; dispatcher and sidecars keep `librenms-5uf:upstream-e490aaea11`. No database migration.

| | FEB (`lnms.feb.sg`) | 5UF (`libernms.5ufkefu.com`) |
| --- | --- | --- |
| Image | `librenms-5uf:graph-display-94b6ab7494` | `librenms-5uf:graph-display-94b6ab7494` |
| Service | `web` in `/opt/librenms/compose.yaml` | `librenms` in `/opt/librenms/compose.yml` |
| Compose backup | `/opt/librenms/compose.yaml.bak-graph-display-20261003180202` | `/opt/librenms/compose.yml.bak-graph-display-20261003100813` |
| Build context | `/tmp/graph-display-42a2d8c68b` | `/opt/librenms/releases/graph-display-94b6ab7494` |

Verified after switching: login page 200 (internal and public), no errors in web logs, default and Cacti-style `device_bits` SVGs render, the requested time zone shifts the axis labels, and the Cacti legend shows real current totals.

## Rollback

No schema change, so restoring the previous image is enough: put the compose backup back (or set the web service image to `librenms-5uf:upstream-e490aaea11`) and run `docker compose up -d --no-deps <service>`.
