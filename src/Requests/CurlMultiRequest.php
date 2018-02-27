<?php
namespace Iwannamaybe\PhpCas\Requests;
use Iwannamaybe\PhpCas\Exceptions\CasInvalidArgumentException;
use Iwannamaybe\PhpCas\Exceptions\OutOfSequenceException;

/**
 * Class CurlMultiRequest
 *
 * This interface defines a class library for performing multiple web requests in batches. Implementations of this interface may perform requests serially or in parallel
 *
 * @package Iwannamaybe\PhpCas\Requests
 */
class CurlMultiRequest implements MultiRequestInterface
{
    private $_requests = array();
    private $_sent = false;

    /*********************************************************
     * Add Requests
    *********************************************************/

    /**
     * Add a new Request to this batch.
     * Note, implementations will likely restrict requests to their own concrete
     * class hierarchy.
     *
     * @param RequestInterface $request reqest to add
     *
     * @return void
     * @throws OutOfSequenceException If called after the Request has been sent.
     * @throws CasInvalidArgumentException If passed a Request of the wrong
     * implmentation.
     */
    public function addRequest (RequestInterface $request)
    {
        if ($this->_sent) {
            throw new OutOfSequenceException(
                'Request has already been sent cannot '.__METHOD__
            );
        }
        if (!$request instanceof CurlRequest) {
            throw new CasInvalidArgumentException(
                'As a CAS_Request_CurlMultiRequest, I can only work with CurlRequest objects.'
            );
        }

        $this->_requests[] = $request;
    }

    /**
     * Retrieve the number of requests added to this batch.
     *
     * @return number of request elements
     */
    public function getNumRequests()
    {
        if ($this->_sent) {
            throw new OutOfSequenceException(
                'Request has already been sent cannot '.__METHOD__
            );
        }
        return count($this->_requests);
    }

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
    public function send ()
    {
        if ($this->_sent) {
            throw new OutOfSequenceException(
                'Request has already been sent cannot send again.'
            );
        }
        if (!count($this->_requests)) {
            throw new OutOfSequenceException(
                'At least one request must be added via addRequest() before the multi-request can be sent.'
            );
        }

        $this->_sent = true;

        // Initialize our handles and configure all requests.
        $handles = array();
        $multiHandle = curl_multi_init();
        foreach ($this->_requests as $i => $request) {
            $handle = $request->initAndConfigure();
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            $handles[$i] = $handle;
            curl_multi_add_handle($multiHandle, $handle);
        }

        // Execute the requests in parallel.
        do {
            curl_multi_exec($multiHandle, $running);
        } while ($running > 0);

        // Populate all of the responses or errors back into the request objects.
        foreach ($this->_requests as $i => $request) {
            $buf = curl_multi_getcontent($handles[$i]);
            $request->_storeResponseBody($buf);
            curl_multi_remove_handle($multiHandle, $handles[$i]);
            curl_close($handles[$i]);
        }

        curl_multi_close($multiHandle);
        return true;
    }
}
