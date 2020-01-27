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
	protected $quoterepository;
	protected $productrepository;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\Magento\Framework\App\ResourceConnection $Resource,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Magento\Quote\Model\QuoteRepository $quoteRepository,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\Catalog\Helper\Data $taxHelper,
        Logger $logger
    ) {
		$this->resource 				= $Resource;
		$this->logger 				= $logger;
		$this->helper 				= $dataHelper;
		$this->mcapi 				= $mcapi;
		$this->storemanager 			= $storeManager;
		$this->taxhelper 			= $taxHelper;
		$this->objectmanager		= $objectManager;
		$this->quoterepository 		= $quoteRepository;
		$this->productrepository	= $productRepository;
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
		//$this->mcapi->ImportQuotes 		= $this->helper->getConfig('mailcampaignsrealtimesync/general/import_quotes',$this->mcapi->APIStoreID);	

  		//if ($this->mcapi->ImportQuotes == 1)
		//{		
			try
			{				
				// Retrieve the quote being updated from the event observer
				$quote = $observer->getEvent()->getQuote();
				$quote_data = $quote->getData();
							
				$quote_id = $quote_data["entity_id"];
				$store_id = $quote_data["store_id"];
				
				if ($this->helper->getConfig('mailcampaignstracking/general/tracking_quote_session',$this->mcapi->APIStoreID) == 1)
				{
					$session = $this->objectmanager->get('Magento\Customer\Model\Session');
					if ((int)$session->getCustomer()->getId() == 0 && isset($_COOKIE["mc_subscriber_email"]))
					{
						$mc_subscriber_email = $_COOKIE["mc_subscriber_email"];
						if ($mc_subscriber_email != "" && ((isset($quote_data["customer_email"]) && $quote_data["customer_email"] == "") || !isset($quote_data["customer_email"])))
						{
							$quote->setCustomerEmail($mc_subscriber_email);
							$this->quoterepository->save($quote);
						}
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
		//}	
    }
}