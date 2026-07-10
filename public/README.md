# /public

**Purpose**: the web server's document root — the only folder directly reachable by an HTTP client.

**Responsibilities**: will contain the Laravel front controller (`index.php`) and compiled/public frontend assets (CSS/JS build output, publicly served images) once the application skeleton is committed. Nothing else should ever be placed here — no source code, no configuration, no `.env`.

**Allowed dependencies**: none — this folder is an output/entry-point target, not something other code depends on.

**Future usage**: standard Laravel `public/` contents, plus module-published static assets (if any module needs to publish public assets, it does so via a documented publish step, not by writing directly into this folder from module source).
