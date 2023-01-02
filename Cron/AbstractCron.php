<?php

namespace MailCampaigns\Magento2Connector\Cron;

use Magento\Cron\Model\Schedule;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiQueueHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiStatusHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiPageHelperInterface;

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

    public function __construct(
        ApiHelperInterface $apiHelper
    ) {
        $this->apiHelper = $apiHelper;
        $this->apiStatusHelper = $apiHelper->getStatusHelper();
        $this->apiQueueHelper = $apiHelper->getQueueHelper();
        $this->apiPageHelper = $apiHelper->getPageHelper();
    }

    /**
     * @param Schedule $schedule
     */
    abstract public function execute(Schedule $schedule): void;
}
