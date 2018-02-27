<?php
namespace Iwannamaybe\PhpCas;

use Iwannamaybe\PhpCas\ProxyChain\ProxyChainInterface;

/**
 * Class ProxyChain
 *
 * A normal proxy-chain definition that lists each level of the chain as either a string or regular expression.
 *
 * @package Iwannamaybe\PhpCas
 */
class ProxyChain implements ProxyChainInterface
{
    protected $chain = array();

    /**
     * A chain is an array of strings or regexp strings that will be matched against. Regexp will be matched with preg_match and strings will be matched from the beginning. A string must fully match the beginning of an proxy url. So you can define a full domain as acceptable or go further down.
     * Proxies have to be defined in reverse from the service to the user. If a user hits service A get proxied via B to service C the list of acceptable proxies on C would be array(B,A);
     *
     * @param array $chain A chain of proxies
     */
    public function __construct(array $chain)
    {
        // Ensure that we have an indexed array
        $this->chain = array_values($chain);
    }

    /**
     * Match a list of proxies.
     *
     * @param array $list The list of proxies in front of this service.
     *
     * @return bool
     */
    public function matches(array $list)
    {
        $list = array_values($list);  // Ensure that we have an indexed array
        if ($this->isSizeValid($list)) {
            $mismatch = false;
            foreach ($this->chain as $i => $search) {
                $proxy_url = $list[$i];
                if (preg_match('/^\/.*\/[ixASUXu]*$/s', $search)) {
                    if (!preg_match($search, $proxy_url)) {
	                    $mismatch = true;
	                    break;
                    }
                } else {
                    if (strncasecmp($search, $proxy_url, strlen($search)) != 0) {
	                    $mismatch = true;
	                    break;
                    }
                }
            }
            if (!$mismatch) {
                //Proxy chain matches
                return true;
            }
        }
        //Proxy chain skipped: size mismatch
        return false;
    }

    /**
     * Validate the size of the the list as compared to our chain.
     *
     * @param array $list List of proxies
     *
     * @return bool
     */
    protected function isSizeValid (array $list)
    {
        return (sizeof($this->chain) == sizeof($list));
    }
}
