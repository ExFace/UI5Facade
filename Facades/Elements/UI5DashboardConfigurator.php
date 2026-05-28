<?php
namespace exface\UI5Facade\Facades\Elements;

/**
 * 
 * @method \exface\Core\Widgets\DashboardConfigurator getWidget()
 * @author Andrej Kabachnik
 *
 */
class UI5DashboardConfigurator extends UI5DataConfigurator
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Elements\UI5DataConfigurator::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        foreach ($this->getFilterElements() as $el) {
            $resetJs .= PHP_EOL . $el->buildJsResetter();
        }
        return $resetJs;
    }
    
    protected function getFilterElements() : array
    {
        $els = [];
        foreach ($this->getWidget()->getFilters() as $child) {
            $els[] = $this->getFacade()->getElement($child);
        }
        return $els;
    }
}