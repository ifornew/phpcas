<?php
/**
 * Created by PhpStorm.
 * User: TouchWorld
 * Date: 2018/2/24
 * Time: 15:19
 */

namespace Iwannamaybe\PhpCas;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

class Client
{
	protected $_Config;
	protected $_Url;
	protected $_Request;

	protected $casVersion;//1.0/2.0/3.0/S1
	protected $casHostName;
	protected $casPort;
	protected $casChannel;
	protected $casUri;
	protected $casLoginUri;
	protected $casLogoutUri;
	protected $casRegisterUri;
	protected $casValidation;
	protected $casCert;

	protected $casFake;
	protected $casFakeUserId;
	//TODO:UDF代理
	protected $casUdfProxy;
	protected $casUdfProxyIp;

	protected $casBaseServerUri;

	public function __construct(Repository $config, UrlGenerator $url, Request $request)
	{
		$this->_Config  = $config;
		$this->_Url     = $url;
		$this->_Request = $request;
		$this->initConfigs();
		$this->initBaseServerUri();
	}

	/**
	 * Init the phpcas configs
	 */
	protected function initConfigs()
	{
		//TODO:UDF代理
		$this->casUdfProxy   = boolval($this->_Config->get('cas.cas_udf_proxy', false));
		$this->casUdfProxyIp = $this->_Config->get('cas.cas_udf_proxy_ip', '127.0.0.1');

		$this->casVersion     = $this->_Config->get('cas.cas_version', '2.0');
		$this->casHostName    = $this->udfProxyCasHost();
		$this->casPort        = intval($this->_Config->get('cas.cas_port', 443));
		$this->casChannel     = $this->_Config->get('cas.cas_channel');
		$this->casUri         = $this->_Config->get('cas.cas_uri');
		$this->casLoginUri    = $this->_Config->get('cas.cas_login_uri');
		$this->casLogoutUri   = $this->_Config->get('cas.cas_logout_uri');
		$this->casRegisterUri = $this->_Config->get('cas.cas_register_uri');
		$this->casValidation  = boolval($this->_Config->get('cas.cas_validation', false));
		$this->casCert        = $this->_Config->get('cas.cas_cert');
		$this->casFake        = boolval($this->_Config->get('cas.cas_fake', false));
		$this->casFakeUserId  = intval($this->_Config->get('cas.cas_fake_user_id', 1));
	}

	/**
	 * Get the phpcas hostname by UDF proxy
	 * @return string
	 */
	protected function udfProxyCasHost()
	{
		if ($this->casUdfProxy && $this->_Request->ip() == $this->casUdfProxyIp) {
			return $this->casUdfProxyIp;
		}
		return $this->_Config->get('cas.cas_hostname');
	}

	/**
	 * init base phpcas server uri
	 */
	public function initBaseServerUri()
	{
		if ($this->casPort == 443) {
			$this->casBaseServerUri = "https://{$this->casHostName}";
		} elseif ($this->casPort == 80) {
			$this->casBaseServerUri = "http://{$this->casHostName}";
		} else {
			$this->casBaseServerUri = "http://{$this->casHostName}:{$this->casPort}";
		}
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
		if ($redirect == null) {
			$redirect = $this->_Url->current();
		}
		return "{$this->casBaseServerUri}{$this->casLoginUri}?service={$redirect}&channel={$this->casChannel}";
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
		if ($redirect == null) {
			$redirect = $this->_Url->current();
		}
		return "{$this->casBaseServerUri}{$this->casRegisterUri}?service={$redirect}&channel={$this->casChannel}";
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
		if ($redirect == null) {
			$redirect = $this->_Url->previous();
		}
		return "{$this->casBaseServerUri}{$this->casLogoutUri}?service={$redirect}&channel={$this->casChannel}";
	}

	/**
	 * Force phpcas verify
	 * @return bool
	 */
	public function forceAuthentication()
	{
		if ($this->casFake) {
			return true;
		}
		return Cas::forceAuthentication();
	}

	/**
	 * check if the user is authenticated (previously or by tickets given in the uri)
	 */
	public function isAuthenticated()
	{
		if ($this->casFake) {
			return true;
		}


		$res          = false;
		$validate_url = '';
		if ($this->_wasPreviouslyAuthenticated()) {
			if ($this->hasTicket()) {
				// User has a additional ticket but was already authenticated
				phpCAS::trace(
					'ticket was present and will be discarded, use renewAuthenticate()'
				);
				if ($this->_clearTicketsFromUrl) {
					phpCAS::trace("Prepare redirect to : " . $this->getURL());
					session()->save();
					header('Location: ' . $this->getURL());
					flush();
					phpCAS::traceExit();
					$res = true;
				} else {
					phpCAS::trace(
						'Already authenticated, but skipping ticket clearing since setNoClearTicketsFromUrl() was used.'
					);
					$res = true;
				}
			} else {
				// the user has already (previously during the session) been
				// authenticated, nothing to be done.
				phpCAS::trace(
					'user was already authenticated, no need to look for tickets'
				);
				$res = true;
			}

			// Mark the auth-check as complete to allow post-authentication
			// callbacks to make use of phpCAS::getUser() and similar methods
			$this->markAuthenticationCall($res);
		} else {
			if ($this->hasTicket()) {
				switch ($this->casVersion) {
					case '1.0':
						// if a Service Ticket was given, validate it
						phpCAS::trace(
							'CAS 1.0 ticket `' . $this->getTicket() . '\' is present'
						);
						$this->validateCAS10(
							$validate_url, $text_response, $tree_response, $renew
						); // if it fails, it halts
						phpCAS::trace(
							'CAS 1.0 ticket `' . $this->getTicket() . '\' was validated'
						);
						session()->put('phpCAS.user', $this->_getUser());
						$res          = true;
						$logoutTicket = $this->getTicket();
						break;
					case '2.0':
					case '3.0':
						// if a Proxy Ticket was given, validate it
						phpCAS::trace(
							'CAS ' . $this->getServerVersion() . ' ticket `' . $this->getTicket() . '\' is present'
						);
						$this->validateCAS20(
							$validate_url, $text_response, $tree_response, $renew
						); // note: if it fails, it halts
						phpCAS::trace(
							'CAS ' . $this->getServerVersion() . ' ticket `' . $this->getTicket() . '\' was validated'
						);
						if ($this->isProxy()) {
							$this->_validatePGT(
								$validate_url, $text_response, $tree_response
							); // idem
							phpCAS::trace('PGT `' . $this->_getPGT() . '\' was validated');
							session()->put('phpCAS.pgt', $this->_getPGT());
						}
						session()->put('phpCAS.user', $this->_getUser());
						if (!empty($this->_attributes)) {
							session()->put('phpCAS.attributes', $this->_attributes);
						}
						$proxies = $this->getProxies();
						if (!empty($proxies)) {
							session()->put('phpCAS.proxies', $this->getProxies());
						}
						$res          = true;
						$logoutTicket = $this->getTicket();
						break;
					case 'S1':
						// if we have a SAML ticket, validate it.
						phpCAS::trace(
							'SAML 1.1 ticket `' . $this->getTicket() . '\' is present'
						);
						$this->validateSA(
							$validate_url, $text_response, $tree_response, $renew
						); // if it fails, it halts
						phpCAS::trace(
							'SAML 1.1 ticket `' . $this->getTicket() . '\' was validated'
						);
						session()->put('phpCAS.user', $this->_getUser());
						session()->put('phpCAS.attributes', $this->_attributes);
						$res          = true;
						$logoutTicket = $this->getTicket();
						break;
					default:
						phpCAS::trace('Protocoll error');
						break;
				}
			} else {
				// no ticket given, not authenticated
				phpCAS::trace('no ticket found');
			}

			// Mark the auth-check as complete to allow post-authentication
			// callbacks to make use of phpCAS::getUser() and similar methods
			$this->markAuthenticationCall($res);

			if ($res) {
				// call the post-authenticate callback if registered.
				if ($this->_postAuthenticateCallbackFunction) {
					$args = $this->_postAuthenticateCallbackArgs;
					array_unshift($args, $logoutTicket);
					call_user_func_array(
						$this->_postAuthenticateCallbackFunction, $args
					);
				}

				// if called with a ticket parameter, we need to redirect to the
				// app without the ticket so that CAS-ification is transparent
				// to the browser (for later POSTS) most of the checks and
				// errors should have been made now, so we're safe for redirect
				// without masking error messages. remove the ticket as a
				// security precaution to prevent a ticket in the HTTP_REFERRER
				if ($this->_clearTicketsFromUrl) {
					phpCAS::trace("Prepare redirect to : " . $this->getURL());
					session()->save();
					header('Location: ' . $this->getURL());
					flush();
					phpCAS::traceExit();
				}
			}
		}
		phpCAS::traceEnd($res);
		return $res;
	}
}