<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class MailCampaignsAPIConfig implements ObserverInterface
{
    protected $logger;
	protected $version;
	protected $helper;
	protected $storemanager;
	protected $connection;
	protected $mcapi;
	protected $resource;
	protected $cron;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\App\ResourceConnection $Resource,
        Logger $logger
    ) {
		$this->version 		= '2.0.21';
		$this->logger 		= $logger;
		$this->helper 		= $dataHelper;
		$this->mcapi 		= $mcapi;
		$this->storemanager 	= $storeManager;
		$this->resource 		= $Resource;
    }

    public function execute(EventObserver $observer)
    {
		// set vars
		$this->mcapi->APIWebsiteID 		= $observer->getWebsite();
      	$this->mcapi->APIStoreID 		= $observer->getStore(); 
		$this->mcapi->APIKey 			= $this->helper->getConfig('mailcampaignsapi/general/api_key', $this->mcapi->APIStoreID);
  		$this->mcapi->APIToken 			= $this->helper->getConfig('mailcampaignsapi/general/api_token', $this->mcapi->APIStoreID);
		
		//database connection
		$this->connection = $this->resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
		
		$tableName = $this->resource->getTableName('mc_api_queue');
		if ($this->connection->isTableExists($tableName) != true) 
		{
            // Create mc_api_queue table
            $table = $this->connection
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
            $this->connection->createTable($table);
        }
		
		
        $tableName = $this->resource->getTableName('mc_api_pages');
        if ($this->connection->isTableExists($tableName) != true) 
		{
            // Create mc_api_pages table
            $table = $this->connection
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
                ->setComment('MailCampaigns Pages Table')
                ->setOption('type', 'InnoDB')
                ->setOption('charset', 'utf8');
            $this->connection->createTable($table);
        }
		
  		// get multistore settings
		$config_data 					= array();
		$config_data 					= $this->storemanager->getStore($this->mcapi->APIStoreID)->getData();
		$config_data["website_id"]		= $this->mcapi->APIWebsiteID;
		$config_data["version"] 			= $this->version;
		$config_data["url"] 				= $_SERVER['SERVER_NAME'];
		
		// push data to mailcampaigns api
		$this->mcapi->Call("save_magento_settings", $config_data, 0);
    }
}