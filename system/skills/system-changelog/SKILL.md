---
name: system-changelog
description: Use when creating or updating CHANGELOG.md files in Zolinga modules. Covers Keep a Changelog format, SemVer versioning, and the rule that every zolinga.json version bump must be accompanied by a CHANGELOG.md entry.
argument-hint: "<module-name> [change-type] [description]"
---

# Zolinga Changelog

## Use When

- Bumping the module version in `zolinga.json` — always update `CHANGELOG.md` too.
- Creating a new `CHANGELOG.md` for a module that doesn't have one yet.
- Documenting any notable change: feature, fix, removal, deprecation, or security patch.

## Core Rule

**Every `zolinga.json` version bump must be accompanied by a corresponding `CHANGELOG.md` entry.** If you change the `version` field, you must also add or update the changelog. No exceptions.

## File Location

- **Regular modules**: `modules/<module-name>/CHANGELOG.md`
- **System module** (`system/`): the framework's changelog lives at the **project root** as `CHANGELOG.md`, not inside `system/`. The `system/` pseudo-module is the Zolinga framework itself, so its changelog is the project-level changelog.

## Format: Keep a Changelog

Follow [Keep a Changelog](https://keepachangelog.com/en/1.0.0/). Key principles:

1. **Changelogs are for humans**, not machines.
2. **Every version gets an entry.** No version should be missing.
3. **Group changes by type.** Use these sections:
   - `Added` — new features
   - `Changed` — changes to existing functionality
   - `Deprecated` — features that will be removed in a future release
   - `Removed` — features removed in this release
   - `Fixed` — bug fixes
   - `Security` — vulnerability fixes
4. **Latest version comes first** (reverse chronological order).
5. **Dates use ISO 8601**: `YYYY-MM-DD`.
6. **Omit empty sections.** If a section has no entries, don't include it.
7. **Keep an `[Unreleased]` section** at the top to track upcoming changes. At release time, move it under a new version heading.

## Versioning: SemVer

Follow [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0/):

- Format: `MAJOR.MINOR.PATCH` (e.g. `1.2.3`)
- **PATCH** (`1.2.3` → `1.2.4`): backward-compatible bug fixes
- **MINOR** (`1.2.3` → `1.3.0`): new backward-compatible features; deprecations
- **MAJOR** (`1.2.3` → `2.0.0`): backward-incompatible changes
- Pre-release: append hyphen + identifiers (`1.0.0-alpha`, `1.0.0-alpha.1`)
- Build metadata: append plus + identifiers (`1.0.0+201303131447`)
- No leading zeroes in version numbers

### Zolinga Version Convention

Zolinga modules use a simplified `MAJOR.MINOR` version in `zolinga.json` (e.g. `"version": "1.3"`). Treat this as `MAJOR.MINOR.0` for SemVer purposes. A patch-level change still bumps the minor number in this scheme.

## Workflow

1. **Before bumping `zolinga.json` version**, check if the module has a `CHANGELOG.md`.
   - For `system/` module, check `CHANGELOG.md` at the project root.
   - For other modules, check `modules/<module-name>/CHANGELOG.md`.
2. **If no `CHANGELOG.md` exists**, create one using the boilerplate below.
3. **Add entries under `[Unreleased]`** (or directly under the new version if releasing now) in the appropriate section(s): `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`.
4. **If releasing**, replace `[Unreleased]` with the new version and date: `## [x.y.z] - YYYY-MM-DD`.
5. **Bump the version** in `zolinga.json`.

## Boilerplate: New CHANGELOG.md

```markdown
# Changelog

All notable changes to this module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0/).

## [Unreleased]

## [0.1.0] - YYYY-MM-DD

### Added
- Initial release.
```

## Example: Adding a Change

Given module `ipdefender` at version `1.3`, adding a new search filter:

```markdown
## [Unreleased]

### Added
- Search filter for trademark status on the results page.

## [1.3] - 2025-04-15
...
```

Then bump `zolinga.json`: `"version": "1.3"` → `"version": "1.4"`.

## Example: Releasing

When ready to release, move `[Unreleased]` entries to a versioned section:

```markdown
## [Unreleased]

## [1.4] - 2025-04-29

### Added
- Search filter for trademark status on the results page.

### Fixed
- Corrected date format in expiry notifications.

## [1.3] - 2025-04-15
...
```

## Anti-Patterns to Avoid

- **Don't dump git log diffs** into the changelog. Curate entries for human readability.
- **Don't leave empty sections.** Remove `### Deprecated` if nothing was deprecated.
- **Don't mix change types.** A bug fix goes under `Fixed`, not `Changed`.
- **Don't skip the date.** Always include `YYYY-MM-DD` on version headings.
- **Don't forget `[Unreleased]`.** Track in-progress changes there.

## References

- [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
- [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0/)
- `system-authoring-manifest` skill — for `zolinga.json` version bumping