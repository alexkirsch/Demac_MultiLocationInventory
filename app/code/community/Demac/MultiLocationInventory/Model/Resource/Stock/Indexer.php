<?php

class Demac_MultiLocationInventory_Model_Resource_Stock_Indexer extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Standard resource model init
     */
    protected function _construct()
    {
        $this->_init('demac_multilocationinventory/stock_indexer', 'product_id');
    }

    public function reindex(array $productIds = [])
    {
        if (count($productIds) > 0) {
            // Clean up the product ID array
            $productIds = array_unique(array_filter(array_map('intval', $productIds)));

            // If the product ID array is empty after a cleanup then exit early
            if (empty($productIds)) {
                return;
            }
        }

        // Ensure we have stock rows for all products - outside the transaction on purpose
        $this->createMissingStockRows($productIds);

        // Get the connection and start a transaction
        $conn = $this->_getWriteAdapter();
        $conn->beginTransaction();

        try {
            // Drop and create temp table
            $conn->dropTemporaryTable($this->getMainTable());
            $columns = [
                'product_id'   => [Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false]],
                'manage_stock' => [Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => false]],
                'qty'          => [Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', ['nullable' => false]],
                'is_in_stock'  => [Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => false]],
                'backorders'   => [Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => false]],
                'website_id'   => [Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => false]],
            ];
            $table = $conn->newTable($this->getMainTable());
            foreach ($columns as $columnName => $columnDefenition) {
                array_unshift($columnDefenition, $columnName);
                call_user_func_array([$table, 'addColumn'], $columnDefenition);
            }
            $conn->createTemporaryTable($table);

            // Get table field names
            $fields = array_keys($columns);

            // Define common table names
            $mliStockTable = Mage::getResourceModel('demac_multilocationinventory/stock')->getMainTable();
            $mliLocationTable = Mage::getResourceModel('demac_multilocationinventory/location')->getMainTable();
            $mliWebsiteTable = Mage::getResourceModel('demac_multilocationinventory/location')->getTable('demac_multilocationinventory/website');
            $productTable = Mage::getResourceModel('catalog/product')->getEntityTable();
            $productWebsiteTable = Mage::getResourceModel('catalog/product')->getTable('catalog/product_website');
            $configurableTable = Mage::getResourceModel('catalog/product_type_configurable')->getMainTable();
            $linkTypeTable = Mage::getResourceModel('catalog/product_link')->getTable('catalog/product_link_type');
            $linkTable = Mage::getResourceModel('catalog/product_link')->getMainTable();

            // Define some flags
            $manageStock = Mage::getStoreConfigFlag(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK);
            $backorders = Mage::getStoreConfigFlag(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_BACKORDERS);

            // Define steps array
            /** @var Varien_Db_Select[] $steps */
            $steps = [];

            // Standard product information (simple, virtual, downloadable, giftcard)
            $select = $conn->select();
            $select->from(['p' => $productTable], ['product_id' => 'entity_id']);
            $select->join(['stock' => $mliStockTable], 'p.entity_id=stock.product_id', []);
            $select->where('p.type_id IN (?)', ['simple', 'virtual', 'downloadable', 'giftcard']);
            if (count($productIds)) {
                $select->where('p.entity_id IN (?)', $productIds);
            }
            $steps[] = $select;

            // Configurable product information (configurable)
            $select = $conn->select();
            $select->from(['p' => $productTable], ['product_id' => 'entity_id']);
            $select->join(['link' => $configurableTable], 'p.entity_id=link.parent_id', []);
            $select->join(['stock' => $mliStockTable], 'link.product_id=stock.product_id', []);
            $select->where('p.type_id IN (?)', ['configurable']);
            if (count($productIds)) {
                $select->where('p.entity_id IN (?) OR link.product_id IN (?)', $productIds);
            }
            $steps[] = $select;

            // Grouped product information (grouped)
            $select = $conn->select();
            $select->from(['p' => $productTable], ['product_id' => 'entity_id']);
            $select->join(['lt' => $linkTypeTable], 'lt.code="super"', []);
            $select->join(['link' => $linkTable], 'p.entity_id=link.product_id AND lt.link_type_id=link.link_type_id', []);
            $select->join(['stock' => $mliStockTable], 'link.linked_product_id=stock.product_id', []);
            $select->where('p.type_id IN (?)', ['grouped']);
            if (count($productIds)) {
                $select->where('p.entity_id IN (?) OR link.product_id IN (?)', $productIds);
            }
            $steps[] = $select;

            // Run all the steps
            foreach ($steps as $step) {
                // Add location join
                $step->join(
                    ['location' => $mliLocationTable],
                    'stock.location_id=location.id AND location.status = 1',
                    []
                );

                // Group by product
                $step->group('p.entity_id');

                // Additional columns
                $step->columns(['manage_stock' => 'IF(SUM(stock.manage_stock) > 0, 1, 0)']);
                if ($manageStock) {
                    $step->columns(['qty' => 'SUM(IF(stock.use_config_manage_stock=1 OR stock.manage_stock=1, stock.qty, 0))']);
                    $step->columns(['is_in_stock' => 'IF(SUM(IF(stock.use_config_manage_stock=1 OR stock.manage_stock=1, stock.is_in_stock, 0)) > 0, 1, 0)']);
                } else {
                    $step->columns(['qty' => 'SUM(IF(stock.use_config_manage_stock=0 AND stock.manage_stock=1, stock.qty, 0))']);
                    $step->columns(['is_in_stock' => 'IF(SUM(IF(stock.use_config_manage_stock=0 AND stock.manage_stock=1, stock.is_in_stock, 0)) > 0, 1, 0)']);
                }
                if ($backorders) {
                    $step->columns(['backorders' => 'IF(SUM(IF(stock.use_config_backorders=1 OR stock.backorders=1, 1, 0)) > 0, 1, 0)']);
                } else {
                    $step->columns(['backorders' => 'IF(SUM(IF(stock.use_config_backorders=0 AND stock.backorders=1, 1, 0)) > 0, 1, 0)']);
                }

                // Global count
                $step1 = clone $step;
                $step1->columns(['website_id' => new Zend_Db_Expr('0')]);
                $conn->query($conn->insertFromSelect($step1, $this->getMainTable(), $fields, Varien_Db_Adapter_Interface::INSERT_IGNORE));

                // Per-website counts
                $step2 = clone $step;
                $step2->join(
                    ['product_website' => $productWebsiteTable],
                    'p.entity_id=product_website.product_id',
                    []
                );
                $step2->join(
                    ['location_website' => $mliWebsiteTable],
                    'location.id=location_website.location_id AND product_website.website_id=location_website.website_id',
                    ['website_id']
                );
                $step2->group('location_website.website_id');
                $conn->query($conn->insertFromSelect($step2, $this->getMainTable(), $fields, Varien_Db_Adapter_Interface::INSERT_IGNORE));
            }

            // Update core stock status table.
            $this->updateCoreStockStatus($productIds);

            // Update core stock item table
            $this->updateCoreStockItem($productIds);

            // Commit the transaction
            $conn->commit();
        } catch (Exception $e) {
            // Roll back the transaction
            $conn->rollBack();

            // Re-throw exception
            throw $e;
        }
    }

    /**
     * Create missing multi location inventory stock rows.
     *
     * @param array $productIds
     */
    public function createMissingStockRows(array $productIds = [])
    {
        if (count($productIds) > 0) {
            // Clean up the product ID array
            $productIds = array_unique(array_filter(array_map('intval', $productIds)));

            // If the product ID array is empty after a cleanup then exit early
            if (empty($productIds)) {
                return;
            }
        }

        // Define common table names
        $productTable = Mage::getResourceModel('catalog/product')->getEntityTable();
        $mliLocationTable = Mage::getResourceModel('demac_multilocationinventory/location')->getMainTable();
        $mliStockTable = Mage::getResourceModel('demac_multilocationinventory/stock')->getMainTable();

        $select = $this->_getWriteAdapter()->select();
        $select->from(
            ['p' => $productTable],
            ['product_id' => 'p.entity_id']
        );
        $select->join(
            ['location' => $mliLocationTable],
            '',
            ['location_id' => 'id']
        );
        $select->joinLeft(
            ['stock' => $mliStockTable],
            'p.entity_id=stock.product_id AND location.id=stock.location_id',
            []
        );

        $select->columns(
            [
                'stock_id'                => new Zend_Db_Expr('NULL'),
                'qty'                     => new Zend_Db_Expr('0'),
                'backorders'              => new Zend_Db_Expr('1'),
                'use_config_backorders'   => new Zend_Db_Expr('1'),
                'manage_stock'            => new Zend_Db_Expr('1'),
                'use_config_manage_stock' => new Zend_Db_Expr('1'),
                'is_in_stock'             => new Zend_Db_Expr('0'),
            ]
        );

        $select->where('stock.qty IS NULL');
        if (count($productIds)) {
            $select->where('p.entity_id IN (?)', implode(',', $productIds));
        }

        $select->group('p.entity_id');
        $select->group('location.id');

        $sql = $this->_getWriteAdapter()->insertFromSelect(
            $select,
            $mliStockTable,
            [
                'product_id',
                'location_id',
                'stock_id',
                'qty',
                'backorders',
                'use_config_backorders',
                'manage_stock',
                'use_config_manage_stock',
                'is_in_stock',
            ]
        );

        $this->_getWriteAdapter()->query($sql);
    }

    /**
     * Create missing multi location inventory stock status index rows.
     *
     * @param array $productIds
     */
    protected function createMissingStockIndexRows(array $productIds = [])
    {
        if (count($productIds) > 0) {
            // Clean up the product ID array
            $productIds = array_unique(array_filter(array_map('intval', $productIds)));

            // If the product ID array is empty after a cleanup then exit early
            if (empty($productIds)) {
                return;
            }
        }

        // Define common table names
        $productTable = Mage::getResourceModel('catalog/product')->getEntityTable();
        $productWebsiteTable = Mage::getResourceModel('catalog/product')->getTable('catalog/product_website');

        $select = $this->_getWriteAdapter()->select();
        $select->from(
            ['p' => $productTable],
            ['product_id' => 'p.entity_id']
        );
        $select->join(
            ['website' => $productWebsiteTable],
            'p.entity_id=website.product_id',
            ['website_id']
        );
        $select->joinLeft(
            ['index' => $this->getMainTable()],
            'p.entity_id=index.product_id AND website.website_id=index.website_id',
            []
        );

        $select->columns(['qty' => new Zend_Db_Expr('0')]);
        $select->columns(['is_in_stock' => new Zend_Db_Expr('0')]);
        $select->columns(['backorders' => new Zend_Db_Expr('0')]);

        $select->where('index.qty IS NULL');
        if (count($productIds)) {
            $select->where('p.entity_id IN (?)', implode(',', $productIds));
        }

        $select->group('p.entity_id');
        $select->group('website.website_id');

        $sql = $this->_getWriteAdapter()->insertFromSelect(
            $select,
            $this->getMainTable(),
            [
                'product_id',
                'manage_stock',
                'qty',
                'is_in_stock',
                'backorder',
                'website_id',
            ]
        );

        $this->_getWriteAdapter()->query($sql);
    }

    /**
     * Get core stock item update query.
     *
     * @param array $productIds
     */
    protected function updateCoreStockItem(array $productIds = [])
    {
        $select = $this->_getWriteAdapter()->select();
        $select->join(
            ['src' => $this->getMainTable()],
            'dest.product_id = src.product_id',
            ['qty', 'is_in_stock', 'manage_stock', 'backorders']
        );
        $select->where('src.website_id = 0');
        if (count($productIds)) {
            $select->where('src.product_id IN (?)', $productIds);
        }

        $this->_getWriteAdapter()->query(
            $this->_getWriteAdapter()->updateFromSelect(
                $select,
                ['dest' => Mage::getResourceModel('cataloginventory/stock_item')->getMainTable()]
            )
        );
    }

    /**
     * Get core stock status update query.
     *
     * @param array $productIds
     */
    protected function updateCoreStockStatus(array $productIds = [])
    {
        $select = $this->_getWriteAdapter()->select();
        $select->join(
            ['src' => $this->getMainTable()],
            'dest.product_id=src.product_id AND dest.website_id=src.website_id',
            ['qty' => 'qty', 'stock_status' => 'is_in_stock']
        );
        if (count($productIds)) {
            $select->where('src.product_id IN (?)', $productIds);
        }

        $this->_getWriteAdapter()->query(
            $this->_getWriteAdapter()->updateFromSelect(
                $select,
                ['dest' => Mage::getResourceModel('cataloginventory/stock_status')->getMainTable()]
            )
        );
    }
}
