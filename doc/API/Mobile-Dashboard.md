# Mobile dashboard

`GET /api/v0/me/mobile-dashboard` returns `{ "status": "ok", "layout": null }` or the current API user's saved layout.

`PUT /api/v0/me/mobile-dashboard` accepts `{ "layout": { "version": 4, "modules": [...] } }` and returns the saved layout. The API uses the existing X-Auth-Token authentication; it needs no webpage session or CSRF cookie. Requests cannot specify another user. Each module must have a unique UUID `id` and a string `kind`; configuration fields are retained as data. Empty module arrays are valid. Requests over 64 KiB, encoded widget settings over 64,000 bytes, versions outside 1–4, invalid module IDs and more than 100 modules are rejected without overwriting existing data.

Storage reuses the authenticated user's own `LibreNMS Mobile` dashboard and configuration Notes widget. Shared dashboards owned by another user are never read or changed. New dashboards are private. GET also reads legacy FEBLIBRENMS_MOBILE_V1 configurations, and PUT keeps that representation readable by older iOS clients. Other widgets and dashboards are preserved. User-row locking serializes simultaneous saves without duplicate widgets. No schema migration is required.
