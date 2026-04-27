---
name: system-reuse-first
description: Use before implementing any new feature — check existing services, events, content tags, and installable modules first. Avoid duplicating functionality that already exists in the system.
argument-hint: "<feature-description>"
---

# Reuse First — Check Before You Build

## Use When

- You are about to implement a new feature, helper, utility, or integration.
- You need a service, event, content tag, or module capability and are tempted to write it from scratch.

## Principle

**Always search before you build.** The Zolinga ecosystem already provides many services, events, and content tags. Reusing them keeps the codebase DRY, consistent, and maintainable.

## Workflow

### Step 1 — Check `$api` Services

The global `$api` object exposes all registered services. Before writing any new utility class or service method, check if it already exists.

```bash
# List all services with their class types
grep -oP '\$api->\w+' data/system/api.stub.php | sort -u
```

Or read the full stub file:

```bash
cat data/system/api.stub.php
```

The table below lists framework-level services only (no project-specific modules). For the full live list, always check `data/system/api.stub.php` — services may be added or removed as modules are installed or removed.

| Service | Class | Module | Common Use |
|---------|-------|--------|------------|
| `$api->db` | `DbService` | `zolinga-db` | Database queries, transactions |
| `$api->registry` | `RegistryService` | `zolinga-db` | Persistent key-value store |
| `$api->config` | `ConfigService` | `system` | Read merged config (`global.json` + `local.json`) |
| `$api->log` | `LogService` | `system` | Logging |
| `$api->manifest` | `ManifestService` | `system` | Module manifest access |
| `$api->analytics` | `AnalyticsService` | `system` | Analytics tracking |
| `$api->fs` | `WrapperService` | `system` | Filesystem operations with Zolinga URI support |
| `$api->wiki` | `WikiService` | `system` | Wiki/documentation access |
| `$api->url` | `UrlService` | `zolinga-commons` | URL building and resolution |
| `$api->convert` | `ConvertService` | `zolinga-commons` | Data format conversions |
| `$api->network` | `NetworkService` | `zolinga-commons` | HTTP requests, CIDR matching |
| `$api->currency` | `CurrencyService` | `zolinga-commons` | Currency conversion |
| `$api->downloader` | `DownloaderService` | `zolinga-commons` | File downloads |
| `$api->uploader` | `UploaderService` | `zolinga-commons` | File uploads |
| `$api->pingjoe` | `PingJoeService` | `zolinga-commons` | PingJoe integration |
| `$api->cms` | `PageServer` | `zolinga-cms` | CMS page rendering |
| `$api->cmsParser` | `ContentParser` | `zolinga-cms` | Parse HTML content with content tags |
| `$api->cmsTree` | `TreeRoot` | `zolinga-cms` | CMS page tree navigation |
| `$api->locale` | `LocaleService` | `zolinga-intl` | Translations, locale detection |
| `$api->cron` | `CronService` | `zolinga-cron` | Schedule recurring jobs |
| `$api->user` | `UserService` | `zolinga-rms` | User management, auth |
| `$api->rms` | `Service` | `zolinga-rms` | Rights management |
| `$api->ai` | `AiService` | `zolinga-ai` | AI prompt calls |
| `$api->autoblog` | `AutoblogService` | `zolinga-autoblog` | Automated blog generation |
| `$api->stripe` | `StripeService` | `zolinga-stripe` | Stripe payment integration |
| `$api->stripeClient` | `StripeClientService` | `zolinga-stripe` | Stripe API client |
| `$api->stripeMapper` | `StripeMapperService` | `zolinga-stripe` | Stripe data mapping |

If a service you need is not available, check whether its module is installed. If not, install it:

```bash
bin/zolinga install --module=zolinga-<name>
```

### Step 2 — Check Existing Events and Listeners

Before creating a new event or listener, check if one already exists that you can listen to or dispatch.

```bash
# List all unique event names
jq '[.listen[] | .event] | unique' data/system/system.cache.json

# Find listeners for a specific event
jq '[.listen[] | select(.event == "system:service:log")]' data/system/system.cache.json

# Find all services (sugar "service" becomes "system:service:*")
jq '[.listen[] | select(.event | startswith("system:service:")) | {service: (.event | sub("system:service:"; "")), class: .class}]' data/system/system.cache.json
```

### Step 3 — Check CMS Content Tags

Before creating a new `<content-tag>`, check if one already provides the output you need.

```bash
# List all content tag events
jq '[.listen[] | select(.event | startswith("cms:content:")) | .event] | unique' data/system/system.cache.json
```

Common content tags include: `ai-text`, `menu`, `page-list`, and module-specific tags.

### Step 4 — Check Web Components (Front-End)

If the feature involves front-end UI, check if a web component already exists before building a new one.

```bash
# List all registered web components and their JS module paths
cat public/data/system/web-components.json

# Or query from the system cache
jq '.webComponents' data/system/system.cache.json
```

Web components are custom HTML tags registered via `zolinga.json` (`webComponents` section). They live in `modules/<name>/install/dist/web-components/<tag-name>/`. See `system-web-components` skill for details.

### Step 5 — Check Available Modules (Install If Needed)

If no existing service/event/tag covers the need, check if an installable module provides it.

```bash
# List all available (not yet installed) modules
bin/zolinga install --list

# Refresh the module registry first if needed
bin/zolinga install --refresh

# Install a module
bin/zolinga install --module=<module-name>
```

Currently available modules (check `--list` for the latest):

| Module | Description |
|--------|-------------|
| `zolinga-db` | MySQL database access API and .sql install scripts |
| `zolinga-cron` | Cron job scheduling |
| `zolinga-rms` | Rights Management System |
| `zolinga-intl` | Internationalization / translation |
| `zolinga-ai` | AI services and prompt pipelines |
| `zolinga-cms` | Database-less content management system |
| `zolinga-commons` | Common utilities, widgets, and shared services |
| `zolinga-seo` | SEO services (sitemaps, IndexNow) |

### Step 6 — Check Module Wiki and Source

If a module is already installed, read its wiki and source to understand what it offers before reinventing:

```bash
# Check module wiki
ls modules/<module>/wiki/

# Check module manifest for events, services, and config
cat modules/<module>/zolinga.json

# Check module source
ls modules/<module>/src/
```

### Step 7 — Only Then Build New

If after all checks nothing covers the need:

1. Create the new service, event, or content tag following the appropriate skill:
   - Service → `system-create-service` skill
   - Event listener → `system-create-handler` skill
   - Content tag → `system-content-tags` skill
   - Web component → `system-web-components` skill
2. Register it in the module's `zolinga.json`.
3. Bump the module version to trigger cache reload.

## Quick Reference — Where to Look

| What | Where |
|------|-------|
| API services | `data/system/api.stub.php` |
| All events & listeners | `data/system/system.cache.json` → `.listen` |
| Content tags | `data/system/system.cache.json` → filter `cms:content:*` |
| Web components | `public/data/system/web-components.json` or `data/system/system.cache.json` → `.webComponents` |
| Autoload mappings | `data/system/system.cache.json` → `.autoload` |
| Module manifests | `data/system/system.cache.json` → `.manifests` |
| Available modules | `bin/zolinga install --list` |
| Module docs | `modules/<name>/wiki/` or `system/wiki/` |
| Config | `config/global.json`, `config/local.json` |

> **Tip:** For detailed jq queries and log inspection, see the `system-inspect` skill.

## References

- `system/skills/system-inspect/SKILL.md` — detailed querying of runtime state
- `system/skills/system-module-development/SKILL.md` — module structure and conventions
- `system/skills/system-create-service/SKILL.md` — creating new services
- `system/skills/system-create-handler/SKILL.md` — creating event listeners
- `system/skills/system-content-tags/SKILL.md` — creating CMS content tags