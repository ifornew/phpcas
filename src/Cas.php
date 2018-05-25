<?php

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
		 * @param string $casGuard cas guard
		 * @param string $redirect redirect back uri
		 * @param bool   $gateway
		 * @param bool   $renew
		 *
		 * @return string
		 */
		public function getLoginUri(string $casGuard, string $redirect = null, bool $gateway = false, bool $renew = false)
		{
				return $this->_Client->getLoginUri($casGuard, $redirect, $gateway, $renew);
		}

		/**
		 * Get phpcas register uri
		 *
		 * @param string $casGuard cas guard
		 * @param string $redirect redirect back uri
		 *
		 * @return string
		 */
		public function getRegisterUri(string $casGuard, string $redirect = null)
		{
				return $this->_Client->getRegisterUri($casGuard, $redirect);
		}

		/**
		 * Get phpcas logout uri
		 *
		 * @param string $casGuard cas guard
		 * @param string $redirect redirect back uri
		 *
		 * @return string
		 */
		public function getLogoutUri(string $casGuard, string $redirect = null)
		{
				return $this->_Client->getLogoutUri($casGuard, $redirect);
		}

		/**
		 * get the find password uri
		 *
		 * @param string $casGuard cas guard
		 *
		 * @return string
		 */
		public function getFindPasswordUri(string $casGuard)
		{
				return $this->_Client->getFindPasswordUri($casGuard);
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
				if ($this->_Client->hasTicket()) {
						return $this->_Client->handLoginRequest($authSuccessCallback);
				} else {
						return $next($request);
				}
		}
}