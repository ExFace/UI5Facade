<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Widgets\InputDateTime;

/**
 * Generates sap.m.DateTimePicker for InputDateTime widgets
 * 
 * @method InputDateTime getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5InputDateTime extends UI5InputDate
{
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        return <<<JS

        new sap.m.DateTimePicker("{$this->getId()}", {
            {$this->buildJsProperties()}
		})
        {$this->buildJsInternalModelInit()}
        {$this->buildJsPseudoEventHandlers()}

JS;
    }
    
}