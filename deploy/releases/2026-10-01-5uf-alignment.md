# 5UF deployment alignment — 2026-10-01

## 接手速查（2026-10-01 核实）

本次任务已完成：5UF 缺少首页云端保存接口，已将其四个 LibreNMS 应用容器升级为与 `lnms.feb.sg` 相同的镜像，并完成真实账户保存与采集验证。以下是当日部署快照，后续操作前请重新检查容器状态和镜像 ID。

### 服务器与连接方式

| 项目 | 5UF / 无忧府 | FEB LibreNMS |
| --- | --- | --- |
| 网站 | `https://libernms.5ufkefu.com`（注意拼写为 libernms） | `https://lnms.feb.sg` |
| Cloudflare DNS 源站 A 记录 | `172.237.80.65` | `15.204.167.155` |
| 实际应用主机 | `172.237.80.65`，系统 hostname 为 `localhost` | Jump 资产 `usa7-ops-librenms`，内网 `192.168.101.200` |
| 登录方式 | `ssh -p22 root@172.237.80.65` | 经下面的 JumpServer，以资产账户 `ops` 登录 |
| 部署目录 | `/opt/librenms` | `/opt/librenms` |
| Compose 文件 | `/opt/librenms/compose.yml` | `/opt/librenms/compose.yaml` |
| 应用共享数据 | `/opt/librenms/librenms` → `/data` | `/opt/librenms/data` → `/data` |
| 数据库目录 | `/opt/librenms/db` | `/opt/librenms/db` |
| 额外 RRD 存储 | 现有共享数据目录内，沿用原挂载 | `/opt/librenms/rrd/db` 和 `/opt/librenms/rrd/journal`，由 rrdcached 使用 |

两个域名都开启了 Cloudflare 代理，公共 DNS 查询返回的是 Cloudflare 地址。表中的源站值通过 Cloudflare DNS API 核实；FEB 应用主机的 `base_url` 实测为 `https://lnms.feb.sg/`。公网入口到内网主机之间的具体转发配置本次未展开检查。

JumpServer 入口（用户本次提供且已成功连接）：

```sh
ssh -p60022 jeff@15.204.167.155
# 交互菜单输入 /libre，选择 usa7-ops-librenms。

# 已验证可用于非交互命令的资产直连形式：
ssh -p60022 'jeff@ops@192.168.101.200@15.204.167.155' 'hostname'
```

复用现有 SSH 凭据。`ops` 在该资产上可使用 `sudo -n docker ...`。5UF 直连必须明确指定 `-p22`，本机默认 SSH 端口曾导致错误连接。旧记录中的其他 Jump 入口在本次检查中不可用。香港 `hk3-bt01` 上仍有此域名的旧代理配置，但不是本次确认的当前 5UF 源站，不要据此更新旧机器。

### 仓库和镜像来源

- 服务端仓库：`git@github.com:5UFKEFU/librenms.git`，默认分支为 `master`（不是 `main`）。
- 本次使用已有干净检出 `/Users/jeffcheng/developer/librenms-master`，先快进到远端，再提交部署文档。
- `/Users/jeffcheng/developer/librenms` 是另一个分支检出，检查时有未提交的实时指标改动，不能直接覆盖、重置或拿来当发布基线。
- App 仓库位于 `/Users/jeffcheng/developer/FebLiberNMS`；本次没有修改 App，也不需要重新上传 TestFlight。
- 本次直接传输了 FEB 已运行的完整镜像，没有使用 `deploy/custom-image/Dockerfile` 重新构建。该 Dockerfile 是历史增量打包方式，不能假设它会重现此完整镜像或包含全部最新功能。
- FEB 主机上观察到 `/tmp/librenms-5uf-741bc818ad-build/Dockerfile` 与 `/tmp/librenms-upstream-e490aaea11-build.log`，仅作为构建排查线索；临时路径不保证长期保留，未来重建需重新核实源码、依赖和构建上下文。

### 两端容器清单

5UF 共 7 个：`librenms`、`librenms_dispatcher`、`librenms_syslogng`、`librenms_snmptrapd`、`librenms_db`、`librenms_redis`、`librenms_msmtpd`。Compose 服务名分别为 `librenms`、`dispatcher`、`syslogng`、`snmptrapd`、`db`、`redis`、`msmtpd`。

FEB 共 5 个：`librenms-web-1`、`librenms-dispatcher-1`、`librenms-db-1`、`librenms-redis-1`、`librenms-rrdcached-1`。两端应用镜像相同，不代表容器拓扑、配置或数据库内容相同。

5UF 保留的基础服务镜像为 `mariadb:10`、`redis:7.2-alpine`、`crazymax/msmtpd:latest`；FEB 当时为 `mariadb:10.11`、`redis:7.2-alpine`、`crazymax/rrdcached:latest`。此次没有升级这些基础服务。

### 快速只读检查

```sh
# 5UF：检查运行状态、镜像和版本。
ssh -p22 root@172.237.80.65 \
  'docker ps --format "{{.Names}}\t{{.Image}}\t{{.Status}}"; docker exec -u librenms librenms php /opt/librenms/lnms --version'

# 5UF：确认首页读取和保存路由均存在。
ssh -p22 root@172.237.80.65 \
  'docker exec -u librenms librenms php /opt/librenms/artisan route:list --path=mobile-dashboard --no-ansi'

# FEB：检查参考容器镜像。
ssh -p60022 'jeff@ops@192.168.101.200@15.204.167.155' \
  'sudo -n docker ps --format "{{.Names}}\t{{.Image}}\t{{.Status}}"'
```

首页接口是 `GET` / `PUT /api/v0/me/mobile-dashboard`，需当前用户的 API Token。未登录时两端都可能返回 401，不能据此证明接口存在。本次使用已有凭据实测：升级前 5UF 返回 404，升级后读取、保存原布局、再读取一致。凭据只从已有安全存储读取，不打印、不写入本文件，不把私有数据库备份或环境文件提交到仓库。

### 本次切换顺序与交接边界

1. 检查源镜像与仓库关键文件哈希；通过 Jump 导出镜像，在 5UF 导入并核对完整 image ID。
2. 备份配置和数据库；停止四个应用容器后再做最终数据库备份。
3. 仅替换 Compose 中四个应用服务的镜像，先启动 Web，等待数据库迁移成功。
4. 用真实 API 读取并原样保存已有首页，确认设备列表，再启动三个后台容器。
5. 核对镜像一致、零重启、后台实际完成采集，以及实时指标与历史图表可用。

本次没有修改 FEB 线上部署、两端 DNS、数据库/Redis 镜像或 App 源码。后续如再升级，应保留当前数据、配置和镜像，并再次验证实际采集与首页持久化。数据库迁移后的回退步骤见本文末尾，不能只改回旧镜像。

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
