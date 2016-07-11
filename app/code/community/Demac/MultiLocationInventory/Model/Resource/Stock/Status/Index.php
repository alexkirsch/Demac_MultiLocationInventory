<?php
/**
 * Class Demac_MultiLocationInventory_Model_Resource_Stock_Status_Index
 */
class Demac_MultiLocationInventory_Model_Resource_Stock_Status_Index
    extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Standard resource model init
     */
    protected function _construct()
    {
        $this->_init('demac_multilocationinventory/stock_status_index', 'product_id');
    }

    /**
     * Update core stock status.
     *
     * @param bool|array $productIds
     */
    public function updateCoreStockStatus($productIds = false)
    {
        $this->_getWriteAdapter()->query($this->getUpdateCoreStockStatusQuery($productIds));
    }

    /**
     * Update core stock item.
     *
     * @param bool|array $productIds
     */
    public function updateCoreStockItem($productIds = false)
    {
        $this->_getWriteAdapter()->query($this->getUpdateCoreStockItemQuery($productIds));
    }

    /**
     * Create missing multi location inventory stock rows.
     *
     * @param bool|array $productIds
     */
    public function createMissingStockRows($productIds)
    {
        $productFilter = '';
        if($productIds !== false) {
            $productFilter = ' AND e.entity_id IN(' . implode(',', $productIds) . ')';
        }
        $missingStockRowsQuery       = $this->_getWriteAdapter()
            ->select()
            ->from(
                array(
                    'e' => 'catalog_product_entity'
                ),
                array('product_id' => 'e.entity_id')
            )
            ->columns(new Zend_Db_Expr('0 as qty'))
            ->join(
                array(
                    'location' => 'demac_multilocationinventory_location'
                ),
                '',
                'location.id as location_id'
            )
            ->joinLeft(
                array(
                    'stock' => 'demac_multilocationinventory_stock'
                ),
                'e.entity_id = stock.product_id AND location.id = stock.location_id',
                ''
            )
            ->join(
                array(
                    'stores' => 'demac_multilocationinventory_stores'
                ),
                'location.id = stores.location_id',
                ''
            )
            ->where('stock_id IS NULL' . $productFilter)
            ->group('CONCAT(location.id, \'_\', e.entity_id)');
        $missingStockRowsQueryString = $missingStockRowsQuery->__toString();

        //Create empty stock rows
        $query =
            'INSERT INTO demac_multilocationinventory_stock (product_id, qty, location_id)'
            . ' (' . $missingStockRowsQueryString . ')';
        $this->_getWriteAdapter()->query($query);
    }

    /**
     * Create missing multi location inventory stock status index rows.
     *
     * @param bool|array $productIds
     */
    public function createMissingStockIndexRows($productIds)
    {
        $query =
            'INSERT INTO demac_multilocationinventory_stock_status_index (store_id, product_id, qty, backorders, is_in_stock)'
            . ' ('
            . ' SELECT'
            . '   store.store_id as store_id,'
            . '   e.entity_id as product_id,'
            . '   0 as qty,'
            . '   0 as backorders,'
            . '   0 as is_in_stock'
            . ' FROM catalog_product_entity AS e'
            . ' JOIN core_store AS store'
            . ' LEFT OUTER JOIN demac_multilocationinventory_stock_status_index as stock_idx'
            . '   ON e.entity_id = stock_idx.product_id'
            . '      AND store.store_id = stock_idx.store_id'
            . ' WHERE stock_idx.qty IS NULL';
        if(is_array($productIds)) {
            $query .= ' AND e.entity_id IN(' . implode(',', $productIds) . ')';
        }
        $query .=
            ' GROUP BY CONCAT(store.store_id, \'_\', e.entity_id)'
            . ')';
        $this->_getWriteAdapter()->query($query);
    }

    /**
     * Get Global Stock Status Select (for use in updates)
     *
     * @param bool  $productIds
     * @param array $additionalFields
     *
     * @return string
     */
    protected function getGlobalStockStatusSelectQuery($productIds = false, $additionalFields = array())
    {
        $fields = array('product_id', 'qty', 'is_in_stock', 'manage_stock');
        $fields = array_merge($fields, $additionalFields);

        $stockStatusCollection = Mage::getModel('demac_multilocationinventory/stock_status_index')
            ->getCollection();
        $stockStatusCollection
            ->addFieldToSelect($fields)
            ->addFieldToFilter('store_id', 0);
        if($productIds !== false) {
            $stockStatusCollection
                ->addFieldToFilter(
                    'product_id',
                    array(
                        'in' => $productIds
                    )
                );
        }

        return $stockStatusCollection->getSelectSql(true);
    }

    /**
     * Get core stock status update query.
     *
     * @param bool|array $productIds
     *
     * @return array|string
     *
     * @TODO Update to use varien query builder.
     */
    protected function getUpdateCoreStockStatusQuery($productIds = false)
    {

        $coreStockStatusTable  = Mage::getModel('core/resource')->getTableName('cataloginventory/stock_status');
        $query                 =
            'UPDATE'
            . '  ' . $coreStockStatusTable . ' dest,'
            . '  (' . $this->getGlobalStockStatusSelectQuery($productIds) . ') src '
            . '  SET dest.qty = src.qty,'
            . '    dest.stock_status = src.is_in_stock'
            . '  WHERE dest.product_id = src.product_id';

        return $query;
    }

    /**
     * Get core stock item update query.
     *
     * @param bool|array $productIds
     *
     * @return array|string
     *
     * @TODO Update to use varien query builder.
     */
    protected function getUpdateCoreStockItemQuery($productIds = false)
    {
        $coreStockItemTable    = Mage::getModel('core/resource')->getTableName('cataloginventory/stock_item');
        $query                 =
            'UPDATE'
            . '  ' . $coreStockItemTable . ' dest,'
            . '  (' . $this->getGlobalStockStatusSelectQuery($productIds, array('backorders')) . ') src '
            . '  SET dest.qty = src.qty,'
            . '    dest.is_in_stock = src.is_in_stock,'
            . '    dest.backorders = src.backorders,'
            . '    dest.manage_stock = src.manage_stock'
            . '  WHERE dest.product_id = src.product_id';

        return $query;
    }
}
