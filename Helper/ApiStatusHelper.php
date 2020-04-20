<?php

namespace MailCampaigns\Magento2Connector\Helper;

use InvalidArgumentException;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Data\Collection;
use MailCampaigns\Magento2Connector\Api\ApiStatusHelperInterface;
use MailCampaigns\Magento2Connector\Model;
use MailCampaigns\Magento2Connector\Model\ResourceModel;
use MailCampaigns\Magento2Connector\Model\ResourceModel\ApiStatus\CollectionFactory;

class ApiStatusHelper extends AbstractHelper implements ApiStatusHelperInterface
{
    /**
     * @var ResourceModel\ApiStatus
     */
    protected $apiStatusResourceModel;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Model\ApiStatusFactory
     */
    protected $modelFactory;

    public function __construct(
        Context $context,
        ResourceModel\ApiStatus $apiStatusResourceModel,
        CollectionFactory $collectionFactory,
        Model\ApiStatusFactory $modelFactory
    ) {
        parent::__construct($context);
        $this->apiStatusResourceModel = $apiStatusResourceModel;
        $this->collectionFactory = $collectionFactory;
        $this->modelFactory = $modelFactory;
    }

    /**
     * @inheritDoc
     */
    public function getSyncStart(?string $type = null): int
    {
        $collection = $this->collectionFactory->create();

        $collection
            ->addFieldToSelect('datetime')
            ->setOrder('datetime', Collection::SORT_ORDER_DESC)
            ->setPageSize(1);

        if ($type) {
            $collection->addFilter('type', $type);
        }

        $apiStatus = $collection->getFirstItem();

        // In case no status was found, use a default.
        if (!$apiStatus instanceof Model\ApiStatus) {
            return time() - Model\ApiQueue::DEFAULT_LOOKBACK_TIME;
        }

        return $apiStatus->getDatetime();
    }

    /**
     * @inheritDoc
     */
    public function updateStatus(string $type): ApiStatusHelperInterface
    {
        if (!in_array($type, Model\ApiStatus::$types)) {
            throw new InvalidArgumentException('Invalid cron type!');
        }

        // Remove old record(s).
        $this->apiStatusResourceModel->removeByType($type);

        // Create a new one for the given type.
        /** @var Model\ApiStatus $newStatus */
        $newStatus = $this->modelFactory->create()->setType($type);

        // Save new entry to database.
        $this->apiStatusResourceModel->save($newStatus);

        return $this;
    }
}
