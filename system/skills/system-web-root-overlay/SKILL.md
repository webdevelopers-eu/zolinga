---
name: system-web-root-overlay
description: Use when placing static files (favicon, robots, verification files, etc.) into the virtual web-root overlay so they are served from root URLs without modifying public/.
argument-hint: "<root-relative-path> [source-file]"
---

# Zolinga Web Root Overlay

## Use When

- You need a static file at a root URL such as `/favicon.ico`, `/robots.txt`, `/.well-known/...`, or `/apple-touch-icon.png`.
- You want to avoid modifying git-controlled files directly in `./public`.
- You want root-level static assets to live under `./public/data/system` for easier backup and environment-specific overrides.

## Workflow

1. Place the file in `./public/data/system/root/<path-from-web-root>`.
2. Keep files static (no PHP). Treat this overlay as public static content.
3. Verify the generated Apache vhost includes the `data/system/root` rewrite rule (from `skeleton:apache`).
4. Request the root URL in browser or with `curl` to confirm it is served.
5. For sensitive files, prefer `./data/...` (private path), not this public overlay.

## Examples

- `./public/data/system/root/favicon.ico` -> `/favicon.ico`
- `./public/data/system/root/robots.txt` -> `/robots.txt`
- `./public/data/system/root/.well-known/apple-developer-merchantid-domain-association` -> `/.well-known/apple-developer-merchantid-domain-association`

## Notes

- Dot-files are denied by Apache except `/.well-known` in the generated template.
- When rewrite routing reaches `public/index.php`, it also has a safe fallback for serving overlay files by path with traversal/hidden-file checks.
- Do not create manual symlinks for Agent Skills in `.agents/skills`. Skill links are regenerated automatically when manifests are reloaded; if needed, bump a module version in `zolinga.json` and run `bin/zolinga` (no parameters) to trigger rescan and symlink regeneration.

## References

- `system/wiki/Zolinga Core/Running the System/Page Request.md`
- `system/wiki/ref/event/skeleton/apache.md`
- `system/skeletons/apache/vhost.conf`
- `public/index.php`
