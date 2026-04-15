# Design: CLIFramework → Symfony Console migráció

**Dátum:** 2026-04-13  
**Alap:** [phpbrew/phpbrew#1138](https://github.com/phpbrew/phpbrew/pull/1138) (részben kész, folytatás + befejezés)  
**Típus:** BC Break

---

## Összefoglalás

A `corneltek/cliframework` csomagot leváltjuk **Symfony Console 6.4 LTS**-re. A PR #1138-ban elkezdett munka (6 migrált parancs, Application osztály, DI container) alapján az összes parancsot migráljuk, és az összes PR TODO-t implementáljuk.

---

## Függőségek

### Hozzáadva
- `symfony/console: ^6.4`
- `symfony/dependency-injection: ^6.4`
- `jean85/pretty-package-versions: ^2.0`

### Eltávolítva
- `corneltek/cliframework`

### PHP minimum
- `^8.1` (Symfony 6.4 követelmény, korábbi `^7.2.5 || ^8.0` helyett)

---

## Könyvtárstruktúra

```
src/PhpBrew/
├── Console/
│   ├── Application.php              ← Symfony Application kiterjesztés
│   └── Command/
│       ├── VirtualCommand.php       ← absztrakt alap: use/switch/cd/env/each
│       ├── InitCommand.php
│       ├── KnownCommand.php
│       ├── InstallCommand.php
│       ├── ListCommand.php
│       ├── ListCommandsCommand.php  ← Symfony "list" parancs felváltása
│       ├── UseCommand.php
│       ├── SwitchCommand.php
│       ├── SwitchOffCommand.php
│       ├── EachCommand.php
│       ├── ConfigCommand.php
│       ├── InfoCommand.php
│       ├── EnvCommand.php
│       ├── VariantsCommand.php
│       ├── PathCommand.php
│       ├── CdCommand.php
│       ├── DownloadCommand.php
│       ├── CleanCommand.php
│       ├── UpdateCommand.php
│       ├── CtagsCommand.php
│       ├── ListIniCommand.php
│       ├── SelfUpdateCommand.php
│       ├── RemoveCommand.php
│       ├── PurgeCommand.php
│       ├── OffCommand.php
│       ├── SystemCommand.php
│       ├── SystemOffCommand.php
│       ├── MigratedCommand.php      ← megmarad (régi parancsok átirányítása)
│       ├── Extension/
│
│   Megjegyzés: HelpCommand.php ELHAGYVA — a banner az Application::getHelp()-be
│   kerül, a Symfony Console beépített help parancsát használjuk.
│       │   ├── EnableCommand.php    ← extension:enable
│       │   ├── DisableCommand.php   ← extension:disable
│       │   ├── InstallCommand.php   ← extension:install
│       │   ├── ConfigCommand.php    ← extension:config
│       │   ├── CleanCommand.php     ← extension:clean
│       │   ├── ShowCommand.php      ← extension:show
│       │   └── KnownCommand.php     ← extension:known
│       └── Fpm/
│           ├── StartCommand.php     ← fpm:start
│           ├── StopCommand.php      ← fpm:stop
│           ├── RestartCommand.php   ← fpm:restart
│           └── SetupCommand.php     ← fpm:setup
├── Command/                         ← ÉRINTETLEN (domain logika marad)
├── Tasks/                           ← ÉRINTETLEN
└── ... (összes többi domain osztály)

etc/
└── container.php                    ← Symfony DI konfig
bin/
└── phpbrew                          ← frissített belépési pont
```

---

## Architektúra

### Application (`src/PhpBrew/Console/Application.php`)

Kiterjeszti a `Symfony\Component\Console\Application`-t:
- Megjeleníti a PHPBrew ASCII bannert a `getHelp()`-ben
- A verziót a `jean85/pretty-package-versions`-ből olvassa
- Globális `--no-progress` opciót ad hozzá
- A `console.command` taggel jelölt szolgáltatásokat automatikusan regisztrálja
- A `doRun()` override kezeli a PHPBrew-specifikus kivételeket
- Default parancs: `list-commands`

### DI Container (`etc/container.php`)

Symfony `ContainerBuilder` autowiring-gel:

```php
$container = new ContainerBuilder();
$container->autowire(Application::class)->setPublic(true);
$container->autowire(Command\KnownCommand::class)->addTag('console.command');
// ... minden parancs
$container->compile();
return $container;
```

Új parancs hozzáadása a jövőben: osztály létrehozása + egy `autowire()->addTag()` sor a containerben.

### Belépési pont (`bin/phpbrew`)

```php
(function () {
    foreach ([__DIR__.'/../vendor/autoload.php', __DIR__.'/../../../autoload.php'] as $f) {
        if (file_exists($f)) { require $f; break; }
    }
    $container = require __DIR__ . '/../etc/container.php';
    $container->get(Application::class)->run();
})();
```

---

## Parancs-migrációs minta

### CLIFramework → Symfony Console mapping

| CLIFramework | Symfony Console |
|---|---|
| `brief()` | `setDescription()` a `configure()`-ban |
| `aliases()` | `setAliases()` a `configure()`-ban |
| `usage()` | `setHelp()` a `configure()`-ban |
| `options($opts)` → `$opts->add('u\|update', ...)` | `addOption('update', 'u', InputOption::VALUE_NONE, ...)` |
| `arguments($args)` → `$args->add('version')->validValues(fn)` | `addArgument('version', ..., null, fn(): array)` |
| `$this->options->foo` | `$input->getOption('foo')` |
| `$this->arguments->get('name')` | `$input->getArgument('name')` |
| `$this->logger->info/warn/error/writeln` | `SymfonyStyle::writeln/warning/error` |
| `execute()` void | `execute(InputInterface, OutputInterface): int` → `Command::SUCCESS/FAILURE` |
| `init()` subcommand regisztráció | Külön osztályok, `etc/container.php`-ban registrálva |
| `prepare()` / `finish()` | `initialize()` / nincs finish, `execute()` return értéke |

### VirtualCommand alap

A `use`, `switch`, `cd`, `env`, `each` parancsok shell functionként futnak (a `~/.phpbrew/bashrc` definiálja). Ha valaki közvetlenül hívja a PHP binaryt, kivételt dob:

```php
abstract class VirtualCommand extends Command
{
    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        throw new \RuntimeException(
            "Ha ezt látod, a ~/.phpbrew/bashrc nincs betöltve a shellbe.\n"
            . "Futtasd: source ~/.phpbrew/bashrc"
        );
    }
}
```

### Subcommand névtér (BC Break)

Az alparancsok Symfony Console névtér-szintaxist használnak:

| Régi | Új |
|---|---|
| `phpbrew extension install foo` | `phpbrew extension:install foo` |
| `phpbrew extension enable foo` | `phpbrew extension:enable foo` |
| `phpbrew fpm start` | `phpbrew fpm:start` |

---

## Shell Completion

A Symfony Console 6.4 **beépítetten** tartalmaz shell completion támogatást (bash, zsh, fish). Nincs szükség külső csomagra (`stecman/symfony-console-completion`).

Aktiválás (telepítés után):
```bash
phpbrew completion --help   # shell-specifikus instrukciókat ad
```

Értékjavaslatok parancsokban (Symfony 6.1+ callback API):
```php
->addArgument('version', InputArgument::REQUIRED, 'PHP version', null,
    fn(CompletionInput $input): array => BuildFinder::findInstalledVersions()
)
->addOption('variant', 'v', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
    'Build variant', [], fn(): array => VariantBuilder::getAvailableVariants()
)
```

---

## Hibakezelés

A `Application::doRun()` override kezeli a PHPBrew-specifikus kivételeket, a jelenlegi `Console::runWithTry()` logikáját átvéve:

- `SystemCommandException` → build log utolsó 5 sorát jeleníti meg
- `BadMethodCallException` → alkalmazáslogikai hiba üzenet
- Általános `Exception` → `--debug` flag esetén teljes stack trace, egyébként tiszta hibaüzenet

---

## `--ansi` flag

**Megoldott:** A Symfony Console `Application::configureIO()` automatikusan detektálja a terminal képességeket (ANSI, no-ansi stb.). A shell wrapperekben nincs szükség manuális `--ansi` átadásra.

---

## PR TODO státusz

| TODO | Státusz |
|---|---|
| PHPBrew banner megjelenítése | Implementálva (PR-ban) |
| `jean85/pretty-package-versions` verzióhoz | Implementálva (PR-ban), `^2.0`-ra frissítve |
| DI az Application commandokhoz | Implementálva (PR-ban), teljes migráció szükséges |
| Symfony Service Container (zend-servicemanager helyett) | **Implementálandó** — `symfony/dependency-injection ^6.4` |
| Összes parancs migrálása | **Implementálandó** — ~25 parancs |
| Shell completion | **Megoldott** — Symfony 6.4 beépített, nincs külső csomag |
| Argument suggestion/validation | **Megoldott** — Symfony 6.1+ callback API |
| `--ansi` flag shell wrapperekből | **Megoldott** — Symfony auto-detektál |

---

## Tesztelés

- **Meglévő tesztek** (`tests/`) érintetlenek — domain logikát tesztelnek, nem CLI réteget
- **Új CLI tesztek** — `Symfony\Component\Console\Tester\CommandTester` minden migrált parancshoz
- **Completion tesztek** — `CommandCompletionTester` az argument suggestion callbackekhez
- **phpcs** — meglévő `phpcs.xml.dist` konfiguráció alkalmazandó az új fájlokra
