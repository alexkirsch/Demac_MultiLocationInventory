<?php

/**
 * Class Demac_MultiLocationInventory_Helper_Location
 */
class Demac_MultiLocationInventory_Helper_Location extends Mage_Core_Helper_Abstract
{
    /**
     * @param Mage_Sales_Model_Order      $order
     * @param Mage_Sales_Model_Quote_Item $quoteItem
     *
     * @return int[]
     */
    public function getPrioritizedLocations(Mage_Sales_Model_Order $order, Mage_Sales_Model_Quote_Item $quoteItem)
    {
        $websiteId = intval($order->getStore()->getWebsiteId());

        /** @var Demac_MultiLocationInventory_Model_Resource_Location_Collection $locations */
        $locations = Mage::getModel('demac_multilocationinventory/location')->getCollection();
        $locations->join(
            ['website' => 'demac_multilocationinventory/website'],
            'main_table.id = website.location_id AND website.website_id=' . $websiteId,
            []
        );

        // Generate a 2-level array holding prioritized location IDs
        $prioritizedLocationsProcessing = [];
        foreach ($locations as $location) {
            /** @var Demac_MultiLocationInventory_Model_Location $location */
            $priority = $this->getPriority($location, $order, $quoteItem);
            $prioritizedLocationsProcessing[$priority][] = intval($location->getId());
        }

        // Reverse sort array by keys (which holds priority) so that the highest priority is first
        krsort($prioritizedLocationsProcessing);

        // Flatten the multi-dimentsional array
        $prioritizedLocations = [];
        foreach ($prioritizedLocationsProcessing as $prioritizedLocationsSet) {
            $prioritizedLocations = array_merge($prioritizedLocations, $prioritizedLocationsSet);
        }

        return $prioritizedLocations;
    }

    /**
     * Get a priority score based on Order ID, Location ID and Quote Item ID.
     *
     * @param Demac_MultiLocationInventory_Model_Location $location
     * @param Mage_Sales_Model_Order                      $order
     * @param Mage_Sales_Model_Quote_Item                 $quoteItem
     *
     * @return int
     *
     * @todo     Full refactor to allow routing priority
     */
    public function getPriority(Demac_MultiLocationInventory_Model_Location $location, Mage_Sales_Model_Order $order, Mage_Sales_Model_Quote_Item $quoteItem)
    {
        return ($location->getPriority() * 100) + rand(0, 99);
    }
}
