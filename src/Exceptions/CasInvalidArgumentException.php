<?php
namespace Iwannamaybe\PhpCas\Exceptions;

use InvalidArgumentException;
use Iwannamaybe\PhpCas\CasException;

/**
 * Class CasInvalidArgumentException
 *
 * Exception that denotes invalid arguments were passed.
 *
 * @package Iwannamaybe\PhpCas\Exceptions
 */
class CasInvalidArgumentException extends InvalidArgumentException implements CasException
{

}
?>