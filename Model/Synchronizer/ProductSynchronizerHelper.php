<?php

namespace MailCampaigns\Magento2Connector\Model\Synchronizer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\SearchCriteria;
use Magento\Framework\Api\SortOrder;
use MailCampaigns\Magento2Connector\Api\ApiPageInterface;
use MailCampaigns\Magento2Connector\Api\ProductSynchronizerHelperInterface;

class ProductSynchronizerHelper implements ProductSynchronizerHelperInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var int
     */
    protected $pageCount;

    /**
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * @inheritDoc
     */
    public function getProducts(ApiPageInterface $page, int $pageSize): array
    {
        $searchCriteria = (new SearchCriteria())
            ->setSortOrders([
                (new SortOrder())
                    ->setField('entity_id')
                    ->setDirection(SortOrder::SORT_ASC)
            ])
            ->setCurrentPage($page->getPage() - 1)
            ->setPageSize($pageSize);

        $list = $this->productRepository->getList($searchCriteria);

        // Remember total page count.
        $this->pageCount = ceil($list->getTotalCount() / $pageSize);

        return $list->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getPageCount(): int
    {
        return $this->pageCount ?: 0;
    }

    /**
     * @inheritDoc
     */
    public function getProductsToSynchronize(int $syncStartTs): array
    {
        $syncStartStr = gmdate('Y-m-d H:i:s', $syncStartTs);

        $searchCriteria = (new SearchCriteria())
            ->setFilterGroups([
                (new FilterGroup())->setFilters([
                    (new Filter())
                        ->setField('created_at')
                        ->setConditionType('gteq')
                        ->setValue($syncStartStr),
                    (new Filter())
                        ->setField('updated_at')
                        ->setConditionType('gteq')
                        ->setValue($syncStartStr)
                ])
            ])
            ->setSortOrders([
                (new SortOrder())
                    ->setField('created_at')
                    ->setDirection(SortOrder::SORT_DESC),
                (new SortOrder())
                    ->setField('updated_at')
                    ->setDirection(SortOrder::SORT_DESC)
            ])
            ->setPageSize(1);

        return $this->productRepository
            ->getList($searchCriteria)
            ->getItems();
    }
}
