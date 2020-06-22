<?php

namespace bebold\CrudGenerator;

use bebold\CrudGenerator\Commands\CrudGenerator;
use Illuminate\Support\ServiceProvider;

class CrudGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
		if ($this->app->runningInConsole()) {
			$this->commands([
				CrudGenerator::class,
			]);
		}
    }
}
