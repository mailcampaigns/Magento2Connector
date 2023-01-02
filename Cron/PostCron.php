<?php

namespace MailCampaigns\Magento2Connector\Cron;

use Exception;
use Magento\Cron\Model\Schedule;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;

class PostCron extends AbstractCron
{
    /**
     * @inheritDoc
     */
    public function execute(Schedule $schedule): void
    {
        try {
            // Send queue status.
            $this->apiHelper->reportQueueStatus();

            // Process queue.
            $this->apiHelper->processQueue();
        } catch (ApiCredentialsNotSetException $e) {
        } catch (Exception $e) {
            throw $e;
        }
    }
}
