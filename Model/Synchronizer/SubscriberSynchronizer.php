<?php

namespace MailCampaigns\Magento2Connector\Model\Synchronizer;

use InvalidArgumentException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\ScopeInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiPageInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Api\SubscriberSynchronizerInterface;
use MailCampaigns\Magento2Connector\Api\SynchronizerInterface;

class SubscriberSynchronizer extends AbstractSynchronizer implements SubscriberSynchronizerInterface
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($scopeConfig, $apiHelper, $logHelper);
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function synchronize(AbstractModel $model, ?int $storeId = null, bool $useShortTimeout = false): SynchronizerInterface
    {
        if (!$model instanceof Subscriber) {
            throw new InvalidArgumentException('Expected Subscriber model instance.');
        }

        $this->apiHelper->getClient()->setStoreId($storeId)
            ->call('update_magento_mailing_list', [$model->toArray()], true, $useShortTimeout);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function historicalSync(ApiPageInterface $page): SynchronizerInterface
    {
        parent::historicalSync($page);

        $pageSizeCnfPath = 'mailcampaigns_historical_sync/general/import_mailing_list_amount';

        // Get the page size from configuration settings.
        $pageSize = (int)$this->scopeConfig->getValue(
            $pageSizeCnfPath,
            ScopeInterface::SCOPE_STORE,
            $page->getStoreId()
        );

        // Load subscribers.
        $collection = $this->collectionFactory->create()
            ->addFieldToSelect('*')
            ->setPageSize($pageSize)
            ->setCurPage($page->getPage() - 1);

        $pageCount = $collection->getLastPageNumber();
        $mappedSubscribers = [];

        /** @var Subscriber $subscriber */
        foreach ($collection as $subscriber) {
            $mappedSubscribers[] = $subscriber->getData();
        }

        $this->apiHelper->updateSubscribers($mappedSubscribers, $page->getStoreId());

        $this->updateHistoricalSyncProgress($page, $pageCount);

        return $this;
    }
}
