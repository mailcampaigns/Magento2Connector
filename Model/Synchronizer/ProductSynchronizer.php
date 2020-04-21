<?php

namespace MailCampaigns\Magento2Connector\Model\Synchronizer;

use Exception;
use InvalidArgumentException;
use Magento\Catalog\Helper\Data as TaxHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\ScopeInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiPageInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Api\ProductSynchronizerInterface;
use MailCampaigns\Magento2Connector\Api\SynchronizerInterface;

class ProductSynchronizer extends AbstractSynchronizer implements ProductSynchronizerInterface
{
    /**
     * @var TaxHelper
     */
    protected $taxHelper;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper,
        TaxHelper $taxHelper,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($scopeConfig, $apiHelper, $logHelper);
        $this->taxHelper = $taxHelper;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function synchronize(AbstractModel $model, ?int $storeId = null): SynchronizerInterface
    {
        if (!$model instanceof Product) {
            throw new InvalidArgumentException('Expected Product model instance.');
        }

        // Map product data.
        $d = $this->mapData($model, $storeId);

        // Send the mapped data to the Api.
        $this->post(
            $d['products'],
            $d['related_products'],
            $d['cross_sell_products'],
            $d['up_sell_products'],
            $d['categories']
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function historicalSync(ApiPageInterface $page): SynchronizerInterface
    {
        parent::historicalSync($page);

        $pageSizeCnfPath = 'mailcampaigns_historical_sync/general/import_products_amount';

        // Get the page size from configuration settings.
        $pageSize = (int)$this->scopeConfig->getValue(
            $pageSizeCnfPath,
            ScopeInterface::SCOPE_STORE,
            $page->getStoreId()
        );

        // Load products.
        $collection = $this->collectionFactory->create()->setPage(
            $page->getPage() - 1,
            $pageSize
        );

        $pageCount = $collection->getLastPageNumber();

        // Note: the 'm' prefix is short for 'mapped'.
        $mProducts = [];
        $mRelatedProducts = [];
        $mCrossSellProducts = [];
        $mUpSellProducts = [];
        $mCategories = [];

        /** @var Product $product */
        foreach ($collection as $product) {
            $d = $this->mapData($product);

            $mProducts = array_merge($mProducts, $d['products']);
            $mRelatedProducts = array_merge($mRelatedProducts, $d['related_products']);
            $mCrossSellProducts = array_merge($mCrossSellProducts, $d['cross_sell_products']);
            $mUpSellProducts = array_merge($mUpSellProducts, $d['up_sell_products']);
            $mCategories = array_merge($mCategories, $d['categories']);
        }

        // Send all the mapped data to the Api.
        $this->post($mProducts, $mRelatedProducts, $mCrossSellProducts, $mUpSellProducts,
            $mCategories);

        $this->updateHistoricalSyncProgress($page, $pageCount);

        return $this;
    }

    /**
     * Maps given product's data to prepare the synchronization.
     *
     * Note: the 'm' var prefix is short for 'mapped'.
     *
     * @param Product $product
     * @param int|null $storeId
     * @return array
     */
    protected function mapData(Product $product, ?int $storeId = null): array
    {
        $objectManager = ObjectManager::getInstance();
        $mProduct = [];

        $attributes = $product->getAttributes();
        foreach ($attributes as $attribute) {
            $data = $product->getData($attribute->getAttributeCode());

            if (!is_array($data)) {
                $mProduct[$attribute->getAttributeCode()] = $data;
            }
        }

        // product parent id
        if ($product->getId()) {
            $parentProductIds = $objectManager->create(Configurable::class)
                ->getParentIdsByChild($product->getId());
            $parentProductId = $parentProductIds[0] ?? null;

            /** @var Product $parentProduct */
            $parentProduct = $objectManager->create(Product::class)->load($parentProductId);
            $childProductIds = $objectManager->create(Configurable::class)->getChildrenIds($product->getId());

            if (isset($parentProductId)) {
                $mProduct['parent_id'] = $parentProductId;
            } else {
                $mProduct['parent_id'] = '';
            }
        }

        // Get Price Incl Tax
        $mProduct['price'] = $this->taxHelper->getTaxPrice(
            $product,
            $mProduct['price'],
            true,
            null,
            null,
            null,
            $storeId,
            null,
            true
        );

        // Get Special Price Incl Tax
        $mProduct['special_price'] = $this->taxHelper->getTaxPrice(
            $product,
            $mProduct['special_price'],
            true,
            null,
            null,
            null,
            $storeId,
            null,
            true
        );

        // get lowest tier price / staffel
        $mProduct['lowest_tier_price'] = $product->getTierPrice();

        // als price niet bestaat bij configurable dan van child pakken
        if ($mProduct['price'] == null && !empty($childProductIds) && $mProduct['type_id'] == 'configurable') {
            foreach ($childProductIds[0] as $childProductId) {
                /** @var Product $childProduct */
                $childProduct = $objectManager->create(Product::class)->load($childProductId);

                $mProduct['price'] = $this->taxHelper->getTaxPrice(
                    $childProduct,
                    $childProduct->getFinalPrice(),
                    true,
                    null,
                    null,
                    null,
                    $storeId,
                    null,
                    true
                );

                break;
            }
        }

        // als special_price niet bestaat bij configurable dan van child pakken
        if ($mProduct['special_price'] == null && !empty($childProductIds)
            && $mProduct['type_id'] == 'configurable') {
            foreach ($childProductIds[0] as $childProductId) {
                /** @var Product $childProduct */
                $childProduct = $objectManager->create(Product::class)->load($childProductId);

                $mProduct['special_price'] = $this->taxHelper->getTaxPrice(
                    $childProduct,
                    $childProduct->getSpecialPrice(),
                    true,
                    null,
                    null,
                    null,
                    $storeId,
                    null,
                    true
                );

                break;
            }
        }

        if (isset($parentProduct)) {
            // als omschrijving niet bestaat bij simple dan van parent pakken
            if ($mProduct['description'] == '' && $mProduct['parent_id']
                && $mProduct['type_id'] != 'configurable' && isset($parentProductId)) {
                $mProduct['description'] = $parentProduct->getName();
            }
            if ($mProduct['short_description'] == '' && $mProduct['parent_id']
                && $mProduct['type_id'] != 'configurable' && isset($parentProductId)) {
                $mProduct['short_description'] = $parentProduct->getName();
            }
        }

        // images
        $image_id = 1;

        if ($product->hasData('image') && $product->getData('image') !== 'no_selection') {
            $mProduct['mc:image_url_main'] = $product->getMediaConfig()
                ->getMediaUrl($product->getData('image'));
        } else {
            $mProduct['mc:image_url_main'] = '';
        }

        $productImages = $product->getMediaGalleryImages();
        if (!empty($productImages) && count($productImages) > 0 && is_array($productImages)) {
            foreach ($productImages as $image) {
                $image_id++;
                $mProduct['mc:image_url_' . $image_id] = $image->getUrl();
            }
        }

        //get image from parent if empty and not configurable
        if ($mProduct['mc:image_url_main'] === '' && $mProduct['parent_id']
            && $mProduct['type_id'] != 'configurable' && isset($parentProductId)) {
            if (isset($parentProduct) && $parentProduct->getData('image') != 'no_selection'
                && $parentProduct->hasData('image')) {
                $mProduct['mc:image_url_main'] = $parentProduct->getMediaConfig()
                    ->getMediaUrl($parentProduct->getData('image'));
            } else {
                $mProduct['mc:image_url_main'] = '';
            }
        }

        //get image from child if empty and configurable, loops through child products until it finds an image
        if ($mProduct['mc:image_url_main'] == '' && !empty($childProductIds)
            && $mProduct['type_id'] == 'configurable') {
            foreach ($childProductIds[0] as $childProductId) {
                $childProduct = $objectManager->create(Product::class)->load($childProductId);

                if ($childProduct->getData('image') != null && $childProduct->getData('image') != 'no_selection') {
                    $mProduct['mc:image_url_main'] = $childProduct->getMediaConfig()
                        ->getMediaUrl($childProduct->getData('image'));
                    break;
                } else {
                    $mProduct['mc:image_url_main'] = '';
                }
            }
        }

        // link
        $mProduct['mc:product_url'] = $product->getProductUrl();

        // Stock Status
        $mProduct['stock_status'] = $product->getData('quantity_and_stock_status');

        // Stock quantity
        if ($product->getExtensionAttributes()->getStockItem() != null) {
            $mProduct['quantity'] = $product->getQty();
        } else {
            $mProduct['quantity'] = null;
        }

        // store id
        $mProduct['store_id'] = $product->getStoreID();

        // get related products
        $mRelatedProducts = [];
        $related_product_collection = $product->getRelatedProductIds();
        $mRelatedProducts[$product->getId()]['store_id'] = $mProduct['store_id'];

        if (!empty($related_product_collection) && count($related_product_collection) > 0
            && is_array($related_product_collection)) {
            foreach ($related_product_collection as $pdtid) {
                $mRelatedProducts[$product->getId()]['products'][] = $pdtid;
            }
        }

        // get up sell products
        $mUpSellProducts = [];
        $upsell_product_collection = $product->getUpSellProductIds();

        if (!empty($upsell_product_collection) && count($upsell_product_collection) > 0
            && is_array($upsell_product_collection)) {
            $mUpSellProducts[$product->getId()]['store_id'] = $mProduct['store_id'];

            foreach ($upsell_product_collection as $pdtid) {
                $mUpSellProducts[$product->getId()]['products'][] = $pdtid;
            }
        }

        // get cross sell products
        $mCrossSellProducts = [];
        $crosssell_product_collection = $product->getCrossSellProductIds();

        if (!empty($crosssell_product_collection) && count($crosssell_product_collection) > 0
            && is_array($crosssell_product_collection)) {
            $mCrossSellProducts[$product->getId()]['store_id'] = $mProduct['store_id'];

            foreach ($crosssell_product_collection as $pdtid) {
                $mCrossSellProducts[$product->getId()]['products'][] = $pdtid;
            }
        }

        // Categories
        $mCategories = [];
        $categories = [];
        $objectManager = ObjectManager::getInstance();

        foreach ($product->getCategoryIds() as $category_id) {
            $categories[] = $category_id;

            /** @var Category $cat */
            $cat = $objectManager->create(Category::class)->load($category_id);

            $mCategories[$category_id] = $cat->getName();
        }

        $mProduct['categories'] = json_encode(array_unique($categories));

        return [
            'products' => [$mProduct],
            'related_products' => $mRelatedProducts,
            'cross_sell_products' => $mCrossSellProducts,
            'up_sell_products' => $mUpSellProducts,
            'categories' => $mCategories
        ];
    }

    /**
     * Post mapped data to the Api.
     *
     * @param array $products
     * @param array $relatedProducts
     * @param array $crossSellProducts
     * @param array $upSellProducts
     * @param array $categories
     * @return $this
     * @throws Exception
     */
    protected function post(
        array $products,
        array $relatedProducts,
        array $crossSellProducts,
        array $upSellProducts,
        array $categories
    ): self {
        $apiClient = $this->apiHelper->getClient();

        if (count($products) > 0) {
            $apiClient->call('update_magento_products', $products);
        }

        if (count($relatedProducts) > 0) {
            $apiClient->call('update_magento_related_products', $relatedProducts);
        }

        if (count($crossSellProducts) > 0) {
            $apiClient->call('update_magento_crosssell_products', $crossSellProducts);
        }

        if (count($upSellProducts) > 0) {
            $apiClient->call('update_magento_upsell_products', $upSellProducts);
        }

        if (count($categories) > 0) {
            $apiClient->call('update_magento_categories', $categories);
        }

        return $this;
    }
}