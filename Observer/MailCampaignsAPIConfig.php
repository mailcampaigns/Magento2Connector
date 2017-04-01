<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

class MailCampaignsAPIConfig implements ObserverInterface
{
    protected $logger;
	protected $version;
	protected $helper;
	protected $storemanager;
	protected $mcapi;
	protected $resource;
	protected $cron;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\App\ResourceConnection $Resource,
        Logger $logger
    ) {
		$this->version 		= '2.0.3';
		$this->logger 		= $logger;
		$this->helper 		= $dataHelper;
		$this->mcapi 		= $mcapi;
		$this->storemanager 	= $storeManager;
		$this->resource 		= $Resource;
    }

    public function execute(EventObserver $observer)
    {
		// set vars
		$this->mcapi->APIWebsiteID 		= $observer->getWebsite();
      	$this->mcapi->APIStoreID 		= $observer->getStore(); 
		$this->mcapi->APIKey 			= $this->helper->getConfig('mailcampaignsapi/general/api_key', $this->mcapi->APIStoreID);
  		$this->mcapi->APIToken 			= $this->helper->getConfig('mailcampaignsapi/general/api_token', $this->mcapi->APIStoreID);
		
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