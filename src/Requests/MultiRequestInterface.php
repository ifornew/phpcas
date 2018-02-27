<?php
namespace Iwannamaybe\PhpCas\Requests;

use Iwannamaybe\PhpCas\Exceptions\CasInvalidArgumentException;
use Iwannamaybe\PhpCas\Exceptions\OutOfSequenceException;

/**
 * Interface MultiRequestInterface
 *
 * This interface defines a class library for performing multiple web requests in batches. Implementations of this interface may perform requests serially or in parallel
 *
 * @package Iwannamaybe\PhpCas\Requests
 */
interface MultiRequestInterface
{
    /*********************************************************
     * Add Requests
    *********************************************************/

    /**
     * Add a new Request to this batch.
     * Note, implementations will likely restrict requests to their own concrete
     * class hierarchy.
     *
     * @param RequestInterface $request request interface
     *
     * @return void
     * @throws OutOfSequenceException If called after the Request has been
     * sent.
     * @throws CasInvalidArgumentException If passed a Request of the wrong
     * implmentation.
     */
    public function addRequest (RequestInterface $request);

    /**
     * Retrieve the number of requests added to this batch.
     *
     * @return number of request elements
     */
    public function getNumRequests ();

    /*********************************************************
     * 2. Send the Request
    *********************************************************/

    /**
     * Perform the request. After sending, all requests will have their
     * responses poulated.
     *
     * @return bool TRUE on success, FALSE on failure.
     * @throws OutOfSequenceException If called multiple times.
     */
    public function send ();
}
