<?php

/**
 * Class Demac_MultiLocationInventory_Model_Resource_Location_Collection
 */
class Demac_MultiLocationInventory_Model_Resource_Location_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Init collection
     */
    protected function _construct()
    {
        $this->_init('demac_multilocationinventory/location');
        $this->_map['fields']['location_id'] = 'demac_multilocationinventory_website.location_id';
    }

    /**
     * Perform operations after collection load
     *
     * @return Demac_MultiLocationInventory_Model_Resource_Location_Collection
     *
     */
    protected function _afterLoad()
    {
        foreach ($this->_items as $item) {
            /** @var Demac_MultiLocationInventory_Model_Location $item */
            $item->afterLoad();
        }

        return parent::_afterLoad();
    }

    /**
     * Join store relation table if there is store filter
     *
     * @return NULL
     */
    protected function _renderFiltersBefore()
    {
        if ($this->getFilter('website_id')) {
            $this->getSelect()->join(
                ['website' => $this->getTable('demac_multilocationinventory/website')],
                'main_table.id = website.location_id',
                []
            );
            $this->getSelect()->group('main_table.id');

            /*
             * Allow analytic functions usage because of one field grouping
             */
            $this->_useAnalyticFunction = true;
        }

        return parent::_renderFiltersBefore();
    }

    /**
     * Join stock data to a location collection based on product id
     *
     * @param int  $productId
     *
     * @return $this
     */
    public function joinStock($productId)
    {
        if (isset($this->_joinedTables['stock'])) {
            throw new InvalidArgumentException('The stock table was already joined');
        }

        $productId = intval($productId);
        $this->getSelect()->joinLeft(
            ['stock' => $this->getTable('demac_multilocationinventory/stock')],
            'main_table.id=stock.location_id AND stock.product_id=' . $productId,
            [
                'qty'          => new Zend_Db_Expr('IFNULL(stock.qty, 0)'),
                'backorders'   => new Zend_Db_Expr('IFNULL(stock.backorders, 0)'),
                'is_in_stock'  => new Zend_Db_Expr('IFNULL(stock.is_in_stock, 0)'),
                'manage_stock' => new Zend_Db_Expr('IFNULL(stock.manage_stock, 0)'),
            ]
        );
        $this->getSelect()->group('main_table.id');
        $this->_joinedTables['stock'] = true;

        return $this;
    }

    public function joinWebsites()
    {
        if (!isset($this->_joinedTables['website'])) {
            $this->getSelect()->joinLeft(
                ['website' => $this->getTable('demac_multilocationinventory/website')],
                'main_table.id = website.location_id',
                ['websites' => new Zend_Db_Expr('GROUP_CONCAT(website.website_id SEPARATOR ",")')]
            );
            $this->getSelect()->group('main_table.id');
            $this->_joinedTables['website'] = true;
        }

        return $this;
    }

    public function filterWebsites(array $websiteIds)
    {
        $websiteIds = array_filter(array_map('intval', $websiteIds));
        if (count($websiteIds) === 0) {
            throw new InvalidArgumentException('The $websiteIds argument requires at least one valid value');
        }

        $this->joinWebsites();
        $this->addFieldToFilter('website.website_id', ['in' => $websiteIds]);

        return $this;
    }

    /**
     * Join stock data to a location collection based on product id and store view id.
     *
     * @param int $productId
     * @param int $websiteId
     *
     * @return $this
     */
    public function joinStockAndWebsiteData($productId, $websiteId = null)
    {
        $productId = intval($productId);
        $websiteId = intval($websiteId);
        if (!$productId) {
            throw new InvalidArgumentException('The productId argument is required');
        }

        $this->addFieldToFilter('main_table.status', 1);

        $this->join(
            ['stock' => 'demac_multilocationinventory/stock'],
            'main_table.id = stock.location_id',
            ['stock.qty', 'stock.backorders', 'stock.is_in_stock', 'stock.manage_stock']
        );
        $this->addFieldToFilter('stock.product_id', ['eq' => $productId]);

        $this->join(
            ['website' => 'demac_multilocationinventory/website'],
            'main_table.id = website.location_id',
            []
        );
        if ($websiteId) {
            $this->addFieldToFilter('website.website_id', $websiteId);
        }

        return $this;
    }

}
