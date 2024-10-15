<?php
namespace exface\UI5Facade\Facades\Templates;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Templates\AbstractPlaceholderResolver;
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
    public function resolve(array $placeholders) : array
    {
        $phVals = [];
        foreach ($this->filterPlaceholders($placeholders, $this->prefix) as $placeholder) {
            $option = $this->stripPrefix($placeholder, $this->prefix);
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