<?php

namespace MailCampaigns\Magento2Connector\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ObserverInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;

abstract class AbstractObserver implements ObserverInterface
{
    /**
     * @var ApiHelperInterface
     */
    protected $apiHelper;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->apiHelper = $apiHelper;
    }
}
