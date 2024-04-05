# Installing Additional Modules

All it takes to add a new module to Zolinga is to copy the module's folder into the `modules` directory. The module will be automatically loaded, installed and available for use.

All that you will usually need to do is something like this:

```
cd ./modules/
git clone https://github.com/webdevelopers-eu/zolinga-db
```

# Zolinga Installer

Zolinga provides a simple way to install modules from the command line. To install a module run the following command:

```bash
./bin/zolinga install --module="{MODULE ID}[@{GIT BRANCH}][,...]"
```

Example:

```bash
./bin/zolinga install --module="zolinga-cms,zolinga-db"
./bin/zolinga install --module="zolinga-cms@v1.0,zolinga-db@main"
```

To list all available modules run the following command:

```bash
./bin/zolinga install --list
```

To refresh the list of available modules run the following command:

```bash
./bin/zolinga install --refresh
```

# Private GIT Repositories

If you have a private GIT repository you can use the following syntax to install the module:

```bash 
./bin/zolinga install --module=URL[@BRANCH]
```

Example:

```bash
./bin/zolinga install --module=https://github.com/webdevelopers-eu/zolinga-rms.git
```

# Other Zolinga Modules

Since this whole project is a preparation for my next big project I started with key modules that I will need for the project. I deem them absolute essentials. Here is the list.

- [Zolinga CMS](https://github.com/webdevelopers-eu/zolinga-cms)
> Database-less content management system. Edit content documents as standard .html files yet reap the benefits of templates and dynamic widgets. Add multilingual support with [Zolinga Internationalization](https://github.com/webdevelopers-eu/zolinga-intl).
- [Zolinga Database](https://github.com/webdevelopers-eu/zolinga-db)
> Provides the `$api->db` service for MySQL/MariaDB database access.
- [Zolinga Rights Management System](https://github.com/webdevelopers-eu/zolinga-rms)
> Provides the `$api->user` and `$api->rms` services for user management and rights management. Also provides [Authorization Provider](:Zolinga Core:Events and Listeners:Event Authorization). Requires [Zolinga Database](https://github.com/webdevelopers-eu/zolinga-db).
- [Zolinga Cron](https://github.com/webdevelopers-eu/zolinga-cron)
> Schedule your events to run at specific times. Requires [Zolinga Database](https://github.com/webdevelopers-eu/zolinga-db).
- [Zolinga Internationalization](https://github.com/webdevelopers-eu/zolinga-intl)
> Databaseless, complete and comprehensive support for internationalization and localization of your application. Use unified translation system for your PHP code, Javascript and static HTML files. Uses [gettext](https://www.gnu.org/software/gettext/) system.