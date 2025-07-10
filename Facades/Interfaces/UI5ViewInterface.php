<?php
namespace exface\UI5Facade\Facades\Interfaces;

use exface\UI5Facade\Facades\Elements\UI5AbstractElement;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface UI5ViewInterface {
    
    public function buildJsView() : string;
    
    public function getRootElement() : UI5AbstractElement;
    
    /**
     * Returns the name of the view: e.g. my.app.root.view.my.app.page_alias
     *
     * @return string
     */
    public function getName() : string;
    
    /**
     * Returns the path to the view: e.g. my.app.root/view/my/app/page_alias.view.js
     *
     * @return string
     */
    public function getPath(bool $relativeToAppRoot = false) : string;
    
    /**
     * Returns the name of the default route of the view: e.g. my.app.page_alias
     * 
     * @return string
     */
    public function getRouteName(): string;
    
    public function isBuilt() : bool;
    
    public function getController() : ?UI5ControllerInterface;
    
    public function setController(UI5ControllerInterface $controller) : UI5ViewInterface; 
    
    public function buildJsViewGetter(UI5AbstractElement $fromElement) : string;
    
    /**
     * 
     * @param string $name
     * @return UI5ModelInterface
     */
    public function getModel(string $name = '') : UI5ModelInterface; 
    
    /**
     * Returns TRUE if this view is the root view for the it's webap
     * 
     * @return bool
     */
    public function isWebAppRoot() : bool;

    /**
     * Returns the human-readable name of the view (e.g. to display in breadcrumbs, etc.)
     * 
     * @return string
     */
    public function getCaption() : string;
}