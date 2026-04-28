# Process Content

Process HTML/XML content through the CMS content parser from the command line.

## Usage

```bash
bin/zolinga process:content [options]
```

## Options

- `--input=<file>` — Path to the input HTML/XML file. If omitted, reads from `stdin`.
- `--output=<file>` — Path to write the processed output. If omitted, prints to `stdout`.
- `--url=<url>` — Fake URL to use as the current request URL. Affects path resolution. Defaults to `$api->config['baseURL']`.

## How It Works

1. Reads input HTML/XML from `--input` or `stdin`.
2. Resolves the request path from `--url` or falls back to `baseURL` config.
3. Sets up `$_SERVER` globals (`REQUEST_URI`, `PATH_INFO`, `HTTP_HOST`, `REMOTE_ADDR`, etc.) to mimic a web request.
4. Loads the input into a `DOMDocument` and runs it through `$api->cmsParser->parse()`.
5. All custom CMS content tags (e.g. `<markdown-to-html>`, `<html-to-markdown>`, `<random-chooser>`, etc.) are expanded.
6. Outputs the resulting HTML to `stdout` or writes it to `--output`.

## Examples

Process a file and print to stdout:

```bash
bin/zolinga process:content --input=page.html --url=/test/page
```

Pipe content via stdin:

```bash
cat page.html | bin/zolinga process:content --url=/test/page
```

Write result to a file:

```bash
bin/zolinga process:content --input=page.html --output=result.html
```

Process with default baseURL:

```bash
bin/zolinga process:content --input=page.html
```

## Notes

- Only the CMS content parser runs; page serving, wiki, and other `system:content` handlers are **not** invoked.
- Custom CMS content tags (elements with a dash in their name) are expanded during processing.
- The `--url` value affects path-based routing and analytics tracking.
- Use `Zolinga\System\IS_CLI` to detect CLI mode in your own handlers.
