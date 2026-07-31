# Zolinga PHP Framework — The Vibe Coder’s Dream 🚀

*Etymology: In the Chichewa language of Africa, the word "zolinga" means "goals."*

Zolinga is a ultra-lightweight PHP framework designed for **vibe coders**, beginning developers, and AI agents. It is fully ✋hand-coded🤚, avoiding bloated AI-generated scaffolding. By keeping the codebase extremely lean, Zolinga provides a clean, predictable, and low-complexity blueprint that lets you and your AI build real apps at the speed of thought.

---

## Why Zolinga is the Ultimate AI + Vibe Coding Framework

### 🪙 Low-Token Vibe Architecture
Standard PHP frameworks like Laravel or Symfony are massive. They overload your LLM's context window, draining your wallet in API costs and confusing your AI companion with endless layers of inheritance. 
* **Tiny Footprint:** Zolinga is so clean and compact that AI agents can read, understand, and reason about your entire codebase in a single prompt.
* **Fewer Tokens, Better Code:** Experience lightning-fast responses, near-zero hallucinations, and massive savings on OpenAI/Anthropic/Gemini bills.

### 🔋 Plug-and-Play Agent Cartridges
Forget bloated packages, dependency hell, and messy integration code. In Zolinga, features are organized into self-contained, modular **Agent Cartridges**.
* **Zero Boilerplate:** Each cartridge (contained inside [modules/](modules/)) declares its interfaces, routes, and background jobs in a simple JSON manifest, [zolinga.json](zolinga.json).
* **AI-First Discoverability:** Your AI agent can scan the manifest files, immediately understand what events a cartridge handles, and write perfect code to interact with them inside [modules/](modules/).

### ⚡ Unified Event-Driven Voodoo
In Zolinga, **everything is an event**. Whether a request comes from a user visiting a webpage, an automated cron job, or the command line—it is handled exactly the same way.
* Dispatch an event anywhere, and your AI can easily hook into it or extend it.
* Zero service container overhead, zero ceremony. Simple, direct, and fast PHP 8.4 code.

---

## Table of Contents

- [Zolinga PHP Framework — The Vibe Coder’s Dream 🚀](#zolinga-php-framework--the-vibe-coders-dream-)
  - [Why Zolinga is the Ultimate AI + Vibe Coding Framework](#why-zolinga-is-the-ultimate-ai--vibe-coding-framework)
    - [🪙 Low-Token Vibe Architecture](#-low-token-vibe-architecture)
    - [🔋 Plug-and-Play Agent Cartridges](#-plug-and-play-agent-cartridges)
    - [⚡ Unified Event-Driven Voodoo](#-unified-event-driven-voodoo)
  - [The 2-Minute Quickstart](#the-2-minute-quickstart)
    - [The Simple Way (Local PHP)](#the-simple-way-local-php)
    - [The Fast Way (Docker Container)](#the-fast-way-docker-container)
    - [Running with Apache](#running-with-apache)
  - [How to Vibe Code - Anatomy of an Agent Cartridge](#how-to-vibe-code---anatomy-of-an-agent-cartridge)
    - [The Cartridge Manifest](#the-cartridge-manifest)
    - [The Cartridge Logic](#the-cartridge-logic)
  - [Built-In Framework Superpowers](#built-in-superpowers)
  - [Pre-Loaded Agent Cartridges](#pre-loaded-agent-cartridges)

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

## How to Vibe Code - Anatomy of an Agent Cartridge

When vibe coding, simply point your AI to [zolinga.json](zolinga.json) and ask: *"Add a new capability that does X."* 

Every Agent Cartridge lives in a folder inside the [modules/](modules/) directory. It contains a manifest file and simple PSR-compliant source scripts.

### The Cartridge Manifest

Your cartridge defines its inputs and exports inside a [zolinga.json](zolinga.json) manifest file:

```json
{
    "name": "Hello Vibe",
    "description": "Greeting cartridge designed for AI orchestration.",
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

### The Cartridge Logic

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
        // Zero boilerplates. Just access the API state and vibe.
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
* **No-Config Web Components:** Build slick custom frontends effortlessly. Drop your JS web component file into your cartridge's distribution folder, and Zolinga automatically registers it in the browser window using WHATWG Web Components.
* **Git-Tracked Local Wiki:** All cartridge and core documentation is stored inside the code repository itself in Markdown format. As your AI edits your codebase, it keeps your documentation automatically in sync inside git.

---

## Pre-Loaded Agent Cartridges

You can check out and install useful official cartridges directly from the CLI:

```bash
# List available cartridges
./bin/zolinga install --list

# Plug in and install the CMS and translation layers
./bin/zolinga install --module=zolinga-cms,zolinga-intl,zolinga-db,zolinga-cron
```

* **Zolinga CMS** (`zolinga-cms`) — Database-free, files-centric, ultra-performance management system.
* **Zolinga Cron** (`zolinga-cron`) — Flexible automated schedule and task manager.
* **Zolinga DB** (`zolinga-db`) — Hyper-clean MySQL database mapper.
* **Zolinga RMS** (`zolinga-rms`) — Robust, minimal rights-management and authorization layers.
* **Zolinga Intl** (`zolinga-intl`) — Multi-locale support, localizing both PHP and front-end JS easily.
* **Zolinga AI** (`zolinga-ai`) — Built-in LLM interfaces designed to allow the core to speak directly with generative endpoints.

Enjoy building without the bloat. Welcome to the vibe era of programming! 🚀
