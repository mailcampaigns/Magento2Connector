<?php

namespace MailCampaigns\Magento2Connector\Observer\Configuration;

use Exception;
use Magento\Framework\Event\Observer as EventObserver;
use MailCampaigns\Magento2Connector\Observer\AbstractObserver;

class ApiConfigObserver extends AbstractObserver
{
    public function execute(EventObserver $observer)
    {
        try {
            $storeId = $observer->getDataByKey('store');
            $websiteId = $observer->getDataByKey('website');

            // Push data to MailCampaigns Api.
            $this->apiHelper->saveSettings($storeId, $websiteId);
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }
    }
}