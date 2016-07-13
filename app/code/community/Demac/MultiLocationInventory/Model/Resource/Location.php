<?php

/**
 * Class Demac_MultiLocationInventory_Model_Resource_Location
 */
class Demac_MultiLocationInventory_Model_Resource_Location extends Mage_Core_Model_Resource_Db_Abstract
{
    protected $_location = null;

    protected function _construct()
    {
        $this->_init('demac_multilocationinventory/location', 'id');
    }

    /**
     * @param Mage_Core_Model_Abstract $object
     *
     * @return Mage_Core_Model_Resource_Db_Abstract
     */
    protected function _afterLoad(Mage_Core_Model_Abstract $object)
    {
        if ($object->hasData('websites')) {
            $websites = $object->getData('websites');
            if (is_array($websites)) {
                $websites = array_unique(array_map('intval', $websites));
            } elseif (is_scalar($websites)) {
                $websites = array_unique(array_map('intval', explode(',', $websites)));
            } else {
                $websites = [];
            }
        } else {
            $websites = $this->lookupWebsiteIds($object->getId());
        }

        $object->setData('websites', $websites);

        return parent::_afterLoad($object);
    }

    /**
     * @param Mage_Core_Model_Abstract $object
     *
     * @return Mage_Core_Model_Resource_Db_Abstract
     */
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        $oldWebsites = $this->lookupWebsiteIds($object->getId());
        $newWebsites = $object->getDataUsingMethod('websites');
        if (is_array($newWebsites)) {
            $newWebsites = array_unique(array_map('intval', $newWebsites));
        } elseif (is_scalar($newWebsites)) {
            $newWebsites = array_unique(array_map('intval', explode(',', $newWebsites)));
        } else {
            $newWebsites = [];
        }

        $table = $this->getTable('demac_multilocationinventory/website');
        $insert = array_diff($newWebsites, $oldWebsites);
        $delete = array_diff($oldWebsites, $newWebsites);

        if (count($delete)) {
            $this->_getWriteAdapter()->delete(
                $table,
                [
                    'location_id = ?'   => intval($object->getId()),
                    'website_id IN (?)' => $delete,
                ]
            );
        }

        if (count($insert)) {
            $data = [];
            foreach ($insert as $websiteId) {
                $data[] = ['location_id' => intval($object->getId()), 'website_id' => intval($websiteId)];
            }
            $this->_getWriteAdapter()->insertMultiple($table, $data);
        }

        return parent::_afterSave($object);
    }

    /**
     * Get website ids to which specified item is assigned
     *
     * @param int $locationId
     *
     * @return int[]
     */
    public function lookupWebsiteIds($locationId)
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getTable('demac_multilocationinventory/website'), 'website_id')
            ->where('location_id = ?', intval($locationId));

        return array_map('intval', $adapter->fetchCol($select));
    }
}
