<?php

namespace Localhook\Localhook\Exceptions;

use Exception;

class DeletedChannelException extends Exception
{
    /**
     * DeletedChannelException constructor.
     *
     * @param string $string
     */
    public function __construct($string)
    {
    }
}
