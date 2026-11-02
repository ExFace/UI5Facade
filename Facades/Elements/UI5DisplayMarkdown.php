<?php

namespace exface\UI5Facade\Facades\Elements;

use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement;
use exface\Core\Facades\AbstractAjaxFacade\Elements\ToastUIEditorTrait;
use exface\Core\Factories\ActionFactory;
use exface\Core\Widgets\DisplayMarkdown;

/**
 * UI5 implementation of the corresponding widget.
 * 
 * @see DisplayMarkdown
 */
class UI5DisplayMarkdown extends UI5Value
{
    use ToastUIEditorTrait;

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Text::buildJsConstructorForMainControl()
     */
    public function buildJsConstructorForMainControl($oControllerJs = 'oController')
    {
        $this->registerExternalModules($this->getController());
        $markdownVar = $this->buildJsMarkdownVar();
        
        return <<<JS

        new sap.ui.core.HTML("{$this->getId()}", {
            content: {$this->escapeString("<div style=\"height:{$this->buildCssHeight()}\"> {$this->buildHtmlMarkdownEditor()} </div>")},
            afterRendering: function(oEvent) {
                var oHtml = sap.ui.getCore().byId('{$this->getId()}');
                var oModel = oHtml ? oHtml.getModel() : undefined;
                var sBindingPath = '{$this->getValueBindingPath()}';

                // False on the first render of this control, true on every later re-render
                // (e.g. after a tab switch), because the flag is set on the control at the end.
                var bWasInitialized = oHtml && "_toastUiBinding" in oHtml && oHtml._toastUiBinding;

                // ToastUI's DOM can be missing on first render or wiped by a re-render,
                // so the viewer is (re)created whenever its contents element is absent.
                if (($('#{$this->getId()}').find('.toastui-editor-contents').length === 0)) {
                    {$markdownVar} = {$this->buildJsMarkdownInitViewer()};

                    // A new viewer starts from the widget's initialValue, which is usually empty for
                    // data-bound widgets. Apply the current model value so already-loaded content shows up.
                    if (oModel !== undefined) {
                        var sModelVal = oModel.getProperty(sBindingPath);

                        // On first render the model may still be loading and hold "", which must not
                        // overwrite a non-empty initialValue. On re-renders, "" is the real content.
                        if (sModelVal !== undefined && (bWasInitialized || sModelVal !== '')) {
                            {$this->buildJsValueSetter("sModelVal")}
                        }
                    }
                }

                // The listener below is attached only once, so skip it on re-renders.
                if (bWasInitialized) {
                    return;
                }

                if(oModel !== undefined) {
                    // Property bindings are registered with the model, so attachChange fires on every
                    // model update. A plain sap.ui.model.Binding is never registered and would not fire.
                    var oValueBinding = oModel.bindProperty(sBindingPath);
                    
                    oValueBinding.attachChange(function(oEvent){
                        setTimeout(function(){
                            var sVal = oModel.getProperty(sBindingPath);
                            // Do not update if the model does not have this property
                            if (sVal === undefined) {
                                return;
                            }
                            {$this->buildJsValueSetter("sVal")}
                        }, 0);
                    });
                }

                {$this->buildJsRefreshOnActionPerformed()}
                
                oHtml._toastUiBinding = true;
            }
        })
JS;
    }

    /**
     * Re-reads only the value of this widget from the server and puts it into the data model.
     * 
     * The display has no data source of its own - its value is part of the prefill of the
     * surrounding dialog/page. Instead of refreshing that entire container, this asks the server
     * to prefill just this widget for the current record (identified by its UID in the model).
     * The model change is then picked up by the property binding in the constructor, which
     * redraws the viewer.
     * 
     * Security: uses `exface.Core.ReadPrefill` with this widget as trigger, so the server only
     * accepts columns this widget actually displays and applies the regular data authorization.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsRefresh()
     */
    public function buildJsRefresh()
    {
        if (! $this->isRefreshableFromServer()) {
            return '';
        }
        
        $widget = $this->getWidget();
        $obj = $widget->getMetaObject();
        $action = ActionFactory::createFromString($this->getWorkbench(), 'exface.Core.ReadPrefill', $widget);
        $uidColName = DataColumn::sanitizeColumnName($obj->getUidAttributeAlias());
        $uidPathJs = json_encode($this->getValueBindingPrefix() . $uidColName);
        $uidColNameJs = json_encode($uidColName);
        $valueColPathJs = json_encode('/' . $widget->getDataColumnName());
        $valuePathJs = json_encode($this->getValueBindingPath());
        $objIdJs = json_encode($obj->getId());
        $onLoadedJs = <<<JS

                    var sVal = oResultModel.getProperty({$valueColPathJs});
                    if (sVal !== undefined) {
                        oModel.setProperty({$valuePathJs}, sVal);
                    }
JS;
        
        return <<<JS

            (function(){
                var oHtml = sap.ui.getCore().byId('{$this->getId()}');
                var oModel = oHtml ? oHtml.getModel() : undefined;
                var sUid = oModel ? oModel.getProperty({$uidPathJs}) : undefined;
                var oResultModel = new sap.ui.model.json.JSONModel();
                var oRow = {};
                var oParams;
                
                // Without a record UID there is nothing to re-read (e.g. the prefill is not loaded yet)
                if (sUid === undefined || sUid === null || sUid === '') {
                    return;
                }
                
                oRow[{$uidColNameJs}] = sUid;
                oParams = {
                    action: "exface.Core.ReadPrefill",
                    resource: "{$widget->getPage()->getAliasWithNamespace()}",
                    element: "{$widget->getId()}",
                    object: {$objIdJs},
                    data: {
                        oId: {$objIdJs},
                        rows: [oRow]
                    }
                };
                
                {$this->getServerAdapter()->buildJsServerRequest($action, 'oResultModel', 'oParams', $onLoadedJs)}
            })();
JS;
    }
    
    /**
     * Returns JS to refresh this widget whenever an action explicitly lists it in its `refresh_widget_ids`.
     * 
     * Containers react to `actionperformed` on their own, but value widgets do not, so without this
     * listener a refresh request for this widget would be ignored. Only explicit requests are handled
     * here - action effects alone never trigger a reload.
     * 
     * @return string
     */
    protected function buildJsRefreshOnActionPerformed() : string
    {
        if (! $this->isRefreshableFromServer()) {
            return '';
        }
        
        $eventNs = AbstractJqueryElement::EVENT_NAME_ACTIONPERFORMED . '.' . $this->getId();
        $widgetIdJs = json_encode($this->getWidget()->getId());
        
        return <<<JS

                $(document).off('{$eventNs}').on('{$eventNs}', function(oEvent, oParams) {
                    var sWidgetId = {$widgetIdJs};
                    // The document listener outlives the control if its view is destroyed, so clean up then
                    if (sap.ui.getCore().byId('{$this->getId()}') === undefined) {
                        $(document).off('{$eventNs}');
                        return;
                    }
                    // Event params contain widget ids (not element ids), so compare against those
                    if ((oParams.refresh_not_widgets || []).indexOf(sWidgetId) !== -1) {
                        return;
                    }
                    if ((oParams.refresh_widgets || []).indexOf(sWidgetId) === -1) {
                        return;
                    }
                    {$this->buildJsRefresh()}
                });
JS;
    }
    
    /**
     * Returns TRUE if the value can be re-read by UID: the widget must show an attribute of an object with a UID.
     * 
     * @return bool
     */
    protected function isRefreshableFromServer() : bool
    {
        $widget = $this->getWidget();
        return $widget->isBoundToAttribute() && $widget->getMetaObject()->hasUidAttribute();
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(\exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface $controller) : UI5AbstractElement
    {
        $controller->addExternalModule('libs.exface.toastUi', $this->getFacade()->buildUrlToSource('LIBS.TOASTUI.EDITOR.JS'), 'toastui');
        $controller->addExternalCss($this->getFacade()->buildUrlToSource('LIBS.TOASTUI.EDITOR.CSS'));

        if ($this->getWidget()->hasRenderMermaidDiagrams()) {
            $controller->addExternalModule('libs.exface.mermaid', $this->getFacade()->buildUrlToSource('LIBS.MERMAID.JS'), 'mermaid');
        }
        
        return $this;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5Input::getHeight()
     */
    public function getHeight()
    {
        if ($this->getWidget()->getHeight()->isUndefined()) {
            return (2 * $this->getHeightRelativeUnit()) . 'px';
        }
        return parent::getHeight();
    }
}