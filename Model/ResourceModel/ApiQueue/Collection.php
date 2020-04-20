<?php

namespace MailCampaigns\Magento2Connector\Model\ResourceModel\ApiQueue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MailCampaigns\Magento2Connector\Model;
use MailCampaigns\Magento2Connector\Model\ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model\ApiQueue::class, ResourceModel\ApiQueue::class);
    }
}
