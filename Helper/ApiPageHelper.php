<?php

namespace MailCampaigns\Magento2Connector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Model\AbstractModel;
use MailCampaigns\Magento2Connector\Api\ApiPageHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiPageInterface;
use MailCampaigns\Magento2Connector\Model\ApiPage;
use MailCampaigns\Magento2Connector\Model\ApiPageFactory;
use MailCampaigns\Magento2Connector\Model\ResourceModel;
use MailCampaigns\Magento2Connector\Model\ResourceModel\ApiPage\Collection;
use MailCampaigns\Magento2Connector\Model\ResourceModel\ApiPage\CollectionFactory;

class ApiPageHelper extends AbstractHelper implements ApiPageHelperInterface
{
    /**
     * @var ResourceModel\ApiPage
     */
    protected $resourceModel;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var ApiPageFactory
     */
    protected $apiPageFactory;

    public function __construct(
        Context $context,
        ResourceModel\ApiPage $resourceModel,
        CollectionFactory $collectionFactory,
        ApiPageFactory $apiPageFactory
    ) {
        parent::__construct($context);
        $this->resourceModel = $resourceModel;
        $this->collectionFactory = $collectionFactory;
        $this->apiPageFactory = $apiPageFactory;
    }

    /**
     * @inheritDoc
     */
    public function savePage(ApiPageInterface $page): ApiPageHelperInterface
    {
        if ($page instanceof AbstractModel) {
            $this->resourceModel->save($page);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function deletePage(ApiPageInterface $page): ApiPageHelperInterface
    {
        if ($page instanceof AbstractModel) {
            $this->resourceModel->delete($page);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function createPage(): ApiPageInterface
    {
        /** @var ApiPage $newPage */
        $newPage = $this->apiPageFactory->create();

        return $newPage;
    }

    /**
     * @inheritDoc
     */
    public function deleteByCollectionName(string $collectionName, int $storeId): ApiPageHelperInterface
    {
        $this->resourceModel->deleteByCollectionName($collectionName, $storeId);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getPages(): Collection
    {
        // Create a new collection instance.
        $collection = $this->collectionFactory->create();

        return $collection->setOrder('id', Collection::SORT_ORDER_ASC);
    }
}
