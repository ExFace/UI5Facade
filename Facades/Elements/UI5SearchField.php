<?php
namespace exface\UI5Facade\Facades\Elements;

/**
 * Generates sap.m.SearchField for any value widget.
 * 
 * In contrast to a regular element, SearchField does not have a widget prototype. Any
 * value widget can be rendered as ObjectStatus by instantiating it manually:
 * 
 * ```
 * $element = new UI5SearchField($widget, $this->getFacade());
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class UI5SearchField extends UI5Value
{    
    private $placeholder = null;
    
    private $searchCallbackJs = '';
    
    private $widthCollapsed = '0px';
    
    private $widthExpanded = '200px';
  
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Value::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        return <<<JS
        
        new sap.m.SearchField("{$this->getId()}", {
            width: {$this->buildJsPropertyWidthCollapsed()},
            placeholder: "{$this->getPlaceholder()}",
            search: {$this->getSearchCallbackJs()},
            layoutData: new sap.m.OverflowToolbarLayoutData({priority: "NeverOverflow"})
        }).addEventDelegate({
            onfocusin : function(oEvent) {
                oEvent.srcControl.setWidth({$this->buildJsPropertyWidthExpanded()});
            },
            onsapfocusleave : function(oEvent) {
                var oInput = oEvent.srcControl;
                if (oInput.getValue() === undefined || oInput.getValue().length < 4) {
                    oEvent.srcControl.setWidth({$this->buildJsPropertyWidthCollapsed()});
                }
            }
        }),
        
JS;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyWidthCollapsed() : string
    {
        return "'{$this->getWidthCollapsed()}'";
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyWidthExpanded() : string
    {
        return "'{$this->getWidthExpanded()}'";
    }
    
    /**
     * Setter for the placeholder-text to be displayed in the searchbar when the searchbar contins no text.
     * 
     * @param string $string
     * @return UI5SearchField
     */
    public function setPlaceholder(string $string) : UI5SearchField
    {
        $this->placeholder = $string;
        return $this;
    }
    
    /**
     * Getter for the placeholder-text to be displayed in the searchbar when the searchbar contins no text.
     * Returns an empty string when no placeholder has been defined. 
     * 
     * @return string
     */
    protected function getPlaceholder() : string
    {
        return $this->placeholder ?? '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsValueSetter()
     */
    public function buildJsValueSetterMethod($valueJs)
    {
        return parent::buildJsValueSetterMethod($valueJs) . '.fireSearch({query: ' . $valueJs . '})';
    }
    
    /**
     * Setter for a callback function which will be called on search.
     * 
     * @param string $js
     * @return UI5SearchField
     */
    public function setSearchCallbackJs(string $js) : UI5SearchField
    {
        $this->searchCallbackJs = $js;
        return $this;
    }
    
    /**
     * Getter for a callback function which shall be called on search.
     * 
     * @return string
     */
    protected function getSearchCallbackJs() : string
    {
        return $this->searchCallbackJs;
    }
    
    /**
     * Setter for the width the collapsed searchbar has.
     * 
     * @param string $value
     * @return UI5SearchField
     */
    public function setWidthCollapsed(string $value) : UI5SearchField
    {
        $this->widthCollapsed = $value;
        return $this;
    }
    
    /**
     * Getter for the width the collapsed searchbar shall have.
     * 
     * @return string
     */
    protected function getWidthCollapsed() : string
    {
        return $this->widthCollapsed;
    }
    
    /**
     * Setter for the width the searchbar will have after expansion.
     * 
     * @param string $value
     * @return UI5SearchField
     */
    public function setWidthExpanded(string $value) : UI5SearchField
    {
        $this->widthExpanded = $value;
        return $this;
    }
    
    /**
     * Getter for the width the searchbar shall have after expansion.
     * 
     * @return string
     */
    protected function getWidthExpanded() : string
    {
        return $this->widthExpanded;
    }
}