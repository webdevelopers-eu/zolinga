## Description

Service discovery event. When code accesses `$api->serviceName`, the system dispatches `system:service:serviceName` to find and instantiate the service provider.

- **Event:** `system:service:*`
- **Emitted by:** `Zolinga\System\Api` (bootstrap)
- **Event Type:** `\Zolinga\System\Events\Event`
- **Origin:** `internal`

## Behavior

The first listener with the highest priority that matches the service name is instantiated as the service provider. Services are registered in `zolinga.json` using the `service` shorthand:

```json
{
    "service": "myService",
    "class": "MyModule\\MyService",
    "origin": ["internal"]
}
```

This is equivalent to listening for `system:service:myService`.

## Registered Services

| Service | Class | Module |
|---|---|---|
| `account` | `Ipd\Base\AccountService` | ipdefender |
| `ai` | `Zolinga\AI\Service\AiApi` | zolinga-ai |
| `analytics` | `Zolinga\System\Analytics\AnalyticsService` | system |
| `autoblog` | `Zolinga\Autoblog\Listeners\AutoblogService` | zolinga-autoblog |
| `cms` | `Zolinga\Cms\PageServer` | zolinga-cms |
| `cmsParser` | `Zolinga\Cms\ContentParser` | zolinga-cms |
| `cmsTree` | `Zolinga\Cms\Tree\TreeRoot` | zolinga-cms |
| `config` | `Zolinga\System\Config\ConfigService` | system |
| `convert` | `Zolinga\Commons\ConvertService` | zolinga-commons |
| `cron` | `Zolinga\Cron\CronService` | zolinga-cron |
| `currency` | `Zolinga\Commons\CurrencyService` | zolinga-commons |
| `daqDownloader` | `Ipd\Daq\Downloader` | ipdefender-daq |
| `db` | `Zolinga\Database\DbService` | zolinga-db |
| `downloader` | `Zolinga\Commons\Downloader\DownloaderService` | zolinga-commons |
| `euipo` | `Ipd\Providers\Euipo\EuipoServiceApi` | ipdefender-providers |
| `fs` | `Zolinga\System\Filesystem\WrapperService` | system |
| `ipdCounters` | `Ipd\Base\CountersService` | ipdefender-base |
| `locale` | `Zolinga\Intl\LocaleService` | zolinga-intl |
| `log` | `Zolinga\System\Logger\LogService` | system |
| `manifest` | `Zolinga\System\Config\ManifestService` | system |
| `network` | `Zolinga\Commons\NetworkService` | zolinga-commons |
| `pingjoe` | `Zolinga\Commons\PingJoe\PingJoeService` | zolinga-commons |
| `registry` | `Zolinga\Database\RegistryService` | zolinga-db |
| `rms` | `Zolinga\Rms\Service` | zolinga-rms |
| `stripe` | `Zolinga\Stripe\Services\StripeService` | zolinga-stripe |
| `stripeClient` | `Zolinga\Stripe\Services\StripeClientService` | zolinga-stripe |
| `stripeMapper` | `Zolinga\Stripe\Services\StripeMapperService` | zolinga-stripe |
| `tmview` | `Ipd\Providers\TmView\TmViewService` | ipdefender-providers |
| `uploader` | `Zolinga\Commons\Uploader\UploaderService` | zolinga-commons |
| `url` | `Zolinga\Commons\UrlService` | zolinga-commons |
| `user` | `Zolinga\Rms\UserService` | zolinga-rms |
| `wiki` | `Zolinga\System\Wiki\WikiService` | system |

## See Also

- Services wiki article
