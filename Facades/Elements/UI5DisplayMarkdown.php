<?php
namespace exface\UI5Facade\Facades\Elements;

/**
 * Uses the same source-preserving viewer for data-bound Markdown values.
 *
 * @method \exface\Core\Widgets\DisplayMarkdown getWidget()
 */
class UI5DisplayMarkdown extends UI5Markdown
{
    /**
     * {@inheritDoc}
     * @see UI5Markdown::getHeight()
     */
    public function getHeight()
    {
        if ($this->getWidget()->getHeight()->isUndefined()) {
            return (2 * $this->getHeightRelativeUnit()) . 'px';
        }
        return parent::getHeight();
    }
}
