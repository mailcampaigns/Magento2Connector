<?php

namespace MailCampaigns\Magento2Connector\Helper;

use MailCampaigns\Magento2Connector\Api\LoggerProviderInterface;
use MailCampaigns\Magento2Connector\Model\Logger;

/**
 * This helper provides the logger for this module.
 */
class LoggerProvider implements LoggerProviderInterface
{
    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function getInstance(): Logger
    {
        return $this->logger;
    }
}
