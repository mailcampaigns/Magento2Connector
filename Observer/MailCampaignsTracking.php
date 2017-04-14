<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

class MailCampaignsTracking implements ObserverInterface
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
		$this->version 		= '2.0.4';
		$this->logger 		= $logger;
		$this->helper 		= $dataHelper;
		$this->mcapi 		= $mcapi;
		$this->storemanager 	= $storeManager;
    }

    public function execute(EventObserver $observer)
    {
		// do nothing, reserved for future use
    }
}