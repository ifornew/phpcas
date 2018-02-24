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
		$this->app->singleton(PhpCas::class, function ($app) {
			return new PhpCas();
		});
		$this->app->alias('phpCas', PhpCas::class);
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return ['phpCas'];
	}
}