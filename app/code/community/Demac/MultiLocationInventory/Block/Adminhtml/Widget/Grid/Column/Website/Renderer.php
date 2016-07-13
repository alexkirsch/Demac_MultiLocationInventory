<?php

class Demac_MultiLocationInventory_Block_Adminhtml_Widget_Grid_Column_Website_Renderer extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Render row website views
     *
     * @param Varien_Object $row
     *
     * @return string
     */
    public function render(Varien_Object $row)
    {
        return implode('<br/>', $this->getWebsiteNames($row));
    }

    /**
     * Render row website views for export
     *
     * @param Varien_Object $row
     *
     * @return string
     */
    public function renderExport(Varien_Object $row)
    {
        return implode("\n", $this->getWebsiteNames($row));
    }

    /**
     * @param Varien_Object $row
     *
     * @return string[]
     */
    protected function getWebsiteNames(Varien_Object $row)
    {
        $names = [];

        /** @var Mage_Core_Model_Website[] $websites */
        $websites = Mage::app()->getWebsites();

        $websiteIds = $row->getData($this->getColumn()->getIndex());
        $websiteIds = array_unique(array_filter(array_map('intval', is_array($websiteIds) ? $websiteIds : explode(',', $websiteIds))));
        foreach ($websiteIds as $websiteId) {
            if (isset($websites[$websiteId])) {
                $names[] = $websites[$websiteId]->getName();
            }
        }

        return $names;
    }
}
