<?php
namespace exface\UI5Facade;

use exface\UI5Facade\Facades\Interfaces\UI5ControllerInterface;
use exface\UI5Facade\Facades\Interfaces\UI5ViewInterface;
use exface\UI5Facade\Facades\Elements\UI5AbstractElement;
use exface\Core\DataTypes\StringDataType;
use exface\UI5Facade\Facades\Interfaces\UI5ModelInterface;

class UI5View implements UI5ViewInterface
{
    private $isBuilt = false;
    
    private $contentJs = null;
    
    private $viewName = null;
    
    private $webapp = null;
    
    private $rootElement = null;
    
    private $controller = null;
    
    private $model = null;
    
    public function __construct(Webapp $webapp, string $viewName, ui5AbstractElement $rootElement)
    {
        $this->webapp = $webapp;
        $this->viewName = $viewName;
        $this->rootElement = $rootElement;
    }
    
    protected function buildJsContent(): string
    {
        if ($this->isBuilt === false || $this->contentJs === null) {
            $this->isBuilt = true;
            // Trim off things like line brea in order to make the script concatenatable (e.g. return ...)
            $this->contentJs = trim($this->getRootElement()->buildJsConstructor());
        }
        return $this->contentJs;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ViewInterface::isBuilt()
     */
    public function isBuilt(): bool
    {
        return $this->isBuilt;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ViewInterface::getName()
     */
    public function getName(): string
    {
        return $this->viewName;
    }
    
    public function getRouteName(): string
    {
        return StringDataType::substringAfter($this->getName(), $this->getController()->getWebapp()->getComponentName() . '.view.');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ViewInterface::getPath()
     */
    public function getPath(bool $relativeToAppRoot = false) : string
    {
        if ($relativeToAppRoot === true) {
            $name = StringDataType::substringAfter($this->getName(), $this->getController()->getWebapp()->getComponentName() . '.');
        } else {
            $name = $this->getName();
        }
        return $this->getController()->getWebapp()->convertNameToPath($name, $this->getController()->getWebapp()->getViewFileSuffix());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ViewInterface::getRootElement()
     */
    public function getRootElement(): ui5AbstractElement
    {
        return $this->rootElement;
    }
    
    public function buildJsView(): string
    {
        $controllerName = $this->getController() === null ? 'null' : '"' . $this->getController()->getName() . '"';
        return <<<JS

sap.ui.jsview("{$this->getName()}", {

	/** Specifies the Controller belonging to this View. 
	* In the case that it is not implemented, or that "null" is returned, this View does not have a Controller.
	*/ 
	getControllerName : function() {
		return {$controllerName};
	},

	/** Is initially called once after the Controller has been instantiated. It is the place where the UI is constructed. 
	* Since the Controller is given to this method, its event handlers can be attached right away. 
	*/ 
	createContent : function(oController) {
	    
		return {$this->buildJsContent()}
	    
	}
});

JS;
    }
		
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ViewInterface::getController()
     */
    public function getController(): ?ui5ControllerInterface
    {
        return $this->controller;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ViewInterface::setController()
     */
    public function setController(ui5ControllerInterface $controller): ui5ViewInterface
    {
        $this->controller = $controller;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ViewInterface::buildJsViewGetter()
     */
    public function buildJsViewGetter(ui5AbstractElement $fromElement) : string
    {
        return "{$this->getController()->buildJsComponentGetter($fromElement)}.findViewOfControl(sap.ui.getCore().byId('{$fromElement->getId()}'))";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ViewInterface::getModel()
     */
    public function getModel(string $name = '') : ui5ModelInterface
    {
        if ($this->model === null) {
            $this->model =  $this->webapp->getViewModel($this, $name);
        }
        return $this->model;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5ViewInterface::isWebAppRoot()
     */
    public function isWebAppRoot() : bool
    {
        $rootWidget = $this->getRootElement()->getWidget();
        return $rootWidget->hasParent() === false && $rootWidget->getPage()->is($this->webapp->getRootPage());
    }

    /**
     * {@inheritDoc}
     * @see UI5ViewInterface::getCaption()
     */
    public function getCaption() : string
    {
        $widget = $this->getRootElement()->getWidget();
        $widgetCaption = $widget->getCaption();
        if ($widgetCaption) {
            return $widgetCaption;
        }
        return $widget->getPage()->getName() ?? '';
    }
}