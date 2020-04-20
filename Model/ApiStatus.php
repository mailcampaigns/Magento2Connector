<?php

namespace MailCampaigns\Magento2Connector\Model;

use LogicException;
use Magento\Framework\Model\AbstractModel;
use MailCampaigns\Magento2Connector\Api\ApiStatusInterface;
use MailCampaigns\Magento2Connector\Model\ResourceModel;

class ApiStatus extends AbstractModel implements ApiStatusInterface
{
    public const TYPE_ORDER_CRON = 'order_cron';
    public const TYPE_PRODUCT_CRON = 'product_cron';
    public const TYPE_QUOTE_CRON = 'quote_cron';

    /**
     * @var array Possible types.
     */
    public static $types = [
        self::TYPE_ORDER_CRON,
        self::TYPE_PRODUCT_CRON,
        self::TYPE_QUOTE_CRON
    ];

    /**
     * @inheritDoc
     */
    public function getDatetime()
    {
        return $this->_getData('datetime');
    }

    /**
     * @inheritDoc
     */
    public function setDatetime($ts)
    {
        $this->setData('datetime', (int)$ts);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getType()
    {
        return $this->_getData('type');
    }

    /**
     * @inheritDoc
     */
    public function setType($type)
    {
        if (!in_array($type, self::$types, true)) {
            throw new LogicException('Invalid type! Use one of the constants.');
        }

        $this->setData('type', $type);

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\ApiStatus::class);

        // Set datetime to now by default.
        $this->setDatetime(time());
    }
}
