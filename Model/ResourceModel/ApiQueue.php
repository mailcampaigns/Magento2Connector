<?php

namespace MailCampaigns\Magento2Connector\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Model\ResourceModel;

class ApiQueue extends AbstractDb
{
    /**
     * @var ResourceModel\ApiQueue\CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var ResourceModel\ApiStatus
     */
    protected $apiStatusResourceModel;

    /**
     * @inheritDoc
     */
    public function __construct(
        Context $context,
        LogHelperInterface $logHelper,
        ResourceModel\ApiQueue\CollectionFactory $collectionFactory,
        ResourceModel\ApiStatus $apiStatusResourceModel,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->_logger = $logHelper->getLogger();
        $this->collectionFactory = $collectionFactory;
        $this->apiStatusResourceModel = $apiStatusResourceModel;
    }

    /**
     * Returns total number of calls in queue.
     *
     * @return int
     */
    public function getQueueSize(): int
    {
        return $this->collectionFactory->create()->count();
    }

    /**
     * Returns number of failed queued calls.
     *
     * @return int
     */
    public function getErrorCount(): int
    {
        return $this->collectionFactory->create()
            ->addFieldToFilter('error', ['neq' => 0])
            ->count();
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('mc_api_queue', 'id');
    }
}
