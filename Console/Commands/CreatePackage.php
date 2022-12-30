<?php

namespace Rapidez\PackageCreate\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class CreatePackage extends Command
{
    protected $signature = 'rapidez:package:create {package} {--json-vendor=} {--json-package=}';

    protected $description = 'Create a Rapidez package!';

    protected $vendor;
    protected $jsonVendor;
    protected $package;
    protected $jsonPackage;
    protected $relPath;
    protected $packagePath;
    protected $includeViews = false;
    protected $includeRoutes = false;
    protected $includeConfig = false;

    public function handle(): void
    {
        $vendor = $this->vendor = 'Rapidez';
        $package = $this->package = $this->argument('package');
        $jsonVendor = $this->jsonVendor = $this->option('json-vendor') ?? Str::lower($this->vendor);
        $jsonPackage = $this->jsonPackage = $this->option('json-package') ?? Str::kebab($this->package);
        $packageFolder = config('package-create.package-folder', 'packages');
        $relPath = $this->relPath = __(':packageFolder/:jsonVendor/:jsonPackage', @compact('jsonVendor', 'jsonPackage', 'packageFolder'));
        $packagePath = $this->packagePath = base_path($this->relPath);

        $this->info(__('Creating package directory at: :path', ['path' => $relPath]));
        $this->addPackageDirectory();

        if ($this->confirm('Will this package include views? 👀', false)) {
            $this->info(__('Okay - I\'ll add the views folder for you!'));
            $this->addViewsDirectory();
        }

        if ($this->confirm('Will this package include routes? 🔗', false)) {
            $this->info(__('Okay - I\'ll add the route file for you!'));
            $this->addRouteFile();
        }

        if ($this->confirm('Would you like me to add a config file? ⚙️', false)) {
            $this->info(__('Okay - I\'ll add it right away!'));
            $this->addConfig();
        }

        $this->info(__('Building the Rapidez package 🏗️'));
        $this->createComposerFile();
        $this->createServiceProvider();
        $this->createREADME();
        $this->createLicence();
        $this->createGitIgnore();;

        $this->info(__('Configuring composer repository path'));
        $this->configureComposerRepository();

        $this->info(__('Installing your new package 🪄'));
        $this->installPackage();

        $this->newLine();
        $this->info(__('Your new Rapidez package is installed and ready to use 🚀 Have fun coding!'));
    }

    public function addPackageDirectory(): void
    {
        File::makeDirectory($this->packagePath . '/src', 0755, true, true);
    }

    public function addViewsDirectory(): void
    {
        $this->includeViews = true;
        File::makeDirectory($this->packagePath . '/resources/views', 0755, true, true);
    }

    public function addRouteFile(): void
    {
        $this->includeRoutes = true;
        File::makeDirectory($this->packagePath . '/routes', 0755, true, true);
        File::put($this->packagePath . '/routes/web.php', File::get(__DIR__. '/../../stubs/routes/web.php'));
    }

    public function addConfig(): void
    {
        extract(get_object_vars($this));
        $this->includeConfig = true;
        File::makeDirectory($this->packagePath . '/config', 0755, true, true);
        File::put(__($this->packagePath . '/config/:jsonPackage.php', @compact('jsonPackage')), File::get(__DIR__. '/../../stubs/config/config.php'));
    }

    public function createComposerFile(): void
    {
        extract(get_object_vars($this));
        File::put($this->packagePath . '/composer.json', __(File::get(__DIR__. '/../../stubs/composer.json'), @compact('vendor', 'package', 'jsonVendor', 'jsonPackage')));
    }

    public function createServiceProvider(): void
    {
        extract(get_object_vars($this));
        $loadViews = $this->includeViews ? __('
        $this->loadViewsFrom(__DIR__.\'/../resources/views\', \':jsonVendor\');
        $this->publishes([
            __DIR__.\'/../resources/views\' => resource_path(\'views/vendor/:jsonVendor\')
        ], \'views\');', @compact('jsonVendor')) : '';

        $loadRoutes = $this->includeRoutes ? '
        $this->loadRoutesFrom(__DIR__.\'/../routes/web.php\');' : '';

        $loadConfig = $this->includeConfig ? __('
        $this->publishes([
            __DIR__.\'/../config/:jsonPackage.php\' => config_path(\':jsonPackage.php\')
        ], \'config\');
        $this->mergeConfigFrom(
            __DIR__.\'/../config/:jsonPackage.php\',
            \':jsonPackage\'
        );', @compact('jsonPackage')) : '';

        File::put($this->packagePath . '/src/' . Str::ucfirst(Str::camel($package)) . 'ServiceProvider.php', __(File::get(__DIR__. '/../../stubs/src/ServiceProvider.php'), @compact(
            'vendor', 'package', 'jsonVendor', 'jsonPackage', 'loadRoutes', 'loadViews', 'loadConfig'
        )));
    }

    public function createREADME(): void
    {
        extract(get_object_vars($this));
        $loadViews = $this->includeViews ? __('
If you haven\'t published the Rapidez views yet, you can publish them with:
```
php artisan vendor:publish --provider="Rapidez\\Core\\RapidezServiceProvider" --tag=views
```

## Views
If you need to change the views you can publish them with:
```
php artisan vendor:publish --provider=":vendor\:package\:packageServiceProvider" --tag=views
```
', @compact('vendor', 'package')) : '';

        $loadConfig = $this->includeConfig ? __('
## Configuration
If you need to change the default configuration you can publish it with:
```
php artisan vendor:publish --provider=":vendor\:package\:packageServiceProvider" --tag=config
```
', @compact('vendor', 'package')) : '';

        File::put($this->packagePath . '/README.md', __(File::get(__DIR__. '/../../stubs/README.md'), @compact(
            'vendor', 'package', 'jsonVendor', 'jsonPackage', 'loadViews', 'loadConfig'
        )));
    }

    public function createLicence(): void
    {
        File::put($this->packagePath . '/LICENSE', __(File::get(__DIR__. '/../../stubs/LICENSE')));
    }

    public function createGitIgnore(): void
    {
        File::put($this->packagePath . '/.gitignore', __(File::get(__DIR__. '/../../stubs/.gitignore')));
    }

    public function configureComposerRepository(): void
    {
        extract(get_object_vars($this));
        Process::fromShellCommandline(__('composer config repositories.:jsonVendor/:jsonPackage path "./:relPath" --file composer.json', @compact(
            'relPath', 'jsonVendor', 'jsonPackage'
        )))
            ->setTty(true)
            ->setTimeout(null)
            ->run();
    }

    public function installPackage(): void
    {
        extract(get_object_vars($this));
        Process::fromShellCommandline(__('composer require ":jsonVendor/:jsonPackage:@dev"', @compact('jsonVendor', 'jsonPackage')))
            ->setTty(true)
            ->setTimeout(null)
            ->run();
    }
}
