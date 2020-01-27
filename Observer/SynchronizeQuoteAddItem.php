<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

class SynchronizeQuoteAddItem implements ObserverInterface
{
    protected $logger;
	protected $helper;
	protected $storemanager;
	protected $taxhelper;
	protected $mcapi;
	protected $productrepository;
	protected $quoterepository;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\Quote\Model\QuoteRepository $quoteRepository,
		\Magento\Catalog\Helper\Data $taxHelper,
        Logger $logger
    ) {
		$this->logger 				= $logger;
		$this->helper 				= $dataHelper;
		$this->mcapi 				= $mcapi;
		$this->storemanager 			= $storeManager;
		$this->taxhelper 			= $taxHelper;
		$this->quoterepository 		= $quoteRepository;
		$this->productrepository	= $productRepository;
    }

    public function execute(EventObserver $observer)
    {	
		try
		{				
			// Retrieve the quote being updated from the event observer
			$quote = $observer->getEvent()->getQuote();
			$quote_data = $quote->getData();
						
			$quote_id = $quote_data["entity_id"];
			$store_id = $quote_data["store_id"];
			
			if ($this->helper->getConfig('mailcampaignstracking/general/tracking_quote_session', $store_id) == 1)
			{
				$session = $this->objectmanager->get('Magento\Customer\Model\Session');
				if ((int)$session->getCustomer()->getId() == 0)
				{
					$mc_subscriber_email = $_COOKIE["mc_subscriber_email"];
					if (isset($quote_data["customer_email"]) && $mc_subscriber_email != "" && $quote_data["customer_email"] == "")
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
    }
}