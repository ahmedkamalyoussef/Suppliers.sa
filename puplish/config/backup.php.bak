<?php

return [

    'backup' => [

        /*
         * The name of this backup. This name is used when the backup is downloaded.
         * When you only use a single disk, this name is not used.
         */
        'name' => env('APP_NAME', 'Laravel') . '-backup',

        /*
         * The source of the backup. Here you can specify which directories and files
         * should be backed up. If you use the same value for all disks, this name
         * is not used.
         */
        'source' => [

            'files' => [
                /*
                 * The list of directories and files that will be included in the backup.
                 */
                'include' => [
                    base_path(),
                ],

                /*
                 * These directories and files will be excluded from the backup.
                 *
                 * Directories used by the backup process will automatically be excluded by the backup.
                 */
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    base_path('storage/app/backups'),
                ],

                /*
                 * These files will be excluded from the backup.
                 *
                 * The value is a regular expression that will be used to match the file names.
                 */
                'exclude_files' => [
                    // '.*', // Exclude all hidden files
                ],

                /*
                 * Should files that are not readable be included in the zip?
                 */
                'ignore_unreadable_dirs' => false,

                /*
                 * Whether symlinks should be followed.
                 */
                'follow_symlinks' => false,

                /*
                 * The backup should be performed with the given compression level.
                 * Valid values are 0 (no compression) to 9 (maximum compression).
                 * If you're running the backup on a Windows machine, you can set this to null.
                 * This will use the default compression level for the zip command.
                 */
                'compression_level' => null,
            ],

            /*
             * The database dump configuration. Here you can specify which databases
             * should be backed up and which connections should be used.
             */
            'database' => [
                'dump_compressor' => null,

                'dump_source' => 'single-database',

                'databases' => [
                    'mysql',
                ],

                /*
                 * These database connections will be used to create the database dump.
                 */
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'host' => env('DB_HOST', '127.0.0.1'),
                        'port' => env('DB_PORT', '3306'),
                        'database' => env('DB_DATABASE', 'forge'),
                        'username' => env('DB_USERNAME', 'forge'),
                        'password' => env('DB_PASSWORD', ''),
                        'charset' => 'utf8mb4',
                        'collation' => 'utf8mb4_unicode_ci',
                        'prefix' => '',
                        'strict' => true,
                        'engine' => null,
                        'options' => extension_loaded('pdo_mysql') ? array_filter([
                            PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                        ]) : [],
                    ],
                ],

                /*
                 * The path to the mysqldump command. If this value is empty, the package will try to find it automatically.
                 */
                'dump_command_path' => '',

                /*
                 * The command to execute to create a database dump.
                 * You can add extra options like `--single-transaction` or `--quick`.
                 */
                'dump_command' => 'mysqldump --user="{DB_USERNAME}" --password="{DB_PASSWORD}" --host="{DB_HOST}" --port="{DB_PORT}" --single-transaction --quick --lock-tables=false "{DB_DATABASE}" > "{dumpPath}"',

                /*
                 * The command to execute to create a database dump for PostgreSQL.
                 */
                'dump_command_pgsql' => 'pg_dump --username="{DB_USERNAME}" --host="{DB_HOST}" --port="{DB_PORT}" --format=custom --no-password --file="{dumpPath}" "{DB_DATABASE}"',

                /*
                 * The amount of time (in seconds) after which the dump process will be killed.
                 * This prevents the dump process from running forever.
                 */
                'dump_timeout' => 60 * 5, // 5 minutes

                /*
                 * The amount of time (in seconds) after which the dump command will be killed if it produces no output.
                 * This prevents the dump process from hanging.
                 */
                'dump_idle_timeout' => 60 * 5, // 5 minutes

                /*
                 * The maximum number of retries that should be attempted when a dump fails.
                 */
                'dump_retry_attempts' => 3,

                /*
                 * The amount of time (in seconds) to wait between retry attempts.
                 */
                'dump_retry_delay' => 5, // 5 seconds
            ],
        ],

        /*
         * The archive format. This can be `zip`, `tar` or `tar.gz`.
         */
        'archive' => [
            'format' => 'zip',
            'password' => env('BACKUP_ARCHIVE_PASSWORD'),
            'prefix' => '',
            'suffix' => '',
        ],

        /*
         * The destination filesystems. Here you can specify where the backups should be stored.
         */
        'destination' => [
            /*
             * The filename prefix for the backup.
             */
            'filename_prefix' => '',

            /*
             * The disk names that should be used.
             */
            'disks' => [
                'local',
            ],
        ],

        /*
         * The directory where the temporary files will be stored.
         */
        'temporary_directory' => storage_path('app/backup-temp'),

        /*
         * The number of backups that should be kept.
         */
        'keep_backups' => 30,

        /*
         * The number of backups that should be kept on a daily basis.
         */
        'keep_backups_daily' => 7,

        /*
         * The number of backups that should be kept on a weekly basis.
         */
        'keep_backups_weekly' => 4,

        /*
         * The number of backups that should be kept on a monthly basis.
         */
        'keep_backups_monthly' => 12,

        /*
         * The number of backups that should be kept on a yearly basis.
         */
        'keep_backups_yearly' => 2,

        /*
         * The number of backups that should be kept on a yearly basis.
         */
        'keep_backups_hourly' => 24,

        /*
         * The number of backups that should be kept on a yearly basis.
         */
        'keep_backups_minutely' => 48,

        /*
         * The oldest backup that should be kept.
         * You can use a relative date format (e.g. `1 month`, `3 weeks`, `2 days`) or a Carbon instance.
         */
        'delete_oldest_backups_when_using_more_than_megabytes' => 5000,

        /*
         * The cleanup strategy that should be used.
         * Available strategies: `default`, `old`, `young`.
         */
        'cleanup_strategy' => 'default',

        /*
         * The number of backups that should be kept on a daily basis.
         */
        'default_backup_number' => 30,
    ],

    'notifications' => [

        /*
         * The notifications that will be sent when a backup succeeds or fails.
         */
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailed::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessful::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailed::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessful::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFound::class => [],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFound::class => ['mail'],
        ],

        /*
         * The mail configuration for the notifications.
         */
        'mail' => [
            'to' => 'your@example.com',
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],

        /*
         * The Slack configuration for the notifications.
         */
        'slack' => [
            'webhook_url' => env('SLACK_WEBHOOK_URL'),

            /*
             * If this is set to null the channel will be the default one of the webhook.
             */
            'channel' => null,

            /*
             * The username that will be used for the Slack messages.
             */
            'username' => 'Backup Bot',

            /*
             * The icon that will be used for the Slack messages.
             */
            'icon' => ':backup:',

        ],

        /*
         * The Discord configuration for the notifications.
         */
        'discord' => [
            'webhook_url' => env('DISCORD_WEBHOOK_URL'),

            /*
             * If this is set to null the channel will be the default one of the webhook.
             */
            'channel' => null,

            /*
             * The username that will be used for the Discord messages.
             */
            'username' => 'Backup Bot',

            /*
             * The icon that will be used for the Discord messages.
             */
            'avatar_url' => null,
        ],
    ],

    /*
     * The monitor that will be used to check the health of the backups.
     */
    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'Laravel'),
            'disks' => ['local'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    /*
     * The cleanup strategy that will be used.
     */
    'cleanup' => [
        'default' => [
            'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,
            'delete_oldest_backups_when_using_more_than_megabytes' => 5000,
        ],
    ],

    /*
     * The backup job that will be used.
     */
    'jobs' => [
        'backup' => \Spatie\Backup\Jobs\BackupJob::class,
        'cleanup' => \Spatie\Backup\Jobs\CleanupJob::class,
    ],
];
