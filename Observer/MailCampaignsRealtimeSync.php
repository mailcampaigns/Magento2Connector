<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

class MailCampaignsRealtimeSync implements ObserverInterface
{
    protected $logger;
	protected $version;
	protected $helper;
	protected $storemanager;
	protected $mcapi;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
        Logger $logger
    ) {
		$this->version 		= '2.0.32';
		$this->logger 		= $logger;
		$this->helper 		= $dataHelper;
		$this->mcapi 		= $mcapi;
		$this->storemanager 	= $storeManager;
    }

    public function execute(EventObserver $observer)
    {
		// do nothing, reserved for future use

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
