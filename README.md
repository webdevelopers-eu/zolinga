# Zolinga PHP Framework
*Etymology: In the Chichewa language of Africa, the word "zolinga" means "goals."*

## But... why?

I'm fully committed to a single project, and these components—framework, cron, translation module, database access, a database-less CMS, and simple Rights Management—are the vital building blocks needed for it. After two months of intense effort and drawing from twenty years of experience, I felt it was fitting to give back to the open-source community before delving into the closed-source aspect of the project.

You might wonder why not opt for an existing solution? Well, the project I'm working on is anticipated to run for the next 10 years. 90% of the code has no parallel in existing modules. For that reason, it needs to be both minimalistic and immutable. Minimalistic because fewer features equate to less maintenance headaches and fewer upgrade issues. Immutable because it significantly reduces the cost of maintaining modules. 

The problem with all popular frameworks is their sheer popularity. They tend to be inundated with competing needs, ideas, and requirements, leading to a perpetual cycle of breaking compatibility, adding unnecessary features, and fixing bugs. My project isn't intended to be upgraded or rewritten every two years, which is the average lifespan of a major release in any popular framework. It needs to run for 10 years stright with minimal investment. Constantly working on the app to keep up with the extensive list of fixed bugs from vendors, for features you don't even use, is not a feasible solution.

So, that's how this minimalistic and maximally immutable project came to be.

It had to meet the following criteria:

- Be minimalistic, devoid of any unnecessary features, yet have a smart design to cover all current and future use cases
- Ensure strict, well-defined modularity to effortlessly incorporate new features and seamlessly expand the project. From front-end components and JavaScript to the backend with PHP.
- Have an immutable API wherever possible (WHATWG Web Components, bare PHP, own modules - dependencies that are stable and/or under full control)
- Require a minimalistic, database-less CMS that facilitates updates via FTP or similar methods. It must support pluggable dynamic elements managed by other modules, with a customizable templating system that can be easily extended. Additionally, it must seamlessly integrate with the ability to be replaced with a full-blown, database-driven version when necessary.
- Include simple Rights Management
- Offer Cron support to schedule and execute tasks
- Provide unmatched language translation support (expecting usage in 8+ languages)
- Offer a simple MySQL API
- Have comprehensive documentation
- Adhere to simple programming rules to ensure easy onboarding for professionals with varying skill levels
- Have an built-in git-controlled documentation system that can include private documentation without the need to maintain and update external documentation servers

Let me introduce you to Zolinga, a PHP framework that meets all these criteria.

## Introduction
Zolinga is a minimalist and well-thought-out PHP framework. It does not require a database (although modules might). It encapsulates the distilled essence of practical experience gained over years of developing web applications. It offers a simple yet comprehensive API for building robust, efficient, and stable applications.

**This is an innovative addition to the PHP framework landscape, so I encourage you to approach it with a sense of adventure, albeit with caution. Dive in and discover the streamlined efficiency and robustness that Zolinga offers.**

## Target Audience

If you're tired of overcomplicated and extensive solutions for simple problems and are eager to explore new approaches, Zolinga is for you. Whether you're a PHP developer at any level, Zolinga offers a refreshing alternative.

With Zolinga, you can craft your project with ease, focusing on coding essential features without the burden of unnecessary components. Zolinga's minimalist API design makes it perfect for those who prefer to use minimal features or are eager to add minimalistic components to their projects. Despite its minimalist approach, Zolinga is a powerful platform capable of handling even the largest projects, offering indefinite growth potential.

Start small and easy and grow with your project at your own pace in any direction, thanks to Zolinga's smart flexibility and scalability. 

## Requirements
- PHP 8.2 or higher

## Installation

### The Common Way
To install Zolinga, follow these steps:

1. Clone the repository to your local machine.
```bash
    git clone https://github.com/webdevelopers-eu/zolinga.git
```

2. Configure your web server to serve the `public` directory (see section Apache bellow as an example). Alternatively, you can use the built-in PHP web server by running the following script `./bin/zolinga --server` inside the Zolinga directory.

3. Navigate to the URL depending on how you started the server. If you ran `./bin/zolinga --server` then follow the instructions in the console. If you configured your web server to serve the `public` directory, then you know what to do. You should first visit the `/wiki/` URL. The WIKI page default password is `ZOLINGA` (Duh! 😜). Bundled Zolinga WIKI is the right place to start digging deeper into the Zolinga framework.

### Docker Quick Test

Pull the PHP image and run the Zolinga framework inside a container as fast as you can. 😜

```bash
$ docker pull php
$ docker run -p 8888:8888 -it --name my_php_container php /bin/bash
dock:$ apt update && apt install -y git
dock:$ git clone https://github.com/webdevelopers-eu/zolinga.git /tmp/zolinga
dock:$ /tmp/zolinga/bin/zolinga --server
```

Then visit [http://localhost:8888](http://localhost:8888) in your browser.

### Apache

This is an example how Apache on Debian can be configured to serve Zolinga.

```bash
# Checkout the repository into /var/www folder 
$ git clone https://github.com/webdevelopers-eu/zolinga.git /var/www/zolinga.localhost

# Set the correct permission - www-data user is the common user for Apache
$ chown -R www-data.www-data /var/www/zolinga.localhost

# Generate example Apache configuration file and put it into /etc/apache2/sites-available
$ /var/www/zolinga.localhost/bin/zolinga skeleton:apache --serverName=zolinga.local --ip=127.0.0.1 > /etc/apache2/sites-available/010-zolinga.conf

# Enable the newly created configuration file
$ a2ensite 010-zolinga.conf

# Restart Apache
$ systemctl restart apache2
```

Now add the following line to your `/etc/hosts` file:

```
127.0.0.1 zolinga.localhost
```

Then visit [http://zolinga.localhost](http://zolinga.localhost) in your browser.

Of course it presumes that PHP is already installed on your system. If not, you can install it by running the following commands:

```bash
apt install libapache2-mod-php8.2
a2enmod php8.2
```

## Anatomy of a Module
A module is a directory that contains a `zolinga.json` file, which describes the module's functionality to the system. This file specifies the script autoload rules and the events that the module listens to. The listener manifest section allows you to define event handlers that respond to various system events resulting in processing various types of requests, such as HTTP, AJAX, and command line. Special events can also instantiate your event handler as a system service to be directly accessed by other code through `$api->{serviceName}` syntax for maximum speed and versatility.

### Manifest File
Each module contains a `zolinga.json` file that describes the module. 

Module manifest file example:

```json
{
    "name": "Hello World",
    "description": "A simple Hello World module.",
    "version": "1.0.0",
    "authors": ["John Doe <john@example.com>"],
    "listen": [
        {
            "event": "system:content",
            "class": "\\Example\\HelloWorld\\Server",
            "method": "outputPage",
            "priority": 0.8,
            "origin": ["remote"]
        }
    ],
    "autoload": {
        "Example\\HelloWorld\\": "src/"
    },
    "config": {
        "helloWorld": {
            "myGreeting": "Hello World! 🥳🎉"
        }
    }
}
``` 
Yes, as you would guess, if you dispatch an event `system:content` from a remote origin, the `outputPage` method of the `Server` class in the `Example\HelloWorld` namespace will be called.

```php
use Zolinga\System\Events\Event;

(new Event('system:content', Event::ORIGIN_REMOTE))->dispatch();
```

This is all there is to it. Except for a few optional syntax sugars to make the module manifest easier to write and read, you don't need to know anything more than what is in this example. You're practically the Zolinga expert now!

## Installing Modules

To install new module run the following command:

```bash
./bin/zolinga install --module={ID}
```

Example:

```bash
./bin/zolinga install --module=zolinga-cms
``` 

To list all available modules run the following command:

```bash
./bin/zolinga install --list
```

Enjoy! 🎉

# Related Modules

You may want to check also other modules. Just add them to Zolinga by running

```bash
./bin/zolinga install --module=ID[,ID,...]
```

E.g.

```bash
./bin/zolinga install --list
./bin/zolinga install --module=zolinga-cron,zolinga-db,zolinga-rms,zolinga-intl,zolinga-cms
```

- [Zolinga CMS](https://github.com/webdevelopers-eu/zolinga-cms) - databaseless content management system
- [Zolinga Cron](https://github.com/webdevelopers-eu/zolinga-cron) - cron jobs
- [Zolinga DB](https://github.com/webdevelopers-eu/zolinga-db) - simple MySQL API
- [Zolinga RMS](https://github.com/webdevelopers-eu/zolinga-rms) - simple Rights Management
- [Zolinga Intl](https://github.com/webdevelopers-eu/zolinga-intl) - language translation support

When you install a module, it will be automatically added to the `modules` directory and its documentation will be merged into inbuilt Zolinga WIKI Documentation right in your Zolinga installation.
