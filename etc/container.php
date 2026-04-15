<?php

use PhpBrew\Console\Application;
use PhpBrew\Console\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();

$container->autowire(Application::class)->setPublic(true);

// Core commands
$container->autowire(Command\InitCommand::class)->setPublic(true);
$container->autowire(Command\KnownCommand::class)->setPublic(true);
$container->autowire(Command\ListCommand::class)->setPublic(true);
$container->autowire(Command\ListCommandsCommand::class)->setPublic(true);
$container->autowire(Command\InstallCommand::class)->setPublic(true);
$container->autowire(Command\DownloadCommand::class)->setPublic(true);
$container->autowire(Command\CleanCommand::class)->setPublic(true);
$container->autowire(Command\UpdateCommand::class)->setPublic(true);
$container->autowire(Command\RemoveCommand::class)->setPublic(true);
$container->autowire(Command\PurgeCommand::class)->setPublic(true);
$container->autowire(Command\InfoCommand::class)->setPublic(true);
$container->autowire(Command\VariantsCommand::class)->setPublic(true);
$container->autowire(Command\PathCommand::class)->setPublic(true);
$container->autowire(Command\ConfigCommand::class)->setPublic(true);
$container->autowire(Command\CtagsCommand::class)->setPublic(true);
$container->autowire(Command\ListIniCommand::class)->setPublic(true);
$container->autowire(Command\SelfUpdateCommand::class)->setPublic(true);

// Virtual commands (shell-delegated)
$container->autowire(Command\UseCommand::class)->setPublic(true);
$container->autowire(Command\SwitchCommand::class)->setPublic(true);
$container->autowire(Command\SwitchOffCommand::class)->setPublic(true);
$container->autowire(Command\EachCommand::class)->setPublic(true);
$container->autowire(Command\EnvCommand::class)->setPublic(true);
$container->autowire(Command\CdCommand::class)->setPublic(true);
$container->autowire(Command\OffCommand::class)->setPublic(true);
$container->autowire(Command\SystemCommand::class)->setPublic(true);
$container->autowire(Command\SystemOffCommand::class)->setPublic(true);
$container->autowire(Command\MigratedCommand::class)->setPublic(true);

// Extension subcommands
$container->autowire(Command\Extension\EnableCommand::class)->setPublic(true);
$container->autowire(Command\Extension\DisableCommand::class)->setPublic(true);
$container->autowire(Command\Extension\InstallCommand::class)->setPublic(true);
$container->autowire(Command\Extension\ConfigCommand::class)->setPublic(true);
$container->autowire(Command\Extension\CleanCommand::class)->setPublic(true);
$container->autowire(Command\Extension\ShowCommand::class)->setPublic(true);
$container->autowire(Command\Extension\KnownCommand::class)->setPublic(true);
$container->autowire(Command\Extension\ListCommand::class)->setPublic(true);

// FPM subcommands
$container->autowire(Command\Fpm\StartCommand::class)->setPublic(true);
$container->autowire(Command\Fpm\StopCommand::class)->setPublic(true);
$container->autowire(Command\Fpm\RestartCommand::class)->setPublic(true);
$container->autowire(Command\Fpm\SetupCommand::class)->setPublic(true);

$container->compile();

// Register all commands with the Application.
$app = $container->get(Application::class);
$app->addCommands([
    $container->get(Command\InitCommand::class),
    $container->get(Command\KnownCommand::class),
    $container->get(Command\ListCommand::class),
    $container->get(Command\ListCommandsCommand::class),
    $container->get(Command\InstallCommand::class),
    $container->get(Command\DownloadCommand::class),
    $container->get(Command\CleanCommand::class),
    $container->get(Command\UpdateCommand::class),
    $container->get(Command\RemoveCommand::class),
    $container->get(Command\PurgeCommand::class),
    $container->get(Command\InfoCommand::class),
    $container->get(Command\VariantsCommand::class),
    $container->get(Command\PathCommand::class),
    $container->get(Command\ConfigCommand::class),
    $container->get(Command\CtagsCommand::class),
    $container->get(Command\ListIniCommand::class),
    $container->get(Command\SelfUpdateCommand::class),
    // Virtual commands (shell-delegated)
    $container->get(Command\UseCommand::class),
    $container->get(Command\SwitchCommand::class),
    $container->get(Command\SwitchOffCommand::class),
    $container->get(Command\EachCommand::class),
    $container->get(Command\EnvCommand::class),
    $container->get(Command\CdCommand::class),
    $container->get(Command\OffCommand::class),
    $container->get(Command\SystemCommand::class),
    $container->get(Command\SystemOffCommand::class),
    $container->get(Command\MigratedCommand::class),
    // Extension subcommands
    $container->get(Command\Extension\EnableCommand::class),
    $container->get(Command\Extension\DisableCommand::class),
    $container->get(Command\Extension\InstallCommand::class),
    $container->get(Command\Extension\ConfigCommand::class),
    $container->get(Command\Extension\CleanCommand::class),
    $container->get(Command\Extension\ShowCommand::class),
    $container->get(Command\Extension\KnownCommand::class),
    $container->get(Command\Extension\ListCommand::class),
    // FPM subcommands
    $container->get(Command\Fpm\StartCommand::class),
    $container->get(Command\Fpm\StopCommand::class),
    $container->get(Command\Fpm\RestartCommand::class),
    $container->get(Command\Fpm\SetupCommand::class),
]);

return $container;
