<?php

namespace MailCampaigns\Magento2Connector\Observer\Configuration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Message;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;

use MailCampaigns\Magento2Connector\Observer\AbstractObserver;

abstract class AbstractHistoricalSyncConfigObserver extends AbstractObserver
{
    /**
     * @var Message\ManagerInterface
     */
    protected $messageManager;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper,

        Message\ManagerInterface $messageManager
    ) {
        parent::__construct($scopeConfig, $apiHelper);
        $this->messageManager = $messageManager;
    }

    /**
     * @inheritDoc
     */
    abstract public function execute(EventObserver $observer);
}
