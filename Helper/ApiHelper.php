<?php

namespace MailCampaigns\Magento2Connector\Helper;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Module\ModuleList;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MailCampaigns\Magento2Connector\Api\ApiClientInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiPageHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiQueueHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiStatusHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Model\ApiQueue;

class ApiHelper extends AbstractHelper implements ApiHelperInterface
{
    /** @var string */
    const MODULE_NAME = 'MailCampaigns_Magento2Connector';

    /** @var int */
    const DEFAULT_PAGE_SIZE = 250;

    /**
     * @var int Maximum time in seconds the queue will process the queued Api calls.
     */
    protected static $queueTimeout = 55;

    /**
     * @var ModuleList
     */
    protected $moduleList;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ApiClientInterface
     */
    protected $client;

    /**
     * @var ApiQueueHelperInterface
     */
    protected $queueHelper;

    /**
     * @var ApiStatusHelperInterface
     */
    protected $statusHelper;

    /**
     * @var ApiPageHelperInterface
     */
    protected $pageHelper;

    /**
     * @var LogHelperInterface
     */
    protected $logHelper;

    /**
     * @inheritDoc
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ModuleList $moduleList,
        ApiClientInterface $client,
        ApiQueueHelperInterface $queueHelper,
        ApiStatusHelperInterface $statusHelper,
        ApiPageHelperInterface $pageHelper,
        LogHelperInterface $logHelper
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->moduleList = $moduleList;
        $this->_logger = $logHelper->getLogger();
        $this->client = $client;
        $this->queueHelper = $queueHelper;
        $this->statusHelper = $statusHelper;
        $this->pageHelper = $pageHelper;
        $this->logHelper = $logHelper;
    }

    /**
     * @inheritDoc
     */
    public function processQueue(): ApiHelperInterface
    {
        $startTime = time();
        $queuedCalls = $this->queueHelper->getQueuedCalls();
        $queueCnt = $queuedCalls->count();
        $processedCnt = 0;

        if ($queueCnt < 1) {
            return $this;
        }

        $this->_logger->info(sprintf('Processing %d queued Api calls..', $queueCnt));

        /** @var ApiQueue $queuedCall */
        foreach ($queuedCalls as $queuedCall) {
            $this->client->processQueuedCall($queuedCall);

            // Detect timeout.
            if ((time() - $startTime) > self::$queueTimeout) {
                $this->_logger->info(sprintf(
                    'Timeout reached for processing queue, '
                    . 'stopping (successfully processed %d of %d queued Api calls)..',
                    $processedCnt,
                    $queueCnt
                ));

                return $this;
            }
        }

        $this->_logger->info('Finished processing all queued Api calls.');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function reportQueueStatus(): ApiHelperInterface
    {
        $defaultStoreId = $this->storeManager->getDefaultStoreView()->getId();

        $this->client->setStoreId($defaultStoreId)->call('report_magento_queue_status', [
            'queue_size' => $this->queueHelper->getQueueSize(),
            'error_count' => $this->queueHelper->getErrorCount(),
            'datetime' => time()
        ]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function saveSettings(int $storeId, string $websiteId): ApiHelperInterface
    {
        // Retrieve store configuration data.
        $settings = $this->storeManager->getStore($storeId)->getData();

        // Add additional data.
        $settings['website_id'] = $websiteId;
        $settings['version'] = $this->getModuleVersion();
        $settings['url'] = $this->_request->getDistroBaseUrl();
        $settings['logging_enabled'] = $this->logHelper->isLoggingEnabled();
        $settings['logging_level'] = $this->logHelper->getCurrentLoggingLevel();

        // Send settings to Api.
        $this->client
            ->setStoreId($storeId)
            ->call('save_magento_settings', $settings, false);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function initHistoricalSync(
        string $collectionName,
        int $storeId,
        AbstractDb $collection,
        ?string $pageSizeCnfPath = null
    ): ApiHelperInterface {
        // Delete remote data.
        $this->client->setStoreId($storeId)->call('reset_magento_tables', [
            'collection' => $collectionName
        ]);

        // Delete from pages table.
        $this->pageHelper->deleteByCollectionName($collectionName, $storeId);

        if (null !== $pageSizeCnfPath) {
            $pageSize = (int)$this->scopeConfig->getValue(
                $pageSizeCnfPath,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        } else {
            $pageSize = self::DEFAULT_PAGE_SIZE;
        }

        $lastPageNr = $collection->setPageSize($pageSize)->getLastPageNumber();

        $apiPage = $this->pageHelper->createPage();

        $apiPage
            ->setStoreId($storeId)
            ->setCollection($collectionName)
            ->setPage(1)
            ->setTotal($lastPageNr);

        $this->pageHelper->savePage($apiPage);

        $this->client->setStoreId($storeId)->call('update_magento_progress', [
            'store_id' => $storeId,
            'collection' => $collectionName,
            'page' => 1,
            'total' => $lastPageNr,
            'datetime' => time(),
            'finished' => 0
        ]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function updateCustomers(array $data, ?int $storeId = null, bool $useShortTimeout = false): ApiHelperInterface
    {
        $this->client
            ->setStoreId($storeId)
            ->call('update_magento_customers', $data, true, $useShortTimeout);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function updateSubscribers(array $data, ?int $storeId = null): ApiHelperInterface
    {
        $this->client
            ->setStoreId($storeId)
            ->call('update_magento_mailing_list', $data);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function deleteProduct(Product $product, int $storeId): ApiHelperInterface
    {
        $content = [
            'filters' => [
                'entity_id' => $product->getEntityId()
            ]
        ];

        $this->client
            ->setStoreId($storeId)
            ->queue($content, 'delete_magento_product');

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getUpdates(int $storeId): array
    {
        return $this->client->setStoreId($storeId)
            ->call("get_magento_updates", ['store_id' => $storeId], false);
    }

    /**
     * @inheritDoc
     */
    public function getClient(): ApiClientInterface
    {
        return $this->client;
    }

    /**
     * @inheritDoc
     */
    public function getStatusHelper(): ApiStatusHelperInterface
    {
        return $this->statusHelper;
    }

    /**
     * @inheritDoc
     */
    public function getQueueHelper(): ApiQueueHelperInterface
    {
        return $this->queueHelper;
    }

    /**
     * @inheritDoc
     */
    public function getPageHelper(): ApiPageHelperInterface
    {
        return $this->pageHelper;
    }

    /**
     * @inheritDoc
     */
    public function getModuleVersion(): string
    {
        $moduleInfo = $this->moduleList->getOne(self::MODULE_NAME);

        return is_array($moduleInfo) && isset($moduleInfo['setup_version'])
            ? $moduleInfo['setup_version'] : '';
    }
}
