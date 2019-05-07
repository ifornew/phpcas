<?php

namespace Iwannamaybe\PhpCas;

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
	 * @param string $guard    cas guard
	 * @param string $redirect redirect back uri
	 * @param bool   $gateway
	 * @param bool   $renew
	 *
	 * @return string
	 */
	public function getLoginUri(string $guard, string $redirect = null, bool $gateway = false, bool $renew = false)
	{
		return $this->_Client->setGuard($guard)->getLoginUri($redirect, $gateway, $renew);
	}

	/**
	 * Get phpcas logout uri
	 *
	 * @param string $guard    cas guard
	 * @param string $redirect redirect back uri
	 *
	 * @return string
	 */
	public function getLogoutUri(string $guard, string $redirect = null)
	{
		return $this->_Client->setGuard($guard)->getLogoutUri($redirect);
	}

	/**
	 * get the find password uri
	 *
	 * @param string $guard cas guard
	 *
	 * @return string
	 */
	public function getFindPasswordUri(string $guard)
	{
		return $this->_Client->setGuard($guard)->getFindPasswordUri();
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
	 * @param string   $guard
	 * @param callable $authSuccessCallback
	 *
	 * @return \Illuminate\Http\RedirectResponse|null
	 */
	public function checkAuthentication(string $guard, callable $authSuccessCallback)
	{
		if ($this->_Client->setGuard($guard)->hasTicket()) {
			return $this->_Client->setGuard($guard)->handLoginRequest($authSuccessCallback);
		}
		return null;
	}
}