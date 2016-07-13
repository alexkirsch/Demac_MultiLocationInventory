<?php

/**
 * Class Demac_MultiLocationInventory_Block_Adminhtml_Catalog_Product_Edit_Multilocationinventory
 */
class Demac_MultiLocationInventory_Block_Adminhtml_Catalog_Product_Edit_Multilocationinventory extends Mage_Adminhtml_Block_Widget implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Inventory for each location
     *
     * @var array[]
     */
    private $locations = [];

    /**
     * Total quantity available for this product globally
     *
     * @var float
     */
    private $globalInventory = 0.0;

    /**
     * Total quantity within the current scope
     *
     * @var float
     */
    private $scopeInventory = 0.0;

    /**
     * Init the tab and set it's template
     */
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('demac/catalog_multilocationinventory.phtml');
        $this->loadLocationsInventoriesData();
    }

    /**
     * Returns the product id.
     *
     * @return int
     */
    protected function getProductId()
    {
        return Mage::app()->getRequest()->getParam('id');
    }

    /**
     * Returns the current store view id or NULL.
     *
     * @return int
     */
    protected function getStoreViewId()
    {
        return Mage::app()->getRequest()->getParam('store');
    }

    /**
     * Returns the tab's label.
     *
     * @return string
     */
    public function getTabLabel()
    {
        return $this->__('Multi Location Inventory');
    }

    /**
     * Returns the tab's title.
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->__('Multi Location Inventory');
    }

    /**
     * Returns true/false if the tab can or can't be displayed.
     *
     * @return bool
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Returns true/false if that tab should be hidden.
     *
     * @return bool
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Get stock details for each location.
     *
     * @return array
     */
    public function getLocationsInventories()
    {
        return $this->locations;
    }

    /**
     * Get inventory within the current store view scope.
     *
     * @return array
     */
    public function getScopeInventory()
    {
        return $this->scopeInventory;
    }

    /**
     * Get global inventory.
     *
     * @return array
     */
    public function getGlobalInventory()
    {
        return $this->globalInventory;
    }

    /**
     * Load stock details for each location.
     */
    private function loadLocationsInventoriesData()
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load($this->getProductId());
        $storeViewId = $this->getStoreViewId();
        $websiteId = ($storeViewId ? Mage::app()->getStore($storeViewId)->getWebsiteId() : null);

        /** @var Demac_MultiLocationInventory_Model_Resource_Location_Collection $collection */
        $collection = Mage::getModel('demac_multilocationinventory/location')->getCollection();
        $collection->joinStock($product->getId());
        $collection->joinWebsites();

        $locations = [];
        foreach ($collection as $locationWithStock) {
            /** @var Demac_MultiLocationInventory_Model_Location $locationWithStock */

            // Ensure that qty is a float
            $locationWithStock->setDataUsingMethod('qty', floatval($locationWithStock->getDataUsingMethod('qty')));

            // Add a flag indicating if this location is allowed for this product
            $allowed = (bool)count(array_intersect($product->getWebsiteIds(), $locationWithStock->getDataUsingMethod('websites')));
            $allowed = $allowed && $locationWithStock->getStatus();
            $locationWithStock->setData('allowed', $allowed);
            if ($allowed || in_array($websiteId, $locationWithStock->getDataUsingMethod('websites'))) {
                $this->scopeInventory += $locationWithStock->getDataUsingMethod('qty');
            }
            $this->globalInventory += $locationWithStock->getDataUsingMethod('qty');
            $locations[] = $locationWithStock->toArray();
        }

        $this->locations = $locations;
    }
}
