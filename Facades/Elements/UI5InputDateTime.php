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
        {$this->buildJsInitFocusedDateValue()}
        {$this->buildJsPseudoEventHandlers()}

JS;
    }

    public function buildJsInitFocusedDateValue(): string
    {
        $defaultTime = $this->getWidget()->getDefaultTime();
        if ($defaultTime == '' ||  $defaultTime == null) {
            return '';
        }
        $defaultTimeJs = json_encode($defaultTime, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        //TODO: Move the time parsing part to the exfTools.js or find an alternative function there.
        return <<<JS
                  .setInitialFocusedDateValue((function(){
                      var date = new Date();
                      var time = $defaultTimeJs.split(':');
                      var hour = parseInt(time[0],10) || 0,
                          minutes = parseInt(time[1],10) || 0,
                          seconds = time[2] ? parseInt(time[2],10) : 0;
                      date.setHours(hour, minutes, seconds, 0);
                      return date;
                  })())
JS;
    }
}