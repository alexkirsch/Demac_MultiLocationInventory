<?php
/**
 * Created by PhpStorm.
 * User: Allan MacGregor - Magento Practice Lead <allan@demacmedia.com>
 * Company: Demac Media Inc.
 * Date: 5/5/14
 * Time: 11:25 AM
 */

/**
 * Class Demac_MultiLocationInventory_Model_CatalogInventory_Stock
 */
class Demac_MultiLocationInventory_Model_CatalogInventory_Stock extends Mage_CatalogInventory_Model_Stock
{
    /**
     * Add stock item objects to products
     *
     * @param   Mage_Catalog_Model_Resource_Product_Collection $productCollection
     *
     * @return  Mage_CatalogInventory_Model_Stock
     */
    public function addItemsToProducts($productCollection)
    {
        /** @var Mage_CatalogInventory_Model_Resource_Stock_Item_Collection $items */
        $items = $this->getItemCollection();
        $items->addProductsFilter($productCollection);
        $storeId = $productCollection->getStoreId();
        if ($storeId) {
            $websiteId = intval(Mage::app()->getStore($storeId)->getWebsiteId());
            $items->join(
                ['status_table' => 'cataloginventory/stock_status'],
                "main_table.product_id=status_table.product_id AND status_table.website_id={$websiteId}",
                ['qty', 'stock_status']
            );
        }

        /** @var Mage_CatalogInventory_Model_Stock_Item[] $stockItems */
        $stockItems = [];
        foreach ($items as $item) {
            /** @var Mage_CatalogInventory_Model_Stock_Item $item */
            $stockItems[$item->getProductId()] = $item;
        }

        foreach ($productCollection as $product) {
            /** @var Mage_Catalog_Model_Product $product */
            if (isset($stockItems[$product->getId()])) {
                $stockItems[$product->getId()]->assignProduct($product);
            }
        }

        return $this;
    }

    /**
     * Get back to stock (when order is canceled or whatever else)
     *
     * @param int     $productId
     * @param numeric $qty
     *
     * @return Mage_CatalogInventory_Model_Stock
     */
    public function backItemQty($productId, $qty)
    {
        /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
        if ($stockItem->getId() && Mage::helper('catalogInventory')->isQty($stockItem->getTypeId())) {
            $stockItem->addQty($qty);
            if ($stockItem->getCanBackInStock() && $stockItem->getQty() > $stockItem->getMinQty()) {
                $stockItem->setIsInStock(true)
                    ->setStockStatusChangedAutomaticallyFlag(true);
            }
            $stockItem->save();
        }

        return $this;
    }
}
