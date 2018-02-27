<?php
namespace Iwannamaybe\PhpCas;

/**
 * A root exception interface for all exceptions in phpCAS.
 *
 * All exceptions thrown in phpCAS should implement this interface to allow them
 * to be caught as a category by clients. Each phpCAS exception should extend
 * an appropriate SPL exception class that best fits its type.
 *
 * For example, an InvalidArgumentException in phpCAS should be defined as
 *
 *		class CAS_InvalidArgumentException
 *			extends InvalidArgumentException
 *			implements CasException
 *		{ }
 *
 * This definition allows the CAS_InvalidArgumentException to be caught as either
 * an InvalidArgumentException or as a CasException.
 *
 * @class    CasException
 * @category Authentication
 * @package  PhpCAS
 * @author   Adam Franco <afranco@middlebury.edu>
 * @license  http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @link     https://wiki.jasig.org/display/CASC/phpCAS
 *
 */
interface CasException
{

}
?>