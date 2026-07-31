---
name: system-check-modules
description: Use BEFORE building any new feature, page, or capability. The Zolinga core ships alone — official modules (CMS, DB, Intl, Commons, Cron, RMS, AI) provide most common features. Install them instead of hand-rolling a custom module. Always read README.md "Pre-Loaded Modules" first.
argument-hint: "<feature you are about to build>"
---

# Check Installable Modules Before Building

## Use When

- You are about to create a web page, content site, blog, or any HTML output.
- You are about to build a database layer, mapper, or migration system.
- You are about to add translations, localization, or multi-locale support.
- You are about to build auth, login, rights management, or user accounts.
- You are about to build scheduled jobs, cron tasks, or recurring workers.
- You are about to build common UI widgets, form helpers, or client-side utilities.
- You are about to wire an LLM / generative AI endpoint into the app.
- A fresh checkout has an empty or near-empty `modules/` directory.

## Principle

**The Zolinga core is intentionally minimal.** It does almost nothing on its own. Real capabilities come from official modules that you install with `bin/zolinga install`. Before writing a custom module to deliver a feature, check whether an official module already provides it. Installing the official module is almost always simpler, better supported, and auto-loads its own Agent Cartridge (skill) to teach you how to use it.

Do NOT hand-roll a custom module for: CMS pages, database access, translations, auth, cron, common widgets, or AI endpoints. Install the matching official module first.

## Workflow

1. Read the `README.md` section **"Pre-Loaded Modules & Cartridges"** — it lists the official modules and what each one does.
2. Check what is already installed:
   ```bash
   ls modules/
   ```
3. List modules available to install:
   ```bash
   bin/zolinga install --list
   ```
4. Install the official module(s) that cover the feature you need:
   ```bash
   bin/zolinga install --module=<module-name>[,<module-name>...]
   ```
5. Run `bin/zolinga` (no parameters) to apply the install and refresh symlinks.
6. After install, the module's Agent Cartridge (skill) appears in `.agents/skills/` — load and follow it instead of guessing the API.

## Official Module Cheat-Sheet

| Need | Module | Install command |
|------|--------|-----------------|
| Web pages, content tags, HTML site | `zolinga-cms` | `bin/zolinga install --module=zolinga-cms` |
| Database access (`$api->db`) | `zolinga-db` | `bin/zolinga install --module=zolinga-db` |
| Translations / multi-locale | `zolinga-intl` | `bin/zolinga install --module=zolinga-intl` |
| Auth, users, rights (`$api->user`) | `zolinga-rms` | `bin/zolinga install --module=zolinga-rms` |
| Scheduled jobs / cron | `zolinga-cron` | `bin/zolinga install --module=zolinga-cron` |
| Common widgets, URL/HTTP/currency helpers | `zolinga-commons` | `bin/zolinga install --module=zolinga-commons` |
| LLM / generative AI endpoints | `zolinga-ai` | `bin/zolinga install --module=zolinga-ai` |

## Common Mistake

A fresh `git clone` of the core has an empty `modules/` directory. An agent that ignores this will start generating a bespoke module (custom content handler, custom DB wrapper, custom i18n) — duplicating work that official modules already do, better. **Always install first, build only what official modules do not cover.**

## References

- `README.md` — section "Pre-Loaded Modules & Cartridges"
- `bin/zolinga install --help`
- `system/skills/system-reuse-first/SKILL.md`
- `.agents/skills/zolinga-setup/SKILL.md`