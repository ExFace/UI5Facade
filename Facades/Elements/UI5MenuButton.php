<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\JqueryButtonTrait;

/**
 * Generates sap.m.MenuButton for MenuButton widgets
 *
 * @method \exface\Core\Widgets\MenuButton getWidget()
 * 
 * @author Andrej Kabachnik
 *        
 */
class UI5MenuButton extends UI5AbstractElement
{
    use JqueryButtonTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::buildJsConstructor()
     */
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        return <<<JS

    new sap.m.MenuButton("{$this->getId()}", {
        text: "{$this->getCaption()}",
        {$this->buildJsPropertyIcon()}
        {$this->buildJsProperties()}
        menu: [
            new sap.m.Menu({
                items: [
                    {$this->buildJsMenuItems()}
                ]
            })
		]
	})
    .addStyleClass("{$this->buildCssElementClass()}")
    {$this->buildJsPseudoEventHandlers()}

JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::isVisible()
     */
    protected function isVisible() : bool
    {
        $selfVisible = parent::isVisible();
        $itemsVisible = false;
        if ($selfVisible === true) {
            foreach($this->getWidget()->getButtons() as $btn) {
                if ($btn->isHidden() === false) {
                    $itemsVisible = true;
                    break;
                }
            }
        }
        return $selfVisible && $itemsVisible;
    }
        
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::getCaption()
     */
    protected function getCaption() : string
    {
        $caption = parent::getCaption();
        $widget = $this->getWidget();
        if ($caption == '' && !$widget->getIcon()) {
            $caption = '...';
        }
        return $caption;
    }
        
    /**
     * 
     * @return string
     */
    protected function buildJsMenuItems() : string
    {
        $js = '';
        $last_parent = null;
        $start_section = false;
        /* @var $b \exface\Core\Widgets\Button */
        foreach ($this->getWidget()->getButtons() as $b) {
            if (is_null($last_parent)){
                $last_parent = $b->getParent();
            }
            
            // Create a menu entry: a link for actions or a separator for empty buttons
            if (! $b->getCaption() && ! $b->getAction()){
                $start_section = true;
            } else {
                $btnElement = new UI5MenuItem($b, $this->getFacade());
                
                if ($b->getParent() !== $last_parent){
                    $start_section = true;
                    $last_parent = $b->getParent();
                }
                
                $btnElement->setStartsSection($start_section);
                
                $js .= $btnElement->buildJsConstructor() . ',';
            }
        }
        return $js;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsPropertyIcon()
    {
        $widget = $this->getWidget();
        return ($widget->getIcon() ? 'icon: "' . $this->getIconSrc($widget->getIcon()) . '", ' : '');
    }
    
    /**
     *
     * {@inheritdoc}
     * @see JqueryButtonTrait::buildJsCloseDialog()
     */
    protected function buildJsCloseDialog() : string
    {
        return '';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerConditionalProperties()
     */
    public function registerConditionalProperties() : UI5AbstractElement
    {
        // Make sure, the MenuButton is hidden if no menu items are visible.
        // TODO Should probably make it visible again once at least one item is visible?
        if ($this->isVisible()) {
            foreach ($this->getWidget()->getButtons() as $btn) {
                $btnEl = $this->getFacade()->getElement($btn);
                if ($btnId = $btnEl->getId()) {
                    $this->addPseudoEventHandler('onAfterRendering', <<<JS
    
                    sap.ui.getCore().byId('$btnId').$().on('visibleChange', function(oEvent){
                        var oMenuButton = sap.ui.getCore().byId('{$this->getId()}');
                        var bItemsVisible = false;
                        oMenuButton.getMenu().getItems().forEach(function(oItem){
                            if (oItem.getVisible() === true){
                                bItemsVisible = true;
                            }
                        });
                        if (bItemsVisible === false) {
                            oMenuButton.setVisible(bItemsVisible);
                        }
                    });
                        
JS);
                }
            }
        }
        
        return parent::registerConditionalProperties();
    }
}