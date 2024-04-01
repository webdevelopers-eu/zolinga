# Zolinga PHP Framework

## Introduction
Zolinga is a minimalist and well-thought-out PHP framework. It does not require a database (although modules might). It encapsulates the distilled essence of practical experience gained over years of developing web applications. It offers a simple yet comprehensive API for building robust, efficient, and stable applications.

**This is an innovative addition to the PHP framework landscape, so I encourage you to approach it with a sense of adventure, albeit with caution. Dive in and discover the streamlined efficiency and robustness that Zolinga offers.**

## Target Audience

If you describe yourself as intermediate or expert PHP developer and you're embarking on a project and absolutely refuse to deal with a bloated frameworks, if you're determined to code all the essential features yourself (except for CMS, rights, cron, translations) because no existing framework meets your requirements, if you're well aware that you need to document your work but can't be bothered with "external" solutions and clumsy "documentation generators", if you're looking for a framework that is both "lazy" and "ambitious" and that provides "self-documenting" and "self-explaining" features, if you're looking for a framework that is "stable" and "minimalist" that you can use for years to come... then Zolinga is the perfect match for your lazy yet ambitious self.

Don't expect the full experience of a massive dependency tree where you require a single component using `composer require` and end up with ten thousand other components you don't need. We're not there yet, and I hope we never will be. ;-)

## Why Use Zolinga?
- **⭐ Stable API** 
> Upgrading the Zolinga framework won't break your modules. Your module will continue to work seamlessly with future versions of the framework. We are confident that the API is complete, so you can rely on it for the long term.
- **⭐ Minimalist API**
> We don't do things more complicated than they need to be. A one carefuly chosen approach that can be applied to nearly all use cases is the core of Zolinga's design philosophy. Events, events, events! (Here, imagine [Steven Balmer](https://www.youtube.com/watch?v=Vhh_GeBPOhs) jumping and shouting "Events, events, events!") Sold, right?
- **⭐ Robust API**
> The API is meticulously designed to handle all possible use cases, leaving no room for ambiguity or confusion. It provides a solid foundation for building powerful applications.
- **⭐ Speed**
> Zolinga prioritizes speed, lightweightness, and minimalism. We avoid unnecessary bloat in the framework, ensuring optimal performance for your applications.
- **⭐ No Dependencies**
> Zolinga is a self-contained framework that does not rely on any external dependencies. This deliberate design choice gives developers complete control over the framework's API, ensuring stability and eliminating the risk of disruptions caused by external factors.
- **⭐ Versatility**
> Zolinga is designed to handle a wide range of use cases, from command-line applications to database-less projects, AJAX servers, document servers, and microservice servers. It provides the flexibility and adaptability that programmers need to tackle any project efficiently and effectively.
- **⭐ Developer-Friendly**
> Zolinga prioritizes developer experience by providing a clean and intuitive API. It aims to reduce the learning curve and make development tasks more enjoyable.
- **⭐ Documentation**
> Zolinga is the only self-documenting PHP framework. This feature enhances the developer experience by providing a convenient way to document and reference code within the framework. Stop googling documentation, it is right there. ;-)

## Requirements
- PHP 8.2 or higher

## Installation
To install Zolinga, follow these steps:

1. Clone the repository to your local machine.
```bash
    git clone https://github.com/webdevelopers-eu/zolinga.git
```

2. Configure your web server to serve the `public` directory. Alternatively, you can use the built-in PHP web server by running the following script `./bin/zolinga --server` inside the Zolinga directory.

3. Navigate to the URL depending on how you started the server. If you ran `./bin/zolinga --server` then follow the instructions in the console. If you configured your web server to serve the `public` directory, then you know what to do. You should first visit the `/wiki/` URL. The WIKI page default password is `ZOLINGA` (Duh! 😜). Bundled Zolinga WIKI is the right place to start digging deeper into the Zolinga framework.

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
