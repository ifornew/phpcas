<?php
namespace Iwannamaybe\PhpCas\Exceptions;

use Iwannamaybe\PhpCas\CasConst;
use Iwannamaybe\PhpCas\CasException;
use Iwannamaybe\PhpCas\Client;
use RuntimeException;

/**
 * Class AuthenticationException
 * This interface defines methods that allow proxy-authenticated service handlers to interact with phpCAS.
 * Proxy service handlers must implement this interface as well as call
 * phpCAS::initializeProxiedService($this) at some point in their implementation.
 * While not required, proxy-authenticated service handlers are encouraged to implement the CAS_ProxiedService_Testable interface to facilitate unit testing.
 * @package Iwannamaybe\PhpCas\Exceptions
 */
class AuthenticationException extends RuntimeException implements CasException
{
	/**
	 * This method is used to print the HTML output when the user was not authenticated.
	 *
	 * @param Client $client           phpcas client
	 * @param string $failure          the failure that occured
	 * @param string $cas_url          the URL the CAS server was asked for
	 * @param bool   $no_response      the response from the CAS server (other parameters are ignored if TRUE)
	 * @param bool   $bad_response     bad response from the CAS server ($err_code and $err_msg ignored if TRUE)
	 * @param string $cas_response     the response of the CAS server
	 * @param int    $err_code         the error code given by the CAS server
	 * @param string $err_msg          the error message given by the CAS server
	 */
	public function __construct($client, $failure, $cas_url, $no_response, $bad_response = false, $cas_response = '', $err_code = null, $err_msg = '')
	{
		$messages   = array();
		$lang       = $client->getTranslator();
		$messages[] = 'CAS URL: ' . $cas_url;
		$messages[] = 'Authentication failure: ' . $failure;
		if ($no_response) {
			$messages[] = 'Reason: no response from the CAS server';
		} else {
			if ($bad_response) {
				$messages[] = 'Reason: bad response from the CAS server';
			} else {
				switch ($client->getServerVersion()) {
					case CasConst::CAS_VERSION_1_0:
						$messages[] = 'Reason: CAS error';
						break;
					case CasConst::CAS_VERSION_2_0:
					case CasConst::CAS_VERSION_3_0:
						if (empty($err_code)) {
							$messages[] = 'Reason: no CAS error';
						} else {
							$messages[] = 'Reason: [' . $err_code . '] CAS error: ' . $err_msg;
						}
						break;
				}
			}
			$messages[] = 'CAS response: ' . $cas_response;
		}
		parent::__construct(implode("\n", $messages));
	}
}

?>
