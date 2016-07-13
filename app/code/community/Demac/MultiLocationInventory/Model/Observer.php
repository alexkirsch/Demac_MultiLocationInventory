<?php

/**
 * Class Demac_MultiLocationInventory_Model_Observer
 */
class Demac_MultiLocationInventory_Model_Observer
{

    /**
     * @var array of arrays with Quote Stock Id => Quantity Ordered
     */
    private $checkoutProducts = [];

    /**
     * Deduct ordered products from inventory if the appropriate config setting is enabled.
     * Triggers after checkout submit.
     *
     * @param $observer
     *
     * @TODO pass off removeStockFromLocations and backorderRemainingStock to some sort of background worker.
     */
    public function checkoutAllSubmitAfter($observer)
    {
        if (Mage::getStoreConfig('cataloginventory/options/can_subtract')) {
            $order = $observer->getEvent()->getOrder();
            $quote = $observer->getEvent()->getQuote();

            $this->checkoutProducts = [];
            $updatedProducts = [];
            $quoteItems = $observer->getEvent()->getQuote()->getAllItems();

            foreach ($quoteItems as $quoteItem) {
                if (sizeof($quoteItem->getChildren()) == 0) {
                    $updatedProducts[] = $quoteItem->getProductId();

                    $this->checkoutProducts[$quoteItem->getId()] = $quoteItem->getQty();
                    if (!is_null($quoteItem->getParentItem())) {
                        $this->checkoutProducts[$quoteItem->getId()] = $quoteItem->getParentItem()->getQty();
                    }
                }
            }

            $this->removeStockFromLocations($order, $quote);

            Mage::getModel('demac_multilocationinventory/indexer')->reindex($updatedProducts);
        }
    }

    /**
     * Remove stock from locations.
     *
     * @param $order
     * @param $quote
     */
    protected function removeStockFromLocations(Mage_Sales_Model_Order $order, Mage_Sales_Model_Quote $quote)
    {
        $orderId = $order->getId();

        foreach ($this->checkoutProducts as $checkoutProductQuoteItemId => $checkoutProductQuantity) {
            $checkoutProductItem = $quote->getItemById($checkoutProductQuoteItemId);
            if ($checkoutProductItem->getProduct()->getTypeId() == 'simple' || $checkoutProductItem->getProduct()->getTypeId() == 'giftcard') {
                $checkoutProductId = $checkoutProductItem->getProductId();
                $locationIds = Mage::helper('demac_multilocationinventory/location')->getPrioritizedLocations($order, $checkoutProductItem);

                //loop through each location and distribute the inventory
                $stockCollection = Mage::getModel('demac_multilocationinventory/stock')
                    ->getCollection()
                    ->addFieldToSelect(['location_id', 'qty'])
                    ->addFieldToFilter(
                        'location_id',
                        [
                            'in' => $locationIds,
                        ]
                    )
                    ->addFieldToFilter(
                        'qty',
                        [
                            'gt' => '0',
                        ]
                    )
                    ->addFieldToFilter('product_id', $checkoutProductId);
                $stockCollection
                    ->getSelect()
                    ->order('FIELD(location_id,' . implode(',', $locationIds) . ')');

                Mage::getModel('demac_multilocationinventory/resource_iterator')->walk(
                    $stockCollection->getSelect(),
                    [
                        [$this, '_locationStockIterate'],
                    ],
                    [
                        'invoker'       => $this,
                        'quote_item_id' => $checkoutProductQuoteItemId,
                        'product_id'    => $checkoutProductId,
                    ]
                );

                //Get Backorder Location
                //Reduce backorder inventory if possible...
                foreach ($this->checkoutProducts as $checkoutProductQuoteItemId => $checkoutProductQuantity) {
                    if ($checkoutProductQuantity > 0) {
                        $backorderLocationCollection = Mage::getModel('demac_multilocationinventory/stock')
                            ->getCollection()
                            ->addFieldToSelect(['location_id', 'qty'])
                            ->addFieldToFilter(
                                'location_id',
                                [
                                    'in' => $locationIds,
                                ]
                            )
                            ->addFieldToFilter(
                                'backorders',
                                [
                                    'eq' => '1',
                                ]
                            )
                            ->addFieldToFilter('product_id', $checkoutProductId);
                        $backorderLocationCollection
                            ->getSelect()
                            ->order('FIELD(location_id,' . implode(',', $locationIds) . ')')
                            ->limit(1);

                        if ($backorderLocationCollection->getSize()) {
                            $firstBackorderLocation = $backorderLocationCollection->getFirstItem();
                            $stockId = $firstBackorderLocation['stock_id'];
                            $locationId = $firstBackorderLocation['location_id'];
                            $availableQty = $firstBackorderLocation['qty'];
                            $orderStockSource = Mage::getModel('demac_multilocationinventory/order_stock_source');
                            $orderStockSource->setSalesQuoteItemId($checkoutProductQuoteItemId);
                            $orderStockSource->setLocationId($locationId);
                            $remainingQty = $orderStockSource->getQty() - $checkoutProductQuantity;
                            $orderStockSource->setQty($remainingQty);
                            $orderStockSource->save();
                            $this->checkoutProducts[$checkoutProductQuoteItemId] = 0;
                            $stock = Mage::getModel('demac_multilocationinventory/stock')->load($stockId);
                            $stock->setQty($remainingQty);
                            $stock->save();
                        }
                    }
                }
            }
        }
    }

    /**
     * Iterate through locations / stock deducting until the order is processed..
     *
     * @param $args
     *
     * @return bool
     */
    public function _locationStockIterate($args)
    {
        $quoteItemId = $args['quote_item_id'];
        $productId = $args['product_id'];
        $requestedQty = $this->checkoutProducts[$quoteItemId];

        $row = $args['row'];
        $stockId = $row['stock_id'];
        $locationId = $row['location_id'];
        $availableQty = $row['qty'];
        $requestedQty = $this->checkoutProducts[$quoteItemId];

        $orderStockSource = Mage::getModel('demac_multilocationinventory/order_stock_source');
        $orderStockSource->setSalesQuoteItemId($quoteItemId);
        $orderStockSource->setLocationId($locationId);

        if ($requestedQty > 0) {
            if ($requestedQty >= $availableQty) {
                //deduct available qty
                $orderStockSource->setQty($availableQty);
                $orderStockSource->save();
                $this->checkoutProducts[$quoteItemId] -= $availableQty;
                $stock = Mage::getModel('demac_multilocationinventory/stock')->load($stockId);
                $stock->setQty(0);
                if (!$stock->getBackorders()) {
                    $stock->setIsInStock(0);
                }
                $stock->save();
            } else {
                //deduct full requested amount
                $orderStockSource->setQty($requestedQty);
                $orderStockSource->save();
                $this->checkoutProducts[$quoteItemId] = 0;
                $stock = Mage::getModel('demac_multilocationinventory/stock')->load($stockId);
                $stock->setQty($stock->getQty() - $requestedQty);
                $stock->save();

                //returning false causes our iterator to stop.
                return false;
            }
        }
    }

}
