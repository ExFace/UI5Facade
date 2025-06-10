<?php
namespace exface\UI5Facade\Facades\Templates;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\UI5Facade\Facades\UI5Facade;

/**
 * Replaces the [#breadcrumbs#] in jEasyUI templates.
 * 
 * ## Placeholders
 *
 * - `[#ui5:<option>#]` - replaced by option specific value
 *
 * @author Andrej Kabachnik
 *
 */
class UI5CustomPlaceholders extends AbstractPlaceholderResolver
{
    private $facade = null;
    
    protected string $prefix = 'ui5:';
    
    /**
     *
     * @param FacadeInterface $facade
     * @param string $prefix
     */
    public function __construct(UI5Facade $facade)
    {
        $this->facade = $facade;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders, ?LogBookInterface $logbook = null) : array
    {
        $phVals = [];
        foreach ($this->filterPlaceholders($placeholders) as $placeholder) {
            $option = $this->stripPrefix($placeholder);
            switch ($option) {
                case 'density_body_class':
                    if ($this->facade->getContentDensity() === 'cozy') {
                        $val = 'sapUiBody';
                    } else {
                        $val = 'sapUiBody sapUiSizeCompact';
                    }
                    break;
                default:
                    $val = '';
            }
            $phVals[$placeholder] = $val;
        }
        
        return $phVals;
    }
}