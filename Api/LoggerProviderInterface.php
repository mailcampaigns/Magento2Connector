<?php

namespace MailCampaigns\Magento2Connector\Api;

use MailCampaigns\Magento2Connector\Model\Logger;

interface LoggerProviderInterface
{
    /**
     * @return Logger
     */
    public function getInstance(): Logger;
}
