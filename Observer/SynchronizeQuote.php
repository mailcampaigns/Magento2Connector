<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

class SynchronizeQuote implements ObserverInterface
{
    protected $logger;
	protected $resource;
	protected $connection;
	protected $helper;
	protected $storemanager;
	protected $taxhelper;
	protected $mcapi;
	protected $objectmanager;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\Magento\Framework\App\ResourceConnection $Resource,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Magento\Catalog\Helper\Data $taxHelper,
        Logger $logger
    ) {
		$this->resource 			= $Resource;
		$this->logger 			= $logger;
		$this->helper 			= $dataHelper;
		$this->mcapi 			= $mcapi;
		$this->storemanager 		= $storeManager;
		$this->taxhelper 		= $taxHelper;
		$this->objectmanager	= $objectManager;
    }

    public function execute(EventObserver $observer)
    {		
		//database connection
		$this->connection = $this->resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
			
		// set vars
		$this->mcapi->APIWebsiteID 		= $observer->getWebsite();
      	$this->mcapi->APIStoreID 		= $observer->getStore(); 

		$this->mcapi->APIKey 			= $this->helper->getConfig('mailcampaignsapi/general/api_key', $this->mcapi->APIStoreID);
  		$this->mcapi->APIToken 			= $this->helper->getConfig('mailcampaignsapi/general/api_token', $this->mcapi->APIStoreID);
		$this->mcapi->ImportQuotes 		= $this->helper->getConfig('mailcampaignsrealtimesync/general/import_quotes',$this->mcapi->APIStoreID);	

  		if ($this->mcapi->ImportQuotes == 1)
		{		
			try
			{				
				// Retrieve the quote being updated from the event observer
				$quote = $observer->getEvent()->getQuote();
				$quote_data = $quote->getData();
							
				$quote_id = $quote_data["entity_id"];
				$store_id = $quote_data["store_id"];
							
				// Get table names
				$tn__sales_flat_quote 		= $this->resource->getTableName('quote');
				$tn__sales_flat_order 		= $this->resource->getTableName('sales_order');
				$tn__sales_flat_quote_item 	= $this->resource->getTableName('quote_item');
				
				// abandonded carts quotes
				$sql        = "SELECT q.*
				FROM `".$tn__sales_flat_quote."` AS q
				WHERE
				q.entity_id = ".$quote_id."
				ORDER BY  `q`.`updated_at` DESC";
				
				$data = array(); $i = 0;
				$rows       = $this->connection->fetchAll($sql);
				foreach ($rows as $row)
				{
					foreach ($row as $key => $value)
					{
						if (!is_numeric($key)) $data[$i][$key] = $value;
					}	
					
					$i++;
				}
				
				if ($i > 0)
				{
					$this->mcapi->QueueAPICall("update_magento_abandonded_cart_quotes", $data);
				}
								
				// abandonded carts quote items										
				$items = $quote->getAllItems();
				foreach ($items as $item) 
				{			
					$quote_data 	= $item->get();
					
					$quote_id   	= $quote_data["quote_id"];
					$item_id   		= $quote_data["item_id"];
					$store_id   	= $quote_data["store_id"];
					$product_id 	= $quote_data["product_id"];
					$qty			= $quote_data["qty"];
					$price			= $quote_data["price"];
					
					// Get product
					if ($product_id > 0)
					{
						$product = $this->objectmanager->create('Magento\Catalog\Model\Product')->load($product_id);
						
						// Get Price Incl Tax
						$price = $this->taxhelper->getTaxPrice($product, $price, true, NULL, NULL, NULL, $store_id, NULL, true);
										
						// add abandonded carts quote items
						$data = array("item_id" => $item_id, "store_id" => $store_id, "quote_id" => $quote_id, "qty" => $qty, "price" => $price, "product_id" => $product_id);
						$this->mcapi->QueueAPICall("update_magento_abandonded_cart_products", $data);
					}
				}
			}
			catch (\Magento\Framework\Exception\NoSuchEntityException $e)
			{
				$this->mcapi->DebugCall($e->getMessage());
			}
			catch (Exception $e)
			{
				$this->mcapi->DebugCall($e->getMessage());
			}
		}		
    }
}