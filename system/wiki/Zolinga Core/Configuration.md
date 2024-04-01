# Zolinga Configuration

Each module contains its own *default* configuration section inside `zolinga.json` file.

Since this is a default configuration that is meant to be changed by the user, there is also a `./config` directory that contains "global" and "local" configuration files.

- `./config/global.json`
    - On start it is merged with the default "config" sections in all `zolinga.json` files overwriting them all.
- `./config/local.json`
  - On start it is merged with the `global.json` a all `zolinga.json` files overwriting them all.
  - This file is meant to contain configurations that pertain to this particular machine installation and is not supposed to be synced or deployed to other machines.

# Rules

- Never modify the `zolinga.json` files in the modules. These contain unchangeable default configurations as authored by the module developer. If you need to change a configuration, do it in `global.json` or `local.json`.
- Do not rsync or deploy `local.json` to other machines. It is meant to be local to one machine only. If you need to rsync or deploy some configuration from `local.json` to other machines, it means that you are doing something wrong. You should move that configuration to `global.json` instead.

# Accessing the Configuration

The `ArrayObject` service `$api->config` contains merged configurations in following order:

1. `zolinga.json`'s config sections from all modules
2. `./config/global.json`
3. `./config/local.json`

The latter overwrites the former. The resulting array can be accessed like any other service on global `$api` object like this:

```php
    echo $api->config['ecs']['currency'];
```


The configuration is an ArrayObject service that can be accessed via `$GLOBALS['api']->config`.

# Related

- `$api->config` service