# Combined NVIDIA graphs and video memory in GiB — 2026-10-09

Source: `master` at `ff8cb4b23e`.

## Changes

- New `application/nvidia_util.inc.php` draws GPU (`sm`) and memory bandwidth (`mem`) utilisation in one graph. Both are percentages.
- New `application/nvidia_codec.inc.php` draws video encoder (`enc`) and decoder (`dec`) utilisation in one graph.
- `nvidia-common.inc.php` accepts `$rrdVars` (rrd variable => legend label) to combine metrics. Graphs that set only `$rrdVar` draw as before.
- `nvidia_fb` shows video memory in GiB with one decimal place: `$divider = 1024`, and a new `$gprint_format` option in `generic_multi_line_exact_numbers.inc.php`, which defaults to the old `%8.0lf%s`.
- The applications API lists the two new graphs automatically. The FebNMS app (build 95) shows `util`, `fb`, `codec`, `temp`, `pwr` and `pclk`, and falls back to the separate graphs on servers without the combined ones.

## Deployment

The web and dispatcher overlays were rebuilt at `ff8cb4b23e` on both hosts: `svc-retries-ff8cb4b23e` and `dispatcher-svc-retries-ff8cb4b23e`. Each host has a compose backup named `*.bak-svc-ff8cb4b23e`. Logins returned 200, and the new graphs render for sg1-pc01 on 5UF.

The first 5UF build attempt used an incomplete overlay bundle. The build script failed before compose was touched, so the running containers were unaffected. The bundle was rebuilt with all 18 files before the images were switched.

## Rollback

Switch both compose files back to the `*-55113b27fe` tags and recreate the containers.
