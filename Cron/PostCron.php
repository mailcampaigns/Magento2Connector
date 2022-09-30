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
            // Just add a debug message to the filelog.
            if (method_exists($this->logger, 'addDebug')) {
                $this->logger->addDebug($e->getMessage());
            }
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }
    }
}
