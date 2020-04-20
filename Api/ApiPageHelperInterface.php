<?php

namespace MailCampaigns\Magento2Connector\Api;

use MailCampaigns\Magento2Connector\Model\ResourceModel\ApiPage\Collection;

interface ApiPageHelperInterface
{
    /**
     * @param ApiPageInterface $page
     * @return ApiPageHelperInterface
     */
    public function savePage(ApiPageInterface $page): ApiPageHelperInterface;

    /**
     * @param ApiPageInterface $page
     * @return ApiPageHelperInterface
     */
    public function deletePage(ApiPageInterface $page): ApiPageHelperInterface;

    /**
     * @return ApiPageInterface
     */
    public function createPage(): ApiPageInterface;

    /**
     * @param string $collectionName
     * @param int $storeId
     * @return $this
     */
    public function deleteByCollectionName(string $collectionName, int $storeId): self;

    /**
     * @return Collection
     */
    public function getPages(): Collection;
}
