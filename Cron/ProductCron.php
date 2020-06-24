<?php

namespace MailCampaigns\Magento2Connector\Cron;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Cron\Model\Schedule;
use Magento\Framework\Data\Collection;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Api\ProductSynchronizerInterface;
use MailCampaigns\Magento2Connector\Model\ApiStatus;

class ProductCron extends AbstractCron
{
    /**
     * @var ProductSynchronizerInterface
     */
    protected $synchronizer;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    public function __construct(
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper,
        ProductSynchronizerInterface $synchronizer,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($apiHelper, $logHelper);
        $this->synchronizer = $synchronizer;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute(Schedule $schedule): void
    {
        try {
            $syncStartTs = $this->apiStatusHelper->getSyncStart(ApiStatus::TYPE_PRODUCT_CRON);
            $syncStartStr = gmdate('Y-m-d H:i:s', $syncStartTs);

            $this->apiStatusHelper->updateStatus(ApiStatus::TYPE_PRODUCT_CRON);

            // Synchronize updated products.
            $productsUpdated = $this->collectionFactory->create()
                ->addFieldToSelect('*')
                ->addFieldToFilter('updated_at', ['gteq' => $syncStartStr])
                ->setOrder('updated_at', Collection::SORT_ORDER_DESC);

            /** @var Product $product */
            foreach ($productsUpdated as $product) {
                $storeIds = $product->getStoreIds();

                foreach ($storeIds as $storeId) {
                    $this->synchronizer->synchronize($product, $storeId);
                }
            }

            // Synchronize new products.
            $productsNew = $this->collectionFactory->create()
                    ->addFieldToSelect('*')
                    ->addFieldToFilter('created_at', ['gteq' => $syncStartStr])
                    ->setOrder('created_at', Collection::SORT_ORDER_DESC);

            /** @var Product $product */
            foreach ($productsNew as $product) {
                $storeIds = $product->getStoreIds();

                foreach ($storeIds as $storeId) {
                    $this->synchronizer->synchronize($product, $storeId);
                }
            }
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);

            throw $e;
        }
    }
}
