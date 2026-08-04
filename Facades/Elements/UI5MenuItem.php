<?php
namespace exface\UI5Facade\Facades\Elements;

/**
 * Renders a sap.m.MenuItem for Button widgets inside a MenuButton
 * 
 * @method \exface\Core\Widgets\Button getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5MenuItem extends UI5Button
{
    private $startsSection = false;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Button::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {       
        $this->registerExternalModules($this->getController());
        
        $startsSectionJs = '';
        
        if ($this->getStartsSection() === true) {
            $startsSectionJs .= 'startsSection: true,';
        }
        
        /* @var $btnElement \exface\UI5Facade\Facades\Elements\UI5Button */
        $handler = $this->buildJsClickViewEventHandlerCall();
        $press = $handler !== '' ? 'press: ' . $handler . ',' : '';
        
        return <<<JS
        
                        new sap.m.MenuItem('{$this->getId()}', {
                            {$startsSectionJs}
                            text: "{$this->getCaption()}",
                            icon: "{$this->getIconSrc($this->getWidget()->getIcon())}",
                            {$this->buildJsPropertyTooltip()}
                            {$this->buildJsPropertyVisibile()}
                            {$press}
                        })
                        {$this->buildJsPseudoEventHandlers()}
                        .addStyleClass("{$this->buildCssWidgetClass()}")
                        
JS;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildCssElementClass()
     */
    public function buildCssElementClass()
    {
        return 'exf-btn-menu-item';
    }
    
    /**
     * 
     * @return bool
     */
    public function getStartsSection() : bool
    {
        return $this->startsSection;
    }
    
    /**
     * 
     * @param bool $value
     * @return UI5MenuItem
     */
    public function setStartsSection(bool $value) : UI5MenuItem
    {
        $this->startsSection = $value;
        return $this;
    }
}