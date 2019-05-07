<?php

return [
	/*
	|--------------------------------------------------------------------------
	| PHPCas Guard
	|--------------------------------------------------------------------------
	*/
	'web' => [
		/*
		|--------------------------------------------------------------------------
		| CAS URI
		|--------------------------------------------------------------------------
		|
		| Sometimes is /cas
		|
		*/
		'server'            => 'http://172.16.30.60:8080/cas',
		'login_uri'         => '',
		'logout_uri'        => '/logout',
		'find_password_uri' => '',
		'version'           => '2.0',
		'channel'           => env('APP_NAME', null),

		/*
		|--------------------------------------------------------------------------
		| CAS Certificate
		|--------------------------------------------------------------------------
		|
		| Path to the CAS certificate file
		|
		*/
		'cert'              => null,
		'cert_validate'     => false,

		/*
		|--------------------------------------------------------------------------
		| CAS Validation
		|--------------------------------------------------------------------------
		|
		| CAS server SSL validation: 'ca' for certificate from a CA or self-signed
		| certificate, empty for no SSL validation.
		|
		*/
		'cert_cn_validate'  => false,
		'session_key'       => 'phpcas',
		'ticket_key'        => 'ticket',

		/*
		|--------------------------------------------------------------------------
		| CAS Except url or route
		|--------------------------------------------------------------------------
		|
		| .
		|
		*/
		'except'            => [
			'url'   => [
				'_debugbar'
			],
			'route' => [

			]
		],

		/*
		|--------------------------------------------------------------------------
		| PHPCas Fake
		|--------------------------------------------------------------------------
		*/
		'fake'              => false,
		'fake_user_id'      => 18602301175
	]
];