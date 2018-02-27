<?php
namespace Iwannamaybe\PhpCas\Exceptions;

use Iwannamaybe\PhpCas\CasException;

/**
 * Class OutOfSequenceBeforeClientException
 *
 * This class defines Exceptions that should be thrown when the sequence of operations is invalid. In this case it should be thrown when the client() or proxy() call has not yet happened and no client or proxy object exists
 *
 * @package Iwannamaybe\PhpCas\Exceptions
 */
class OutOfSequenceBeforeClientException extends OutOfSequenceException implements CasException
{
	/**
	 * OutOfSequenceBeforeClientException constructor.
	 */
    public function __construct ()
    {
        parent::__construct('this method cannot be called before phpCAS::client() or phpCAS::proxy()');
    }
}
