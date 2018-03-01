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
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Iwannamaybe\PhpCas\Exceptions\AuthenticationException;
use Iwannamaybe\PhpCas\Exceptions\CasInvalidArgumentException;
use Iwannamaybe\PhpCas\ProxyChain\ProxyChainAllowedList;
use Iwannamaybe\PhpCas\Requests\CurlMultiRequest;
use Iwannamaybe\PhpCas\Requests\CurlRequest;
use Iwannamaybe\PhpCas\Requests\MultiRequestInterface;
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
	 * @var array $_proxies This array will store a list of proxies in front of this application. This property will only be populated if this script is being proxied rather than accessed directly.It is set in CAS_Client::validateCAS20() and can be read by Client::getProxies()
	 */
	private $proxies = array();

	/**
	 * @var bool $rebroadcast whether to rebroadcast pgtIou/pgtId and logoutRequest
	 */
	private $rebroadcast = false;
	/**
	 * @var array $rebroadcastNodes array of the rebroadcast nodes
	 */
	private $rebroadcastNodes = [];

	/**
	 * @param array $rebroadcastHeaders An array to store extra rebroadcast curl options.
	 */
	private $rebroadcastHeaders = array();

	/**
	 * @var string $pgt the Proxy Grnting Ticket given by the CAS server (empty otherwise). Written by CAS_Client::_setPGT(), read by Client::getPGT() and Client::hasPGT()
	 */
	private $pgt = '';

	/**
	 * @var boolean $clearTicketFromUri If true, phpCAS will clear session tickets from the URL after a successful authentication.
	 */
	private $clearTicketFromUri = true;

	/**
	 * @var array $curlOptions An array to store extra curl options.
	 */
	private $curlOptions = [];

	/**
	 * @var string $requestImplementation The class to instantiate for making web requests in readUrl(). The class specified must implement the RequestInterface. By default CurlRequest is used, but this may be overridden to supply alternate request mechanisms for testing.
	 */
	private $requestImplementation = CurlRequest::class;

	/**
	 * @var string $multiRequestImplementation The class to instantiate for making web requests in readUrl(). The class specified must implement the RequestInterface. By default CurlRequest is used, but this may be overridden to supply alternate request mechanisms for testing.
	 */
	private $multiRequestImplementation = CurlMultiRequest::class;

	public function __construct(Repository $config, UrlGenerator $url, Request $request, Log $logger, Redirector $redirect)
	{
		$this->_Config   = $config;
		$this->_Url      = $url;
		$this->_Request  = $request;
		$this->_Logger   = $logger;
		$this->_Redirect = $redirect;
		$this->initConfigs();
		$this->initTicket();
	}

	/**
	 * Init the phpcas configs
	 */
	private function initConfigs()
	{
		$serverConfig                = new ServerConfig();
		$serverConfig->casFake       = boolval($this->_Config->get('phpcas.cas_fake', false));
		$serverConfig->casFakeUserId = intval($this->_Config->get('phpcas.cas_fake_user_id', 1));

		$serverConfig->casVersion           = $this->_Config->get('phpcas.cas_version', '2.0');
		$serverConfig->casHostName          = $this->initCasHost();
		$serverConfig->casPort              = intval($this->_Config->get('phpcas.cas_port', 443));
		$serverConfig->casBaseServerUri     = $this->initBaseServerUri($serverConfig->casHostName, $serverConfig->casPort);
		$serverConfig->casChannel           = $this->_Config->get('phpcas.cas_channel');
		$serverConfig->casUri               = $this->_Config->get('phpcas.cas_uri');
		$serverConfig->casLoginUri          = $this->_Config->get('phpcas.cas_login_uri');
		$serverConfig->casLogoutUri         = $this->_Config->get('phpcas.cas_logout_uri');
		$serverConfig->casRegisterUri       = $this->_Config->get('phpcas.cas_register_uri');
		$serverConfig->casValidateUri       = $this->initValidateUri($serverConfig->casVersion);
		$serverConfig->casProxyValidateUri  = $this->initProxyValidateUri($serverConfig->casVersion);
		$serverConfig->casSamlValidateUri   = $this->initSamlValidateUri($serverConfig->casVersion);
		$serverConfig->casCert              = $this->_Config->get('phpcas.cas_cert');
		$serverConfig->casCertValidate      = boolval($this->_Config->get('phpcas.cas_cert_validate', false));
		$serverConfig->casCertCnValidate    = boolval($this->_Config->get('phpcas.cas_cert_cn_validate', false));
		$serverConfig->casLang              = $this->_Config->get('app.locale');
		$serverConfig->sessionCasKey        = $this->_Config->get('phpcas.cas_session_key');
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
		$cas_udf_proxy    = boolval($this->_Config->get('phpcas.cas_udf_proxy', false));
		$cas_udf_proxy_ip = $this->_Config->get('phpcas.cas_udf_proxy_ip', '127.0.0.1');
		if ($cas_udf_proxy && $this->_Request->ip() == $cas_udf_proxy_ip) {
			return $cas_udf_proxy_ip;
		}
		return $this->_Config->get('phpcas.cas_hostname');
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

	private function initTicket()
	{
		$ticket = $this->_Request->get('ticket', null);
		if (preg_match('/^[SP]T-/', $ticket)) {
			$this->_Logger->info("Ticket `{$ticket}` found");
			$this->setTicket($ticket);
		} elseif (!empty($ticket)) {
			//ill-formed ticket, halt
			$this->_Logger->error('ill-formed ticket found in the URL (ticket=`' . htmlentities($ticket) . '\')');
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
				return '/cas/validate';
				break;
			case '2.0':
				return '/cas/serviceValidate';
				break;
			case '3.0':
				return '/cas/p3/serviceValidate';
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
			return '';
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
			'service' => $redirect == null ? $this->getRequestUri() : $redirect,
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
	protected function setTicket($ticket)
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
			$this->setClearTicketFromUri();
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
	 * Configure the client to not send redirect headers and call exit() on authentication success. The normal redirect is used to remove the service ticket from the client's URL, but for running unit tests we need to continue without exiting. Needed for testing authentication
	 *
	 * @param bool $value
	 *
	 * @return void
	 */
	public function setClearTicketFromUri($value = true)
	{
		$this->clearTicketFromUri = $value;
	}

	public function getClearTicketFromUri()
	{
		return $this->clearTicketFromUri;
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
	 * check if the user is authenticated (previously or by tickets given in the uri)
	 *
	 * @param bool $renew true to force the authentication with the CAS server
	 *
	 * @return true when the user is authenticated. Also may redirect to the same URL without the ticket.
	 */
	public function isAuthenticated($renew = false)
	{
		if($this::$_ServerConfig->casFake){
			$this->setUser($this::$_ServerConfig->casFakeUserId);
			return true;
		}
		switch ($this->getServerVersion()) {
			case '1.0':
				// if a Service Ticket was given, validate it
				$this->_Logger->info("CAS 1.0 ticket '{$this->getTicket()}' is present");
				$this->validateCAS10($validate_url, $text_response, $renew); // if it fails, it halts
				$this->_Logger->info("CAS 1.0 ticket '{$this->getTicket()}' was validated");
				return true;
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
					$this->_Request->session()->put($this::$_ServerConfig->sessionPgtKey, $this->getPGT());
				}
				if (!empty($proxies = $this->getProxies())) {
					$this->_Request->session()->put($this::$_ServerConfig->sessionProxiesKey, $proxies);
				}
				return true;
				break;
			case 'S1':
				// if we have a SAML ticket, validate it.
				$this->_Logger->info("SAML 1.1 ticket `{$this->getTicket()}` is present");
				$this->validateSA($validate_url, $text_response, $tree_response, $renew); // if it fails, it halts
				$this->_Logger->info("SAML 1.1 ticket `{$this->getTicket()}` was validated");
				return true;
				break;
			default:
				$this->_Logger->info('Protocoll error');
				break;
		}
		return false;
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
		if (empty($params)) {
			return $url;
		}
		$seperate = (strstr($url, '?') === false) ? '?' : '&';
		$query    = http_build_query($params);
		return $url . $seperate . $query;
	}

	/**
	 * This method is used to validate a CAS 1,0 ticket; halt on failure, and sets $validate_url, $text_reponse and $tree_response on success.
	 *
	 * @param string &$validate_url  reference to the the URL of the request to the CAS server.
	 * @param string &$text_response reference to the response of the CAS server, as is (XML text).
	 * @param bool   $renew          true to force the authentication with the CAS server
	 *
	 * @return bool true when successfull and issue a AuthenticationException and false on an error
	 */
	protected function validateCAS10(&$validate_url, &$text_response, $renew = false)
	{
		$query_params['ticket'] = $this->getTicket();
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
		$query_params['ticket'] = $this->getTicket();
		if ($this->isProxy()) {
			// pass the callback url for CAS proxies
			$query_params['pgtUrl'] = ($this->getCallbackUri());
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
				//$this->readExtraAttributesCas20($success_elements);
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
					/** @var DOMElement $node */
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
	 * @param bool $withoutChannel
	 * @param bool $withoutTicket
	 *
	 * @return string URL
	 */
	public function getRequestUri($withoutChannel=true,$withoutTicket = true)
	{
		if (empty($this->requestUri)) {
			$route_name = $this->_Request->route()->getName();
			if(in_array($route_name,['login','logout'])){
				$this->requestUri = $this->_Url->previous();
			}else{
				$this->requestUri = $this->_Url->current();
			}
		}
		$request_array = explode('?', $this->requestUri, 2);
		$query_params  = [];
		if (isset($request_array[1])) {
			parse_str($request_array[1], $query_params);
		}
		if($withoutChannel){
			unset($query_params['channel']);
		}
		if($withoutTicket){
			unset($query_params['ticket']);
		}
		return $this->buildUri(rtrim($request_array[0],'/'), $query_params);
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
			'service' => $this->getRequestUri()
		]);
	}

	/**
	 * This method is used to retrieve the proxy validating URL of the CAS server.
	 * @return string proxy validate Uri.
	 */
	public function getProxyTicketValidateUri()
	{
		return $this->buildUri($this::$_ServerConfig->casBaseServerUri . $this::$_ServerConfig->casProxyValidateUri, [
			'service' => $this->getRequestUri()
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
		$params['TARGET'] = $this->getRequestUri();
		if ($renew) {
			$params['renew'] = 'true';
		}
		return $this->buildUri($this::$_ServerConfig->casBaseServerUri . $this::$_ServerConfig->casSamlValidateUri, $params);
	}

	public function handLoginRequest(callable $callback, $renew = false)
	{
		if ($this->isAuthenticated($renew)) {
			$callback($this->getUser());
			return $this->_Redirect->to($this->getRequestUri());
		} else {
			throw new AuthenticationException($this,'Ticket认证失败');
			return $this->_Redirect->to($this->getLoginUri());
		}
	}

	public function isLogoutRequest()
	{
		return $this->_Request->has('logoutRequest');
	}

	public function handLogoutRequest($checkClient = true, $allowedClients = [])
	{
		if ($this->isLogoutRequest()) {
			$this->_Logger->info("登出请求来过");
			$decoded_logout_rq = urldecode($_POST['logoutRequest']);
			$this->_Logger->info("SAML REQUEST: " . $decoded_logout_rq);
			$allowed   = false;
			$client_ip = $this->_Request->ip();
			$client    = gethostbyaddr($client_ip);
			if ($checkClient) {
				if (empty($allowedClients)) {
					//TODO:验证服务器地址
					$allowedClients = [$this::$_ServerConfig->casHostName];
				}
				$this->_Logger->info("Client: {$client}/{$client_ip}");
				foreach ($allowedClients as $allowedClient) {
					if (($client == $allowedClient) || ($client_ip == $allowedClient)) {
						$this->_Logger->info("Allowed client `{$allowedClient}` matches, logout request is allowed");
						$allowed = true;
						break;
					} else {
						$this->_Logger->info("Allowed client `{$allowedClient}` does not match");
					}
				}
			} else {
				$this->_Logger->info("No access control set");
				$allowed = true;
			}
			// If Logout command is permitted proceed with the logout
			if ($allowed) {
				$this->_Logger->info("Logout command allowed");
				// Rebroadcast the logout request
				//TODO:广播未监测
				if ($this->rebroadcast && !isset($_POST['rebroadcast'])) {
					$this->rebroadcast(CasConst::LOGOUT);
				}
				// Extract the ticket from the SAML Request
				/*
				preg_match("|<samlp:SessionIndex>(.*)</samlp:SessionIndex>|", $decoded_logout_rq, $tick, PREG_OFFSET_CAPTURE, 3);
				$wrappedSamlSessionIndex = preg_replace('|<samlp:SessionIndex>|', '', $tick[0][0]);
				$ticket2logout = preg_replace('|</samlp:SessionIndex>|', '', $wrappedSamlSessionIndex);
				$this->_Logger->info("Ticket to logout: {$ticket2logout}");
				*/
				Auth::logout();
				$this->_Request->session()->migrate(true);

				// If phpCAS is managing the session_id, destroy session thanks to
				// session_id.
				/*
				if ($this->getChangeSessionID()) {
					$session_id = preg_replace('/[^a-zA-Z0-9\-]/', '', $ticket2logout);
					phpCAS::trace("Session id: ".$session_id);

					// destroy a possible application session created before phpcas
					if (session()->getId() !== "") {
						session()->invalidate();
					}
					// fix session ID
					//session()->setId($session_id);
					$_COOKIE[session()->getName()]=$session_id;
					$_GET[session()->getName()]=$session_id;

					// Overwrite session
					session()->invalidate();
				}
				*/
			} else {
				$this->_Logger->error("Unauthorized logout request from client '" . $client . "'");
			}
		}
	}

	/**
	 * This method rebroadcasts logout/pgtIou requests. Can be LOGOUT,PGTIOU
	 *
	 * @param int $type type of rebroadcasting.
	 *
	 * @return void
	 */
	private function rebroadcast($type)
	{
		// Try to determine the IP address of the server
		if (empty($ip = $this->_Request->server('SERVER_ADDR'))) {
			// IIS 7
			$ip = $this->_Request->server('LOCAL_ADDR');
		}
		// Try to determine the DNS name of the server
		if (!empty($ip)) {
			$dns = gethostbyaddr($ip);
		}
		/** @var MultiRequestInterface $multiRequest */
		$multiRequest = new $this->multiRequestImplementation();

		for ($i = 0; $i < sizeof($this->rebroadcastNodes); $i++) {
			if ((($this->getNodeType($this->rebroadcastNodes[$i]) == CasConst::HOSTNAME) && !empty($dns) && (stripos($this->rebroadcastNodes[$i], $dns) === false)) || (($this->getNodeType($this->rebroadcastNodes[$i]) == CasConst::IP) && !empty($ip) && (stripos($this->rebroadcastNodes[$i], $ip) === false))) {
				$this->_Logger->info('Rebroadcast target URL: ' . $this->rebroadcastNodes[$i] . $this->_Request->server('REQUEST_URI'));
				/** @var CurlRequest $request */
				$request = new $this->requestImplementation();

				$url = $this->rebroadcastNodes[$i] . $this->_Request->server('REQUEST_URI');
				$request->setUrl($url);

				if (count($this->rebroadcastHeaders)) {
					$request->addHeaders($this->rebroadcastHeaders);
				}

				$request->makePost();
				if ($type == CasConst::LOGOUT) {
					// Logout request
					$request->setPostBody('rebroadcast=false&logoutRequest=' . $_POST['logoutRequest']);
				} elseif ($type == CasConst::PGTIOU) {
					// pgtIou/pgtId rebroadcast
					$request->setPostBody('rebroadcast=false');
				}
				$request->setCurlOptions([
					                         CURLOPT_FAILONERROR    => 1,
					                         CURLOPT_FOLLOWLOCATION => 1,
					                         CURLOPT_RETURNTRANSFER => 1,
					                         CURLOPT_CONNECTTIMEOUT => 1,
					                         CURLOPT_TIMEOUT        => 4
				                         ]);

				$multiRequest->addRequest($request);
			} else {
				$this->_Logger->info("Rebroadcast not sent to self: {$this->rebroadcastNodes[$i]} ==" . (empty($ip) ? '' : $ip) . '/' . (empty($dns) ? '' : $dns));
			}
		}
		// We need at least 1 request
		if ($multiRequest->getNumRequests() > 0) {
			$multiRequest->send();
		}
	}

	/**
	 * Determine the node type from the URL.
	 *
	 * @param String $nodeURL The node URL.
	 *
	 * @return string hostname
	 */
	private function getNodeType($nodeURL)
	{
		if (preg_match("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/", $nodeURL)) {
			return CasConst::IP;
		} else {
			return CasConst::HOSTNAME;
		}
	}
}