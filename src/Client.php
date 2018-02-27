<?php
/**
 * Created by PhpStorm.
 * User: TouchWorld
 * Date: 2018/2/24
 * Time: 15:19
 */

namespace Iwannamaybe\PhpCas;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Contracts\Logging\Log;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Iwannamaybe\PhpCas\Exceptions\AuthenticationException;
use Iwannamaybe\PhpCas\Exceptions\CasInvalidArgumentException;
use Iwannamaybe\PhpCas\Exceptions\OutOfSequenceBeforeAuthenticationCallException;
use Iwannamaybe\PhpCas\Exceptions\OutOfSequenceException;
use Iwannamaybe\PhpCas\ProxyChain\ProxyChainAllowedList;
use Iwannamaybe\PhpCas\Requests\CurlRequest;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class Client
{
	/**
	 * @var Repository $_Config config manager
	 */
	protected $_Config;

	/**
	 * @var UrlGenerator $_Url url manager
	 */
	protected $_Url;

	/**
	 * @var Request $_Request request manager
	 */
	protected $_Request;

	/**
	 * @var Session $_Session session manager
	 */
	protected $_Session;

	/**
	 * @var Redirector $_Redirect
	 */
	protected $_Redirect;

	/**
	 * @var Log $_Logger log manager
	 */
	private $_Logger;

	/**
	 * @var ServerConfig $_ServerConfig
	 */
	protected static $_ServerConfig;

	/**
	 * A translator to ... er ... translate stuff.
	 * @var \Symfony\Component\Translation\TranslatorInterface
	 */
	protected static $translator;

	protected $requestUri;
	protected $ticket;
	protected $isProxy;
	protected $callbackUri;

	/**
	 * @var string $_user The Authenticated user. Written by CAS_Client::_setUser(), read by Client::getUser().
	 */
	private $user = '';

	/**
	 * @var array $attributes user attributes
	 */
	private $attributes = [];

	private $allowedProxyChains;

	/**
	 * @var bool $canChangeSessionId A variable to whether phpcas will use its own session handling. Default = true
	 */
	private $canChangeSessionId = false;

	/**
	 * @var callback $_attributeParserCallbackFunction ;
	 */
	private $AttributeParserCallbackFunction = null;

	/**
	 * @var array $_attributeParserCallbackArgs ;
	 */
	private $AttributeParserCallbackArgs = [];

	/** @var callback $postAuthenticateCallbackFunction ;
	 */
	private $postAuthenticateCallbackFunction = null;

	/**
	 * @var array $postAuthenticateCallbackArgs ;
	 */
	private $postAuthenticateCallbackArgs = [];

	/**
	 * @var array $_proxies This array will store a list of proxies in front of this application. This property will only be populated if this script is being proxied rather than accessed directly.It is set in CAS_Client::validateCAS20() and can be read by Client::getProxies()
	 */
	private $proxies = array();

	/**
	 * a boolean to know if the CAS client is running in callback mode. Written by Client::setCallBackMode(), read by Client::isCallbackMode().
	 * @hideinitializer
	 */
	private $callbackMode = false;

	/**
	 * @var bool $rebroadcast whether to rebroadcast pgtIou/pgtId and logoutRequest
	 */
	private $rebroadcast = false;
	/**
	 * @var array $rebroadcastNodes array of the rebroadcast nodes
	 */
	private $rebroadcastNodes = [];

	/**
	 * @var string $pgt the Proxy Grnting Ticket given by the CAS server (empty otherwise). Written by CAS_Client::_setPGT(), read by Client::getPGT() and Client::hasPGT()
	 */
	private $pgt = '';

	private $authenticationCaller;

	/**
	 * @var boolean $_clearTicketsFromUrl If true, phpCAS will clear session tickets from the URL after a successful authentication.
	 */
	private $clearTicketsFromUrl = true;

	/**
	 * @var array $curlOptions An array to store extra curl options.
	 */
	private $curlOptions = [];

	/**
	 * @var string $_requestImplementation
	 * The class to instantiate for making web requests in readUrl().
	 * The class specified must implement the RequestInterface.
	 * By default CurlRequest is used, but this may be overridden to
	 * supply alternate request mechanisms for testing.
	 */
	private $requestImplementation = CurlRequest::class;

	public function __construct(Repository $config, UrlGenerator $url, Request $request, Session $session, Log $logger, Redirector $redirect)
	{
		$this->_Config   = $config;
		$this->_Url      = $url;
		$this->_Request  = $request;
		$this->_Session  = $session;
		$this->_Logger   = $logger;
		$this->_Redirect = $redirect;
		$this->initConfigs();
	}

	/**
	 * Init the phpcas configs
	 */
	private function initConfigs()
	{
		$serverConfig = new ServerConfig();
		$serverConfig->casFake              = boolval($this->_Config->get('cas.cas_fake', false));
		$serverConfig->casFakeUserId        = intval($this->_Config->get('cas.cas_fake_user_id', 1));

		$serverConfig->casVersion           = $this->_Config->get('cas.cas_version', '2.0');
		$serverConfig->casHostName          = $this->initCasHost();
		$serverConfig->casPort              = intval($this->_Config->get('cas.cas_port', 443));
		$serverConfig->casBaseServerUri     = $this->initBaseServerUri($serverConfig->casHostName, $serverConfig->casPort);
		$serverConfig->casChannel           = $this->_Config->get('cas.cas_channel');
		$serverConfig->casUri               = $this->_Config->get('cas.cas_uri');
		$serverConfig->casLoginUri          = $this->_Config->get('cas.cas_login_uri');
		$serverConfig->casLogoutUri         = $this->_Config->get('cas.cas_logout_uri');
		$serverConfig->casRegisterUri       = $this->_Config->get('cas.cas_register_uri');
		$serverConfig->casValidateUri          = $this->initValidateUri($serverConfig->casVersion);
		$serverConfig->casProxyValidateUri     = $this->initProxyValidateUri($serverConfig->casVersion);
		$serverConfig->casSamlValidateUri      = $this->initSamlValidateUri($serverConfig->casVersion);
		$serverConfig->casCert              = $this->_Config->get('cas.cas_cert');
		$serverConfig->casCertValidate      = boolval($this->_Config->get('cas.cas_cert_validate', false));
		$serverConfig->casCertCnValidate    = boolval($this->_Config->get('cas.cas_cert_cn_validate', false));
		$serverConfig->casLang              = $this->_Config->get('app.locale');
		$serverConfig->sessionCasKey        = $this->_Config->get('cas.cas_session_key');
		$serverConfig->sessionAuthChecked   = "{$serverConfig->sessionCasKey}.authChecked";
		$serverConfig->sessionUserKey       = "{$serverConfig->sessionCasKey}.user";
		$serverConfig->sessionAttributesKey = "{$serverConfig->sessionCasKey}.attributes";
		$serverConfig->sessionPgtKey        = "{$serverConfig->sessionCasKey}.pgt";
		$serverConfig->sessionProxiesKey    = "{$serverConfig->sessionCasKey}.proxies";

		$this::$_ServerConfig = $serverConfig;
	}

	/**
	 * Get the phpcas hostname by UDF proxy
	 * @return string
	 */
	protected function initCasHost()
	{
		$cas_udf_proxy   = boolval($this->_Config->get('cas.cas_udf_proxy', false));
		$cas_udf_proxy_ip = $this->_Config->get('cas.cas_udf_proxy_ip', '127.0.0.1');
		if ($cas_udf_proxy && $this->_Request->ip() == $cas_udf_proxy_ip) {
			return $cas_udf_proxy_ip;
		}
		return $this->_Config->get('cas.cas_hostname');
	}

	/**
	 * init base phpcas server uri
	 *
	 * @param string $casHostName
	 * @param int    $casPort
	 *
	 * @return string
	 */
	private function initBaseServerUri($casHostName, $casPort = 443)
	{
		if ($casPort == 443) {
			return "https://{$casHostName}";
		} elseif ($casPort == 80) {
			return "http://{$casHostName}";
		} else {
			return "http://{$casHostName}:{$casPort}";
		}
	}

	/**
	 * init cas validate uri
	 *
	 * @param string $casVersion
	 *
	 * @return string
	 */
	private function initValidateUri($casVersion = '2.0')
	{
		switch ($casVersion) {
			case '1.0':
				return 'validate';
				break;
			case '2.0':
				return 'serviceValidate';
				break;
			case '3.0':
				return 'p3/serviceValidate';
				break;
			default:
				throw new CasInvalidArgumentException('not support version');
		}
	}

	/**
	 * init cas proxy validate uri
	 *
	 * @param string $casVersion
	 *
	 * @return string
	 */
	private function initProxyValidateUri($casVersion = '2.0')
	{
		switch ($casVersion) {
			case '1.0':
				return '';
				break;
			case '2.0':
				return 'proxyValidate';
				break;
			case '3.0':
				return 'p3/proxyValidate';
				break;
			default:
				throw new CasInvalidArgumentException('not support version');
		}
	}

	/**
	 * init cas saml validate uri
	 *
	 * @param string $casVersion
	 *
	 * @return string
	 */
	private function initSamlValidateUri($casVersion = 'S1')
	{
		if ($casVersion == CasConst::SAML_VERSION_1_1) {
			return 'samlValidate';
		} else {
			throw new CasInvalidArgumentException('not support version');
		}
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
		$params = [
			'service' => $redirect == null ? $this->getRequestUriWithoutTicket() : $redirect,
			'channel' => $this::$_ServerConfig->casChannel
		];
		if ($gateway) {
			$params['gateway'] = 'true';
		}
		if ($renew) {
			$params['renew'] = 'true';
		}
		return $this->buildUri($this::$_ServerConfig->casBaseServerUri . $this::$_ServerConfig->casLoginUri, $params);
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
		return $this->buildUri($this::$_ServerConfig->casBaseServerUri . $this::$_ServerConfig->casRegisterUri, [
			'service' => $redirect == null ? $this->_Url->current() : $redirect,
			'channel' => $this::$_ServerConfig->casChannel
		]);
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
		return $this->buildUri($this::$_ServerConfig->casBaseServerUri . $this::$_ServerConfig->casLogoutUri, [
			'service' => ($redirect == null) ? $this->_Url->previous() : $redirect,
			'channel' => $this::$_ServerConfig->casChannel
		]);
	}

	/**
	 * This method returns the Service Ticket provided in the URL of the request.
	 * @return string service ticket.
	 */
	public function getTicket()
	{
		return $this->ticket;
	}

	/**
	 * This method stores the Service Ticket.
	 *
	 * @param string $ticket The Service Ticket.
	 *
	 * @return void
	 */
	public function setTicket($ticket)
	{
		$this->ticket = $ticket;
	}

	/**
	 * This method tells if a Service Ticket was stored.
	 * @return bool if a Service Ticket has been stored.
	 */
	public function hasTicket()
	{
		return !empty($this->ticket);
	}

	/**
	 * get cas server version
	 * @return string
	 */
	public function getServerVersion()
	{
		return $this::$_ServerConfig->casVersion;
	}

	/**
	 * Set a parameter whether to allow phpCas to change session_id
	 *
	 * @param bool $allowed allow phpCas to change session_id
	 *
	 * @return void
	 */
	public function setCanChangeSessionId($allowed)
	{
		$this->canChangeSessionId = $allowed;
	}

	/**
	 * Get whether phpCas is allowed to change session_id
	 * @return bool
	 */
	public function getCanChangeSessionId()
	{
		return $this->canChangeSessionId;
	}

	/**
	 * This method is used to set additional user curl options.
	 *
	 * @param string $key   name of the curl option
	 * @param string $value value of the curl option
	 *
	 * @return void
	 */
	public function setExtraCurlOption($key, $value)
	{
		$this->curlOptions[$key] = $value;
	}

	/**
	 * Set the Proxy array, probably from persistant storage.
	 *
	 * @param array $proxies An array of proxies
	 */
	private function setProxies($proxies)
	{
		$this->proxies = $proxies;
		if (!empty($proxies)) {
			// For proxy-authenticated requests people are not viewing the URL directly since the client is another application making a web-service call. Because of this, stripping the ticket from the URL is unnecessary and causes another web-service request to be performed. Additionally, if session handling on either the client or the server malfunctions then the subsequent request will not complete successfully.
			$this->setNoClearTicketsFromUrl();
		}
	}

	/**
	 * Answer an array of proxies that are sitting in front of this application. This method will only return a non-empty array if we have received and validated a Proxy Ticket.
	 * @return array
	 * @access public
	 */
	public function getProxies()
	{
		return $this->proxies;
	}

	/**
	 * This method returns the Proxy Granting Ticket given by the CAS server.
	 * @return string the Proxy Granting Ticket.
	 */
	private function getPGT()
	{
		return $this->pgt;
	}

	/**
	 * Configure the client to not send redirect headers and call exit() on authentication success. The normal redirect is used to remove the service ticket from the client's URL, but for running unit tests we need to continue without exiting.
	 * Needed for testing authentication
	 * @return void
	 */
	public function setNoClearTicketsFromUrl()
	{
		$this->clearTicketsFromUrl = false;
	}

	/**
	 * This method sets the CAS user's login name.
	 *
	 * @param string $user the login name of the authenticated user.
	 *
	 * @return void
	 */
	private function setUser($user)
	{
		$this->user = $user;
	}

	/**
	 * This method returns the CAS user's login name.
	 * @return string the login name of the authenticated user
	 * @warning should be called only after Client::forceAuthentication() or Client::isAuthenticated(), otherwise halt with an error.
	 */
	public function getUser()
	{
		// Sequence validation
		$this->ensureAuthenticationCallSuccessful();
		return $this->user;
	}

	/**
	 * Set an array of attributes
	 *
	 * @param array $attributes a key value array of attributes
	 */
	public function setAttributes($attributes)
	{
		$this->attributes = $attributes;
	}

	/**
	 * Set a callback function to be run when parsing CAS attributes. The callback function will be passed a XMLNode as its first parameter,followed by any $additionalArgs you pass.
	 *
	 * @param string $function       callback function to call
	 * @param array  $additionalArgs optional array of arguments
	 *
	 * @return void
	 */
	public function setCasAttributeParserCallback($function, array $additionalArgs = array())
	{
		$this->AttributeParserCallbackFunction = $function;
		$this->AttributeParserCallbackArgs     = $additionalArgs;
	}


	/**
	 * This method stores the Proxy Granting Ticket.
	 *
	 * @param string $pgt The Proxy Granting Ticket.
	 *
	 * @return void
	 */
	private function setPGT($pgt)
	{
		$this->pgt = $pgt;
	}

	/**
	 * Tells if a CAS client is a CAS proxy or not
	 * @return bool true when the CAS client is a CAs proxy, false otherwise
	 */
	public function isProxy()
	{
		return $this->isProxy;
	}

	/**
	 * This method is called to be sure that the user is authenticated. When not authenticated, halt by redirecting to the CAS server; otherwise return true.
	 * @return true when the user is authenticated; otherwise halt.
	 */
	public function forceAuthentication()
	{
		if ($this::$_ServerConfig->casFake) {
			return true;
		} elseif ($this->isAuthenticated()) {
			// the user is authenticated, nothing to be done.
			$this->_Logger->info('no need to authenticate');
			return true;
		} else {
			// the user is not authenticated, redirect to the CAS server
			if ($this->_Session->exists(static::$_ServerConfig->sessionAuthChecked)) {
				$this->_Session->forget(static::$_ServerConfig->sessionAuthChecked);
			}
			$this->redirectToCas(false);
			// never reached
			return false;
		}
	}

	/**
	 * This method is used to redirect the client to the CAS server. It is used by CAS_Client::forceAuthentication() and Client::checkAuthentication().
	 *
	 * @param bool $gateway true to check authentication, false to force it
	 * @param bool $renew   true to force the authentication with the CAS server
	 *
	 * @return void
	 */
	public function redirectToCas($gateway = false, $renew = false)
	{
		$cas_url = $this->getLoginUri(null, $gateway, $renew);
		$this->_Session->save();
		$this->_Logger->info("Redirect to : " . $cas_url);
		$this->_Redirect->to($cas_url);
		//TODO:抛出异常结束程序
		//throw new GracefullTerminationException();
	}

	/**
	 * check if the user is authenticated (previously or by tickets given in the uri)
	 *
	 * @param bool $renew true to force the authentication with the CAS server
	 *
	 * @return true when the user is authenticated. Also may redirect to the same URL without the ticket.
	 */
	public function isAuthenticated($renew = false)
	{
		$res          = false;
		$validate_url = '';
		$logoutTicket = '';
		if ($this::$_ServerConfig->casFake) {
			$res = true;
		} elseif ($this->wasPreviouslyAuthenticated()) {
			if ($this->hasTicket()) {
				// User has a additional ticket but was already authenticated
				$this->_Logger->info('ticket was present and will be discarded, use renewAuthenticate()');
				if ($this->clearTicketsFromUrl) {
					$this->_Logger->info("Prepare redirect to : {$this->getRequestUriWithoutTicket()}");
					$this->_Session->save();
					$this->_Redirect->to($this->getRequestUriWithoutTicket());
					$res = true;
				} else {
					$this->_Logger->info('Already authenticated, but skipping ticket clearing since setNoClearTicketsFromUrl() was used.');
					$res = true;
				}
			} else {
				// the user has already (previously during the session) been authenticated, nothing to be done.
				$this->_Logger->info('user was already authenticated, no need to look for tickets');
				$res = true;
			}
			// Mark the auth-check as complete to allow post-authentication callbacks to make use of phpCAS::getUser() and similar methods
			$this->markAuthenticationCall($res);
		} else {
			if ($this->hasTicket()) {
				switch ($this->getServerVersion()) {
					case '1.0':
						// if a Service Ticket was given, validate it
						$this->_Logger->info("CAS 1.0 ticket '{$this->getTicket()}' is present");
						$this->validateCAS10($validate_url, $text_response, $tree_response, $renew); // if it fails, it halts
						$this->_Logger->info("CAS 1.0 ticket '{$this->getTicket()}' was validated");
						$this->_Session->put($this::$_ServerConfig->sessionUserKey, $this->getUser());
						$res          = true;
						$logoutTicket = $this->getTicket();
						break;
					case '2.0':
					case '3.0':
						// if a Proxy Ticket was given, validate it
						$this->_Logger->info("CAS {$this->getServerVersion()} ticket `{$this->getTicket()}` is present");
						$this->validateCAS20($validate_url, $text_response, $tree_response, $renew); // note: if it fails, it halts
						$this->_Logger->info("CAS {$this->getServerVersion()} ticket `{$this->getTicket()}` was validated");
						if ($this->isProxy()) {
							$this->validatePGT($validate_url, $text_response, $tree_response); // idem
							$this->_Logger->info("PGT `{$this->getPGT()}` was validated");
							$this->_Session->put($this::$_ServerConfig->sessionPgtKey, $this->getPGT());
						}
						$this->_Session->put($this::$_ServerConfig->sessionUserKey, $this->getUser());
						if (!empty($this->attributes)) {
							$this->_Session->put($this::$_ServerConfig->sessionAttributesKey, $this->attributes);
						}
						if (!empty($proxies = $this->getProxies())) {
							$this->_Session->put($this::$_ServerConfig->sessionProxiesKey, $proxies);
						}
						$res          = true;
						$logoutTicket = $this->getTicket();
						break;
					case 'S1':
						// if we have a SAML ticket, validate it.
						$this->_Logger->info("SAML 1.1 ticket `{$this->getTicket()}` is present");
						$this->validateSA($validate_url, $text_response, $tree_response, $renew); // if it fails, it halts
						$this->_Logger->info("SAML 1.1 ticket `{$this->getTicket()}` was validated");
						$this->_Session->put($this::$_ServerConfig->sessionUserKey, $this->getUser());
						$this->_Session->put($this::$_ServerConfig->sessionAttributesKey, $this->attributes);
						$res          = true;
						$logoutTicket = $this->getTicket();
						break;
					default:
						$this->_Logger->info('Protocoll error');
						break;
				}
			} else {
				// no ticket given, not authenticated
				$this->_Logger->info('no ticket found');
			}

			// Mark the auth-check as complete to allow post-authentication callbacks to make use of phpCAS::getUser() and similar methods
			$this->markAuthenticationCall($res);

			if ($res) {
				// call the post-authenticate callback if registered.
				if ($this->postAuthenticateCallbackFunction) {
					$args = $this->postAuthenticateCallbackArgs;
					array_unshift($args, $logoutTicket);
					call_user_func_array($this->postAuthenticateCallbackFunction, $args);
				}

				// if called with a ticket parameter, we need to redirect to the app without the ticket so that CAS-ification is transparent to the browser (for later POSTS) most of the checks and errors should have been made now, so we're safe for redirect without masking error messages. remove the ticket as a security precaution to prevent a ticket in the HTTP_REFERRER
				if ($this->clearTicketsFromUrl) {
					$this->_Logger->info('Prepare redirect to : ' . $this->getRequestUriWithoutTicket());
					$this->_Session->save();
					$this->_Redirect->to($this->getRequestUriWithoutTicket());
				}
			}
		}
		return $res;
	}

	/**
	 * This method is used to validate a PGT; halt on failure.
	 *
	 * @param string     &$validate_url the URL of the request to the CAS server.
	 * @param string     $text_response the response of the CAS server, as is (XML text); result of Client::validateCAS10() or Client::validateCAS20().
	 * @param DOMElement $tree_response the response of the CAS server, as a DOM XML tree; result of Client::validateCAS10() or Client::validateCAS20().
	 *
	 * @return bool true when successfull and issue a AuthenticationException and false on an error
	 */
	private function validatePGT(&$validate_url, $text_response, $tree_response)
	{
		if ($tree_response->getElementsByTagName("proxyGrantingTicket")->length == 0) {
			$this->_Logger->info('<proxyGrantingTicket> not found');
			// authentication succeded, but no PGT Iou was transmitted
			throw new AuthenticationException(
				$this, 'Ticket validated but no PGT Iou transmitted',
				$validate_url, false/*$no_response*/, false/*$bad_response*/,
				$text_response
			);
		} else {
			// PGT Iou transmitted, extract it
			$pgt_iou = trim($tree_response->getElementsByTagName("proxyGrantingTicket")->item(0)->nodeValue);
			if (preg_match('/PGTIOU-[\.\-\w]/', $pgt_iou)) {
				$pgt = $this->loadPGT($pgt_iou);
				if ($pgt == false) {
					$this->_Logger->info('could not load PGT');
					throw new AuthenticationException(
						$this,
						'PGT Iou was transmitted but PGT could not be retrieved',
						$validate_url,
						false,
						false,
						$text_response
					);
				}
				$this->setPGT($pgt);
			} else {
				$this->_Logger->info('PGTiou format error');
				throw new AuthenticationException(
					$this,
					'PGT Iou was transmitted but has wrong format',
					$validate_url,
					false,
					false,
					$text_response
				);
			}
		}
		return true;
	}

	/**
	 * Mark the caller of authentication. This will help client integraters determine problems with their code flow if they call a function such as getUser() before authentication has occurred.
	 *
	 * @param bool $auth True if authentication was successful, false otherwise.
	 *
	 * @return void
	 */
	public function markAuthenticationCall($auth)
	{
		// store where the authentication has been checked and the result
		$dbg                        = debug_backtrace();
		$this->authenticationCaller = array(
			'file'   => $dbg[1]['file'],
			'line'   => $dbg[1]['line'],
			'method' => $dbg[1]['class'] . '::' . $dbg[1]['function'],
			'result' => (boolean)$auth
		);
	}

	/**
	 * This method returns true when the CAs client is running i callback mode,false otherwise.
	 * @return boolean.
	 */
	private function isCallbackMode()
	{
		return $this->callbackMode;
	}

	/**
	 * This method tells if the current session is authenticated.
	 * @return true if authenticated based soley on $_SESSION variable
	 */
	public function isSessionAuthenticated()
	{
		return $this->_Session->has($this::$_ServerConfig->sessionUserKey);
	}

	/**
	 * This method tells if the user has already been (previously) authenticated by looking into the session variables.
	 * @note This function switches to callback mode when needed.
	 * @return true when the user has already been authenticated; false otherwise.
	 */
	private function wasPreviouslyAuthenticated()
	{
		if ($this->isCallbackMode()) {
			// Rebroadcast the pgtIou and pgtId to all nodes
			if ($this->rebroadcast && !$this->_Request->has('rebroadcast')) {
				$this->rebroadcast(CasConst::PGTIOU);
			}
			if ($this->rebroadcast && !isset($_POST['rebroadcast'])) {
				$this->rebroadcast(CasConst::PGTIOU);
			}
			$this->callback();
		}
		$auth = false;
		if ($this->isProxy()) {
			// CAS proxy: username and PGT must be present
			if ($this->isSessionAuthenticated() && $this->_Session->has($this::$_ServerConfig->sessionPgtKey)) {
				// authentication already done
				$this->setUser($this->_Session->get($this::$_ServerConfig->sessionUserKey));
				if ($this->_Session->exists($this::$_ServerConfig->sessionAttributesKey)) {
					$this->setAttributes($this->_Session->get($this::$_ServerConfig->sessionAttributesKey));
				}
				$this->setPGT($this->_Session->get($this::$_ServerConfig->sessionPgtKey));
				$this->_Logger->info("user = `{$this->_Session->get($this::$_ServerConfig->sessionUserKey)}`, PGT = `{$this->_Session->get($this::$_ServerConfig->sessionPgtKey)}`");

				// Include the list of proxies
				if ($this->_Session->exists($this::$_ServerConfig->sessionProxiesKey)) {
					$this->setProxies($this->_Session->get($this::$_ServerConfig->sessionProxiesKey));
					$this->_Logger->info('proxies = "' . implode('", "', $this->_Session->get($this::$_ServerConfig->sessionProxiesKey)) . '"');
				}

				//重要修改
				$auth = true;
			} elseif ($this->isSessionAuthenticated() && !$this->_Session->has($this::$_ServerConfig->sessionPgtKey)
			) {
				// these two variables should be empty or not empty at the same time
				$this->_Logger->info("username found (`{$this->_Session->get($this::$_ServerConfig->sessionUserKey)}``) but PGT is empty");
				// unset all tickets to enforce authentication
				$this->_Session->forget($this::$_ServerConfig->sessionCasKey);
				$this->setTicket('');
			} elseif (!$this->isSessionAuthenticated() && $this->_Session->has($this::$_ServerConfig->sessionPgtKey)) {
				// these two variables should be empty or not empty at the same time
				$this->_Logger->info("PGT found (`{$this->_Session->get($this::$_ServerConfig->sessionPgtKey)}`) but username is empty");
				// unset all tickets to enforce authentication
				$this->_Session->forget($this::$_ServerConfig->sessionCasKey);
				$this->setTicket('');
			} else {
				$this->_Logger->info('neither user nor PGT found');
			}
		} else {
			// `simple' CAS client (not a proxy): username must be present
			if ($this->isSessionAuthenticated()) {
				// authentication already done
				$this->setUser($this->_Session->get($this::$_ServerConfig->sessionUserKey));
				if ($this->_Session->exists($this::$_ServerConfig->sessionAttributesKey)) {
					$this->setAttributes($this->_Session->get($this::$_ServerConfig->sessionAttributesKey));
				}
				$this->_Logger->info('user = `' . $this->_Session->get($this::$_ServerConfig->sessionUserKey) . '\'');

				// Include the list of proxies
				if ($this->_Session->exists($this::$_ServerConfig->sessionProxiesKey)) {
					$this->setProxies($this->_Session->get($this::$_ServerConfig->sessionProxiesKey));
					$this->_Logger->info('proxies = "' . implode('", "', $this->_Session->get($this::$_ServerConfig->sessionProxiesKey)) . '"');
				}

				$auth = true;
			} else {
				$this->_Logger->info('no user found');
			}
		}
		return $auth;
	}

	/**
	 * This method is called by CAS_Client::CAS_Client() when running in callback mode. It stores the PGT and its PGT Iou, prints its output and halts.
	 * @return void
	 */
	private function callback()
	{
		$pgt_iou = $_GET['pgtIou'];
		$pgt     = $_GET['pgtId'];
		if (preg_match('/PGTIOU-[\.\-\w]/', $pgt_iou)) {
			if (preg_match('/[PT]GT-[\.\-\w]/', $pgt)) {
				$this->_Logger->info('Storing PGT `' . $pgt . '\' (id=`' . $pgt_iou . '\')');
				echo '<p>Storing PGT `' . $pgt . '\' (id=`' . $pgt_iou . '\').</p>';
				$this->storePGT($pgt, $pgt_iou);
				$this->_Logger->info("Successfull Callback");
			} else {
				$this->_Logger->error('PGT format invalid' . $pgt);
			}
		} else {
			$this->_Logger->error('PGTiou format invalid' . $pgt_iou);
		}

		// Flush the buffer to prevent from sending anything other then a 200 Success Status back to the CAS Server. The Exception would normally report as a 500 error.
		flush();
		//throw new GracefullTerminationException();
	}

	/**
	 * Ensure that authentication was checked. Terminate with exception if no authentication was performed
	 * @throws OutOfSequenceException
	 * @return void
	 */
	public function ensureAuthenticationCallSuccessful()
	{
		$this->ensureAuthenticationCalled();
		if (!$this->authenticationCaller['result']) {
			throw new OutOfSequenceException("authentication was checked (by {$this->getAuthenticationCallerMethod()}() at {$this->getAuthenticationCallerFile()}:{$this->getAuthenticationCallerLine()}) but the method returned false");
		}
	}

	/**
	 * Answer information about the authentication caller. Throws a CAS_OutOfSequenceException if wasAuthenticationCalled() is false and markAuthenticationCall() didn't happen.
	 * @return array Keys are 'file', 'line', and 'method'
	 */
	public function getAuthenticationCallerFile()
	{
		$this->ensureAuthenticationCalled();
		return $this->authenticationCaller['file'];
	}

	/**
	 * Answer information about the authentication caller. Throws a CAS_OutOfSequenceException if wasAuthenticationCalled() is false and markAuthenticationCall() didn't happen.
	 * @return array Keys are 'file', 'line', and 'method'
	 */
	public function getAuthenticationCallerLine()
	{
		$this->ensureAuthenticationCalled();
		return $this->authenticationCaller['line'];
	}

	/**
	 * Answer information about the authentication caller. Throws a CAS_OutOfSequenceException if wasAuthenticationCalled() is false and markAuthenticationCall() didn't happen.
	 * @return array Keys are 'file', 'line', and 'method'
	 */
	public function getAuthenticationCallerMethod()
	{
		$this->ensureAuthenticationCalled();
		return $this->authenticationCaller['method'];
	}

	/**
	 * Ensure that authentication was checked. Terminate with exception if no authentication was performed
	 * @throws OutOfSequenceBeforeAuthenticationCallException
	 * @return void
	 */
	private function ensureAuthenticationCalled()
	{
		if (!$this->wasAuthenticationCalled()) {
			throw new OutOfSequenceBeforeAuthenticationCallException();
		}
	}

	/**
	 * Answer true if authentication has been checked.
	 * @return bool
	 */
	public function wasAuthenticationCalled()
	{
		return !empty($this->authenticationCaller);
	}

	/**
	 * Initialize the translator instance if necessary.
	 * @return \Symfony\Component\Translation\TranslatorInterface
	 */
	protected static function translator()
	{
		if (static::$translator === null) {
			static::$translator = new Translator('en');
			static::$translator->addLoader('array', new ArrayLoader());
			static::setLocale('en');
		}
		return static::$translator;
	}

	/**
	 * Get the translator instance in use
	 * @return \Symfony\Component\Translation\TranslatorInterface
	 */
	public static function getTranslator()
	{
		return static::translator();
	}

	/**
	 * Set the current translator locale and indicate if the source locale file exists
	 *
	 * @param string $locale
	 *
	 * @return bool
	 */
	public static function setLocale($locale)
	{
		$locale = preg_replace_callback('/\b([a-z]{2})[-_](?:([a-z]{4})[-_])?([a-z]{2})\b/', function ($matches) {
			return $matches[1] . '_' . (!empty($matches[2]) ? ucfirst($matches[2]) . '_' : '') . strtoupper($matches[3]);
		}, strtolower($locale));
		if (file_exists($filename = __DIR__ . '/Lang/' . $locale . '.php')) {
			static::translator()->setLocale($locale);
			// Ensure the locale has been loaded.
			static::translator()->addResource('array', require $filename, $locale);
			return true;
		}
		return false;
	}

	/**
	 * This method is used to append query parameters to an url. Since the url might already contain parameter it has to be detected and to build a proper Uri
	 *
	 * @param string $url    base url to add the query params to
	 * @param array  $params params in query form with & separated
	 *
	 * @return string uri with query params
	 */
	public function buildUri($url, array $params)
	{
		$seperate = (strstr($url, '?') === false) ? '?' : '&';
		$query    = http_build_query($params);
		return $url . $seperate . $query;
	}

	/**
	 * This method is used to validate a CAS 1,0 ticket; halt on failure, and
	 * sets $validate_url, $text_reponse and $tree_response on success.
	 *
	 * @param string     &$validate_url  reference to the the URL of the request to
	 *                                   the CAS server.
	 * @param string     &$text_response reference to the response of the CAS
	 *                                   server, as is (XML text).
	 * @param DOMElement &$tree_response reference to the response of the CAS
	 *                                   server, as a DOM XML tree.
	 * @param bool       $renew          true to force the authentication with the CAS server
	 *
	 * @return bool true when successfull and issue a CAS_AuthenticationException
	 * and false on an error
	 */
	protected function validateCAS10(&$validate_url, &$text_response, &$tree_response, $renew = false)
	{
		$query_params['ticket'] = urlencode($this->getTicket());
		if ($renew) {
			$query_params['renew'] = 'true';
		}
		// build the URL to validate the ticket
		$validate_url = $this->buildUri($this->getTicketValidateUri(), $query_params);

		// open and read the URL
		if (!$this->readUri($validate_url, $headers, $text_response, $err_msg)) {
			$this->_Logger->info("could not open URL '{$validate_url}' to validate ({$err_msg})");
			throw new AuthenticationException(
				$this,
				'CAS 1.0 ticket not validated',
				$validate_url,
				true
			);
		}
		if (preg_match('/^no\n/', $text_response)) {
			$this->_Logger->info('Ticket has not been validated');
			throw new AuthenticationException(
				$this,
				'ST not validated',
				$validate_url,
				false,
				false,
				$text_response
			);
		} elseif (!preg_match('/^yes\n/', $text_response)) {
			$this->_Logger->info('ill-formed response');
			throw new AuthenticationException(
				$this,
				'Ticket not validated',
				$validate_url,
				false,
				true,
				$text_response
			);
		}
		// ticket has been validated, extract the user name
		$arr = preg_split('/\n/', $text_response);
		$this->setUser(trim($arr[1]));
		$this->renameSession($this->getTicket());
		return true;
	}

	/**
	 * This method is used to validate a cas 2.0 ST or PT; halt on failure Used for all CAS 2.0 validations
	 *
	 * @param string     $validate_url  the url of the reponse
	 * @param string     $text_response the text of the repsones
	 * @param DOMElement $tree_response the domxml tree of the respones
	 * @param bool       $renew         true to force the authentication with the CAS server and false on an error
	 *
	 * @throws AuthenticationException
	 * @return bool
	 */
	public function validateCAS20(&$validate_url, &$text_response, &$tree_response, $renew = false)
	{
		if ($this->getAllowedProxyChains()->isProxyingAllowed()) {
			$validate_base_url = $this->getProxyTicketValidateUri();
		} else {
			$validate_base_url = $this->getTicketValidateUri();
		}
		$query_params['ticket'] = urlencode($this->getTicket());
		if ($this->isProxy()) {
			// pass the callback url for CAS proxies
			$query_params['pgtUrl'] = urlencode($this->getCallbackUri());
		}
		if ($renew) {
			$query_params['renew'] = 'true';
		}
		$validate_url = $this->buildUri($validate_base_url, $query_params);

		// open and read the URL
		if (!$this->readUri($validate_url, $headers, $text_response, $err_msg)) {
			$this->_Logger->info('could not open URL \'' . $validate_url . '\' to validate (' . $err_msg . ')');
			throw new AuthenticationException(
				$this,
				'Ticket not validated',
				$validate_url,
				true
			);
		}

		$dom = new DOMDocument();
		// Fix possible whitspace problems
		$dom->preserveWhiteSpace = false;
		// CAS servers should only return data in utf-8
		$dom->encoding = "utf-8";
		// read the response of the CAS server into a DOMDocument object
		if (!($dom->loadXML($text_response))) {
			// read failed
			throw new AuthenticationException(
				$this,
				'Ticket not validated',
				$validate_url,
				false,
				true,
				$text_response
			);
		} elseif (!($tree_response = $dom->documentElement)) {
			// read the root node of the XML tree failed
			throw new AuthenticationException(
				$this,
				'Ticket not validated',
				$validate_url,
				false,
				true,
				$text_response
			);
		} elseif ($tree_response->localName != 'serviceResponse') {
			// insure that tag name is 'serviceResponse' bad root node
			throw new AuthenticationException(
				$this,
				'Ticket not validated',
				$validate_url,
				false,
				true,
				$text_response
			);
		} elseif ($tree_response->getElementsByTagName("authenticationFailure")->length != 0) {
			// authentication failed, extract the error code and message and throw exception
			$auth_fail_list = $tree_response->getElementsByTagName("authenticationFailure");
			throw new AuthenticationException(
				$this,
				'Ticket not validated',
				$validate_url,
				false,
				false,
				$text_response,
				$auth_fail_list->item(0)->getAttribute('code'),
				trim($auth_fail_list->item(0)->nodeValue)
			);
		} elseif ($tree_response->getElementsByTagName("authenticationSuccess")->length != 0) {
			// authentication succeded, extract the user name
			$success_elements = $tree_response->getElementsByTagName("authenticationSuccess");
			if ($success_elements->item(0)->getElementsByTagName("user")->length == 0) {
				// no user specified => error
				throw new AuthenticationException(
					$this,
					'Ticket not validated',
					$validate_url,
					false,
					true,
					$text_response
				);
			} else {
				$this->setUser(trim($success_elements->item(0)->getElementsByTagName("user")->item(0)->nodeValue));
				$this->readExtraAttributesCas20($success_elements);
				// Store the proxies we are sitting behind for authorization checking
				$proxyList = array();
				if (sizeof($arr = $success_elements->item(0)->getElementsByTagName("proxy")) > 0) {
					foreach ($arr as $proxyElem) {
						$this->_Logger->info('Found Proxy:' . $proxyElem->nodeValue);
						$proxyList[] = trim($proxyElem->nodeValue);
					}
					$this->setProxies($proxyList);
					$this->_Logger->info('Storing Proxy List');
				}
				// Check if the proxies in front of us are allowed
				if (!$this->getAllowedProxyChains()->isProxyListAllowed($proxyList)) {
					throw new AuthenticationException(
						$this,
						'Proxy not allowed',
						$validate_url,
						false,
						true,
						$text_response
					);
				} else {
					$this->renameSession($this->getTicket());
					return true;
				}
			}
		} else {
			throw new AuthenticationException(
				$this,
				'Ticket not validated',
				$validate_url,
				false,
				true,
				$text_response
			);
		}
	}

	/**
	 * This method is used to validate a SAML TICKET; halt on failure, and sets $validate_url, $text_reponse and $tree_response on success. These parameters are used later by CAS_Client::_validatePGT() for CAS proxies.
	 *
	 * @param string     &$validate_url  reference to the the URL of the request to the CAS server.
	 * @param string     &$text_response reference to the response of the CAS server, as is (XML text).
	 * @param DOMElement &$tree_response reference to the response of the CAS server, as a DOM XML tree.
	 * @param bool       $renew          true to force the authentication with the CAS server
	 *
	 * @return bool true when successfull and issue a AuthenticationException and false on an error
	 */
	public function validateSA(&$validate_url, &$text_response, &$tree_response, $renew = false)
	{
		// build the URL to validate the ticket
		$validate_url = $this->getSamlValidateUri($renew);

		// open and read the URL
		if (!$this->readUri($validate_url, $headers, $text_response, $err_msg)) {
			$this->_Logger->info("could not open URL `{$validate_url}` to validate ({$err_msg})");
			throw new AuthenticationException(
				$this,
				'SA not validated',
				$validate_url,
				true
			);
		}
		$this->_Logger->info('server version: ' . $this->getServerVersion());
		// analyze the result depending on the version
		if ($this->getServerVersion() == CasConst::SAML_VERSION_1_1) {
			// create new DOMDocument Object
			$dom = new DOMDocument();
			// Fix possible whitspace problems
			$dom->preserveWhiteSpace = false;
			// read the response of the CAS server into a DOM object
			if (!($dom->loadXML($text_response))) {
				$this->_Logger->info('dom->loadXML() failed');
				throw new AuthenticationException(
					$this,
					'SA not validated',
					$validate_url,
					false,
					true,
					$text_response
				);
			}
			// read the root node of the XML tree
			if (!($tree_response = $dom->documentElement)) {
				$this->_Logger->info('documentElement() failed');
				throw new AuthenticationException(
					$this,
					'SA not validated',
					$validate_url,
					false,
					true,
					$text_response
				);
			} elseif ($tree_response->localName != 'Envelope') {
				// insure that tag name is 'Envelope'
				$this->_Logger->info("bad XML root node (should be `Envelope` instead of `{$tree_response->localName}`");
				throw new AuthenticationException(
					$this,
					'SA not validated',
					$validate_url,
					false,
					true,
					$text_response
				);
			} elseif ($tree_response->getElementsByTagName("NameIdentifier")->length != 0) {
				// check for the NameIdentifier tag in the SAML response
				$success_elements = $tree_response->getElementsByTagName("NameIdentifier");
				$this->_Logger->info('NameIdentifier found');
				$user = trim($success_elements->item(0)->nodeValue);
				$this->_Logger->info('user = `' . $user . '`');
				$this->setUser($user);
				$this->setSessionAttributes($text_response);
				$this->renameSession($this->getTicket());
				return true;
			} else {
				$this->_Logger->info('no <NameIdentifier> tag found in SAML payload');
				throw new AuthenticationException(
					$this,
					'SA not validated',
					$validate_url,
					false,
					true,
					$text_response
				);
			}
		}
		return false;
	}

	/**
	 * This method is used to acces a remote URL.
	 *
	 * @param string $url      the URL to access.
	 * @param string &$headers an array containing the HTTP header lines of the
	 *                         response (an empty array on failure).
	 * @param string &$body    the body of the response, as a string (empty on
	 *                         failure).
	 * @param string &$err_msg an error message, filled on failure.
	 *
	 * @return true on success, false otherwise (in this later case, $err_msg
	 * contains an error message).
	 */
	private function readUri($url, &$headers, &$body, &$err_msg)
	{
		/** @var CurlRequest $request */
		$request = new $this->requestImplementation;
		if (count($this->curlOptions)) {
			$request->setCurlOptions($this->curlOptions);
		}
		$request->setUrl($url);
		if (empty($this::$_ServerConfig->casCert) && $this::$_ServerConfig->casCertValidate) {
			$this->_Logger->error('one of the methods setCasCACert() or setNoCasCertValidation() must be called.');
		}
		if (!empty($this::$_ServerConfig->casCert)) {
			$request->setSslCaCert($this::$_ServerConfig->casCert, $this::$_ServerConfig->casCertCnValidate);
		}
		// add extra stuff if SAML
		if ($this::$_ServerConfig->casVersion == CasConst::SAML_VERSION_1_1) {
			$request->addHeader("soapaction: http://www.oasis-open.org/committees/security");
			$request->addHeader("cache-control: no-cache");
			$request->addHeader("pragma: no-cache");
			$request->addHeader("accept: text/xml");
			$request->addHeader("connection: keep-alive");
			$request->addHeader("content-type: text/xml");
			$request->makePost();
			$request->setPostBody($this->buildSAMLPayload());
		}
		if ($request->send()) {
			$headers = $request->getResponseHeaders();
			$body    = $request->getResponseBody();
			$err_msg = '';
			return true;
		} else {
			$headers = '';
			$body    = '';
			$err_msg = $request->getErrorMessage();
			return false;
		}
	}

	/**
	 * Renaming the session
	 *
	 * @param string $ticket name of the ticket
	 *
	 * @return void
	 */
	private function renameSession($ticket)
	{
		if ($this->getCanChangeSessionId()) {
			if (!empty($this->user)) {
				$this->_Session->migrate();
				$this->_Session->setId(md5($ticket));
				return;

				$old_session = $this->_Session->all();
				$this->_Logger->info("Killing session: " . $this->_Session->getId());
				$this->_Session->migrate(true);
				// set up a new session, of name based on the ticket
				$session_id = preg_replace('/[^a-zA-Z0-9\-]/', '', $ticket);
				$this->_Logger->info("Starting session: " . $session_id);
				//session()->setId($session_id);
				//session()->start();
				$this->_Logger->info('Restoring old session vars');
				$this->_Session->replace($old_session);
			} else {
				$this->_Logger->info('Session should only be renamed after successfull authentication');
			}
		} else {
			$this->_Logger->info('Skipping session rename since phpCAS is not handling the session.');
		}
	}

	/**
	 * This method will parse the DOM and pull out the attributes from the SAML payload and put them into an array, then put the array into the session.
	 *
	 * @param string $text_response the SAML payload.
	 *
	 * @return bool true when successfull and false if no attributes a found
	 */
	private function setSessionAttributes($text_response)
	{
		$attr_array = array();
		// create new DOMDocument Object
		$dom = new DOMDocument();
		// Fix possible whitspace problems
		$dom->preserveWhiteSpace = false;
		if (($dom->loadXML($text_response))) {
			$xPath = new DOMXpath($dom);
			$xPath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:1.0:protocol');
			$xPath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:1.0:assertion');
			$nodelist = $xPath->query("//saml:Attribute");

			if ($nodelist) {
				foreach ($nodelist as $node) {
					$xres        = $xPath->query("saml:AttributeValue", $node);
					$name        = $node->getAttribute("AttributeName");
					$value_array = array();
					foreach ($xres as $node2) {
						$value_array[] = $node2->nodeValue;
					}
					$attr_array[$name] = $value_array;
				}
				// UGent addition...
				foreach ($attr_array as $attr_key => $attr_value) {
					if (count($attr_value) > 1) {
						$this->attributes[$attr_key] = $attr_value;
						$this->_Logger->info("* " . $attr_key . "=" . print_r($attr_value, true));
					} else {
						$this->attributes[$attr_key] = $attr_value[0];
						$this->_Logger->info("* " . $attr_key . "=" . $attr_value[0]);
					}
				}
				return true;
			} else {
				$this->_Logger->info("SAML Attributes are empty");
			}
		}
		return false;
	}

	/**
	 * This method will parse the DOM and pull out the attributes from the XML payload and put them into an array, then put the array into the session.
	 *
	 * @param \DOMNodeList $success_elements payload of the response
	 *
	 * @return bool true when successfull, halt otherwise by calling
	 * Client::_authError().
	 */
	private function readExtraAttributesCas20($success_elements)
	{
		$extra_attributes = array();
		// "Jasig Style" Attributes:
		//
		// 	<cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
		// 		<cas:authenticationSuccess>
		// 			<cas:user>jsmith</cas:user>
		// 			<cas:attributes>
		// 				<cas:attraStyle>RubyCAS</cas:attraStyle>
		// 				<cas:surname>Smith</cas:surname>
		// 				<cas:givenName>John</cas:givenName>
		// 				<cas:memberOf>CN=Staff,OU=Groups,DC=example,DC=edu</cas:memberOf>
		// 				<cas:memberOf>CN=Spanish Department,OU=Departments,OU=Groups,DC=example,DC=edu</cas:memberOf>
		// 			</cas:attributes>
		// 			<cas:proxyGrantingTicket>PGTIOU-84678-8a9d2sfa23casd</cas:proxyGrantingTicket>
		// 		</cas:authenticationSuccess>
		// 	</cas:serviceResponse>
		//
		if ($this->AttributeParserCallbackFunction !== null && is_callable($this->AttributeParserCallbackFunction)) {
			array_unshift($this->AttributeParserCallbackArgs, $success_elements->item(0));
			$this->_Logger->info("Calling attritubeParser callback");
			$extra_attributes = call_user_func_array($this->AttributeParserCallbackFunction, $this->AttributeParserCallbackArgs);
		} elseif ($success_elements->item(0)->getElementsByTagName("attributes")->length != 0) {
			$attr_nodes = $success_elements->item(0)->getElementsByTagName("attributes");
			$this->_Logger->info("Found nested jasig style attributes");
			if ($attr_nodes->item(0)->hasChildNodes()) {
				// Nested Attributes
				foreach ($attr_nodes->item(0)->childNodes as $attr_child) {
					$this->_Logger->info("Attribute [{$attr_child->localName}] = {$attr_child->nodeValue}");
					$this->addAttributeToArray($extra_attributes, $attr_child->localName, $attr_child->nodeValue);
				}
			}
		} else {
			// "RubyCAS Style" attributes
			//
			// 	<cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
			// 		<cas:authenticationSuccess>
			// 			<cas:user>jsmith</cas:user>
			//
			// 			<cas:attraStyle>RubyCAS</cas:attraStyle>
			// 			<cas:surname>Smith</cas:surname>
			// 			<cas:givenName>John</cas:givenName>
			// 			<cas:memberOf>CN=Staff,OU=Groups,DC=example,DC=edu</cas:memberOf>
			// 			<cas:memberOf>CN=Spanish Department,OU=Departments,OU=Groups,DC=example,DC=edu</cas:memberOf>
			//
			// 			<cas:proxyGrantingTicket>PGTIOU-84678-8a9d2sfa23casd</cas:proxyGrantingTicket>
			// 		</cas:authenticationSuccess>
			// 	</cas:serviceResponse>
			//
			$this->_Logger->info("Testing for rubycas style attributes");
			$childnodes = $success_elements->item(0)->childNodes;
			foreach ($childnodes as $attr_node) {
				switch ($attr_node->localName) {
					case 'user':
					case 'proxies':
					case 'proxyGrantingTicket':
						continue;
					default:
						if (strlen(trim($attr_node->nodeValue))) {
							$this->_Logger->info("Attribute [{$attr_node->localName}] = {$attr_node->nodeValue}");
							$this->addAttributeToArray($extra_attributes, $attr_node->localName, $attr_node->nodeValue);
						}
				}
			}
		}
		// "Name-Value" attributes.
		//
		// Attribute format from these mailing list thread:
		// http://jasig.275507.n4.nabble.com/CAS-attributes-and-how-they-appear-in-the-CAS-response-td264272.html
		// Note: This is a less widely used format, but in use by at least two institutions.
		//
		// 	<cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
		// 		<cas:authenticationSuccess>
		// 			<cas:user>jsmith</cas:user>
		//
		// 			<cas:attribute name='attraStyle' value='Name-Value' />
		// 			<cas:attribute name='surname' value='Smith' />
		// 			<cas:attribute name='givenName' value='John' />
		// 			<cas:attribute name='memberOf' value='CN=Staff,OU=Groups,DC=example,DC=edu' />
		// 			<cas:attribute name='memberOf' value='CN=Spanish Department,OU=Departments,OU=Groups,DC=example,DC=edu' />
		//
		// 			<cas:proxyGrantingTicket>PGTIOU-84678-8a9d2sfa23casd</cas:proxyGrantingTicket>
		// 		</cas:authenticationSuccess>
		// 	</cas:serviceResponse>
		//
		if (!count($extra_attributes) && $success_elements->item(0)->getElementsByTagName("attribute")->length != 0) {
			/** @var \DOMNodeList $attr_nodes */
			$attr_nodes = $success_elements->item(0)->getElementsByTagName("attribute");
			$firstAttr  = $attr_nodes->item(0);
			if (!$firstAttr->hasChildNodes() && $firstAttr->hasAttribute('name') && $firstAttr->hasAttribute('value')) {
				$this->_Logger->info("Found Name-Value style attributes");
				// Nested Attributes
				foreach ($attr_nodes as $attr_node) {
					if ($attr_node->hasAttribute('name') && $attr_node->hasAttribute('value')) {
						$this->_Logger->info("Attribute [{$attr_node->getAttribute('name')}] = {$attr_node->getAttribute('value')}");
						$this->addAttributeToArray($extra_attributes, $attr_node->getAttribute('name'), $attr_node->getAttribute('value'));
					}
				}
			}
		}
		$this->setAttributes($extra_attributes);
		return true;
	}

	/**
	 * Add an attribute value to an array of attributes.
	 *
	 * @param array  &$attributeArray reference to array
	 * @param string $name            name of attribute
	 * @param string $value           value of attribute
	 *
	 * @return void
	 */
	private function addAttributeToArray(array &$attributeArray, $name, $value)
	{
		// If multiple attributes exist, add as an array value
		if (isset($attributeArray[$name])) {
			// Initialize the array with the existing value
			if (!is_array($attributeArray[$name])) {
				$existingValue         = $attributeArray[$name];
				$attributeArray[$name] = array($existingValue);
			}
			$attributeArray[$name][] = trim($value);
		} else {
			$attributeArray[$name] = trim($value);
		}
	}

	/**
	 * This method is used to build the SAML POST body sent to /samlValidate URL.
	 * @return string SOAP-encased SAMLP artifact (the ticket).
	 */
	private function buildSAMLPayload()
	{
		$sa = urlencode($this->getTicket());
		return CasConst::SAML_SOAP_ENV . CasConst::SAML_SOAP_BODY . CasConst::SAMLP_REQUEST . CasConst::SAML_ASSERTION_ARTIFACT . $sa . CasConst::SAML_ASSERTION_ARTIFACT_CLOSE . CasConst::SAMLP_REQUEST_CLOSE . CasConst::SAML_SOAP_BODY_CLOSE . CasConst::SAML_SOAP_ENV_CLOSE;
	}

	/**
	 * This method returns the URL of the current request (without any ticket CGI parameter).
	 *
	 * @param bool $withoutTicket
	 *
	 * @return string URL
	 */
	public function getRequestUriWithoutTicket($withoutTicket = true)
	{
		if (empty($this->requestUri)) {
			$this->requestUri = $this->_Url->current();
		}
		if ($withoutTicket) {
			$request_array = explode('?', $this->requestUri, 2);
			$query_params  = [];
			if (isset($request_array[1])) {
				parse_str($request_array[1], $query_params);
				unset($query_params['ticket']);
			}
			$this->requestUri = $this->buildUri($request_array[0], $query_params);
		}
		return $this->requestUri;
	}

	/**
	 * This method returns the URL that should be used for the PGT callback (in
	 * fact the URL of the current request without any CGI parameter, except if
	 * phpCAS::setFixedCallbackURL() was used).
	 * @return string callback URL
	 */
	private function getCallbackUri()
	{
		if (empty($this->callbackUri)) {
			$request_array     = explode('?', $this->_Url->current(), 1);
			$this->callbackUri = $request_array[0];
		}
		return $this->callbackUri;
	}

	/**
	 * Answer the ProxyChainAllowedList object for this client.
	 * @return ProxyChainAllowedList
	 */
	public function getAllowedProxyChains()
	{
		if (empty($this->allowedProxyChains)) {
			$this->allowedProxyChains = new ProxyChainAllowedList();
		}
		return $this->allowedProxyChains;
	}

	/**
	 * This method is used to retrieve the service validating URL of the CAS server.
	 * @return string serviceValidate Uri
	 */
	protected function getTicketValidateUri()
	{
		return $this->buildUri($this::$_ServerConfig->casBaseServerUri . $this::$_ServerConfig->casValidateUri, [
			'service' => urlencode($this->getRequestUriWithoutTicket())
		]);
	}

	/**
	 * This method is used to retrieve the proxy validating URL of the CAS server.
	 * @return string proxy validate Uri.
	 */
	public function getProxyTicketValidateUri()
	{
		return $this->buildUri($this::$_ServerConfig->casBaseServerUri . $this::$_ServerConfig->casProxyValidateUri, [
			'service' => urlencode($this->getRequestUriWithoutTicket())
		]);
	}

	/**
	 * This method is used to retrieve the SAML validating URL of the CAS server.
	 *
	 * @param bool $renew
	 *
	 * @return string samlValidate URL.
	 */
	public function getSamlValidateUri($renew = false)
	{
		$params['TARGET'] = urlencode($this->getRequestUriWithoutTicket());
		if ($renew) {
			$params['renew'] = 'true';
		}
		return $this->buildUri($this::$_ServerConfig->casBaseServerUri . $this::$_ServerConfig->casSamlValidateUri, $params);
	}
}