<?php

/**
 * Class Demac_MultiLocationInventory_Model_CatalogInventory_Resource_Stock
 */
class Demac_MultiLocationInventory_Model_CatalogInventory_Resource_Stock extends Mage_CatalogInventory_Model_Resource_Stock
{
    /**
     * Get stock items data for requested products
     *
     * @param Mage_CatalogInventory_Model_Stock $stock
     * @param array                             $productIds
     * @param bool                              $lockRows
     *
     * @return array
     */
    public function getProductsStock($stock, $productIds, $lockRows = false)
    {
        if (empty($productIds)) {
            return [];
        }

        $productTable = $this->getTable('catalog/product');
        $itemTable = $this->getTable('cataloginventory/stock_item');
        $statusTable = $this->getTable('cataloginventory/stock_status');
        $select = $this->_getWriteAdapter()->select()
            ->from(['si' => $itemTable])
            ->join(['p' => $productTable], 'p.entity_id=si.product_id', ['type_id'])
            ->join(['ss' => $statusTable], 'p.entity_id=ss.product_id AND ss.website_id=' . Mage::app()->getWebsite()->getId(), ['qty', 'is_in_stock'])
            ->where('stock_id=?', $stock->getId())
            ->where('product_id IN(?)', $productIds)
            ->forUpdate($lockRows);

        return $this->_getWriteAdapter()->fetchAll($select);
    }

}
