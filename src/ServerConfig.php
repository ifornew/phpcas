<?php
/**
 * Created by PhpStorm.
 * User: TouchWorld
 * Date: 2018/2/26
 * Time: 11:56
 */

namespace Iwannamaybe\PhpCas;

/**
 * Class CasServerInfo
 * @package Iwannamaybe\PhpCas
 */
class ServerConfig
{
	/**
	 * @var string $casVersion available cas version:1.0/2.0/3.0/S1
	 */
	public $casVersion;

	public $casHostName;

	public $casPort;

	public $casChannel;

	public $casUri;

	public $casLoginUri;

	public $casLogoutUri;

	public $casRegisterUri;

	public $casValidateUri;

	public $casProxyValidateUri;

	public $casSamlValidateUri;

	public $casCert;

	/**
	 * @var bool $casCertCnValidate validate CN of the CAS server certificate
	 */
	public $casCertCnValidate = true;

	/**
	 * @var bool $casCertValidate Set to true to validate the CAS server.
	 */
	public $casCertValidate = true;

	public $casFake;

	public $casFakeUserId;

	public $casLang;

	public $casBaseServerUri;

	public $sessionCasKey;

	public $sessionAuthChecked;

	public $sessionUserKey;

	public $sessionAttributesKey;

	public $sessionPgtKey;

	public $sessionProxiesKey;
}