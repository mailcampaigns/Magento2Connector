<?php

namespace MailCampaigns\Magento2Connector\Cron;

use Exception;
use Magento\Cron\Model\Schedule;

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
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }
    }
}
