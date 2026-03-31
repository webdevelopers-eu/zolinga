## Description

Filesystem service providing support for Zolinga URI schemes: `public://`, `private://`, `dist://`, `module://`, and `wiki://`.

- **Service:** `$api->fs`
- **Class:** `Zolinga\System\Filesystem\WrapperService`
- **Module:** system
- **Event:** `system:service:fs`

## Usage

```php
$path = $api->fs->toPath("public://data/file.txt");
```
