## Description

Configuration service. Instantiates the `$api->config` ArrayObject interface with all merged configs from `zolinga.json`, `config/global.json`, and `config/local.json`.

- **Service:** `$api->config`
- **Class:** `Zolinga\System\Config\ConfigService`
- **Module:** system
- **Event:** `system:service:config`

## Usage

```php
$value = $api->config['section']['key'];
```
