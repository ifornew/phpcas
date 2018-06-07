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
 *
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

		public $casGuardKey;

		public $casGuard;

		public $casExcept;

		public $casLoginUri;

		public $casLogoutUri;

		public $casRegisterUri;

		public $casFindPasswordUri;

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

		//TODO:UDF代理
		public $casUdfProxy;

		public $casUdfProxyIp;

		public $casTicketKey;

		public $sessionCasKey;

		public $sessionPgtKey;

		public $sessionProxiesKey;
}