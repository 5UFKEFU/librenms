# NVIDIA graph colours — 2026-10-09

Source: `master` at `55113b27fe`.

## Changes

- `includes/html/graphs/application/nvidia-common.inc.php` gives each NVIDIA metric its own colour. The previous graphs all used the shared "greens" palette. A second GPU gets a darker shade of the same colour.
- `generic_multi_line_exact_numbers.inc.php` draws a translucent `AREA` under a 1.5 px line when a series sets `'area' => true`. Other graphs keep the original `LINE2`.
- Graphs are still time-series lines. Bar and pie charts were considered and rejected: rrdtool data is a time series, and a pie has no time axis.

## Deployment

The web and dispatcher overlays were rebuilt at `55113b27fe` on both hosts:

- FEB: `svc-retries-55113b27fe` (web) and `dispatcher-svc-retries-55113b27fe` (dispatcher).
- 5UF: the same two tags.

Logins returned 200 on both hosts. The FebNMS app (build 94) shows the coloured charts on sg1-pc01.

## Rollback

Switch both compose files back to the `*-41bfa8f0c3` tags and recreate the containers.
