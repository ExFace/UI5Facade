<?php
namespace exface\UI5Facade\Facades\Elements;

class UI5WidgetGroup extends UI5Container
{
    
    public function buildJsConstructor($oControllerJs = 'oController') : string
    {
        if ($this->getWidget()->isHidden()) {
            return parent::buildJsConstructor($oControllerJs);
        }
        //TODO check hide caption? -> DONE
        $caption = '';
        if (! $this->getWidget()->getHideCaption()) {
            $captionText = $this->getCaption() ? 'text: "' . $this->getCaption() . '",' : '';
            $caption = <<<JS
            new sap.ui.core.Title({
                    {$captionText}
                }),

JS;
        }
        
        return  <<<JS
                {$caption}}
                {$this->buildJsChildrenConstructors()}
JS;
    }
}
?>