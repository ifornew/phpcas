<?php
namespace Iwannamaybe\PhpCas\Exceptions;

use Iwannamaybe\PhpCas\CasException;
use BadMethodCallException;

/**
 * Class OutOfSequenceException
 *
 * This class defines Exceptions that should be thrown when the sequence of
 * operations is invalid. Examples are:
 *		- Requesting the response before executing a request.
 *		- Changing the URL of a request after executing the request.
 */
class OutOfSequenceException extends BadMethodCallException implements CasException
{

}
