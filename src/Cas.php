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
use Illuminate\Support\Facades\Auth;

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
	 * @param bool   $gateway
	 * @param bool   $renew
	 *
	 * @return string
	 */
	public function getLoginUri($redirect = null, $gateway = false, $renew = false)
	{
		return $this->_Client->getLoginUri($redirect, $gateway, $renew);
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

	/**
	 * get the find password uri
	 * @return string
	 */
	public function getFindPasswordUri()
	{
		return $this->_Client->getFindPasswordUri();
	}

	/**
	 * handle the logout request from the server
	 */
	public function handLogoutRequest()
	{
		$this->_Client->handLogoutRequest();
	}

	/**
	 * check the cas authentication while the ticket is received
	 *
	 * @param Request  $request
	 * @param Closure  $next
	 * @param callable $authSuccessCallback
	 *
	 * @return \Illuminate\Http\RedirectResponse|mixed
	 */
	public function checkAuthentication(Request $request, Closure $next, callable $authSuccessCallback)
	{
		if ($this->_Client->checkFake() || $this->_Client->hasTicket()) {
			return $this->_Client->handLoginRequest($authSuccessCallback);
		} else {
			return $next($request);
		}
	}
}