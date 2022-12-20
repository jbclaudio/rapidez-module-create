<?php

namespace Rapidez\PackageCreate\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class CreatePackage extends Command
{
    protected $signature = 'rapidez:package:create {package} {--json-vendor=} {--json-package=} {--stub=}';

    protected $description = 'Create a Rapidez package!';

    protected $vendor;
    protected $jsonVendor;
    protected $package;
    protected $JsonPackage;
    protected $relPath;
    protected $packagePath;

    public function handle()
    {
        $vendor = $this->vendor = 'Rapidez';
        $package = $this->package = $this->argument('package');

        $jsonVendor = $this->jsonVendor = $this->option('json-vendor') ?? Str::lower($this->vendor);
        $jsonPackage = $this->jsonPackage = $this->option('json-package') ?? Str::kebab($this->package);

        $packageFolder = config('package-create.package-folder', 'packages');

        $relPath = $this->relPath = __(':packageFolder/:jsonVendor/:jsonPackage', @compact('jsonVendor', 'jsonPackage', 'packageFolder'));
        $packagePath = $this->packagePath = base_path($this->relPath);

        $this->info(__('Creating directory at: :path', ['path' => $relPath]));
        File::makeDirectory($this->packagePath . '/src', 0755, true, true);
        $this->newLine();

        $this->info(__('Building the Rapidez package 🏗️'));
        $this->runStub();
        $this->newLine();

        $this->info(__('Configuring composer repository path'));
        Process::fromShellCommandline(__('composer config repositories.:jsonVendor/:jsonPackage path "./:relPath" --file composer.json', @compact('relPath', 'jsonVendor', 'jsonPackage')))
            ->setTty(true)
            ->setTimeout(null)
            ->run();
        $this->newLine();

        $this->info(__('Installing your new package 🪄'));
        Process::fromShellCommandline(__('composer require ":jsonVendor/:jsonPackage:@dev"', @compact('jsonVendor', 'jsonPackage')))
            ->setTty(true)
            ->setTimeout(null)
            ->run();
        $this->newLine();

        $this->info(__('Your new Rapidez package is installed and ready to use 🚀 Have fun coding!'));
    }

    /**
     * Create package using very basic stubs.
     */
    public function runStub()
    {
        extract(get_object_vars($this));

        // Create composer.json
        File::put($this->packagePath . '/composer.json', __(File::get(__DIR__. '/../../stubs/composer.json'), @compact('vendor', 'package', 'jsonVendor', 'jsonPackage')));

        // Create ServiceProvider
        File::put($this->packagePath . '/src/' . Str::ucfirst(Str::camel($this->package)) . 'ServiceProvider.php', __(File::get(__DIR__. '/../../stubs/src/ServiceProvider.php'), @compact('vendor', 'package', 'jsonVendor', 'jsonPackage')));

        // Create License
        File::put($this->packagePath . '/LICENSE', __(File::get(__DIR__. '/../../stubs/LICENSE')));

        // Create .gitignore
        File::put($this->packagePath . '/.gitignore', __(File::get(__DIR__. '/../../stubs/.gitignore')));
    }
}
