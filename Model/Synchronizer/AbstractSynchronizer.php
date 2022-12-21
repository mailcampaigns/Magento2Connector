<?php

namespace MailCampaigns\Magento2Connector\Model\Synchronizer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\AbstractModel;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiPageInterface;
use MailCampaigns\Magento2Connector\Api\SynchronizerInterface;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;

abstract class AbstractSynchronizer implements SynchronizerInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ApiHelperInterface
     */
    protected $apiHelper;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->apiHelper = $apiHelper;
    }

    /**
     * @inheritDoc
     */
    abstract public function synchronize(AbstractModel $model, ?int $storeId = null, bool $useShortTimeout = false): SynchronizerInterface;

    /**
     * @inheritDoc
     */
    public function historicalSync(ApiPageInterface $page): SynchronizerInterface
    {
        return $this;
    }

    /**
     * @param ApiPageInterface $page
     * @param int $pageCount
     * @return $this
     * @throws ApiCredentialsNotSetException
     */
    protected function updateHistoricalSyncProgress(ApiPageInterface $page, int $pageCount)
    {
        $finished = $page->getPage() >= $pageCount;

        // Remove page if finished, update otherwise.
        if ($finished) {
            $this->apiHelper->getPageHelper()->deletePage($page);
        } else {
            $page
                ->setPage($page->getPage() + 1)
                ->setTotal($pageCount)
                ->setDatetime(time());

            $this->apiHelper->getPageHelper()->savePage($page);
        }

        // Send progress update to Api.
        $this->apiHelper->getClient()->setStoreId($page->getStoreId())
            ->call('update_magento_progress', [
                'store_id' => $page->getStoreId(),
                'collection' => $page->getCollection(),
                'page' => $page->getPage(),
                'total' => $pageCount,
                'datetime' => time(),
                'finished' => $finished
            ]);

        return $this;
    }
}
