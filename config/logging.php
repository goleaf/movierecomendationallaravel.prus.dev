<?php

declare(strict_types=1);

use App\Logging\RequestContextProcessor;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'stack/json' => [
            'driver' => 'stack',
            'channels' => ['single', 'json'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'Laravel Log'),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'json' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stdout',
            ],
            'formatter' => JsonFormatter::class,
            'formatter_with' => [
                'batchMode' => JsonFormatter::BATCH_MODE_NEWLINES,
                'appendNewline' => true,
            ],
            'processors' => [
                RequestContextProcessor::class,
                PsrLogMessageProcessor::class,
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'importers' => [
            'driver' => 'daily',
            'path' => storage_path('logs/importers.log'),
            'level' => env('IMPORTER_LOG_LEVEL', 'info'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Documented Log Map
    |--------------------------------------------------------------------------
    |
    | This map keeps human-facing documentation in sync with the configured
    | channels. Each entry surfaces the storage target and rotation strategy
    | so README guidance can reference a single source of truth.
    |
    */

    'log_map' => [
        'stack' => [
            'purpose' => 'Default aggregate channel. Delegates to channels listed in LOG_STACK (defaults to "single").',
            'storage' => 'Inherits storage from nested channels.',
            'rotation' => 'Managed by nested channels. Switch LOG_STACK=stack/json when JSON + file logs are both required.',
        ],
        'stack/json' => [
            'purpose' => 'Aggregates "single" and "json" so containers receive structured stdout logs alongside local files.',
            'storage' => 'storage/logs/laravel.log and php://stdout via nested channels.',
            'rotation' => 'File rotation handled by "single"; stdout streams handled by the process manager.',
        ],
        'single' => [
            'purpose' => 'Local development log file for web and queue workers.',
            'storage' => storage_path('logs/laravel.log'),
            'rotation' => 'Single rolling file. Clear manually (rm/truncate) or hand off to logrotate/CI cleanup.',
        ],
        'daily' => [
            'purpose' => 'Date-stamped application logs when long-running history is needed.',
            'storage' => sprintf('%s/%s', storage_path('logs'), 'laravel-YYYY-MM-DD.log'),
            'rotation' => 'Keeps the most recent LOG_DAILY_DAYS (default 14) files, older files are deleted automatically.',
        ],
        'importers' => [
            'purpose' => 'Dedicated channel for ingestion jobs and feed importers.',
            'storage' => sprintf('%s/%s', storage_path('logs'), 'importers-YYYY-MM-DD.log'),
            'rotation' => 'Daily files retained for 14 days to keep ingestion noise out of primary logs.',
        ],
        'json' => [
            'purpose' => 'Structured JSON logs for observability pipelines and container stdout collectors.',
            'storage' => 'php://stdout',
            'rotation' => 'Stream handled by container runtime or host service (e.g. CloudWatch, Loki).',
        ],
        'stderr' => [
            'purpose' => 'Critical output surfaced to process managers and Docker stderr collectors.',
            'storage' => 'php://stderr',
            'rotation' => 'Handled by the host runtime.',
        ],
        'slack' => [
            'purpose' => 'Notifies a Slack channel of critical production failures.',
            'storage' => 'Remote Slack webhook defined via LOG_SLACK_WEBHOOK_URL.',
            'rotation' => 'Retention controlled by Slack history, not the application.',
        ],
        'papertrail' => [
            'purpose' => 'Ships logs to Papertrail over TLS.',
            'storage' => 'Remote Papertrail endpoint defined by PAPERTRAIL_URL and PAPERTRAIL_PORT.',
            'rotation' => 'Retention handled by Papertrail.',
        ],
        'syslog' => [
            'purpose' => 'Pushes logs into the host operating system syslog.',
            'storage' => 'Host syslog daemon (facility configurable via LOG_SYSLOG_FACILITY).',
            'rotation' => 'Host syslog rotation policy applies.',
        ],
        'errorlog' => [
            'purpose' => 'Falls back to PHP\'s default error_log handler.',
            'storage' => 'php.ini error_log destination.',
            'rotation' => 'Managed outside Laravel by the PHP handler.',
        ],
        'null' => [
            'purpose' => 'Silences output entirely (useful for local testing).',
            'storage' => 'Discarded.',
            'rotation' => 'Not applicable.',
        ],
        'emergency' => [
            'purpose' => 'Fallback channel if the configured stack fails.',
            'storage' => storage_path('logs/laravel.log'),
            'rotation' => 'Same manual process as "single".',
        ],
    ],

];
