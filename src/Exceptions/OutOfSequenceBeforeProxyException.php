<?php
namespace Iwannamaybe\PhpCas\Exceptions;

use Iwannamaybe\PhpCas\CasException;

/**
 * Class OutOfSequenceBeforeProxyException
 *
 * This class defines Exceptions that should be thrown when the sequence of operations is invalid. In this case it should be thrown when the proxy() call has not yet happened and no proxy object exists
 *
 * @package Iwannamaybe\PhpCas\Exceptions
 */
class OutOfSequenceBeforeProxyException extends OutOfSequenceException implements CasException
{
	/**
	 * OutOfSequenceBeforeProxyException constructor.
	 */
    public function __construct ()
    {
        parent::__construct('this method cannot be called before phpCAS::proxy()');
    }
}
