<?php

namespace MailCampaigns\Magento2Connector\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ObserverInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Model\Logger;

abstract class AbstractObserver implements ObserverInterface
{
    /**
     * @var ApiHelperInterface
     */
    protected $apiHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->apiHelper = $apiHelper;
        $this->logger = $logHelper->getLogger();
    }
}
