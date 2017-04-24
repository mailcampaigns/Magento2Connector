<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

class SynchronizeQuoteDeleteItem implements ObserverInterface
{
    protected $logger;
	protected $helper;
	protected $storemanager;
	protected $mcapi;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
        Logger $logger
    ) {
		$this->logger 		= $logger;
		$this->helper 		= $dataHelper;
		$this->mcapi 		= $mcapi;
		$this->storemanager 	= $storeManager;
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
			$quote_data = $observer->getEvent()->getQuoteItem()->getData();
			$quote_id   = $quote_data["quote_id"];
			$item_id   	= $quote_data["item_id"];
			$store_id   = $quote_data["store_id"];
			
			// delete abandonded carts quote items
			$data = array("item_id" => $item_id, "store_id" => $store_id, "quote_id" => $quote_id);
			$this->mcapi->QueueAPICall("delete_magento_abandonded_cart_product", $data);	
		}
    }
}