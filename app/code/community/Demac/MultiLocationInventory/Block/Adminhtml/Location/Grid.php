<?php

/**
 * Class Demac_MultiLocationInventory_Block_Adminhtml_Location_Grid
 */
class Demac_MultiLocationInventory_Block_Adminhtml_Location_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Init - prepare widget grid.
     */
    public function __construct()
    {
        parent::__construct();

        // Set some defaults for our grid

        $this->setDefaultSort('id');
        $this->setId('LocationGrid');
        $this->setDefaultDir('asc');
        $this->setSaveParametersInSession(true);
    }

    /**
     * Set this widget grid's collection to be a collection of locations then let parent prepare grid object collection.
     *
     * @return Demac_MultiLocationInventory_Block_Adminhtml_Location_Grid
     */
    protected function _prepareCollection()
    {
        // Get and set our collection for the grid
        $collection = Mage::getModel('demac_multilocationinventory/location')->getCollection();
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * Add all necessary columns then prepare for rendering.
     *
     * @return Demac_MultiLocationInventory_Block_Adminhtml_Location_Grid
     */
    protected function _prepareColumns()
    {
        // Add the columns that should appear in the grid
        $this->addColumn(
            'id',
            [
                'header' => $this->__('ID'),
                'align'  => 'right',
                'width'  => '50px',
                'index'  => 'id',
            ]
        );

        $this->addColumn(
            'name',
            [
                'header' => $this->__('Name'),
                'index'  => 'name',
            ]
        );

        $this->addColumn(
            'external_id',
            [
                'header' => $this->__('External ID'),
                'index'  => 'external_id',
            ]
        );

        $this->addColumn(
            'websites',
            [
                'header'   => $this->__('Websites'),
                'index'    => 'websites',
                'renderer' => 'demac_multilocationinventory/adminhtml_widget_grid_column_website_renderer',
                'sortable' => false,
                'filter'   => false,
            ]
        );

        $this->addColumn(
            'priority',
            [
                'header' => $this->__('Priority'),
                'index'  => $this->__('priority'),
                'type'   => 'number',
            ]
        );

        $this->addColumn(
            'status',
            [
                'header'  => $this->__('Status'),
                'index'   => $this->__('status'),
                'type'    => 'options',
                'options' => [
                    0 => $this->__('Disabled'),
                    1 => $this->__('Enabled'),
                ],
            ]
        );

        return parent::_prepareColumns();
    }

    /**
     * Define identifier field and options for mass actions.
     *
     * @return $this|Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('id');
        $this->getMassactionBlock()->setFormFieldName('demac_multilocationinventory');

        $this->getMassactionBlock()->addItem(
            'delete',
            [
                'label'   => $this->__('Delete'),
                'url'     => $this->getUrl('*/*/massDelete'),
                'confirm' => $this->__('Are you sure?'),
            ]
        );

        $this->getMassactionBlock()->addItem(
            'status',
            [
                'label'      => $this->__('Change status'),
                'url'        => $this->getUrl('*/*/massStatus', ['_current' => true]),
                'additional' => [
                    'visibility' => [
                        'name'   => 'status',
                        'type'   => 'select',
                        'class'  => 'required-entry',
                        'label'  => $this->__('Status'),
                        'values' => [
                            1 => $this->__('Enabled'),
                            0 => $this->__('Disabled'),
                        ],
                    ],
                ],
            ]
        );

        return $this;
    }

    /**
     * Get edit URL for clicking on a row.
     *
     * @param $row
     *
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
