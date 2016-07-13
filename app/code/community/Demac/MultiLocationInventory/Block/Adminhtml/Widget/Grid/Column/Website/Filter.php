<?php

class Demac_MultiLocationInventory_Block_Adminhtml_Widget_Grid_Column_Website_Filter extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Abstract
{
    /**
     * Render HTML of the element
     *
     * @return string
     */
    public function getHtml()
    {
        $elementName = $this->escapeHtml($this->_getHtmlName());
        $validateClass = $this->getColumn()->getValidateClass();
        $html = "<select name=\"{$elementName}\" {$validateClass}>";
        $html .= "<option></option>";

        /** @var Mage_Core_Model_Website[] $websites */
        $websites = Mage::app()->getWebsites();
        foreach ($websites as $website) {
            $selected = ($website->getId() == $this->getValue() ? 'selected="selected"' : '');
            $html .= "<option value=\"{$website->getId()}\" {$selected}>{$website->getName()}</option>";
        }
        $html .= "</select>";

        return $html;
    }

    /**
     * Form condition from element's value
     *
     * @return array|null
     */
    public function getCondition()
    {
        if (is_null($this->getValue())) {
            return null;
        } else {
            return ['eq' => $this->getValue()];
        }
    }
}
