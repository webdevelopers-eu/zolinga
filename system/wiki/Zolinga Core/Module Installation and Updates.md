Priority: 0.7

# Module Installation and Updates

This article describes how can module authors handle the installation scripts and update patches.

For information how to install third-party modules please refer to the [Installing Additional Modules](:Zolinga Core:Installing Additional Modules) article

## Overview

There are two folders in each module:

- `install/install` - contains the installation scripts that are run when the module is installed for the first time. After that no scripts from this folder will be ever run. Even if new scripts are added to this folder, they will be ignored.
- `install/update` - contains the update scripts. During installation, all scripts from this folder will be skipped and never executed. Only new scripts that appear in this folder after initial installation will be executed.

As a result the system runs effectively in two modes:
- **installation** - the very first time the module is detected by the system. All scripts from `install/install` are executed and all scripts from `install/update` are ignored and will never be executed.
- **update** - this mode is triggered every time the system detects that the `zolinga.json` file changed. In this mode the folder `install/install` is ignored and all *new* scripts from `install/update` are executed. Only new scripts. Skipped scripts from the installation phase will never be executed. 

This may seem a bit confusing at first, but it is a very powerful mechanism that allows you to control the installation and update process of your module in a very fine-grained way.

## Rules

- The `install/install` folder should always contain all scripts necessary to install the module from scratch. 
  - You can freely modify old, add new or remove existing scripts from this folder at any time. 
  - They will be executed only once during the installation phase. So modification of these scripts will only affect new installations and not existing systems.
- The `install/update` folder should contain all scripts necessary to update the module from one version to another. 
  - You should not modify the scripts in this folder as already executed scripts will never be executed again and older scripts are essential to upgrading older systems.
  - Only scripts with new names will be executed on existing systems. So in "update" folder you just keep adding new scripts to keep existing systems up to date. Fixing older scripts usually means adding new scripts that fix the problems caused by the older upgrade/installation scripts.

## Development Workflow

- you start with the `install/install` folder and add all scripts necessary to install the module from scratch. You tweak it until it works or until anybody else uses your module.
- then with every change you add **new** scripts to the `install/update` folder to add the incremental change and in the same time you *alter* the old installation scripts or you may add new scripts in the `install/install` folder to reflect the changes in the initial installation process as well.

## Naming Conventions

The scripts in the `install/install` and `install/update` folders should be named in the following way:

`{NUMBER}_{FILE-NAME}.{EXTENSION}`


## Order of Execution

During the installation and update process the Zolinga Core finds all scripts that need to be executed from all modules and sorts them by the natural order of the file names. 

The important thing to notice is that all scripts across all modules are ordered by name. This means that if your script depends on another script from another module, you should name your script in such a way that it is executed after the other script. 

That knowledge is very important for example for `.sql` scripts that use foreign keys referencing tables from other modules. You want to make sure that your script is executed after the script that creates the table you are referencing.  

## Supported Installation Scripts

By default the Zolinga Core supports only `*.php` scripts but any module can add support for any type of scripts.

For each file found in installation or updates folders that is marked for execution the Installer dispatches the `\Zolinga\System\Events\InstallScriptEvent` event of name `system:install:script:{EXTENSION}` where `{EXTENSION}` is the extension of the file. 

For example for `*.php` files system does following.

```php
$event = new InstallScriptEvent($script);
$event->dispatch();
```

You may want to refer to the [Events and Listeners](:Zolinga Core:Events and Listeners) documentation to learn more about events.

The `\Zolinga\System\Events\InstallScriptEvent` event object has a property `$event->ext` holding the file extensions (e.g. "php", "zip") and `$event->patchFile` that holds the full file path. If you want to add support for other file types you can create a listener that listens to the `system:install:script:{EXTENSION}` event from `internal` origin and execute the script accordingly. 

Once the script is executed you are supposed to set the event status to OK by calling `$event->setStatus(200, 'OK')`. The `\Zolinga\System\Events\InstallScriptEvent` implements `Zolinga\System\Events\StoppableInterface` so you may want also call the `$event->stopPropagation()` to prevent further execution of the event by other handlers. But that is optional. Handlers must always check the event status before proceeding with the execution. If the event status is not "undetermined" (`$event->status == Zolinga\System\Types\OriginEnum::UNDETERMINED`) then the event is considered executed.

That way you can any number of file types to be executed during the installation and update process. For example you can add support for `*.myspecial.php` files by registering a listener for the [system:install:script:php](:ref:event:system:install:script:php) event with higher enough priority so it runs before default listener for PHP installation scripts. If the `$event->patchFile` ends with `.myspecial.php` you can execute the script accordingly. If not leave the event status as is and let the default listener deal with the script.

# Related

- [Installing Additional Modules](:Zolinga Core:Installing Additional Modules) - for information how to install new modules.