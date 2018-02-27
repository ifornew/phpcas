<?php
namespace Iwannamaybe\PhpCas\Exceptions;
use Iwannamaybe\PhpCas\CasException;

/**
 * Class OutOfSequenceBeforeAuthenticationCallException
 *
 * This class defines Exceptions that should be thrown when the sequence of operations is invalid. In this case it should be thrown when an authentication call has not yet happened
 *
 * @package Iwannamaybe\PhpCas\Exceptions
 */
class OutOfSequenceBeforeAuthenticationCallException extends OutOfSequenceException implements CasException
{
	/**
	 * OutOfSequenceBeforeAuthenticationCallException constructor.
	 */
    public function __construct ()
    {
        parent::__construct('An authentication call hasn\'t happened yet.');
    }
}
