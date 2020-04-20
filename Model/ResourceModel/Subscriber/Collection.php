<?php

namespace MailCampaigns\Magento2Connector\Model\ResourceModel\Subscriber;

use Magento\Framework\Data\Collection as MagentoCollection;
use Magento\Newsletter\Model;

class Collection extends MagentoCollection
{
    protected function _construct()
    {
        $this->setItemObjectClass(Model\Subscriber::class);
    }
}
