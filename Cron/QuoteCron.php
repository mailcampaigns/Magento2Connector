<?php

namespace MailCampaigns\Connector\Cron;

class QuoteCron {
 
 	protected $helper;
	protected $resource;
	protected $connection;
	protected $tn__mc_api_pages;
	protected $tn__mc_api_queue;
    protected $mcapi;
    protected $subscriberfactory;
	protected $quoterepository;
    protected $productrepository;
    protected $taxhelper;
  
    public function __construct(
       	\MailCampaigns\Connector\Helper\Data $dataHelper,
        \Magento\Framework\App\ResourceConnection $Resource,
        \Magento\Newsletter\Model\SubscriberFactory $SubscriberFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Magento\Catalog\Helper\Data $taxHelper,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi
    ) {
        $this->resource 	= $Resource;
		$this->helper 		= $dataHelper;
        $this->mcapi 		= $mcapi;
        $this->subscriberfactory 	= $SubscriberFactory;
		$this->quoterepository 	= $quoteRepository;
        $this->productrepository	= $productRepository;
        $this->taxhelper 		= $taxHelper;
    }
 
    public function execute() 
	{		
		//database connection
        $this->connection = $this->resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
		
        try{	
            // Get table names
            $tn__sales_flat_quote 		= $this->resource->getTableName('quote');
            $tn__sales_flat_order 		= $this->resource->getTableName('sales_order');
            $tn__sales_flat_quote_item 	= $this->resource->getTableName('quote_item');
			$tn__mc_api_status			= $this->resource->getTableName('mc_api_status');
			
			// default time
			$last_process_time 			= time() - 300; // default
			
			// select latest time
			$sql        = "SELECT datetime FROM ".$tn__mc_api_status." WHERE type = 'quote_cron' ORDER BY datetime DESC LIMIT 1";
            $rows       = $this->connection->fetchAll($sql);
            foreach ($rows as $row) { $last_process_time = $row["datetime"]; }
			
			// delete old times
			$sql = "DELETE FROM `".$tn__mc_api_status."` WHERE type = 'quote_cron'";
			$this->connection->query($sql);
			
			// save new one
			$this->connection->insert($tn__mc_api_status, array(
				'type'   		=> 'quote_cron',
				'datetime'      => time()
			));
            
            // abandonded carts quotes
			$quote_sql        = "SELECT q.*
			FROM `".$tn__sales_flat_quote."` AS q
			WHERE q.updated_at >= '".gmdate("Y-m-d H:i:s", $last_process_time)."' OR q.created_at >= '".gmdate("Y-m-d H:i:s", $last_process_time)."'
			ORDER BY  `q`.`updated_at` DESC";
			$quote_rows       = $this->connection->fetchAll($quote_sql);
			
			foreach ($quote_rows as $quote_row)
			{
				if ($this->helper->getConfig('mailcampaignsrealtimesync/general/import_quotes', $quote_row["store_id"]))
				{
					// Set API
					$this->mcapi->APIStoreID = $quote_row["store_id"];
					$this->mcapi->APIKey 	 = $this->helper->getConfig('mailcampaignsapi/general/api_key', $this->mcapi->APIStoreID);
					$this->mcapi->APIToken 	 = $this->helper->getConfig('mailcampaignsapi/general/api_token', $this->mcapi->APIStoreID);
										
					// get quote
					$objectMan =  \Magento\Framework\App\ObjectManager::getInstance();
					$quote = $objectMan->create('Magento\Quote\Model\Quote')->load($quote_row["entity_id"]);
					
					$quote_data = $quote_row;
					$quote_data["store_id"] = $quote_row["store_id"];
					
					if(is_object($quote->getShippingAddress())) 
					{
						$address = $quote->getShippingAddress();
						
						$quote_data["BaseShippingAmount"] 			= $address->getBaseShippingAmount();
						$quote_data["BaseShippingDiscountAmount"] 	= $address->getBaseShippingDiscountAmount();
						$quote_data["BaseShippingHiddenTaxAmount"] 	= $address->getBaseShippingHiddenTaxAmount();
						$quote_data["BaseShippingInclTax"] 			= $address->getBaseShippingInclTax();
						$quote_data["BaseShippingTaxAmount"] 		= $address->getBaseShippingTaxAmount();
						
						$quote_data["ShippingAmount"] 				= $address->getShippingAmount();
						$quote_data["ShippingDiscountAmount"] 		= $address->getShippingDiscountAmount();
						$quote_data["ShippingHiddenTaxAmount"] 		= $address->getShippingHiddenTaxAmount();
						$quote_data["ShippingInclTax"] 				= $address->getShippingInclTax();
						$quote_data["ShippingTaxAmount"] 			= $address->getShippingTaxAmount();
					}
						
					// vat
					$quote_data["grand_total_vat"] = $quote_data["grand_total"] - $quote_data["subtotal"];
					$quote_data["base_grand_total_vat"] = $quote_data["base_grand_total"] - $quote_data["base_subtotal"];
					$quote_data["grand_total_with_discount_vat"] = $quote_data["grand_total"] - $quote_data["subtotal_with_discount"];
					$quote_data["base_grand_total_with_discount_vat"] = $quote_data["base_grand_total"] - $quote_data["base_subtotal_with_discount"];				
					
					// update quote
					$this->mcapi->DirectOrQueueCall("update_magento_abandonded_cart_quotes", array($quote_data));
					
					// delete products first
					$this->mcapi->DirectOrQueueCall("delete_magento_abandonded_cart_products", array("quote_id" => $quote_row["entity_id"], "store_id" => $quote_row["store_id"]));
					
					// abandonded carts quote items
					$sql        = "SELECT q.entity_id as quote_id, p.*
					FROM `".$tn__sales_flat_quote."` AS q
					INNER JOIN ".$tn__sales_flat_quote_item." AS p ON p.quote_id = q.entity_id
					WHERE q.entity_id = ".$quote_row["entity_id"]."
					ORDER BY  `q`.`updated_at` DESC";
					$rows       = $this->connection->fetchAll($sql);
					
					$i = 0;
					$quote_item_data = array(); 
					foreach ($rows as $row)
					{
						foreach ($row as $key => $value)
						{
							if (!is_numeric($key)) $quote_item_data[$i][$key] = $value;
						}
						
						$i++;
					}
				
					if ($i > 0)
					{
						// insert products
						$this->mcapi->DirectOrQueueCall("update_magento_abandonded_cart_products", $quote_item_data);
					}
				}
			}
        }
        catch (\Magento\Framework\Exception\NoSuchEntityException $e){
            $this->mcapi->DebugCall($e->getMessage());
        }
        catch (Exception $e){
            $this->mcapi->DebugCall($e->getMessage());
        }
		
		return $this;
    }
}