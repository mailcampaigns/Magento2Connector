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
	protected $mcapi;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\Magento\Framework\App\ResourceConnection $Resource,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
        Logger $logger
    ) {
		$this->resource 		= $Resource;
		$this->logger 		= $logger;
		$this->helper 		= $dataHelper;
		$this->mcapi 		= $mcapi;
		$this->storemanager 	= $storeManager;
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
				$quote_data = $observer->getEvent()->getQuote()->getData();
							
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
					$this->mcapi->DirectOrQueueCall("update_magento_abandonded_cart_quotes", $data);
				}
				
				/*
				// abandonded carts quote items
				$sql        = "SELECT q.entity_id as quote_id, p.product_id, p.store_id, p.item_id, p.qty, p.price
				FROM `".$tn__sales_flat_quote."` AS q
				LEFT JOIN `".$tn__sales_flat_order."` AS o ON o.quote_id = q.entity_id
				INNER JOIN ".$tn__sales_flat_quote_item." AS p ON p.quote_id = q.entity_id
				WHERE
				q.entity_id = ".$quote_id."
				ORDER BY  `q`.`updated_at` DESC";
				$rows       = $this->connection->fetchAll($sql);
				
				$data = array(); $i = 0;
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
					$this->mcapi->DirectOrQueueCall("delete_magento_abandonded_cart_products", $data);
					$this->mcapi->DirectOrQueueCall("update_magento_abandonded_cart_products", $data);	
				}
				*/
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