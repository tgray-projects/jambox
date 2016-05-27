<?php
/**
 * Created by PhpStorm.
 * User: tgray
 * Date: 15-03-12
 * Time: 2:07 PM
 */

namespace Workshop\Authentication;

use Zend\Authentication\Result as ZendResult;

class Result extends ZendResult
{

    /**
     * Failure due to registration being incomplete.
     */
    const FAILURE_UNREGISTERED = -5;
}
