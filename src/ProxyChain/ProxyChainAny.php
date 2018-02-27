<?php
namespace Iwannamaybe\PhpCas\ProxyChain;

/**
 * Class ProxyChainAny
 *
 * A proxy-chain definition that will match any list of proxies.
 *
 * Use this class for quick testing or in certain production screnarios you
 * might want to allow allow any other valid service to proxy your service.
 *
 * THIS CLASS IS HOWEVER NOT RECOMMENDED FOR PRODUCTION AND HAS SECURITY
 * IMPLICATIONS: YOU ARE ALLOWING ANY SERVICE TO ACT ON BEHALF OF A USER
 * ON THIS SERVICE.
 *
 * @package Iwannamaybe\PhpCas\ProxyChain
 */
class ProxyChainAny implements ProxyChainInterface
{
    /**
     * Match a list of proxies.
     *
     * @param array $list The list of proxies in front of this service.
     *
     * @return bool
     */
    public function matches(array $list)
    {
        //Using CAS_ProxyChain_Any. No proxy validation is performed
        return true;
    }

}
