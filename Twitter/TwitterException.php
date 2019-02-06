<?php
/**
 * Created by PhpStorm.
 * User: alan
 * Date: 13/01/17
 * Time: 08:45
 */

namespace doublea\social;


use Exception;

class TwitterException extends \Exception
{

    public function __construct($message = "", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}