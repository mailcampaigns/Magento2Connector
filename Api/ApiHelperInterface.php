<?php

namespace MailCampaigns\Magento2Connector\Api;

use Magento\Catalog\Model\Product;
use Magento\Framework\Data\Collection\AbstractDb;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;

interface ApiHelperInterface
{
    /**
     * @return $this
     * @throws ApiCredentialsNotSetException
     */
    public function processQueue(): self;

    /**
     * @return $this
     * @throws ApiCredentialsNotSetException
     */
    public function reportQueueStatus(): self;

    /**
     * @param int $storeId
     * @param string $websiteId
     * @return $this
     * @throws ApiCredentialsNotSetException
     */
    public function saveSettings(int $storeId, string $websiteId): self;

    /**
     * @param string $collectionName
     * @param int $storeId
     * @param AbstractDb $collection
     * @param string|null $pageSizeCnfPath
     * @return $this
     * @throws ApiCredentialsNotSetException
     */
    public function initHistoricalSync(
        string $collectionName,
        int $storeId,
        AbstractDb $collection,
        ?string $pageSizeCnfPath = null
    ): self;

    /**
     * @param array $data
     * @param int|null $storeId
     * @return $this
     * @throws ApiCredentialsNotSetException
     */
    public function updateCustomers(array $data, ?int $storeId = null): self;

    /**
     * @param array $data
     * @param int|null $storeId
     * @return $this
     */
    public function updateSubscribers(array $data, ?int $storeId = null): self;

    /**
     * @param Product $product
     * @param int $storeId
     * @return $this
     */
    public function deleteProduct(Product $product, int $storeId): self;

    /**
     * @param int $storeId
     * @return array
     */
    public function getUpdates(int $storeId): array;

    /**
     * @return ApiClientInterface
     */
    public function getClient(): ApiClientInterface;

    /**
     * @return ApiStatusHelperInterface
     */
    public function getStatusHelper(): ApiStatusHelperInterface;

    /**
     * @return ApiQueueHelperInterface
     */
    public function getQueueHelper(): ApiQueueHelperInterface;

    /**
     * @return ApiPageHelperInterface
     */
    public function getPageHelper(): ApiPageHelperInterface;

    /**
     * @return string
     */
    public function getModuleVersion(): string;
}
