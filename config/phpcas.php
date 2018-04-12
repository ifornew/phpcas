<?php

return [
	/*
	|--------------------------------------------------------------------------
	| PHPCas Fake
	|--------------------------------------------------------------------------
	*/
	'cas_fake' => env('CAS_FAKE', false),

	'cas_fake_user_id' => env('CAS_FAKE_USER_ID', 1),

	/*
	|--------------------------------------------------------------------------
	| PHPCas UdfProxy
	|--------------------------------------------------------------------------
	*/
	'cas_udf_proxy'    => env('CAS_UDF_PROXY', false),
	'cas_udf_proxy_ip' => '127.0.0.1',

	'cas_version'=>env('CAS_VERSION','2.0'),

	/*
	|--------------------------------------------------------------------------
	| PHPCas Hostname
	|--------------------------------------------------------------------------
	|
	| Example: 'cas.myuniv.edu'.
	|
	*/
	'cas_hostname' => env('CAS_HOSTNAME'),


	/*
	|--------------------------------------------------------------------------
	| Cas Port
	|--------------------------------------------------------------------------
	|
	| Usually 443 is default
	|
	*/
	'cas_port' => env('CAS_PORT', 443),

	'cas_channel'=>env('CAS_CHANNEL',1),

	/*
	|--------------------------------------------------------------------------
	| CAS URI
	|--------------------------------------------------------------------------
	|
	| Sometimes is /cas
	|
	*/
	'cas_uri' => env('CAS_URI', '/cas'),

	'cas_login_uri'=>env('CAS_LOGIN_URI',''),

	'cas_logout_uri'=>env('CAS_LOGOUT_URI',''),

	'cas_register_uri'=>env('CAS_REGISTER_URI',''),

	'cas_find_password_uri' => env('CAS_FIND_PASSWORD_URI', ''),

	/*
	|--------------------------------------------------------------------------
	| CAS Certificate
	|--------------------------------------------------------------------------
	|
	| Path to the CAS certificate file
	|
	*/
	'cas_cert'              => env('CAS_CERT', ''),

	'cas_validate' => env('CAS_VALIDATE', false),

	/*
	|--------------------------------------------------------------------------
	| CAS Validation
	|--------------------------------------------------------------------------
	|
	| CAS server SSL validation: 'ca' for certificate from a CA or self-signed
	| certificate, empty for no SSL validation.
	|
	*/

	'cas_cert_cn_validate' => env('CAS_CERT_CN_VALIDATE', false),

	'cas_session_key' => env('CAS_SESSION_KEY', 'phpCas'),

	'cas_ticket_key' => env('CAS_TICKET_KEY', 'ticket')
];