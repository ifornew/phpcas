<?php
namespace Iwannamaybe\PhpCas\Requests;

/**
 * Class CurlRequest
 *
 * Provides support for performing web-requests via curl
 *
 * @package Iwannamaybe\PhpCas\Requests
 */
class CurlRequest extends AbstractRequest implements RequestInterface
{
    /**
     * Set additional curl options
     *
     * @param array $options option to set
     *
     * @return void
     */
    public function setCurlOptions (array $options)
    {
        $this->_curlOptions = $options;
    }
    private $_curlOptions = array();

    /**
     * Send the request and store the results.
     *
     * @return bool true on success, false on failure.
     */
    protected function sendRequest ()
    {
        $ch = $this->initAndConfigure();

        /*********************************************************
         * Perform the query
        *********************************************************/
        $buf = curl_exec($ch);
        if ( $buf === false ) {
            $this->storeErrorMessage(
                'CURL error #'.curl_errno($ch).': '.curl_error($ch)
            );
            $res = false;
        } else {
            $this->storeResponseBody($buf);
            $res = true;

        }
        curl_close($ch);
        return $res;
    }

    /**
     * Internal method to initialize our cURL handle and configure the request.
     * This method should NOT be used outside of the CurlRequest or the
     * CurlMultiRequest.
     *
     * @return resource The cURL handle on success, false on failure
     */
    public function initAndConfigure()
    {
        $ch = curl_init($this->url);
	    curl_setopt_array($ch, $this->_curlOptions);
        /*********************************************************
         * Set SSL configuration
        *********************************************************/
        if ($this->caCertPath) {
            if ($this->validateCN) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
            curl_setopt($ch, CURLOPT_CAINFO, $this->caCertPath);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        /*********************************************************
         * Configure curl to capture our output.
        *********************************************************/
        // return the CURL output into a variable
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // get the HTTP header with a callback
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, '_curlReadHeaders'));
        /*********************************************************
         * Add cookie headers to our request.
        *********************************************************/
        if (count($this->cookies)) {
            $cookieStrings = array();
            foreach ($this->cookies as $name => $val) {
                $cookieStrings[] = $name.'='.$val;
            }
            curl_setopt($ch, CURLOPT_COOKIE, implode(';', $cookieStrings));
        }
        /*********************************************************
         * Add any additional headers
        *********************************************************/
        if (count($this->headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        }
        /*********************************************************
         * Flag and Body for POST requests
        *********************************************************/
        if ($this->isPost) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->postBody);
        }
        return $ch;
    }

    /**
     * Store the response body.
     * This method should NOT be used outside of the CurlRequest or the
     * CurlMultiRequest.
     *
     * @param string $body body to stor
     *
     * @return void
     */
    private function _storeResponseBody ($body)
    {
        $this->storeResponseBody($body);
    }

    /**
     * Internal method for capturing the headers from a curl request.
     *
     * @param CurlRequest $ch     handle of curl
     * @param string $header header
     *
     * @return int
     */
    private function _curlReadHeaders ($ch, $header)
    {
        $this->storeResponseHeader($header);
        return strlen($header);
    }
}
