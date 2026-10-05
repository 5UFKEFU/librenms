# Phone-width graph sizing — 2026-10-05

Source: `master` at `7e133c1981` (`128952c9d0` + `7e133c1981`), on top of the deployed image `librenms-5uf:alert-transports-bf9b6940eb`.

## Changes

Only graphs whose client sets `font_size` (the app always does) change:

- No 0.75 thumbnail zoom below 350 px. On a phone card the zoom shrank the whole image and legend, so the app could not crop the legend and drew the graph small and letterboxed.
- `--disable-rrdtool-tag`: the vertical "RRDTOOL / TOBI OETIKER" tag took width the plot can use.
- Below 450 px, short time labels: `HH:MM` for six-hour and day graphs, `MM/DD` for week graphs (every other label when very narrow). rrdtool's defaults overlapped ("SepThu").

## Deployment

Overlay of `LibreNMS/Data/Graphing/GraphParameters.php` (owned `1000:1000`, 644, SHA-256 prefix `fc05f4e3cac4ab82`). Before the first overlay the running file matched `26041b0e35` on both hosts. Built in two steps (`graph-sizing-128952c9d0`, then `graph-timegrid` on top); the running image is `librenms-5uf:graph-timegrid`. Only the web service was switched; no database migration.

| | FEB (`lnms.feb.sg`) | 5UF (`libernms.5ufkefu.com`) |
| --- | --- | --- |
| Image | `librenms-5uf:graph-timegrid` | `librenms-5uf:graph-timegrid` |
| Service | `web` in `/opt/librenms/compose.yaml` (line 47) | `librenms` in `/opt/librenms/compose.yml` (line 38) |
| Compose backup (before this release) | `/opt/librenms/compose.yaml.bak-graph-sizing-20261005001409` | `/opt/librenms/compose.yml.bak-graph-sizing-20261005001444` |

Verified: login 200 (internal and public), no web log errors; on FEB, phone-width Cacti `device_bits` graphs (271×160 for 7 days and 6 hours, 361×160 for a day) render at full scale without the tag, with readable time labels, and the app's legend parser finds the legend (three rows, plot height 198).

## Rollback

No schema change. Restore the compose backup (image `librenms-5uf:alert-transports-bf9b6940eb`) and run `docker compose up -d --no-deps <service>`.
