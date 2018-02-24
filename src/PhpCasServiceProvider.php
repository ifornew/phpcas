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
	 * Register the services providers.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->singleton('phpcas', function ($app) {
			return new Cas();
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