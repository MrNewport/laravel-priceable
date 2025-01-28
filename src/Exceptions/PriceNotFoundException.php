<?php

namespace MrNewport\LaravelPriceable\Exceptions;

use Exception;

class PriceNotFoundException extends Exception
{
    public function __construct($message = "No price found for the given criteria.")
    {
        parent::__construct($message);
    }
}
