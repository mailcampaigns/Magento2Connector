<?php

namespace MailCampaigns\Connector\Model\Plugin;

use Magento\Catalog\Model\Product\Action\Interceptor;

class UpdateAttributes
{
	protected $helper;
	protected $storemanager;
	protected $objectmanager;
	protected $productrepository;
	protected $mcapi;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
		$this->helper 				= $dataHelper;
		$this->mcapi 				= $mcapi;
		$this->storemanager 			= $storeManager;
		$this->productrepository	= $productRepository;
		$this->objectmanager 		= $objectManager;
    }
	
    public function aroundUpdateAttributes(
        Interceptor $interceptor,
        \Closure $closure,
        $productIds,
        $attrData,
        $storeId
    ) {
		//execute the original method and remember the result;
		$result = $closure($productIds, $attrData, $storeId);
		
		// set vars
      	$this->mcapi->APIStoreID 		= $storeId; 
		$this->mcapi->APIKey 			= $this->helper->getConfig('mailcampaignsapi/general/api_key', $this->mcapi->APIStoreID);
  		$this->mcapi->APIToken 			= $this->helper->getConfig('mailcampaignsapi/general/api_token', $this->mcapi->APIStoreID);
		$this->mcapi->ImportProducts 	= $this->helper->getConfig('mailcampaignsrealtimesync/general/import_products',$this->mcapi->APIStoreID);	

  		if ($this->mcapi->ImportProducts == 1)
		{			
			foreach ($productIds as $product_id)
			{
				// Retrieve the product being updated from the event observer
				$i = 0;	
				$product_data = array();
				$related_products = array();
				
				$product = $this->objectmanager->create('Magento\Catalog\Model\Product')->load($product_id);
				
				$this->mcapi->APIWebsiteID 		= $product->getWebsiteId() ;
			
				$attributes = $product->getAttributes();
				foreach ($attributes as $attribute)
				{
					$data = $product->getData($attribute->getAttributeCode());
					if (!is_array($data)) $product_data[$i][$attribute->getAttributeCode()] = $data;
				}
				
				// get lowest tier price / staffel
				$lowestTierPrice = $product->getResource()->getAttribute('tier_price')->getValue($product); 
				$product_data[$i]["lowest_tier_price"] = $lowestTierPrice;
	
				// images
				$image_id = 1;
				$product_data[$i]["mc:image_url_main"] = $product->getMediaConfig()->getMediaUrl($product->getData('image'));
				$product_images = $product->getMediaGalleryImages();
				if (sizeof($product_images) > 0)
				{
					foreach ($product_images as $image)
					{
						$product_data[$i]["mc:image_url_".$image_id++.""] = $image->getUrl();
					} 
				}
	
				// link
				$product_data[$i]["mc:product_url"] = $product->getProductUrl();
				
				// store id
				$product_data[$i]["store_id"] = $product->getStoreID();
				
				// get related products
				$related_product_collection = $product->getRelatedProductIds();
				$related_products[$product->getId()]["store_id"] = $product_data[$i]["store_id"];
				foreach($related_product_collection as $pdtid)
				{
					$related_products[$product->getId()]["products"][] = $pdtid;
				}
							
				if (sizeof($product_data) > 0)
					$this->mcapi->QueueAPICall("update_magento_products", $product_data);
				
				if (sizeof($related_product_collection) > 0)
					$this->mcapi->QueueAPICall("update_magento_related_products", $related_products);	
			}
		}		
		
        return $result;
    }
}