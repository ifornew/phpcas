<?php
/**
 * Created by PhpStorm.
 * User: TouchWorld
 * Date: 2018/2/24
 * Time: 15:18
 */

namespace Iwannamaybe\PhpCas;


class Cas
{
	/**
	 * @var Client
	 */
	protected $casClient;

	/**
	 * Cas constructor.
	 */
	public function __construct($app)
	{
		$this->casClient = $app['phpcas.client'];
	}

	/**
	 * Get phpcas login url
	 * @return string
	 */
	public function getLoginUrl($redirect = null)
	{
		$current_url = $redirect ?? url()->current();
		if ($this->casPort == 443) {
			$cas_base_url = "https://{$this->casHostName}:{$this->casLoginUri}";
		} elseif ($this->casPort == 80) {
			$cas_base_url = "http://{$this->casHostName}{$this->casLoginUri}";
		} else {
			$cas_base_url = "http://{$this->casHostName}:{$this->casPort}{$this->casLoginUri}";
		}
		return "$cas_base_url?service={$current_url}&channel={$this->casChannel}";
	}

	/**
	 * Sso register url
	 * @return string
	 */
	public function ssoRegisterUrl()
	{
		$current_url = url()->current();
		if ($this->casPort == 443) {
			$sso_base_url = "https://{$this->casHostName}:{$this->casRegisterUri}";
		} elseif ($this->casPort == 80) {
			$sso_base_url = "http://{$this->casHostName}{$this->casRegisterUri}";
		} else {
			$sso_base_url = "http://{$this->casHostName}:{$this->casPort}{$this->casRegisterUri}";
		}
		return "$sso_base_url?service={$current_url}&channel={$this->casChannel}";
	}

	/**
	 * Force sso verify
	 * @return bool
	 */
	public function forceAuthentication()
	{
		//TODO:Cas模拟
		if (config('cas.cas_fake')) {
			return true;
		}
		return Cas::forceAuthentication();
	}

	/**
	 * Auto inject the sso auth info
	 */
	public function injectCasAuth()
	{
		//TODO:CAS模拟
		if (config('cas.cas_fake')) {
			Auth::onceUsingId(config('cas.cas_fake_user_id'));
		} elseif (Cas::isAuthenticated()) {
			//TODO:default password for create new user
			$user = User::firstOrNew(['mobile' => Cas::getUser()]);
			//$user = User::firstOrNew(['mobile' => Cas::getUser(), 'password' => bcrypt(123456)]);
			Auth::onceUsingId($user->id);
		}
	}

	/**
	 * Sso loginout
	 *
	 * @param null $redirect sso logout redirect url
	 */
	public function logout($redirect = null)
	{
		Cas::logout(['service' => $redirect ?? url()->previous()]);
	}
}