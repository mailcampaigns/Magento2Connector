<?php

namespace MailCampaigns\Magento2Connector\Cron;

use Magento\Cron\Model\Schedule;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiQueueHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiStatusHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiPageHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Model\Logger;

abstract class AbstractCron
{
    /**
     * @var ApiHelperInterface
     */
    protected $apiHelper;

    /**
     * @var ApiStatusHelperInterface
     */
    protected $apiStatusHelper;

    /**
     * @var ApiQueueHelperInterface
     */
    protected $apiQueueHelper;

    /**
     * @var ApiPageHelperInterface
     */
    protected $apiPageHelper;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper
    ) {
        $this->apiHelper = $apiHelper;
        $this->apiStatusHelper = $apiHelper->getStatusHelper();
        $this->apiQueueHelper = $apiHelper->getQueueHelper();
        $this->apiPageHelper = $apiHelper->getPageHelper();
        $this->logger = $logHelper->getLogger();
    }

    /**
     * @param Schedule $schedule
     */
    abstract public function execute(Schedule $schedule): void;
}
