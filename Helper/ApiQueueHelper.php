<?php

namespace MailCampaigns\Magento2Connector\Helper;

use InvalidArgumentException;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use MailCampaigns\Magento2Connector\Api\ApiQueueHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiQueueInterface;
use MailCampaigns\Magento2Connector\Model\ApiQueue;
use MailCampaigns\Magento2Connector\Model\ApiQueueFactory;
use MailCampaigns\Magento2Connector\Model\ResourceModel;
use MailCampaigns\Magento2Connector\Model\ResourceModel\ApiQueue\Collection;
use MailCampaigns\Magento2Connector\Model\ResourceModel\ApiQueue\CollectionFactory;

class ApiQueueHelper extends AbstractHelper implements ApiQueueHelperInterface
{
    /**
     * @var ResourceModel\ApiQueue
     */
    protected $resourceModel;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var ApiQueueFactory
     */
    protected $apiQueueFactory;

    public function __construct(
        Context $context,
        ResourceModel\ApiQueue $resourceModel,
        CollectionFactory $collectionFactory,
        ApiQueueFactory $apiQueueFactory
    ) {
        parent::__construct($context);
        $this->resourceModel = $resourceModel;
        $this->collectionFactory = $collectionFactory;
        $this->apiQueueFactory = $apiQueueFactory;
    }

    /**
     * @inheritDoc
     */
    public function getQueueSize(): int
    {
        return $this->resourceModel->getQueueSize();
    }

    /**
     * @inheritDoc
     */
    public function getErrorCount(): int
    {
        return $this->resourceModel->getErrorCount();
    }

    /**
     * @inheritDoc
     */
    public function getQueuedCalls(): Collection
    {
        $collection = $this->collectionFactory->create();

        return $collection
            ->addFieldToFilter('error', ['eq' => 0])
            ->setOrder('id', 'ASC')
            ->setPageSize(2000)
            ->loadWithFilter();
    }

    /**
     * @inheritDoc
     */
    public function validateQueuedCallData(array $data): ApiQueueHelperInterface
    {
        if (!array_key_exists('api_key', $data) || !array_key_exists('api_token', $data)
            || !array_key_exists('method', $data)) {
            throw new InvalidQueuedCallException;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function add(string $streamData): ApiQueueHelperInterface
    {
        // Create a new queue entry.
        $apiQueue = $this->apiQueueFactory->create();
        $apiQueue->setStreamData($streamData);

        // Save the new entry.
        $this->resourceModel->save($apiQueue);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function save(ApiQueueInterface $apiQueue): ApiQueueHelperInterface
    {
        if (!$apiQueue instanceof ApiQueue) {
            throw new InvalidArgumentException('Expected ApiQueue (model) instance.');
        }

        $this->resourceModel->save($apiQueue);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function removeFromQueue(ApiQueueInterface $apiQueue): ApiQueueHelperInterface
    {
        if (!$apiQueue instanceof ApiQueue) {
            throw new InvalidArgumentException('Expected ApiQueue (model) instance.');
        }

        $this->resourceModel->delete($apiQueue);

        return $this;
    }
}
