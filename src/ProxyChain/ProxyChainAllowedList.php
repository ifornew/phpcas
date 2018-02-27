<?php
namespace Iwannamaybe\PhpCas\ProxyChain;

/**
 * Class ProxyChainAllowedList
 * ProxyChain is a container for storing chains of valid proxies that can be used to validate proxied requests to a service
 * @package Iwannamaybe\PhpCas\ProxyChain
 */
class ProxyChainAllowedList
{
	private $_chains = array();

	/**
	 * Check whether proxies are allowed by configuration
	 * @return bool
	 */
	public function isProxyingAllowed()
	{
		return (count($this->_chains) > 0);
	}

	/**
	 * Add a chain of proxies to the list of possible chains
	 *
	 * @param ProxyChainInterface $chain A chain of proxies
	 *
	 * @return void
	 */
	public function allowProxyChain(ProxyChainInterface $chain)
	{
		$this->_chains[] = $chain;
	}

	/**
	 * Check if the proxies found in the response match the allowed proxies
	 *
	 * @param array $proxies list of proxies to check
	 *
	 * @return bool whether the proxies match the allowed proxies
	 */
	public function isProxyListAllowed(array $proxies)
	{
		if (empty($proxies)) {
			//No proxies were found in the response
			return true;
		} elseif (!$this->isProxyingAllowed()) {
			//Proxies are not allowed
			return false;
		} else {
			$res = $this->contains($proxies);
			return $res;
		}
	}

	/**
	 * Validate the proxies from the proxy ticket validation against the
	 * chains that were definded.
	 *
	 * @param array $list List of proxies from the proxy ticket validation.
	 *
	 * @return bool any chain fully matches the supplied list
	 */
	public function contains(array $list)
	{
		foreach ($this->_chains as $chain) {
			if ($chain->matches($list)) {
				return true;
			}
		}
		//No proxy chain matches.
		return false;
	}
}
?>
