<?php
/**
 * Created by PhpStorm.
 * User: TouchWorld
 * Date: 2018/2/24
 * Time: 14:38
 */
namespace Iwannamaybe\PhpCas;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Iwannamaybe\PhpCas\Cas
 */
class CasFacade extends Facade
{
	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor()
	{
		return 'cas';
	}
}