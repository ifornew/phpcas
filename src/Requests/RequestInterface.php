<?php
namespace Iwannamaybe\PhpCas\Requests;
use Iwannamaybe\PhpCas\Exceptions\OutOfSequenceException;

/**
 * Interface RequestInterface
 *
 * This interface defines a class library for performing web requests.
 *
 * @package Iwannamaybe\PhpCas\Requests
 */
interface RequestInterface
{
    /*********************************************************
     * Configure the Request
    *********************************************************/

    /**
     * Set the URL of the Request
     *
     * @param string $url url to set
     *
     * @return void
     * @throws OutOfSequenceException If called after the Request has been sent.
     */
    public function setUrl ($url);

    /**
     * Add a cookie to the request.
     *
     * @param string $name  name of cookie
     * @param string $value value of cookie
     *
     * @return void
     * @throws OutOfSequenceException If called after the Request has been sent.
     */
    public function addCookie ($name, $value);

    /**
     * Add an array of cookies to the request.
     * The cookie array is of the form
     *     array('cookie_name' => 'cookie_value', 'cookie_name2' => cookie_value2')
     *
     * @param array $cookies cookies to add
     *
     * @return void
     * @throws OutOfSequenceException If called after the Request has been sent.
     */
    public function addCookies (array $cookies);

    /**
     * Add a header string to the request.
     *
     * @param string $header header to add
     *
     * @return void
     * @throws OutOfSequenceException If called after the Request has been sent.
     */
    public function addHeader ($header);

    /**
     * Add an array of header strings to the request.
     *
     * @param array $headers headers to add
     *
     * @return void
     * @throws OutOfSequenceException If called after the Request has been sent.
     */
    public function addHeaders (array $headers);

    /**
     * Make the request a POST request rather than the default GET request.
     *
     * @return void
     * @throws OutOfSequenceException If called after the Request has been sent.
     */
    public function makePost ();

    /**
     * Add a POST body to the request
     *
     * @param string $body body to add
     *
     * @return void
     * @throws OutOfSequenceException If called after the Request has been sent.
     */
    public function setPostBody ($body);


    /**
     * Specify the path to an SSL CA certificate to validate the server with.
     *
     * @param string  $caCertPath  path to cert file
     * @param boolean $validate_cn validate CN of SSL certificate
     *
     * @return void
     * @throws OutOfSequenceException If called after the Request has been sent.
     */
    public function setSslCaCert ($caCertPath, $validate_cn = true);



    /*********************************************************
     * 2. Send the Request
    *********************************************************/

    /**
     * Perform the request.
     *
     * @return bool TRUE on success, FALSE on failure.
     * @throws OutOfSequenceException If called multiple times.
     */
    public function send ();

    /*********************************************************
     * 3. Access the response
    *********************************************************/

    /**
     * Answer the headers of the response.
     *
     * @return array An array of header strings.
     * @throws OutOfSequenceException If called before the Request has been sent.
     */
    public function getResponseHeaders ();

    /**
     * Answer HTTP status code of the response
     *
     * @return int
     * @throws OutOfSequenceException If called before the Request has been sent.
     */
    public function getResponseStatusCode ();

    /**
     * Answer the body of response.
     *
     * @return string
     * @throws OutOfSequenceException If called before the Request has been sent.
     */
    public function getResponseBody ();

    /**
     * Answer a message describing any errors if the request failed.
     *
     * @return string
     * @throws OutOfSequenceException If called before the Request has been sent.
     */
    public function getErrorMessage ();
}
