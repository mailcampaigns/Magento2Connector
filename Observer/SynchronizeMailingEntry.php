<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

class SynchronizeMailingEntry implements ObserverInterface
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
		$this->mcapi->ImportMailinglist 	= $this->helper->getConfig('mailcampaignsrealtimesync/general/import_mailing_list',$this->mcapi->APIStoreID);	

  		if ($this->mcapi->ImportMailinglist == 1)
		{
			$event = $observer->getEvent();
			$subscriber = $event->getDataObject();
			$subscriber_tmp = (array)$subscriber->getData();
		
			$subscriber_data = array();
			$subscriber_data[0] = $subscriber_tmp;	
					
			$this->mcapi->QueueAPICall("update_magento_mailing_list", $subscriber_data);
		}
    }
}