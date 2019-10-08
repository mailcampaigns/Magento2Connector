<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;

class MailCampaignsHistoricalSync implements ObserverInterface
{
	protected $version;
	protected $helper;
	protected $storemanager;
	protected $mcapi;
	protected $resource;
	protected $connection;
	protected $objectmanager;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\App\ResourceConnection $resourceConnection,
		\Magento\Framework\ObjectManagerInterface $objectManager
    ) {
		$this->version 				= '2.1.0';
		$this->helper 				= $dataHelper;
		$this->mcapi 				= $mcapi;
		$this->storemanager 			= $storeManager;
		$this->resource 				= $resourceConnection;
		$this->objectmanager 		= $objectManager;
    }

    public function execute(EventObserver $observer)
    {
		//database connection
		$this->connection = $this->resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);

		// Set vars
		$tn__mc_api_queue 						= $this->resource->getTableName('mc_api_queue');
		$tn__mc_api_pages 						= $this->resource->getTableName('mc_api_pages');

		// Get table names
		$tn__sales_flat_quote 					= $this->resource->getTableName('quote');
		$tn__sales_flat_order 					= $this->resource->getTableName('sales_order');
		$tn__sales_flat_order_item 				= $this->resource->getTableName('sales_order_item');
		$tn__sales_flat_quote_item 				= $this->resource->getTableName('quote_item');
		$tn__catalog_category_product 			= $this->resource->getTableName('catalog_category_product');
		$tn__catalog_category_entity_varchar 	= $this->resource->getTableName('catalog_category_entity_varchar');
		$tn__eav_entity_type 					= $this->resource->getTableName('eav_entity_type');
		$tn__catalog_category_entity 			= $this->resource->getTableName('catalog_category_entity');

		$this->mcapi->APIWebsiteID 				= $observer->getWebsite();
      	$this->mcapi->APIStoreID 				= $observer->getStore();
		$this->mcapi->APIKey 					= $this->helper->getConfig('mailcampaignsapi/general/api_key', $this->mcapi->APIStoreID);
  		$this->mcapi->APIToken 					= $this->helper->getConfig('mailcampaignsapi/general/api_token', $this->mcapi->APIStoreID);

  		$this->mcapi->ImportCustomersHistory 	= $this->helper->getConfig('mailcampaignshistoricalsync/general/import_customers_history', $this->mcapi->APIStoreID);
		$this->mcapi->ImportOrdersHistory 		= $this->helper->getConfig('mailcampaignshistoricalsync/general/import_order_history', $this->mcapi->APIStoreID);
		$this->mcapi->ImportProductsHistory 		= $this->helper->getConfig('mailcampaignshistoricalsync/general/import_products_history', $this->mcapi->APIStoreID);
		$this->mcapi->ImportMailinglistHistory 	= $this->helper->getConfig('mailcampaignshistoricalsync/general/import_mailing_list_history', $this->mcapi->APIStoreID);
		$this->mcapi->ImportOrderProductsHistory = $this->helper->getConfig('mailcampaignshistoricalsync/general/import_order_history', $this->mcapi->APIStoreID);

		/* Customers */
		if ($this->mcapi->ImportCustomersHistory == 1)
		{
			$sql = "DELETE FROM ".$tn__mc_api_pages." WHERE collection = 'customer/customer' AND store_id = ".$this->mcapi->APIStoreID."";
			$this->resource->getConnection()->query($sql);
		
			// drop tables in mailcampaigns
			$this->mcapi->Call("reset_magento_tables", array("collection" => "customer/customer"), 0);

			$sql = "INSERT INTO `".$tn__mc_api_pages."` SET
					collection = 'customer/customer',
					datetime = ".time().",
					page = 1,
					store_id = ".$this->mcapi->APIStoreID."
					";
			$this->resource->getConnection()->query($sql);

			// Update progress
			$customersCollection = $this->objectmanager->create('Magento\Customer\Model\Customer')->setStoreId($this->mcapi->APIStoreID)->getCollection();
			$customersCollection->addAttributeToSelect('*');
			$customersCollection->setPageSize(100);
			$pages = $customersCollection->getLastPageNumber();

			$mc_import_data = array("store_id" => $this->mcapi->APIStoreID, "collection" => 'customer/customer', "page" => 1, "total" => (int)$pages, "datetime" => time(), "finished" => 0);
			$this->mcapi->Call("update_magento_progress", $mc_import_data);
		}

		/* Orders */
		if ($this->mcapi->ImportOrdersHistory == 1)
		{
			$sql = "DELETE FROM ".$tn__mc_api_pages." WHERE collection = 'sales/order' AND store_id = ".$this->mcapi->APIStoreID."";
			$this->resource->getConnection()->query($sql);
			$sql = "DELETE FROM ".$tn__mc_api_pages." WHERE collection = 'sales/order/products' AND store_id = ".$this->mcapi->APIStoreID."";
			$this->resource->getConnection()->query($sql);
			
			// drop tables in mailcampaigns
			$this->mcapi->Call("reset_magento_tables", array("collection" => "sales/order"), 0);
			$this->mcapi->Call("reset_magento_tables", array("collection" => "sales/order/products"), 0);

			$sql = "INSERT INTO `".$tn__mc_api_pages."` SET
					collection = 'sales/order',
					datetime = ".time().",
					page = 1,
					store_id = ".$this->mcapi->APIStoreID."
					";
			$this->resource->getConnection()->query($sql);

			$sql = "INSERT INTO `".$tn__mc_api_pages."` SET
				collection = 'sales/order/products',
				datetime = ".time().",
				page = 1,
				store_id = ".$this->mcapi->APIStoreID."
				";
			$this->resource->getConnection()->query($sql);

			// Update progress
			$ordersCollection = $this->objectmanager->create('Magento\Sales\Model\Order')->setStoreId($this->mcapi->APIStoreID)->getCollection();
			$ordersCollection->setPageSize(100);
			$pages = $ordersCollection->getLastPageNumber();

			$mc_import_data = array("store_id" => $this->mcapi->APIStoreID, "collection" => 'sales/order', "page" => 1, "total" => (int)$pages, "datetime" => time(), "finished" => 0);
			$this->mcapi->Call("update_magento_progress", $mc_import_data);

			$pagesize = 250;
			$sql        = "SELECT COUNT(*) AS pages FROM `".$tn__sales_flat_order."` AS o INNER JOIN ".$tn__sales_flat_order_item." AS oi ON oi.order_id = o.entity_id WHERE o.store_id = ".$this->mcapi->APIStoreID." OR o.store_id = 0";
			$pages 		= ceil($this->connection->fetchOne($sql) / $pagesize);
			if ($pages == 0) $pages = 1;

			$mc_import_data = array("store_id" => $this->mcapi->APIStoreID, "collection" => 'sales/order/products', "page" => 1, "total" => (int)$pages, "datetime" => time(), "finished" => 0);
			$this->mcapi->Call("update_magento_progress", $mc_import_data);
		}
		
		/* Products */
		if ($this->mcapi->ImportProductsHistory == 1)
		{
			$sql = "DELETE FROM ".$tn__mc_api_pages." WHERE collection = 'catalog/product' AND store_id = ".$this->mcapi->APIStoreID."";
			$this->resource->getConnection()->query($sql);
		
			// drop tables in mailcampaigns
			$this->mcapi->Call("reset_magento_tables", array("collection" => "catalog/product"), 0);

			$sql = "INSERT INTO `".$tn__mc_api_pages."` SET
					collection = 'catalog/product',
					datetime = ".time().",
					page = 1,
					store_id = ".$this->mcapi->APIStoreID."
					";
			$this->resource->getConnection()->query($sql);

			// Update progress
			$productsCollection = $this->objectmanager->create('Magento\Catalog\Model\Product')->setStoreId($this->mcapi->APIStoreID)->getCollection();
			$productsCollection->setPageSize(10);
			$pages = $productsCollection->getLastPageNumber();

			$mc_import_data = array("store_id" => $this->mcapi->APIStoreID, "collection" => 'catalog/product', "page" => 1, "total" => (int)$pages, "datetime" => time(), "finished" => 0);
			$this->mcapi->Call("update_magento_progress", $mc_import_data);
		}

		/* Subscribers */
		if ($this->mcapi->ImportMailinglistHistory == 1)
		{
			$sql = "DELETE FROM ".$tn__mc_api_pages." WHERE collection = 'newsletter/subscriber_collection' AND store_id = ".$this->mcapi->APIStoreID."";
			$this->resource->getConnection()->query($sql);
		
			// drop tables in mailcampaigns
			$this->mcapi->Call("reset_magento_tables", array("collection" => "newsletter/subscriber_collection"), 0);

			$sql = "INSERT INTO `".$tn__mc_api_pages."` SET
					collection = 'newsletter/subscriber_collection',
					datetime = ".time().",
					page = 1,
					store_id = ".$this->mcapi->APIStoreID."
					";
			$this->resource->getConnection()->query($sql);

			// Update progress
			$mailinglistCollection = $this->objectmanager->create('Magento\Newsletter\Model\Subscriber')->setStoreId($this->mcapi->APIStoreID)->getCollection();
			$mailinglistCollection->setPageSize(100);
			$pages = $mailinglistCollection->getLastPageNumber();

			$mc_import_data = array("store_id" => $this->mcapi->APIStoreID, "collection" => 'newsletter/subscriber_collection', "page" => 1, "total" => (int)$pages, "datetime" => time(), "finished" => 0);
			$this->mcapi->Call("update_magento_progress", $mc_import_data);
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
