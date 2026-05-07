---
name: system-run-and-test
description: Use when running Zolinga locally, triggering CLI-origin events, evaluating scripts, and finding the correct run/test command workflow.
argument-hint: "[event-name] [params]"
---

# Zolinga Run and Test

## Use When

- Running local development server or CLI events.
- Executing scripts for diagnostics.
- Validating behavior after code changes.

## Workflow

1. **Always use `bin/zolinga`** — never run `php -f` or other direct PHP invocations. The `bin/zolinga` wrapper sets up the full framework environment (autoloading, config, services, error handling). Direct `php -f` calls will miss framework initialization and fail.
2. Discover CLI capabilities with `bin/zolinga --help`.
3. Trigger CLI-origin events with `bin/zolinga <event-name> <params>`.
4. Execute ad hoc scripts with `bin/zolinga --execute=\"<path>\"`.
5. Evaluate short snippets with `bin/zolinga --eval=\"<php-code>\"`.
6. Start local web server with `bin/zolinga --server[=[HOST:]PORT]` and optional `--xdebug`. Default is `0.0.0.0:8888`. Omit the argument to use the default, e.g. `bin/zolinga --server`. See `bin/zolinga --help` for details.
7. Use `config/local.json` then `config/global.json` (`baseURL`) to determine front-end URL defaults.
8. Create a new module with `bin/zolinga skeleton:module --name=<module-name>`.
9. Generate Apache config with `bin/zolinga skeleton:apache ...` (see `system/wiki/ref/event/skeleton/apache.md`).
10. Process HTML/XML content through the CMS pipeline from CLI with `bin/zolinga process:content --input=<file> --url=/test/page` (see `system/wiki/ref/event/process/content.md`).

## Temporary Scripts

Ad-hoc temporary, debugging, or testing scripts must follow these placement rules:

- **Default:** `./tmp/ai-*.*` — always use the `ai-` prefix (e.g. `ai-test-db.php`, `ai-check-urls.php`).
- **Data folder:** `./data/system/tmp/ai-*.*`
- **Public data folder:** `./public/data/system/tmp/ai-*.*`
- **Dist folder:** `./public/dist/system/tmp/ai-*.*`
- **Public URL access:** `./public/tmp/ai-*.*` — for scripts that must be reachable via HTTP.

Create the `tmp/` directory if it does not exist. Always clean up temporary scripts after use.

## Stdout vs Stderr

`bin/zolinga` writes system logs, warnings, and debug messages to **stderr**, while the actual event response (JSON) and any listener `echo` output go to **stdout**. This lets you separate structured output from diagnostic noise using shell redirection:

```bash
# Capture only the JSON response
bin/zolinga my:event > response.json

# Capture response and suppress logs
bin/zolinga my:event > response.json 2> /dev/null

# Capture response and logs separately
bin/zolinga my:event > response.json 2> debug.log

# Pipe response to another tool while still seeing logs in terminal
bin/zolinga my:event | jq '.response.data'
```
## Runtime Constants (defined in `system/define.php`)

| Constant | Value | Description |
|----------|-------|-------------|
| `Zolinga\System\IS_CLI` | `PHP_SAPI === 'cli'` | `true` when running from command line, `false` during web requests. Use to branch CLI-only logic (e.g. skip output buffering, avoid `exit()` in web context). |
| `Zolinga\System\IS_INTERACTIVE` | `IS_CLI && posix_isatty(STDOUT)` or env `INTERACTIVE` | `true` when running in an interactive terminal (TTY) or when `INTERACTIVE=1` is set. Use to decide whether to show progress bars, prompts, or colored output. |

## Documentation Abstract

- Begin with `Running the System.md` for execution modes.
- Use command-line and custom script docs for CLI workflows.
- Use request and AJAX pages for runtime behavior checks after deployment.

## References

- `system/wiki/Zolinga Core/Running the System.md`
- `system/wiki/Zolinga Core/Running the System/Command Line.md`
- `system/wiki/Zolinga Core/Running the System/Custom Scripts.md`
- `system/wiki/Zolinga Core/Running the System/Page Request.md`
- `system/wiki/Zolinga Core/Running the System/AJAX.md`
- `system/wiki/ref/event/skeleton/apache.md`
- `system/wiki/templates/Running the System.md`
- `system/wiki/Zolinga Core/Installing Additional Modules.md`
