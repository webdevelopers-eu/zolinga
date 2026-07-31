# Zolinga PHP Framework — The Vibe Coder’s Dream 🚀

> 🤖 Built for People. 🧠 Orchestrated by AI. Lightweight PHP framework with native MCP support. ✨ Hand-crafted from the ground up, and micro-optimized to minimize token usage with AI.

*Etymology: In the Chichewa language of Africa, the word "zolinga" means "goals."*

Zolinga is a ultra-lightweight PHP framework designed for **vibe coders**, beginning developers, and AI agents. It is fully ✋hand-coded🤚, avoiding bloated AI-generated scaffolding. By keeping the codebase extremely lean, Zolinga provides a clean, predictable, and low-complexity blueprint that lets you and your AI build real apps at the speed of thought.

---

## Why Zolinga is the Ultimate AI + Vibe Coding Framework

### 🪙 Low-Token Vibe Architecture
Standard PHP frameworks like Laravel or Symfony are massive. They overload your LLM's context window, draining your wallet in API costs and confusing your AI companion with endless layers of inheritance. 
* **Tiny Footprint:** Zolinga is so clean and compact that AI agents can read, understand, and reason about your entire codebase in a single prompt.
* **Fewer Tokens, Better Code:** Experience lightning-fast responses, near-zero hallucinations, and massive savings on OpenAI/Anthropic/Gemini bills.

### 🔌 Modular Simplicity
Forget bloated packages, dependency hell, and messy boilerplate. In Zolinga, features are organized into self-contained, modular **modules** inside the [modules/](modules/) directory.
* **Zero Boilerplate:** Each module declares its event listeners, interfaces, and settings in a simple JSON manifest, [zolinga.json](zolinga.json).
* **AI-First Discoverability:** Your AI agent can scan the manifest files, instantly understand what events a module handles, and write perfect code to interact with them inside [modules/](modules/).

### 🔋 Plug-and-Play Agent Cartridges
Teaching your AI companion how to build and maintain your app is as simple as inserting a retro game cartridge.
* **Symlinked Skills:** When a module is installed, its specialized AI prompts and domain-specific action specifications—its **Agent Cartridges**—are automatically symlinked into [.agents/skills/](.agents/skills/).
* **Instant Expert AI:** These cartridges instantly slot directly into your development agent's active memory pool, teaching your AI precisely how to test, document, and expand that module without confusing or overloading its main instruction context!

### ⚡ Unified Event-Driven Voodoo
In Zolinga, **everything is an event**. Whether a request comes from a user visiting a webpage, an automated cron job, or the command line—it is handled exactly the same way.
* Dispatch an event anywhere, and your AI can easily hook into it or extend it.
* Zero service container overhead, zero ceremony. Simple, direct, and fast PHP 8.4 code.

---

## Table of Contents

- [Zolinga PHP Framework — The Vibe Coder’s Dream 🚀](#zolinga-php-framework--the-vibe-coders-dream-)
  - [Why Zolinga is the Ultimate AI + Vibe Coding Framework](#why-zolinga-is-the-ultimate-ai--vibe-coding-framework)
    - [🪙 Low-Token Vibe Architecture](#-low-token-vibe-architecture)
    - [🔌 Modular Simplicity](#-modular-simplicity)
    - [🔋 Plug-and-Play Agent Cartridges](#-plug-and-play-agent-cartridges)
    - [⚡ Unified Event-Driven Voodoo](#-unified-event-driven-voodoo)
  - [The Architecture of Difference: Zolinga vs. Legacy Frameworks](#the-architecture-of-difference-zolinga-vs-legacy-frameworks)
  - [The 2-Minute Quickstart](#the-2-minute-quickstart)
    - [The Simple Way (Local PHP)](#the-simple-way-local-php)
    - [The Fast Way (Docker Container)](#the-fast-way-docker-container)
    - [Running with Apache](#running-with-apache)
  - [How to Vibe Code - Anatomy of a Module](#how-to-vibe-code---anatomy-of-a-module)
    - [The Module Manifest](#the-module-manifest)
    - [The Module Logic](#the-module-logic)
  - [Built-In Framework Superpowers](#built-in-framework-superpowers)
  - [Pre-Loaded Modules](#pre-loaded-modules)

---

## The Architecture of Difference: Zolinga vs. Legacy Frameworks

Zolinga isn't just a mini-framework; it is designed on a completely different set of assumptions about development. Here is how it stacks up against traditional heavyweights like Laravel or Symfony:

| Dimension | Legacy Frameworks (Laravel, Symfony) | Zolinga Framework |
| :--- | :--- | :--- |
| **AI Native Execution** | Zero native support. Requires custom middleware controllers, third-party libraries, and manual endpoint mapping. | **Native Model Context Protocol (MCP) Server.** Speaks JSON-RPC 2.0 natively at `/mcp/`. AI agents list tools, inspect resources, and use prompts out-of-the-box. |
| **Parsing & Templating** | Dynamic regex string replacement engines (Blade, Twig) compile code to messy files. Highly prone to generation errors. | **Logical DOM-Centric Architecture.** Translates and renders HTML templates strictly via the native **PHP DOM extension** and XPath, ensuring structured schema stability. |
| **Dependency Lifespan** | Deep composer/npm trees under constant change. High maintenance costs, major breaking updates every 12–24 months. | **Immutable Framework Dependency.** Zero external packages. Built strictly on WHATWG web component standards and bare PHP 8.4—designed to run unaltered for 10+ years. |
| **Service Wiring** | Complex Dependency Injection Containers (DIC), reflection/binding configs, and heavy services setup. | **Global `$api` State & Manifest Wiring.** Fully decoupled module manifests auto-load and register event listeners instantly. Direct, lightning-fast execution. |
| **Developer Wiki** | Centralized documentation servers or disconnected text documents that quickly drift out of sync. | **Self-Inlined Markdown Wikis.** Each module carries its own Markdown files that compiled in real-time to a secure, local developer Wiki. |

---

## The 2-Minute Quickstart

Getting started is designed to be effortless. You don’t need complex package managers or build scripts list.

### The Simple Way (Local PHP)

1. **Clone the project:**
   ```bash
   git clone https://github.com/webdevelopers-eu/zolinga.git
   ```
2. **Launch the built-in server:**
   ```bash
   ./bin/zolinga --server
   ```
3. **Explore your setup:**
   Open your browser to the local URL shown in your terminal. You can access the unified in-app developer guide at `/wiki` using the default password `ZOLINGA`.

### The Fast Way (Docker Container)

Have Docker installed? Spin up Zolinga in under two minutes:

```bash
$ docker pull php
$ docker run -p 8888:8888 -it --name my_php_container php /bin/bash
dock:$ apt update && apt install -y git
dock:$ git clone https://github.com/webdevelopers-eu/zolinga.git /tmp/zolinga
dock:$ /tmp/zolinga/bin/zolinga --server
```

Now, visit [http://localhost:8888](http://localhost:8888) and start vibing!

### Running with Apache

If you are running on Debian/Ubuntu and prefer a traditional Apache setup:

```bash
# Clone the repository
$ git clone https://github.com/webdevelopers-eu/zolinga.git /var/www/zolinga.localhost

# Set standard permissions for Apache
$ chown -R www-data:www-data /var/www/zolinga.localhost

# Use the zolinga CLI tool to scaffold your Apache virtual host file
$ /var/www/zolinga.localhost/bin/zolinga skeleton:apache --serverName=zolinga.local --ip=127.0.0.1 > /etc/apache2/sites-available/010-zolinga.conf

# Enable the site and reload
$ a2ensite 010-zolinga.conf
$ systemctl restart apache2
```

---

## How to Vibe Code - Anatomy of a Module

When vibe coding, simply point your AI to [zolinga.json](zolinga.json) and ask: *"Add a new capability that does X."* 

Every module lives in a folder inside the [modules/](modules/) directory. It contains a manifest file and simple PSR-compliant source scripts.

### The Module Manifest

Your module defines its inputs, capabilities, and settings inside a [zolinga.json](zolinga.json) manifest file:

```json
{
    "name": "Hello Vibe",
    "description": "Greeting module designed for AI orchestration.",
    "version": "1.0.0",
    "authors": ["Vibe Coder <developer@example.com>"],
    "listen": [
        {
            "event": "system:content:html",
            "class": "\\Example\\HelloVibe\\Server",
            "method": "outputPage",
            "priority": 0.8,
            "origin": ["remote"]
        }
    ],
    "autoload": {
        "Example\\HelloVibe\\": "src/"
    },
    "config": {
        "helloVibe": {
            "myGreeting": "Hello Vibe Coder! 🥳🎉"
        }
    }
}
```

### The Module Logic

The companion PHP code is incredibly clean and written in modern PHP. For example, to handle the web request event above:

```php
declare(strict_types=1);

namespace Example\HelloVibe;

use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Events\Event;

class Server implements ListenerInterface 
{
    /**
     * Responds to HTML content requests.
     */
    public function outputPage(Event $event): void 
    {
        // Zero boilerplate. Just access the global API services and vibe.
        global $api;
        
        $greeting = $api->config['helloVibe']['myGreeting'] ?? 'Hello!';
        echo "<h1>$greeting</h1>";
        
        $event->setStatus(Event::STATUS_COMPLETED);
    }
}
```

---

## Built-In Framework Superpowers

* **Database-less CMS with Dynamic Tags:** Pages are simple static HTML files, not rows in a database. Inject dynamic logic anywhere with markup tags like `<replace-vars>` or `<include-file>`. Extremely fast to load, easy to back up, and easily understood by AI.
* **Instant Multilingual (Gettext):** Native translation out-of-the-box. Context-aware translations are defined in standard `.po`/`.mo` structures with direct JavaScript frontend binding. No huge localization libraries needed.
* **No-Config Web Components:** Build slick custom frontends effortlessly. Drop your JS web component file into your module's distribution folder, and Zolinga automatically registers it in the browser window using WHATWG Web Components.
* **Git-Tracked Local Wiki:** All module and core documentation is stored inside the code repository itself in Markdown format. As your AI edits your codebase, it keeps your documentation automatically in sync inside git.

---

## Pre-Loaded Modules & Cartridges

You can check out and install useful official modules directly from the CLI:

```bash
# List available modules
./bin/zolinga install --list

# Plug in and install the CMS and translation layers (which also slots in their respective Agent Cartridges)
./bin/zolinga install --module=zolinga-cms,zolinga-intl,zolinga-db,zolinga-cron
```

* **Zolinga CMS** (`zolinga-cms`) — Database-free, files-centric, ultra-performance management system.
* **Zolinga Cron** (`zolinga-cron`) — Flexible automated schedule and task manager.
* **Zolinga DB** (`zolinga-db`) — Hyper-clean MySQL database mapper.
* **Zolinga RMS** (`zolinga-rms`) — Robust, minimal rights-management and authorization layers.
* **Zolinga Intl** (`zolinga-intl`) — Multi-locale support, localizing both PHP and front-end JS easily.
* **Zolinga AI** (`zolinga-ai`) — Built-in LLM interfaces designed to allow the core to speak directly with generative endpoints.

Enjoy building without the bloat. Welcome to the vibe era of programming! 🚀
