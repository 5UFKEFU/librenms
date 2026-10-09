# Service check diagnostics — 2026-10-09

Source: `master` at `e19a0c0545`.

## Changes

- `service_diagnostics()` in `includes/services.inc.php` describes what a check reached, so a client can confirm it tested the intended machine. It returns:
  - the check command, with `-a`, `--password` and the MySQL/PostgreSQL `-p` masked;
  - DNS answers for the target and the virtual host;
  - for `check_http`/`check_curl`, a `curl -v` run against the same address (`--connect-to` when `-I` is set), filtered to the resolved and established connection, TLS certificate and response headers;
  - for TCP services, the address and port that answered and the local source address.
- `POST service-checks` (test) and `POST services/{id}/check` (check now) add a `diagnostics` array of lines. Clients that don't read it are unaffected.
- The FebNMS app (build 96) shows the connected address ("实际连接到") and the details under each test or check result.

## Deployment

The web and dispatcher overlays were rebuilt on both hosts, first at `c40b927099`, then at `e19a0c0545` to keep curl 8.22's "Established connection" line. The current tags are `svc-retries-e19a0c0545` and `dispatcher-svc-retries-e19a0c0545`, and the compose backups are named `*.bak-svc-<commit>`. Logins returned 200. A check of `www.5ufclub.com` with `-I 129.226.164.53` reported a connection to 129.226.164.53:443.

## Rollback

Switch both compose files back to the `*-ff8cb4b23e` tags and recreate the containers.
