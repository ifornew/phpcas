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
	protected $config;
	protected $url;
	protected $request;

	protected $casHostName;
	protected $casPort;
	protected $casChannel;
	protected $casUri;
	protected $casLoginUri;
	protected $casRegisterUri;
	protected $casValidation;
	protected $casCert;
	//TODO:UDF代理
	protected $casUdfProxy;
	protected $casUdfProxyIp;

	public function __construct(Repository $config, UrlGenerator $url, Request $request)
	{
		$this->config  = $config;
		$this->url     = $url;
		$this->request = $request;
		$this->initCasConfigs();
	}

	/**
	 * Init the phpcas configs
	 */
	protected function initCasConfigs()
	{
		//TODO:UDF代理
		$this->casUdfProxy   = boolval($this->config->get('cas.cas_udf_proxy', false));
		$this->casUdfProxyIp = $this->config->get('cas.cas_udf_proxy_ip', '127.0.0.1');

		$this->casHostName    = $this->udfProxyCasHost();
		$this->casPort        = intval($this->config->get('cas.cas_port', 443));
		$this->casChannel     = $this->config->get('cas.cas_channel');
		$this->casUri         = $this->config->get('cas.cas_uri');
		$this->casLoginUri    = $this->config->get('cas.cas_login_uri');
		$this->casRegisterUri = $this->config->get('cas.cas_register_uri');
		$this->casValidation  = boolval($this->config->get('cas.cas_validation', false));
		$this->casCert        = $this->config->get('cas.cas_cert');
	}

	/**
	 * Get the cas hostname by UDF proxy
	 * @return string
	 */
	protected function udfProxyCasHost()
	{
		if ($this->casUdfProxy && $this->request->ip() == $this->casUdfProxyIp) {
			return $this->casUdfProxyIp;
		}
		return $this->config->get('cas.cas_hostname');
	}

	/**
	 * Sso login url
	 * @return string
	 */
	public function getLoginBaseUrl($redirect = null)
	{
		$current_url = url()->current();
		if ($this->casPort == 443) {
			$cas_base_url = "https://{$this->casHostName}:{$this->casLoginUri}";
		} elseif ($this->casPort == 80) {
			$cas_base_url = "http://{$this->casHostName}{$this->casLoginUri}";
		} else {
			$cas_base_url = "http://{$this->casHostName}:{$this->casPort}{$this->casLoginUri}";
		}
		return "$cas_base_url?service={$current_url}&channel={$this->casChannel}";
	}
}