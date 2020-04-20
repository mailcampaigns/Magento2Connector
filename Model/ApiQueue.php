<?php

namespace MailCampaigns\Magento2Connector\Model;

use Magento\Framework\Model\AbstractModel;
use MailCampaigns\Magento2Connector\Api\ApiQueueInterface;

class ApiQueue extends AbstractModel implements ApiQueueInterface
{
    /** @var int Number of seconds to look back for entries in queue by default. */
    const DEFAULT_LOOKBACK_TIME = 300;

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
    public function getStreamData()
    {
        return $this->_getData('stream_data');
    }

    /**
     * @inheritDoc
     */
    public function setStreamData($streamData)
    {
        $this->setData('stream_data', $streamData);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasError()
    {
        return 1 === (int)$this->_getData('error');
    }

    /**
     * @inheritDoc
     */
    public function setHasError($hasError)
    {
        $this->setData('error', $hasError ? 1 : 0);
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\ApiQueue::class);

        // Set defaults.
        $this
            ->setDatetime(time())
            ->setHasError(0);
    }
}
