<?php
/* @var Mage_Core_Model_Resource_Setup $this */
$this->startSetup();

$table = $this->getConnection()->newTable($this->getTable('demac_multilocationinventory/location'));
$table->addColumn(
    'id',
    Varien_Db_Ddl_Table::TYPE_INTEGER,
    null,
    [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ],
    'Location ID'
);
$table->addColumn(
    'name',
    Varien_Db_Ddl_Table::TYPE_VARCHAR,
    255,
    ['nullable' => false],
    'Name'
);
$table->addColumn(
    'external_id',
    Varien_Db_Ddl_Table::TYPE_TEXT,
    255,
    ['unique' => true],
    'External ID for client usage'
);
$table->addColumn(
    'address',
    Varien_Db_Ddl_Table::TYPE_VARCHAR,
    255,
    ['nullable' => false],
    'Address'
);
$table->addColumn(
    'zipcode',
    Varien_Db_Ddl_Table::TYPE_VARCHAR,
    10,
    ['nullable' => false],
    'ZipCode'
);
$table->addColumn(
    'city',
    Varien_Db_Ddl_Table::TYPE_VARCHAR,
    255,
    ['nullable' => false],
    'City'
);
$table->addColumn(
    'region_id',
    Varien_Db_Ddl_Table::TYPE_VARCHAR,
    255,
    ['nullable' => false],
    'Region/Province'
);
$table->addColumn(
    'country_id',
    Varien_Db_Ddl_Table::TYPE_VARCHAR,
    255,
    [],
    'Country'
);
$table->addColumn(
    'status',
    Varien_Db_Ddl_Table::TYPE_SMALLINT,
    6,
    ['nullable' => false],
    'Status'
);
$table->addColumn(
    'lat',
    Varien_Db_Ddl_Table::TYPE_VARCHAR,
    255,
    ['nullable' => true],
    'Latitude Value'
);
$table->addColumn(
    'long',
    Varien_Db_Ddl_Table::TYPE_VARCHAR,
    255,
    ['nullable' => true],
    'Longitude Value'
);
$table->addColumn(
    'priority',
    Varien_Db_Ddl_Table::TYPE_BOOLEAN,
    null,
    ['default' => 0],
    'Location Priority'
);
$table->addColumn(
    'created_time',
    Varien_Db_Ddl_Table::TYPE_DATETIME,
    null,
    [],
    'Creation Time'
);
$table->addColumn(
    'update_time',
    Varien_Db_Ddl_Table::TYPE_DATETIME,
    null,
    [],
    'Modification Time'
);
$table->addIndex(
    $this->getIdxName('demac_multilocationinventory/location', ['external_id'], Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
    ['external_id'],
    ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE]
);
$table->setComment('Location Table');
$this->getConnection()->createTable($table);

$table = $this->getConnection()->newTable($this->getTable('demac_multilocationinventory/website'));
$table->addColumn(
    'location_id',
    Varien_Db_Ddl_Table::TYPE_INTEGER,
    null,
    [
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ],
    'Location ID'
);
$table->addColumn(
    'website_id',
    Varien_Db_Ddl_Table::TYPE_SMALLINT,
    null,
    [
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ],
    'Website ID'
);
$table->addForeignKey(
    $this->getFkName('demac_multilocationinventory/website', 'location_id', 'demac_multilocationinventory/location', 'id'),
    'location_id',
    $this->getTable('demac_multilocationinventory/location'),
    'id',
    Varien_Db_Ddl_Table::ACTION_CASCADE,
    Varien_Db_Ddl_Table::ACTION_CASCADE
);
$table->addForeignKey(
    $this->getFkName('demac_multilocationinventory/website', 'website_id', 'core/website', 'website_id'),
    'website_id',
    $this->getTable('core/website'),
    'website_id',
    Varien_Db_Ddl_Table::ACTION_CASCADE,
    Varien_Db_Ddl_Table::ACTION_CASCADE
);
$table->setComment('Location To Magento Website Linkage Table');
$this->getConnection()->createTable($table);

$table = $this->getConnection()->newTable($this->getTable('demac_multilocationinventory/stock'));
$table->addColumn(
    'stock_id',
    Varien_Db_Ddl_Table::TYPE_INTEGER,
    null,
    [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary'  => true,
    ],
    'Stock Id'
);
$table->addColumn(
    'location_id',
    Varien_Db_Ddl_Table::TYPE_INTEGER,
    null,
    [
        'unsigned' => true,
        'nullable' => false,
    ],
    'Location ID'
);
$table->addColumn(
    'product_id',
    Varien_Db_Ddl_Table::TYPE_INTEGER,
    null,
    [
        'nullable' => false,
        'unsigned' => true,
    ],
    'Product ID'
);
$table->addColumn(
    'qty',
    Varien_Db_Ddl_Table::TYPE_DECIMAL,
    '12,4',
    [
        'nullable' => false,
        'default'  => '0.0000',
    ],
    'Qty'
);
$table->addColumn(
    'backorders',
    Varien_Db_Ddl_Table::TYPE_SMALLINT,
    null,
    [
        'unsigned' => true,
        'nullable' => false,
        'default'  => '0',
    ],
    'Backorders'
);
$table->addColumn(
    'use_config_backorders',
    Varien_Db_Ddl_Table::TYPE_SMALLINT,
    null,
    [
        'unsigned' => true,
        'nullable' => false,
        'default'  => '1',
    ],
    'Use Config Backorders'
);
$table->addColumn(
    'is_in_stock',
    Varien_Db_Ddl_Table::TYPE_SMALLINT,
    null,
    [
        'unsigned' => true,
        'nullable' => false,
        'default'  => '0',
    ],
    'Is In Stock'
);
$table->addColumn(
    'manage_stock',
    Varien_Db_Ddl_Table::TYPE_SMALLINT,
    null,
    [
        'unsigned' => true,
        'nullable' => false,
        'default'  => '1',

    ],
    'Manage Stock'
);
$table->addColumn(
    'use_config_manage_stock',
    Varien_Db_Ddl_Table::TYPE_SMALLINT,
    null,
    [
        'unsigned' => true,
        'nullable' => false,
        'default'  => '1',
    ],
    'Use Config Manage Stock'
);
$table->addIndex(
    $this->getIdxName('demac_multilocationinventory/stock', ['product_id', 'location_id'], Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
    ['product_id', 'location_id'],
    ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE]
);
$table->addForeignKey(
    $this->getFkName('demac_multilocationinventory/stock', 'location_id', 'demac_multilocationinventory/location', 'id'),
    'location_id',
    $this->getTable('demac_multilocationinventory/location'),
    'id',
    Varien_Db_Ddl_Table::ACTION_CASCADE,
    Varien_Db_Ddl_Table::ACTION_CASCADE
);
$table->addForeignKey(
    $this->getFkName('demac_multilocationinventory/stock', 'product_id', 'catalog/product', 'entity_id'),
    'product_id',
    $this->getTable('catalog/product'),
    'entity_id',
    Varien_Db_Ddl_Table::ACTION_CASCADE,
    Varien_Db_Ddl_Table::ACTION_CASCADE
);
$this->getConnection()->createTable($table);

$table = $this->getConnection()->newTable($this->getTable('demac_multilocationinventory/order_stock_source'));
$table->addColumn(
    'id',
    Varien_Db_Ddl_Table::TYPE_INTEGER,
    null,
    [
        'identity' => true,
        'primary'  => true,
        'unsigned' => true,
        'nullable' => false,
    ]
);
$table->addColumn(
    'sales_quote_item_id',
    Varien_Db_Ddl_Table::TYPE_INTEGER,
    null,
    [
        'unsigned' => true,
        'nullable' => false,
    ],
    'Sales Quote Item ID'
);
$table->addColumn(
    'location_id',
    Varien_Db_Ddl_Table::TYPE_INTEGER,
    null,
    [
        'unsigned' => true,
        'nullable' => false,
    ],
    'Location ID'
);
$table->addColumn(
    'qty',
    Varien_Db_Ddl_Table::TYPE_DECIMAL,
    '12,4',
    [
        'nullable' => false,
    ],
    'Qty'
);
$table->addColumn(
    'is_backorder',
    Varien_Db_Ddl_Table::TYPE_BOOLEAN,
    null,
    [
        'nullable' => false,
    ],
    'Is Backorder?'
);
$table->addForeignKey(
    $this->getFkName('demac_multilocationinventory/order_stock_source', 'sales_quote_item_id', 'sales/quote_item', 'item_id'),
    'sales_quote_item_id',
    $this->getTable('sales/quote_item'),
    'item_id',
    Varien_Db_Ddl_Table::ACTION_CASCADE,
    Varien_Db_Ddl_Table::ACTION_CASCADE
);
$table->addForeignKey(
    $this->getFkName('demac_multilocationinventory/order_stock_source', 'location_id', 'demac_multilocationinventory/location', 'id'),
    'location_id',
    $this->getTable('demac_multilocationinventory/location'),
    'id',
    Varien_Db_Ddl_Table::ACTION_CASCADE,
    Varien_Db_Ddl_Table::ACTION_CASCADE
);
$this->getConnection()->createTable($table);

$this->endSetup();
