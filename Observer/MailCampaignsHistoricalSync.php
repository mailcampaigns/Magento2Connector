<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

class MailCampaignsHistoricalSync implements ObserverInterface
{
    protected $logger;
	protected $version;
	protected $helper;
	protected $storemanager;
	protected $mcapi;
	protected $connection;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\App\ResourceConnection $resourceConnection,
        Logger $logger
    ) {
		$this->version 		= '2.0.0';
		$this->logger 		= $logger;
		$this->helper 		= $dataHelper;
		$this->mcapi 		= $mcapi;
		$this->storemanager 	= $storeManager;
		$this->connection 	= $resourceConnection;
    }

    public function execute(EventObserver $observer)
    {	
		// Set vars
		$tn__mc_api_queue 						= $this->connection->getTableName('mc_api_queue');
		$tn__mc_api_pages 						= $this->connection->getTableName('mc_api_pages');
		
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
		$sql = "DELETE FROM ".$tn__mc_api_pages." WHERE collection = 'customer/customer' AND store_id = ".$this->mcapi->APIStoreID."";
		$this->connection->getConnection()->query($sql);
		if ($this->mcapi->ImportCustomersHistory == 1)
		{	
			$sql = "INSERT INTO `".$tn__mc_api_pages."` SET 
					collection = 'customer/customer',
					datetime = ".time().",
					page = 1,
					store_id = ".$this->mcapi->APIStoreID."
					";
			$this->connection->getConnection()->query($sql);
		}
		
		/* Orders */
		$sql = "DELETE FROM ".$tn__mc_api_pages." WHERE collection = 'sales/order' AND store_id = ".$this->mcapi->APIStoreID."";
		$this->connection->getConnection()->query($sql);
		if ($this->mcapi->ImportOrdersHistory == 1)
		{	
			$sql = "INSERT INTO `".$tn__mc_api_pages."` SET 
					collection = 'sales/order',
					datetime = ".time().",
					page = 1,
					store_id = ".$this->mcapi->APIStoreID."
					";
			$this->connection->getConnection()->query($sql);
			
			$sql = "INSERT INTO `".$tn__mc_api_pages."` SET 
				collection = 'sales/order/products',
				datetime = ".time().",
				page = 1,
				store_id = ".$this->mcapi->APIStoreID."
				";
			$this->connection->getConnection()->query($sql);
		}
		
		/* Products */
		$sql = "DELETE FROM ".$tn__mc_api_pages." WHERE collection = 'catalog/product' AND store_id = ".$this->mcapi->APIStoreID."";
		$this->connection->getConnection()->query($sql);
		if ($this->mcapi->ImportProductsHistory == 1)
		{
			$sql = "INSERT INTO `".$tn__mc_api_pages."` SET 
					collection = 'catalog/product',
					datetime = ".time().",
					page = 1,
					store_id = ".$this->mcapi->APIStoreID."
					";
			$this->connection->getConnection()->query($sql);
		}

		/* Subscribers */
		$sql = "DELETE FROM ".$tn__mc_api_pages." WHERE collection = 'newsletter/subscriber_collection' AND store_id = ".$this->mcapi->APIStoreID."";
		$this->connection->getConnection()->query($sql);		
		if ($this->mcapi->ImportMailinglistHistory == 1)
		{
			$sql = "INSERT INTO `".$tn__mc_api_pages."` SET 
					collection = 'newsletter/subscriber_collection',
					datetime = ".time().",
					page = 1,
					store_id = ".$this->mcapi->APIStoreID."
					";
			$this->connection->getConnection()->query($sql);
		}
    }
}