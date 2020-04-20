<?php

namespace MailCampaigns\Magento2Connector\Model\ResourceModel\ApiStatus;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MailCampaigns\Magento2Connector\Model\ResourceModel;
use MailCampaigns\Magento2Connector\Model;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model\ApiStatus::class, ResourceModel\ApiStatus::class);
    }
}
