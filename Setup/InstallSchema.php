<?php

namespace MailCampaigns\Connector\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $tableName = $installer->getTable('mc_api_queue');
        if ($installer->getConnection()->isTableExists($tableName) != true) {
            // Create mc_api_queue table
            $table = $installer->getConnection()
                ->newTable($tableName)
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true
                    ],
                    'ID'
                )
                ->addColumn(
                    'datetime',
                    Table::TYPE_INTEGER,
                    11,
                    ['nullable' => false, 'default' => 0],
                    'Timestamp'
                )
                ->addColumn(
                    'stream_data',
                    Table::TYPE_TEXT,
                    '2M',
                    ['nullable' => false, 'default' => ''],
                    'JSON data to post'
                )
                ->addColumn(
                    'error',
                    Table::TYPE_SMALLINT,
                    1,
                    ['nullable' => false, 'default' => '0'],
                    'Error'
                )
                ->setComment('MailCampaigns Queue Table')
                ->setOption('type', 'InnoDB')
                ->setOption('charset', 'utf8');
            $installer->getConnection()->createTable($table);
        }
		
        $tableName = $installer->getTable('mc_api_pages');
        if ($installer->getConnection()->isTableExists($tableName) != true) {
            // Create mc_api_pages table
            $table = $installer->getConnection()
                ->newTable($tableName)
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true
                    ],
                    'ID'
                )
                ->addColumn(
                    'datetime',
                    Table::TYPE_INTEGER,
                    11,
                    ['nullable' => false, 'default' => 0],
                    'Timestamp'
                )
                ->addColumn(
                    'collection',
                    Table::TYPE_TEXT,
                    100,
                    ['nullable' => false, 'default' => ''],
                    'Collection'
                )
                ->addColumn(
                    'store_id',
                    Table::TYPE_INTEGER,
                    11,
                    ['nullable' => false, 'default' => '0'],
                    'Store ID'
                )
				 ->addColumn(
                    'page',
                    Table::TYPE_INTEGER,
                    11,
                    ['nullable' => false, 'default' => '0'],
                    'Page index'
                )
				 ->addColumn(
                    'total',
                    Table::TYPE_INTEGER,
                    11,
                    ['nullable' => false, 'default' => '0'],
                    'Total pages'
                )
                ->setComment('MailCampaigns Queue Table')
                ->setOption('type', 'InnoDB')
                ->setOption('charset', 'utf8');
            $installer->getConnection()->createTable($table);
        }

        $installer->endSetup();
    }
}