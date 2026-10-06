# SNMP self-check service type — 2026-10-06

Source: `master` at `54e45e8dbd`, on top of `librenms-5uf:service-check-0c95f8bfc3` (`0c95f8bfc3`).

## Changes

New service type `snmp_extend`. Its parameter is the name of an `extend` in the device's `snmpd.conf`; `scripts/check-snmp-extend.php` reads `nsExtendResult` and `nsExtendOutputFull` over the device's own SNMP settings and exits with the extend's status. A device can then check something that is only reachable locally, without opening it to the poller or storing credentials in the check. `LibreNMS\Services::list()` includes the type so the API accepts it.

## Deployment

| | FEB (`lnms.feb.sg`) | 5UF (`libernms.5ufkefu.com`) |
| --- | --- | --- |
| Web image | `librenms-5uf:snmp-extend-54e45e8dbd` (service `web`) | `librenms-5uf:snmp-extend-54e45e8dbd` (service `librenms`) |
| Poller image | `librenms-5uf:dispatcher-snmp-extend-54e45e8dbd` (service `dispatcher`; `upstream-e490aaea11` plus only the two new check files) | same container as web |
| Compose backup | `/opt/librenms/compose.yaml.bak-snmp-extend-20261006084618` | `/opt/librenms/compose.yml.bak-snmp-extend-20261006084501` |

No database migration. Login 200 on both hosts; the FEB dispatcher resumed polling.

## First use: MySQL on sg1-febcloud01 (5UF device 35)

MySQL there listens on 3306 but is firewalled from the poller, and no password is used:

- `/etc/snmp/mysql_alive` runs `mysqladmin --no-defaults ping` against 127.0.0.1 as a non-existent user. MySQL answers the handshake before authentication, so "Access denied" still proves it is up (`mysqladmin` exits 0); a dead server exits non-zero and the script reports CRITICAL.
- `extend mysql_alive /etc/snmp/mysql_alive` appended to `/etc/snmp/snmpd.conf` (backup `snmpd.conf.bak.mysql-alive.*`), snmpd restarted.
- 5UF service 18 "MySQL" (`snmp_extend`, param `mysql_alive`) returns `MYSQL OK - server is answering on 127.0.0.1:3306`.

The old service 13 (type `mysql`, target 127.0.0.1, disabled) is left in place.

## Rollback

Restore the compose backups and `docker compose up -d --no-deps <services>`. On sg1-febcloud01, remove the extend line and `/etc/snmp/mysql_alive`, then restart snmpd.
