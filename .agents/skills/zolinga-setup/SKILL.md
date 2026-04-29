---
name: zolinga-setup
description: Use when setting up a basic Zolinga installation, checking prerequisites, fixing writable-path problems, configuring the local URL, and starting the framework for the first time.
argument-hint: "[host:port or setup goal]"
---

# Zolinga Setup

## Use When

- Preparing a fresh Zolinga checkout to run locally.
- Fixing first-run problems such as missing permissions or wrong base URL.
- Starting the built-in server or wiring the project into Apache.
- Verifying that the framework bootstraps correctly before working on modules.

## Workflow

1. Confirm the PHP version is at least 8.2.
2. Make sure these paths are writable by the user running PHP: `/.agents/skills/`, `/data/`, `/public/data/`, and `/public/dist/`.
3. Check `config/local.json` first, then `config/global.json`, for the effective `baseURL`.
4. Use `bin/zolinga --help` to confirm the CLI entrypoint is working.
5. Start the local server with `bin/zolinga --server=<host>:<port>`.
6. If you use Apache instead, generate an example config with `bin/zolinga skeleton:apache ...` and serve the `public/` directory.
7. Open the local site and then visit `/wiki/` to confirm the framework booted and documentation is available.
8. If startup fails, check writable-path errors first, then review `data/system/logs/messages.log`.
9. The framework by itself does nothing much you need modules installed to see real behavior. Use `bin/zolinga install --list` to see available modules and `bin/zolinga install --module=<module-name>` to install them.

## Quick Checks

- `bin/zolinga` runs without fatal bootstrap errors.
- `public/dist/` and `public/data/` are created and writable.
- Module manifests load correctly and the local wiki opens.
- The configured URL matches the way the project is actually being served.

## Troubleshooting

- If bootstrap reports permission errors, fix ownership or write access for the required directories.
- If the site opens on the wrong host or port, re-check `baseURL` and how the server was started.
- If module assets are missing, run `bin/zolinga` (no parameters) to trigger the install/update flow so `public/dist/{module}` symlinks are refreshed.
- If a module behaves as if it is not loaded, inspect `data/system/system.cache.json` and the module `zolinga.json` file.

## References

- `README.md`
- `system/wiki/Zolinga Core/Running the System.md`
- `system/wiki/Zolinga Core/Running the System/Command Line.md`
- `system/wiki/Zolinga Core/Module Installation and Updates.md`
- `system/wiki/ref/event/skeleton/apache.md`
- `system/src/Loader/Bootstrap.php`
