<?php

namespace MailCampaigns\Magento2Connector\Api;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\InputException;

interface ProductSynchronizerHelperInterface
{
    /**
     * @param ApiPageInterface $page
     * @param int $pageSize
     * @return array
     * @throws InputException
     */
    public function getProducts(ApiPageInterface $page, int $pageSize): array;

    /**
     * Returns number of pages for last 'getProducts' call.
     *
     * @return int
     */
    public function getPageCount(): int;

    /**
     * @param int $syncStartTs
     * @return ProductInterface[]
     * @throws InputException
     */
    public function getProductsToSynchronize(int $syncStartTs): array;
}
