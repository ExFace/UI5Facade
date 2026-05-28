<?php
namespace exface\UI5Facade\Facades\Interfaces;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface UI5ConfirmationElementInterface 
{
    
    /**
     * Returns JS code to show the confirmation if required
     * 
     * @param string $jsRequestData
     * @param string $onContinueJs
     * @param string $onCancelJs
     * @return void
     */
    public function buildJsConfirmation(string $jsRequestData, string $onContinueJs, string $onCancelJs = '') : string;
}