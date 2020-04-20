<?php

namespace MailCampaigns\Magento2Connector\Model\Synchronizer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Logger\Monolog;
use Magento\Framework\Model\AbstractModel;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiPageInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Api\SynchronizerInterface;

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

    /**
     * @var Monolog
     */
    protected $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->apiHelper = $apiHelper;
        $this->logger = $logHelper->getLogger();
    }

    /**
     * @inheritDoc
     */
    abstract public function synchronize(AbstractModel $model, ?int $storeId = null): SynchronizerInterface;

    /**
     * @inheritDoc
     */
    public function historicalSync(ApiPageInterface $page): SynchronizerInterface
    {
        // Add an entry to the log.
        $logMsg = sprintf(
            'Starting bulk import for `%s` [page %d/%d]',
            $page->getCollection(),
            $page->getPage(),
            $page->getTotal()
        );

        $this->logger->addDebug($logMsg);

        return $this;
    }

    /**
     * @param ApiPageInterface $page
     * @param int $pageCount
     * @return $this
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
