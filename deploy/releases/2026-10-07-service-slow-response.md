# Slow-response tracking for services — 2026-10-07

Source: `master` at `0b1e9fa05a`, on top of `svc-retries-0b39d03e63`.

## Changes

Migration `2026_10_07_120000_add_slow_response_to_services_table` adds these columns to `services`:

- `service_slow_after`: seconds; null means off.
- `service_slow_count`: consecutive slow checks so far.
- `service_slow`: whether the service is currently slow.
- `service_response_time`: the latest measured response time, in seconds.

`poll_service()` reads the check's response time from its perfdata (`time`, or a ping's `rta`, normalised to seconds) and stores it.

A successful check slower than `service_slow_after` increments `service_slow_count`. After `service_retries` slow checks in a row, `service_slow` becomes 1. `service_status` is left as the plugin reported it, so a slow service still counts as up and global service-down rules don't fire.

Changes of the slow flag are written to the eventlog and re-run that device's alert rules. The "Check now" API also returns `service_slow` and `service_response_time`.

The FebNMS app creates one rule per server for this, with builder `services.service_slow = 1` scoped to that device.

## Deployment

Both hosts were deployed with the same steps as `2026-10-06-service-interval-retries`: the services table was backed up, the web container was switched, the migration was run, then the dispatcher was switched.

| | FEB | 5UF |
| --- | --- | --- |
| Web | `svc-retries-0b1e9fa05a` | `svc-retries-0b1e9fa05a` |
| Poller | `dispatcher-svc-retries-0b1e9fa05a` | `dispatcher-svc-retries-0b1e9fa05a` |
| Services backup | `/tmp/services-before-slow-20261007134204.sql` | `/root/db-backups/services-before-slow-20261007134011.sql` |
| Compose backup | `compose.yaml.bak-svc-0b1e9fa05a` | `compose.yml.bak-svc-0b1e9fa05a` |

Login returned 200 on both hosts.

## Verification

All of these ran on 5UF inside rolled-back transactions:

- **Slow flag:** service 10 was given a tiny threshold and `service_retries=2`. It gave slow_count 1 then 2; `service_slow` became 1 on the second check; status stayed 0.
- **Rule matching:** the rule query `SELECT … AND services.service_slow = 1` matched device 35 only once the flag was set.
- **Rule creation:** `add_edit_rule` accepted the app's exact payload. The rule was scoped to device 35 and generated the expected query.

## Rollback

Restore the compose backups and recreate web and dispatcher. The new columns are nullable or defaulted, so older code ignores them.
