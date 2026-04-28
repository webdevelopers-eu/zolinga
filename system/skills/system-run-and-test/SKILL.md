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

1. Discover CLI capabilities with `bin/zolinga --help`.
2. Trigger CLI-origin events with `bin/zolinga <event-name> <params>`.
3. Execute ad hoc scripts with `bin/zolinga --execute=\"<path>\"`.
4. Evaluate short snippets with `bin/zolinga --eval=\"<php-code>\"`.
5. Start local web server with `bin/zolinga --server=<host>:<port>` and optional `--xdebug`.
6. Use `config/local.json` then `config/global.json` (`baseURL`) to determine front-end URL defaults.
7. Create a new module with `bin/zolinga skeleton:module --name=<module-name>`.
8. Generate Apache config with `bin/zolinga skeleton:apache ...` (see `system/wiki/ref/event/skeleton/apache.md`).
9. Process HTML/XML content through the CMS pipeline from CLI with `bin/zolinga process:content --input=<file> --url=/test/page` (see `system/wiki/ref/event/process/content.md`).
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
