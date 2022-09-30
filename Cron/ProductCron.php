<?php

namespace MailCampaigns\Magento2Connector\Cron;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Cron\Model\Schedule;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Api\ProductSynchronizerInterface;
use MailCampaigns\Magento2Connector\Api\ProductSynchronizerHelperInterface;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;
use MailCampaigns\Magento2Connector\Model\ApiStatus;

class ProductCron extends AbstractCron
{
    /**
     * @var ProductSynchronizerInterface
     */
    protected $synchronizer;

    /**
     * @var ProductSynchronizerHelperInterface
     */
    protected $synchronizerHelper;

    public function __construct(
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper,
        ProductSynchronizerInterface $synchronizer,
        ProductSynchronizerHelperInterface $synchronizerHelper
    ) {
        parent::__construct($apiHelper, $logHelper);

        $this->synchronizer = $synchronizer;
        $this->synchronizerHelper = $synchronizerHelper;
    }

    /**
     * @inheritDoc
     */
    public function execute(Schedule $schedule): void
    {
        try {
            $syncStartTs = $this->apiStatusHelper->getSyncStart(ApiStatus::TYPE_PRODUCT_CRON);
            $products = $this->synchronizerHelper->getProductsToSynchronize($syncStartTs);

            $this->apiStatusHelper->updateStatus(ApiStatus::TYPE_PRODUCT_CRON);

            /** @var Product $product */
            foreach ($products as $product) {
                $storeIds = $product->getStoreIds();

                foreach ($storeIds as $storeId) {
                    // Load product data of a specific store.
                    $product = $this->synchronizerHelper->getProduct($product->getId(), $storeId);

                    $this->synchronizer->synchronize($product, $storeId);
                }
            }
        } catch (ApiCredentialsNotSetException $e) {
            // Just add a debug message to the filelog.
            if (method_exists($this->logger, 'addDebug')) {
                $this->logger->addDebug($e->getMessage());
            }
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);

            throw $e;
        }
    }
}
