## Syntax

```bash
bin/zolinga install [--list] [--refresh] [--module=NAME ...]
```

## Description

Installs modules from the Zolinga repository using GIT. Supports listing available modules, refreshing repository data, and installing one or more modules with dependency resolution.

## Parameters

- `--list` — List available modules from the repository.
- `--refresh` — Refresh the cached repository data.
- `--module=NAME` — Module name(s) to install. Can be specified multiple times.
- `--help` — Display usage help.

## Examples

List available modules:

```bash
bin/zolinga install --list
```

Install a module:

```bash
bin/zolinga install --module=zolinga-cms
```

Refresh repository and install:

```bash
bin/zolinga install --refresh --module=zolinga-ai
```
