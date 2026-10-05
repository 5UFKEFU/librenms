# Rule notification timing over the API — 2026-10-05

Source: `master` at `e027db4112`, on top of the deployed image `librenms-5uf:disk-groups-175e9fb1e8` (`175e9fb1e8`).

## Changes

`POST/PUT rules` accepts `operation_timing`: `{"max_notifications": <int ≥ 1 | null>, "step_duration_seconds": <int ≥ 0>}`. It sets every problem-phase segment of the rule's alert operation:

- `escalation_step_to = escalation_step_from + max - 1`; null means the segment keeps sending while the alert is active, and 1 means it sends once.
- `step_duration_seconds` is the gap between sends; 0 means the operation's default.

If the operation is shared with other rules, it is copied first (`<rule name> — API`, with the same segments and transports) and the copy is assigned to this rule, so the other rules keep their timing. A rule without an operation returns 400. The FebNMS app uses this for "Repeat notifications / Maximum sends / Repeat interval".

## Deployment

Overlay of `includes/html/api_functions.inc.php` (owned `1000:1000`, 644). Before building, the running file matched `175e9fb1e8` on both hosts. Only the web service was switched; no database migration. The dispatcher (alert runner) is unchanged.

| | FEB (`lnms.feb.sg`) | 5UF (`libernms.5ufkefu.com`) |
| --- | --- | --- |
| Image | `librenms-5uf:rule-timing-e027db4112` | `librenms-5uf:rule-timing-e027db4112` |
| Service | `web` in `/opt/librenms/compose.yaml` (line 47) | `librenms` in `/opt/librenms/compose.yml` (line 38) |
| Compose backup | `` | `/opt/librenms/compose.yml.bak-rule-timing-20261005112842` |

Verified on FEB before the deploy, inside a transaction that was rolled back: a rule sharing operation 1 got its own copy (two transport maps carried over) with `escalation_step_to = 3` and `step_duration_seconds = 600`, and operation 1 was unchanged; a second call on the now-unshared copy updated it in place (to = null, step = 0). Login 200 and no web log errors on both hosts after the switch.

## Rollback

No schema change. Restore the compose backup (image `librenms-5uf:disk-groups-175e9fb1e8`) and run `docker compose up -d --no-deps <service>`. Operations copied by this API stay assigned to their rules.
