<?php
namespace Iwannamaybe\PhpCas\ProxyChain;

/**
 * Interface ProxyChainInterface
 *
 * An interface for classes that define a list of allowed proxies in front of
 *
 * @package Iwannamaybe\PhpCas\ProxyChain
 */
interface ProxyChainInterface
{
    /**
     * Match a list of proxies.
     *
     * @param array $list The list of proxies in front of this service.
     *
     * @return bool
     */
    public function matches(array $list);
}