<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

class SynchronizeQuoteUpdateItem implements ObserverInterface
{
    protected $logger;
	protected $helper;
	protected $storemanager;
	protected $taxhelper;
	protected $mcapi;
	protected $productrepository;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\Catalog\Helper\Data $taxHelper,
        Logger $logger
    ) {
		$this->logger 				= $logger;
		$this->helper 				= $dataHelper;
		$this->mcapi 				= $mcapi;
		$this->storemanager 			= $storeManager;
		$this->taxhelper 			= $taxHelper;
		$this->productrepository	= $productRepository;
    }

    public function execute(EventObserver $observer)
    {		
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
				$quote_data 		= $observer->getItem();
				
				$quote_id   	= $quote_data["quote_id"];
				$item_id   		= $quote_data["item_id"];
				$store_id   	= $quote_data["store_id"];
				$product_id 		= $quote_data["product_id"];
				$qty			= $quote_data["qty"];
				$price			= $quote_data["price"];
				
				// Get product
				$product = $this->productrepository->getById($product_id);
				
				// Get Price Incl Tax
				$price = $this->taxhelper->getTaxPrice($product, $price, true, NULL, NULL, NULL, $this->mcapi->APIStoreID, NULL, true);
								
				// add abandonded carts quote items
				if ($item_id > 0)
				{
					$data = array("item_id" => $item_id, "store_id" => $store_id, "quote_id" => $quote_id, "qty" => $qty, "price" => $price, "product_id" => $product_id);
					$this->mcapi->DirectOrQueueCall("update_magento_abandonded_cart_products", $data);
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