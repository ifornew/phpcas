<?php

namespace Iwannamaybe\PhpCas\ProxyChain;

use Iwannamaybe\PhpCas\ProxyChain;

/**
 * Class ProxyChainTrusted
 * A proxy-chain definition that defines a chain up to a trusted proxy and delegates the resposibility of validating the rest of the chain to that trusted proxy
 * @package Iwannamaybe\PhpCas\ProxyChain
 */
class ProxyChainTrusted extends ProxyChain implements ProxyChainInterface
{
	/**
	 * Validate the size of the the list as compared to our chain.
	 *
	 * @param array $list list of proxies
	 *
	 * @return bool
	 */
	protected function isSizeValid(array $list)
	{
		return (sizeof($this->chain) <= sizeof($list));
	}

}
