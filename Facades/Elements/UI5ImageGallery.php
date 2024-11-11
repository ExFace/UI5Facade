<?php
namespace exface\UI5Facade\Facades\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Elements\SlickGalleryTrait;
use exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait;
use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsDateFormatter;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Facades\AbstractAjaxFacade\Elements\JsUploaderTrait;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Widgets\Parts\Uploader;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\DataTypes\ByteSizeDataType;

/**
 * 
 * @method exface\Core\Widgets\Chart getWidget()
 * @method UI5ControllerInterface getController()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5ImageGallery extends UI5AbstractElement
{
    use SlickGalleryTrait, UI5DataElementTrait {
        SlickGalleryTrait::buildJsValueGetter insteadof UI5DataElementTrait;
        SlickGalleryTrait::buildJsDataGetter as buildJsSlickDataGetter;
        SlickGalleryTrait::buildJsDataResetter insteadof UI5DataElementTrait;
        SlickGalleryTrait::buildJsUploadStore as buildJsUploadStoreViaTrait;
        UI5DataElementTrait::buildJsDataLoaderOnLoaded as buildJsDataLoaderOnLoadedViaTrait;
    }
    
    use JsUploaderTrait;
    
    /**
     * 
     * @param string $oControllerJs
     * @return string
     */
    public function buildJsConstructorForControl($oControllerJs = 'oController') : string
    {
        $this->getConfiguratorElement()->registerFiltersWithApplyOnChange($this);
        $this->addCarouselFeatureButtons($this->getWidget()->getToolbarMain()->getButtonGroup(0), 1);
        
        $controller = $this->getController(); 
        $controller->addOnShowViewScript($this->buildJsUploadEditorInit($oControllerJs));
        $this->registerExternalModules($controller);
        
        $title = $this->escapeJsTextValue($this->escapeString($this->getHint(), false, true), false);
        
        // **IMPORTANT:** double-check that slick is not initialized yet. For some reason afterRendering()
        // is called multiple times sometimes!
        $chart = <<<JS

                new sap.ui.core.HTML("{$this->getId()}", {
                    content: "<div id=\"{$this->getId()}\" style=\"height: calc({$this->getHeight()} - 2.75rem - 2px);\"><div id=\"{$this->getIdOfSlick()}\" class=\"slick-carousel horizontal\" style=\"height: 100%\" title=\"{$title}\"></div></div>",
                    afterRendering: function(oEvent) {
                        var jqSlick = $('#{$this->getIdOfSlick()}');
                        var oHtml = sap.ui.getCore().byId('{$this->getId()}');
                        var oModel = oHtml.getModel();
                        if (jqSlick.hasClass('slick-initialized')) {
                            return;
                        }


                        {$this->buildJsSlickInit()}
                        setTimeout(function() {
                            {$this->buildJsSlickSlidesFromData('jqSlick', 'oModel.getData()')}
                        }, 0);

                        {$this->buildJsUploaderInit('jqSlick')}

                        oHtml.getParent().addStyleClass('sapUiNoContentPadding');
                    }
                })

JS;
                        
        return $this->buildJsPanelWrapper($chart, $oControllerJs);
    }
    
    /**
     * 
     * @return string
     */
    protected function getHint() : string
    {
        $hint = $this->getHintForSlick();
        if ($this->getWidget()->isUploadEnabled()) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            $hint .= PHP_EOL . '- ' . $translator->translate('WIDGET.BUTTON.HINT_DATA_MAX_SIZE', ['%size_formatted%' => ByteSizeDataType::formatWithScale($this->getAjaxPostSizeMax())]);
        }
        return $hint ?? '';
    }
    
    /**
     * 
     * @return string
     */
    public function getIdOfSlick() : string
    {
        return $this->getId() . '_slick';
    }
    
    /**
     * 
     * @param string $oModelJs
     * @return string
     */
    protected function buildJsDataLoaderOnLoaded(string $oModelJs = 'oModel') : string
    {
        return $this->buildJsDataLoaderOnLoadedViaTrait($oModelJs) . <<<JS

                var carousel = $('#{$this->getIdOfSlick()}');
                    
                {$this->buildJsSlickSlidesFromData('carousel', 'oModel.getData()')}

JS;
    }
    
    /**
     * 
     * @param string $oParamsJs
     * @param string $onUploadCompleteJs
     * @return string
     */
    protected function buildJsUploadSend(string $oParamsJs, string $onUploadCompleteJs) : string
    {
        $uploader = $this->getUploader();
        $uploadAction = $uploader->getInstantUploadAction();
        $onUploadCompleteJs = <<<JS
        
            if (oUploadModel.getProperty('/success') !== undefined){
           		{$this->buildJsShowMessageSuccess("oUploadModel.getProperty('/success')")}
			}

            {$onUploadCompleteJs}
            
JS;
        return <<<JS

                var oUploadModel = new sap.ui.model.json.JSONModel();
                oUploadModel.setData($oParamsJs.data);
                {$this->buildJsDataLoaderOnLoadedHandleWidgetLinks('oUploadModel')}
                {$this->getServerAdapter()->buildJsServerRequest($uploadAction, 'oUploadModel', 'oParams', $onUploadCompleteJs, $onUploadCompleteJs)}

JS;
    }
    
    protected function buildJsUploadStore(string $oParamsJs, string $onUploadCompleteJs) : string
    {
        return <<<JS
        
            var oUploadModel = new sap.ui.model.json.JSONModel();
            oUploadModel.setData($oParamsJs.data);
            {$this->buildJsDataLoaderOnLoadedHandleWidgetLinks('oUploadModel')}
            $oParamsJs.data = oUploadModel.getData();

            {$this->buildJsUploadStoreViaTrait($oParamsJs, $onUploadCompleteJs)}
            
JS;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::registerExternalModules()
     */
    public function registerExternalModules(UI5ControllerInterface $controller) : UI5AbstractElement
    {
        foreach ($this->getJsIncludes() as $src) {
            $name = StringDataType::substringAfter($src, '/', $src, false, true);
            $name = str_replace('-', '_', $name);
            
            $name = 'libs.exface.slick.' . $name;
            $controller->addExternalModule($name, $src);
        }
        
        foreach ($this->getCssIncludes() as $src) {
            $controller->addExternalCss($src);
        }
        
        return $this;
    }
        
    /**
     * 
     * @return string[]
     */
    protected function getJsIncludes() : array
    {
        $htmlTagsArray = $this->buildHtmlHeadTagsSlick();
        $tags = implode('', $htmlTagsArray);
        $jsTags = [];
        preg_match_all('#<script[^>]*src="([^"]*)"[^>]*></script>#is', $tags, $jsTags);
        return $jsTags[1];
    }
    
    /**
     *
     * @return string[]
     */
    protected function getCssIncludes() : array
    {
        $htmlTagsArray = $this->buildHtmlHeadTagsSlick();
        $tags = implode('', $htmlTagsArray);
        $jsTags = [];
        preg_match_all('#<link[^>]*href="([^"]*)"[^>]*/?>#is', $tags, $jsTags);
        return $jsTags[1];
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\UI5Facade\Facades\Elements\Traits\UI5DataElementTrait::buildJsShowMessageOverlay()
     */
    protected function buildJsShowMessageOverlay(string $message) : string
    {
        return '';
    }
    
    /**
     *
     * {@inheritdoc}
     * @see UI5DataElementTrait::buildJsOfflineHint()
     */
    protected function buildJsOfflineHint(string $oTableJs = 'oTable') : string
    {
        return '';
    }
    
    /**
     * 
     * @see UI5DataElementTrait::isEditable()
     */
    protected function isEditable()
    {
        return false;
    }
    
    /**
     * 
     * @see UI5DataElementTrait::buildJsGetRowsSelected()
     */
    protected function buildJsGetRowsSelected(string $oControlJs) : string
    {
        return ''; // TODO
    }
    
    /**
     * @see UI5DataElementTrait
     */
    protected function hasPaginator() : bool
    {
        return false;
    }
    
    /**
     *
     * @see JsUploaderTrait::getUploader()
     */
    protected function getUploader() : Uploader
    {
        return $this->getWidget()->getUploader();
    }
    
    /**
     * Makes a slick carousel have a default height of 6
     *
     * @see AbstractJqueryElement::buildCssHeightDefaultValue()
     */
    protected function buildCssHeightDefaultValue()
    {
        $widget = $this->getWidget();
        if ($this->isFillingContainer()) {
            return '';
        }
        return $widget->isHorizontal() ? ($this->getHeightRelativeUnit() * 8) . 'px' : '100%';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5AbstractElement::needsContainerContentPadding()
     */
    public function needsContainerContentPadding() : bool
    {
        return false;
    }
    
    protected function buildHtmlNoDataOverlay() : string
    {
        if ($this->getWidget()->isUploadEnabled()) {
            $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('WIDGET.IMAGEGALLERY.HINT_UPLOAD');
        } else {
            $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('WIDGET.IMAGEGALLERY.HINT_EMPTY');
        }
        return <<<HTML
        
            <div id="{$this->getIdOfSlick()}-nodata" class="imagecarousel-overlay">
                <li class="sapMLIB sapMUCNoDataPage sapMLIBFocusable imagecarousel-nodata">
                    <span role="presentation" aria-hidden="true" aria-label="document" class="sapUiIcon sapUiIconMirrorInRTL" style="font-family: 'SAP\2dicons'; font-size: 6rem;"></span>
                    <div class="sapMUCNoDataDescription">
                        {$message}
                    </div>
                </li>
            </div>
    
HTML;
    }
    
    /**
     *
     * @see UI5DataElementTrait::isWrappedInPanel()
     */
    protected function isWrappedInPanel() : bool
    {
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see SlickGalleryTrait::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        return <<<JS

        (function(){
            var bDataPending = {$this->buildJsIsDataPending()};
            if (bDataPending) {
                return {};
            }

            return ({$this->buildJsSlickDataGetter($action)});
        }())
JS;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5DataTable::buildJsClickIsTargetRowCheck()
     */
    protected function buildJsClickIsTargetRowCheck(string $oTargetDomJs = 'oTargetDom') : string
    {
        return "{$oTargetDomJs} !== undefined && $({$oTargetDomJs}).parents('.slick-track').length > 0";
    }
    
    /**
     * 
     * @param string $oControllerJs
     * @return string
     */
    protected function buildJsUploadEditorInit(string $oControllerJs) : string
    {
        $popoverEl = $this->getUploadEditorElement();
        if ($popoverEl === null) {
            return '';
        }
        $popoverEl->getWidget()->addButton($popoverEl->getWidget()->createButton(new UxonObject([
            'caption' => 'OK',
            'show_icon' => false,
            'visibility' => WidgetVisibilityDataType::PROMOTED,
            'align' => EXF_ALIGN_OPPOSITE,
            'action' => [
                'alias' => 'exface.Core.CustomFacadeScript',
                'script' => <<<JS

                    var jqCarousel = $('#{$this->getIdOfSlick()}');
                    var oDataPopover = {$popoverEl->buildJsDataGetter()};
                    var oDataSlick = jqCarousel.data('_exfData');
                    $.extend(oDataSlick.rows[jqCarousel.data('_exfUploadIdx')], oDataPopover.rows[0]);
                    jqCarousel.data(oDataSlick);
                    {$popoverEl->buildJsCloseDialog()}
JS
            ]
        ])));
        return <<<JS

    (function(oController){
        var sButtonsSelector = '#{$this->getId()} .imagecarousel-pending button';
        $(document).off('click', sButtonsSelector);
        $(document).on('click', sButtonsSelector, function(oEvent){
            var jqCarousel = $('#{$this->getIdOfSlick()}');
            var oPopover = function() {
                var oPopover = oController.{$this->getController()->buildJsObjectName('editorPopup', $this)};
                if (oPopover === undefined) {
                    oController.{$this->getController()->buildJsObjectName('editorPopup', $this)} 
                        = oPopover 
                        = {$popoverEl->buildJsConstructor($oControllerJs)};
                    oController.getView().addDependent(oPopover);
                }
                return oPopover;
            }();
            var jqSlide = $(oEvent.target).closest('.slick-slide');
            var jqSlides = $('#{$this->getId()} .slick-slide');
            var iClickedSlideIdx = jqSlides.index(jqSlide);
            var oRow = jqCarousel.data('_exfData').rows[iClickedSlideIdx];
            jqCarousel.data('_exfUploadIdx', iClickedSlideIdx);
            {$popoverEl->buildJsDataSetter("{rows: [oRow]}")}
            oPopover.openBy(oEvent.target);
        });
    })($oControllerJs)
JS;
    }
}