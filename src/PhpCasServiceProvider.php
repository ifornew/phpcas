<?php
/**
 * Created by PhpStorm.
 * User: TouchWorld
 * Date: 2018/2/24
 * Time: 14:37
 */
namespace Iwannamaybe\PhpCas;

use Illuminate\Support\ServiceProvider;

class PhpCasServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$configPath = __DIR__ . '/../config/phpcas.php';
		if (function_exists('config_path')) {
			$publishPath = config_path('phpcas.php');
		} else {
			$publishPath = base_path('config/phpcas.php');
		}
		$this->publishes([$configPath => $publishPath], 'config');
	}

	/**
	 * Register the services providers.
	 *
	 * @return void
	 */
	public function register()
	{
		$configPath = __DIR__ . '/../config/phpcas.php';
		$this->mergeConfigFrom($configPath, 'phpcas');

		$this->app->singleton('phpcas.client', function ($app) {
			return new Client($app['config'],$app['url'],$app['request'],$app['log'],$app['redirect']);
		});
		$this->app->singleton('phpcas', function ($app) {
			return new Cas($app['phpcas.client']);
		});
		$this->app->alias('phpcas', Cas::class);
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return ['phpcas'];
	}
}