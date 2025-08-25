<?php
namespace exface\UI5Facade\Facades\Formatters;

use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsNumberFormatter;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\PercentDataType;

/**
 * 
 * @method JsNumberFormatter getJsFormatter()
 * @method NumberDataType getDataType()
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5NumberFormatter extends AbstractUI5BindingFormatter
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\UI5Facade\Facades\Interfaces\UI5BindingFormatterInterface::buildJsBindingProperties()
     */
    public function buildJsBindingProperties()
    {
        $type = $this->getDataType();
        $formatOptions = '';
        $otherProps = '';
        
        if (! is_null($type->getPrecisionMin())){
            $formatOptions .= <<<JS

                    minFractionDigits: {$type->getPrecisionMin()},
JS;
        }
            
        if (! is_null($type->getPrecisionMax())){
            $formatOptions .= <<<JS

                    maxFractionDigits: {$type->getPrecisionMax()},
JS;
        }
         
        if ($type->getGroupDigits()) {
            $formatOptions .= <<<JS

                    groupingEnabled: true,
                    groupingSize: {$type->getGroupLength()},
                    groupingSeparator: "{$type->getGroupSeparator()}",
                    
JS;
        } else {
            $formatOptions .= <<<JS

                    groupingEnabled: false,
                    groupingSeparator: "",
JS;
        }

        // Add a custom formatter if required for
        // - prefix/suffix
        // - `+`-sign
        // TODO handle NumberDataType::getEmptyFormat() here too?
        if (($type instanceof NumberDataType) 
            && (
                $type->getPrefix() !== null
                || $type->getSuffix() !== null
                || $type->getShowPlusSign() === true
            )
        ) {
            $prefix = $type->getPrefix();
            $prefixJs = $prefix === '' || $prefix === null ? '""' : json_encode($prefix . ' ');
            $suffix = $type->getSuffix();
            $suffixJs = $suffix === '' || $suffix === null ? '""' : json_encode(' ' . $suffix);
            $plusSignJs = $type->getShowPlusSign() ? 'true' : 'false';
            
            $otherProps .= <<<JS

                formatter: function(mVal) {
                    mVal = ({$this->getJsFormatter()->buildJsFormatter('mVal')});
                    var sPrefix = $prefixJs;
                    var sSuffix = $suffixJs;
                    var bPlusSign = $plusSignJs;
                    var nVal = {$this->getJsFormatter()->buildJsFormatParser('mVal')};

                    if (mVal === '' || mVal === null || mVal === undefined) return mVal;

                    if (bPlusSign === true && nVal > 0) {
                        mVal = '+' + mVal;
                    }

                    if (sPrefix !== '') {
                        mVal = sPrefix + mVal;
                    }
                    if (sSuffix !== '') {
                        mVal = mVal + sSuffix;
                    }                    

                    return mVal;
                },
JS;
        } else if ($type instanceof NumberDataType) {
            $otherProps .= <<<JS
                formatter: function(mVal){
                    return ({$this->getJsFormatter()->buildJsFormatter('mVal')});
                },
JS;
        }

        // We do NOT need `constraints` here because they will be handled by the validator
        // see. JsNumberFormatter::buildJsValidator().

        return <<<JS

                type: '{$this->getSapDataType()}',
                formatOptions: {
                    {$formatOptions}
                }, $otherProps

JS;
    }
        
    protected function getSapDataType()
    {
        $type = $this->getDataType();
        if ($type->getPrecisionMax() === 0) {
            return 'sap.ui.model.type.Integer';
        } else {
            return 'sap.ui.model.type.Float';
        }
    }
}