<?php
namespace exface\UI5Facade\Exceptions;

use exface\Core\Exceptions\RuntimeException;

/**
 * Exception the controller model is requested, but was not yet initialized for any of the widget parents
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5ControllerNotInitializedException extends RuntimeException
{
    
}