<?php
/**
 * Created by PhpStorm.
 * User: TouchWorld
 * Date: 2018/2/24
 * Time: 15:18
 */

namespace Iwannamaybe\PhpCas;

use Closure;
use Illuminate\Http\Request;

class Cas
{
	/**
	 * @var Client
	 */
	protected $_Client;

	/**
	 * Cas constructor.
	 *
	 * @param Client $client
	 */
	public function __construct(Client $client)
	{
		$this->_Client = $client;
	}

	/**
	 * Get phpcas login uri
	 *
	 * @param string $redirect redirect back uri
	 *
	 * @return string
	 */
	public function getLoginUri($redirect = null)
	{
		return $this->_Client->getLoginUri($redirect);
	}

	/**
	 * Get phpcas register uri
	 *
	 * @param string $redirect redirect back uri
	 *
	 * @return string
	 */
	public function getRegisterUri($redirect = null)
	{
		return $this->_Client->getRegisterUri($redirect);
	}

	/**
	 * Get phpcas logout uri
	 *
	 * @param string $redirect redirect back uri
	 *
	 * @return string
	 */
	public function getLogoutUri($redirect = null)
	{
		return $this->_Client->getLogoutUri($redirect);
	}

	public function checkAuthentication(Request $request, Closure $next, callable $callback)
	{
		$this->_Client->handLogoutRequest();
		if ($this->_Client->hasTicket()) {
			return $this->_Client->handLoginRequest($callback);
		} else {
			return $next($request);
		}
	}
}