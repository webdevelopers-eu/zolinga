# Configuration Service

The `$api->config` service provides a read-only API to access merged configurations from 

1. `zolinga.json`'s config sections from all modules
2. `./config/global.json`
3. `./config/local.json`

The latter overwrites the former. For more information refer to [Zolinga Configuration](:Zolinga Core:Configuration).