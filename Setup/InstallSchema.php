<?php

namespace MailCampaigns\Magento2Connector\Setup;

use Exception;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Model\Logger;
use Zend_Db_Exception;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(LogHelperInterface $logHelper)
    {
        $this->logger = $logHelper->getLogger();
    }

    /**
     * @inheritDoc
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            $installer = $setup;
            $installer->startSetup();

            $this
                ->installQueueTable($installer)
                ->installPagesTable($installer)
                ->installStatusTable($installer);

            $installer->endSetup();
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }
    }

    /**
     * @param SchemaSetupInterface $installer
     * @return $this
     * @throws Zend_Db_Exception
     */
    protected function installQueueTable(SchemaSetupInterface $installer)
    {
        $tableName = $installer->getTable('mc_api_queue');

        if ($installer->getConnection()->isTableExists($tableName)) {
            return $this;
        }

        $idOptions = [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true
        ];

        $datetimeOptions = [
            'nullable' => false,
            'default' => 0
        ];

        $streamDataOptions = [
            'nullable' => false,
            'default' => ''
        ];

        $errorOptions = [
            'nullable' => false,
            'default' => '0'
        ];

        $table = $installer->getConnection()
            ->newTable($tableName)
            ->setComment('MailCampaigns queue table')
            ->setOption('type', 'InnoDB')
            ->setOption('charset', 'utf8')
            ->addColumn('id', Table::TYPE_INTEGER, null, $idOptions, 'ID')
            ->addColumn('datetime', Table::TYPE_INTEGER, 11, $datetimeOptions, 'Timestamp')
            ->addColumn('stream_data', Table::TYPE_TEXT, '2M', $streamDataOptions, 'JSON data to post')
            ->addColumn('error', Table::TYPE_SMALLINT, 1, $errorOptions, 'Error')
            ->addIndex('datetime_index', ['datetime'])
            ->addIndex('error_index', ['datetime']);

        $installer->getConnection()->createTable($table);

        return $this;
    }

    /**
     * @param SchemaSetupInterface $installer
     * @return $this
     * @throws Zend_Db_Exception
     */
    protected function installPagesTable(SchemaSetupInterface $installer)
    {
        $tableName = $installer->getTable('mc_api_pages');

        if ($installer->getConnection()->isTableExists($tableName)) {
            return $this;
        }

        $idOptions = [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true
        ];

        $datetimeOptions = [
            'nullable' => false,
            'default' => 0
        ];

        $collectionOptions = [
            'nullable' => false,
            'default' => ''
        ];

        $storeIdOptions = [
            'nullable' => false,
            'default' => '0'
        ];

        $pageOptions = [
            'nullable' => false,
            'default' => '0'
        ];

        $totalOptions = [
            'nullable' => false,
            'default' => '0'
        ];

        $table = $installer->getConnection()
            ->newTable($tableName)
            ->setComment('MailCampaigns pages table')
            ->setOption('type', 'InnoDB')
            ->setOption('charset', 'utf8')
            ->addColumn('id', Table::TYPE_INTEGER, null, $idOptions, 'ID')
            ->addColumn('datetime', Table::TYPE_INTEGER, 11, $datetimeOptions, 'Timestamp')
            ->addColumn('collection', Table::TYPE_TEXT, 100, $collectionOptions, 'Collection')
            ->addColumn('store_id', Table::TYPE_INTEGER, 11, $storeIdOptions, 'Store ID')
            ->addColumn('page', Table::TYPE_INTEGER, 11, $pageOptions, 'Page index')
            ->addColumn('total', Table::TYPE_INTEGER, 11, $totalOptions, 'Total pages')
            ->addIndex('datetime_index', ['datetime'])
            ->addIndex('page_index', ['page'])
            ->addIndex('store_id_collection_index', ['store_id', 'collection']);

        $installer->getConnection()->createTable($table);

        return $this;
    }

    /**
     * @param SchemaSetupInterface $installer
     * @return $this
     * @throws Zend_Db_Exception
     */
    protected function installStatusTable(SchemaSetupInterface $installer)
    {
        $tableName = $installer->getTable('mc_api_status');

        if ($installer->getConnection()->isTableExists($tableName)) {
            return $this;
        }

        $idOptions = [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true
        ];

        $datetimeOptions = [
            'nullable' => false,
            'default' => 0
        ];

        $typeOptions = [
            'nullable' => false,
            'default' => ''
        ];

        $table = $installer->getConnection()
            ->newTable($tableName)
            ->setComment('MailCampaigns status table')
            ->setOption('type', 'InnoDB')
            ->setOption('charset', 'utf8')
            ->addColumn('id', Table::TYPE_INTEGER, null, $idOptions, 'ID')
            ->addColumn('datetime', Table::TYPE_INTEGER, 11, $datetimeOptions, 'Timestamp')
            ->addColumn('type', Table::TYPE_TEXT, 100, $typeOptions, 'Collection')
            ->addIndex('datetime_index', ['datetime'])
            ->addIndex('type_index', ['type']);

        $installer->getConnection()->createTable($table);

        return $this;
    }
}
