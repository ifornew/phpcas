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
	public function __construct(Client $client)
	{
		$this->casClient = $client;
	}

	/**
	 * Get phpcas login uri
	 *
	 * @param string $redirect redirect back uri
	 * @return string
	 */
	public function getLoginUri($redirect = null)
	{
		return $this->casClient->getLoginUri($redirect);
	}

	/**
	 * Get phpcas register uri
	 *
	 * @param string $redirect redirect back uri
	 * @return string
	 */
	public function getRegisterUri($redirect = null)
	{
		return $this->casClient->getRegisterUri($redirect);
	}

	/**
	 * Get phpcas logout uri
	 *
	 * @param string $redirect redirect back uri
	 * @return string
	 */
	public function getLogoutUri($redirect = null)
	{
		return $this->casClient->getLogoutUri($redirect);
	}

	/**
	 * Force phpcas verify
	 *
	 * @return bool
	 */
	public function forceAuthentication()
	{
		return $this->casClient->forceAuthentication();
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
}