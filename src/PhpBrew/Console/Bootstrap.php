<?php

namespace PhpBrew\Console;

use PhpBrew\Console\Command;

class Bootstrap
{
    public static function createApplication(): Application
    {
        $app = new Application();
        $app->addCommands([
            // Core commands
            new Command\InitCommand(),
            new Command\KnownCommand(),
            new Command\ListCommand(),
            new Command\ListCommandsCommand(),
            new Command\InstallCommand(),
            new Command\DownloadCommand(),
            new Command\CleanCommand(),
            new Command\UpdateCommand(),
            new Command\RemoveCommand(),
            new Command\PurgeCommand(),
            new Command\InfoCommand(),
            new Command\VariantsCommand(),
            new Command\PathCommand(),
            new Command\ConfigCommand(),
            new Command\CtagsCommand(),
            new Command\ListIniCommand(),
            new Command\SelfUpdateCommand(),
            // Virtual commands (shell-delegated)
            new Command\UseCommand(),
            new Command\SwitchCommand(),
            new Command\SwitchOffCommand(),
            new Command\EachCommand(),
            new Command\EnvCommand(),
            new Command\CdCommand(),
            new Command\OffCommand(),
            new Command\SystemCommand(),
            new Command\SystemOffCommand(),
            new Command\MigratedCommand(),
            // Extension subcommands
            new Command\Extension\EnableCommand(),
            new Command\Extension\DisableCommand(),
            new Command\Extension\InstallCommand(),
            new Command\Extension\ConfigCommand(),
            new Command\Extension\CleanCommand(),
            new Command\Extension\ShowCommand(),
            new Command\Extension\KnownCommand(),
            new Command\Extension\ListCommand(),
            // FPM subcommands
            new Command\Fpm\StartCommand(),
            new Command\Fpm\StopCommand(),
            new Command\Fpm\RestartCommand(),
            new Command\Fpm\SetupCommand(),
        ]);
        return $app;
    }
}
