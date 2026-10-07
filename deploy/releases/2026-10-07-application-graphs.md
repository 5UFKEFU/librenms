# Application graphs API and NVIDIA on sg1-pc01 — 2026-10-07

Source: `master` at `41bfa8f0c3`.

## Changes

- `GET devices/{hostname}/applications` lists a device's LibreNMS applications, each with the graph names it offers. The names are the `includes/html/graphs/application/<type>_*.inc.php` files.
- `GET devices/{hostname}/applications/{app_id}/graphs/{graph}` draws one of those graphs, `application_<type>_<graph>`. A graph name outside that list returns 404.
- The FebNMS app uses this to show NVIDIA GPU charts on the server overview.

## Deployment

The web and dispatcher overlays were rebuilt at `41bfa8f0c3` on both hosts:

- FEB: `svc-retries-41bfa8f0c3` (web) and `dispatcher-svc-retries-41bfa8f0c3` (dispatcher).
- 5UF: the same two tags.

Each host has a compose backup named `*.bak-svc-41bfa8f0c3`. Before building, the running `routes/api.php` and `includes/html/api_functions.inc.php` matched the previous commit. Logins returned 200 on both hosts; FEB answered 502 briefly while the web container restarted.

## sg1-pc01 (5UF device 29, RTX 5070 Ti)

- On the host, LibreNMS's agent script was installed as `/etc/snmp/nvidia` (it reads `nvidia-smi dmon`). The line `extend nvidia /etc/snmp/nvidia` was added to `snmpd.conf`, with a backup at `snmpd.conf.bak.nvidia.*`, and snmpd was restarted.
- Running `lnms device:discover 29 -m applications` created application 2 (`nvidia`). Polling now writes `app-nvidia-2-0.rrd`.
- Verified: the list returns app 2 with 16 graphs, `sm` draws an SVG, and an invalid graph name returns 404.

## Rollback

Restore the compose backups. On sg1-pc01, remove the extend line and the script, then restart snmpd. Delete application 2 in the web UI, under Device → Apps.
