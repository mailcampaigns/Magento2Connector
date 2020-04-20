<?php

namespace MailCampaigns\Magento2Connector\Api;

use Magento\Framework\DataObject;

interface SubscriberManagementInterface
{
    /**
     * @param string $email
     * @return DataObject
     */
    public function subscribe(string $email);

    /**
     * @param int[] $storeIds
     * @param string $subscriberEmail
     * @param string $modifiedSince
     * @param string $sortField
     * @param string $sortDirection
     * @param int $pageNumber
     * @param int $pageSize
     * @return array
     */
    public function search(
        $storeIds = [],
        $subscriberEmail = '',
        $modifiedSince = '',
        $sortField = '',
        $sortDirection = '',
        $pageNumber = 0,
        $pageSize = 0
    );
}
