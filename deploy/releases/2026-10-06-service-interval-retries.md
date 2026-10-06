# Per-service interval, alert after N failures, port_listen — 2026-10-06

Source: `master` at `0b39d03e63` (includes `9372f7c766`, `cb970a3cd7`, `d721bc0e53`).

## Changes

- Migration `2026_10_06_090000_add_interval_and_retries_to_services_table`: `services.service_interval` (seconds, null = 300), `service_retries` (default 1), `service_fail_count` (default 0).
- `check-services.php` skips a service until its interval has passed since `service_checked`. `poll_service()` keeps a failing service OK until `service_retries` checks in a row fail, prefixing the message with `[soft n/N]`, so alerts fire only after consecutive failures.
- New built-in check `port_listen` (`tcp|udp <port>`): reads TCP-MIB `tcpListenerTable` / UDP-MIB `udpEndpointTable` (fallback `udpTable`) over the device's SNMP. Covers UDP and ports the poller can't reach.
- `snmp_extend` and `port_listen` run their scripts with `PHP_BINDIR/php`. Under PHP-FPM, `PHP_BINARY` is php-fpm and PATH is cleared, so before `0b39d03e63` "Check now"/"Test" for these types printed php-fpm usage. Scheduled checks were not affected.
- `service_services_frequency` set to 60 on both hosts so 1–4 minute intervals work. Services without an interval still run every 5 minutes; checked on 5UF, ages kept growing past 75 s with no recheck.

## Deployment

| | FEB | 5UF |
| --- | --- | --- |
| Web | `librenms-5uf:svc-retries-0b39d03e63` (`web`) | `librenms-5uf:svc-retries-0b39d03e63` (`librenms`) |
| Poller | `librenms-5uf:dispatcher-svc-retries-0b39d03e63` (`dispatcher`) | same tag (`dispatcher`) |
| Services table backup | `/tmp/services-before-svc-retries-20261006091223.sql` | `/root/db-backups/services-before-svc-retries-20261006090925.sql` |
| Compose backups | `compose.yaml.bak-svc-retries-*`, `.bak-svc-0b39d03e63` | `compose.yml.bak-svc-retries-*`, `.bak-svc-retries2-*`, `.bak-svc-0b39d03e63` |

The containers applied the migration on start. Poller images are `upstream-e490aaea11` plus only the service files; that image's copies of them matched the fork base before the overlay.

Earlier the same day, 5UF's dispatcher still ran the stock image, so the new `snmp_extend` service 18 failed with status 127 on scheduled runs until the poller overlay; it has passed since.

Verified: a rolled-back transaction on 5UF with `service_retries=3` and a failing `port_listen tcp 3307` gave OK `[soft 1/3]`, OK `[soft 2/3]`, then CRITICAL. In the app, the "Test" for `port_listen udp 53` returned OK through the API. Login 200 on both hosts.

## Rollback

Restore the compose backups and `docker compose up -d --no-deps <web> dispatcher`. The new columns are nullable/defaulted and harmless to older code; drop them with the migration's `down()` if needed. Reset `service_services_frequency` to 300 (`lnms config:set service_services_frequency 300`).
