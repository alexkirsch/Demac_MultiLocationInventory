<?php
/**
 * Created by PhpStorm.
 * User: MichaelK
 * Date: 1/15/14
 * Time: 11:25 AM
 */

/**
 * Class Demac_MultiLocationInventory_Block_Adminhtml_Location_Edit_Tab_Location
 */
class Demac_MultiLocationInventory_Block_Adminhtml_Location_Edit_Tab_Location extends Mage_Adminhtml_Block_Widget_Form
{

    /**
     * Prepare form fields and data for Adminhtml Widget Form rendering.
     *
     * @return Demac_MultiLocationInventory_Block_Adminhtml_Location_Edit_Tab_Location
     */
    protected function _prepareForm()
    {
        /** @var Demac_MultiLocationInventory_Model_Location $locationModel */
        $locationModel = Mage::registry('multilocationinventory_data');
        $isEdit = (bool)$locationModel->getId();

        $form = new Varien_Data_Form();
        $fieldset = $form->addFieldset(
            'demac_multilocationinventory_form',
            [
                'legend' => $this->__('Location Information'),
            ]
        );

        $this->_prepareFormHiddenFields($fieldset, $isEdit);

        $fieldset->addField(
            'name',
            'text',
            [
                'label'    => $this->__('Name'),
                'class'    => 'required-entry',
                'required' => true,
                'name'     => 'name',
            ]
        );

        $fieldset->addField(
            'external_id',
            'text',
            [
                'label'    => $this->__('External ID'),
                'required' => false,
                'name'     => 'external_id',
            ]
        );

        $fieldset->addField(
            'status',
            'select',
            [
                'label'  => $this->__('Status'),
                'name'   => 'status',
                'values' => [
                    [
                        'value' => 1,
                        'label' => $this->__('Enabled'),
                    ],

                    [
                        'value' => 0,
                        'label' => $this->__('Disabled'),
                    ],
                ],
            ]
        );

        if (Mage::app()->isSingleStoreMode()) {
            $this->_prepareFormWebsiteSelectorHiddenField($fieldset);
            $locationModel->setDataUsingMethod('websites', [Mage::app()->getWebsite()->getId()]);
        } else {
            $this->_prepareFormWebsiteSelectorField($fieldset);
        }

        $this->_prepareFormAddressFields($fieldset);

        $fieldset->addField(
            'lat',
            'text',
            [
                'label'    => $this->__('Latitude'),
                'required' => false,
                'name'     => 'lat',
            ]
        );

        $fieldset->addField(
            'long',
            'text',
            [
                'label'    => $this->__('Longitude'),
                'required' => false,
                'name'     => 'long',
            ]
        );

        $form->setValues($locationModel->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Add hidden fields for id and create/update time to the form.
     *
     * @param $fieldset
     * @param $isEdit
     */
    protected function _prepareFormHiddenFields(Varien_Data_Form_Element_Fieldset $fieldset, $isEdit)
    {
        if ($isEdit) {
            $fieldset->addField(
                'id',
                'hidden',
                [
                    'name' => 'id',
                ]
            );
        }

        $fieldset->addField(
            'created_time',
            'hidden',
            [
                'name' => 'created_time',
            ]
        );

        $fieldset->addField(
            'update_time',
            'hidden',
            [
                'name' => 'update_time',
            ]
        );
    }

    /**
     * Add hidden field to specify the current store to the form.
     *
     * @param Varien_Data_Form_Element_Fieldset $fieldset
     */
    protected function _prepareFormWebsiteSelectorHiddenField(Varien_Data_Form_Element_Fieldset $fieldset)
    {
        $fieldset->addField(
            'websites',
            'hidden',
            [
                'name'  => 'websites[]',
                'value' => Mage::app()->getWebsite()->getId(),
            ]
        );
    }

    /**
     * Add field for store selection to the form.
     *
     * @param Varien_Data_Form_Element_Fieldset $fieldset
     */
    protected function _prepareFormWebsiteSelectorField(Varien_Data_Form_Element_Fieldset $fieldset)
    {
        $values = [];
        foreach (Mage::app()->getWebsites() as $website) {
            $values[] = ['value' => $website->getId(), 'label' => $website->getName()];
        }

        $fieldset->addField(
            'websites',
            'multiselect',
            [
                'name'     => 'websites[]',
                'label'    => $this->__('Websites'),
                'required' => false,
                'values'   => $values,
            ]
        );
    }

    /**
     * Add address fields to the form.
     *
     * @param $fieldset
     */
    protected function _prepareFormAddressFields($fieldset)
    {
        $fieldset->addField(
            'address',
            'text',
            [
                'label'    => $this->__('Address'),
                'class'    => 'required-entry',
                'required' => true,
                'name'     => 'address',
            ]
        );

        $fieldset->addField(
            'zipcode',
            'text',
            [
                'label'    => $this->__('Postal Code'),
                'class'    => 'required-entry',
                'required' => true,
                'name'     => 'zipcode',
            ]
        );

        $fieldset->addField(
            'city',
            'text',
            [
                'label'    => $this->__('City'),
                'class'    => 'required-entry',
                'required' => true,
                'name'     => 'city',
            ]
        );

        $values = [];
        $countryId = Mage::registry('multilocationinventory_data')->getCountryId();
        if ($countryId) {
            $values = Mage::helper('demac_multilocationinventory')->getRegions($countryId);
        }
        $fieldset->addField(
            'region_id',
            'select',
            [
                'name'   => 'region_id',
                'label'  => 'State/Province',
                'values' => $values,
            ]
        );

        $countryList = Mage::getModel('directory/country')->getCollection()->toOptionArray();
        $country = $fieldset->addField(
            'country_id',
            'select',
            [
                'label'    => $this->__('Country'),
                'name'     => 'country_id',
                'title'    => 'country',
                'values'   => $countryList,
                'onchange' => 'getstate(this)',
            ]
        );
        $country->setAfterElementHtml(
            "<script type=\"text/javascript\">
            function getstate(selectElement){
                var reloadurl = '" . $this->getUrl('adminhtml/multiLocationInventory/region') . "country/' + selectElement.value;
                new Ajax.Request(reloadurl, {
                    method: 'get',
                    onLoading: function (stateform) {
                        $('region_id').update('Searching...');
                    },
                    onComplete: function(stateform) {
                        $('region_id').update(stateform.responseText);
                    }
                });
            }
        </script>"
        );
    }
}
