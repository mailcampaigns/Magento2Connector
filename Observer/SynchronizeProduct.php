<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

class SynchronizeProduct implements ObserverInterface
{
    protected $logger;
	protected $helper;
	protected $storemanager;
	protected $objectmanager;
	protected $productrepository;
	protected $taxhelper;
	protected $mcapi;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\Catalog\Helper\Data $taxHelper,
    	Logger $logger
    ) {
		$this->logger 				= $logger;
		$this->helper 				= $dataHelper;
		$this->mcapi 				= $mcapi;
		$this->storemanager 			= $storeManager;
		$this->productrepository	= $productRepository;
		$this->taxhelper 			= $taxHelper;
		$this->objectmanager 		= $objectManager;
    }

    public function execute(EventObserver $observer)
    {
		// set vars
		$this->mcapi->APIWebsiteID 		= $observer->getWebsite();
      	$this->mcapi->APIStoreID 		= $observer->getStore();
		$this->mcapi->APIKey 			= $this->helper->getConfig('mailcampaignsapi/general/api_key', $this->mcapi->APIStoreID);
  		$this->mcapi->APIToken 			= $this->helper->getConfig('mailcampaignsapi/general/api_token', $this->mcapi->APIStoreID);
		$this->mcapi->ImportProducts 	= $this->helper->getConfig('mailcampaignsrealtimesync/general/import_products',$this->mcapi->APIStoreID);

  		if ($this->mcapi->ImportProducts == 1)
		{
			try
			{
				// Retrieve the product being updated from the event observer
				$i = 0;
				$product_data = array();
				$related_products = array();

				$product = $observer->getEvent()->getProduct();

				$attributes = $product->getAttributes();
				foreach ($attributes as $attribute)
				{
					$data = $product->getData($attribute->getAttributeCode());
					if (!is_array($data)) $product_data[$i][$attribute->getAttributeCode()] = $data;
				}

				// product parent & child id
				if($product->getId() != "")
				{
				  $objectMan =  \Magento\Framework\App\ObjectManager::getInstance();
				  $parent_product_id = $objectMan->create('Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable')->getParentIdsByChild($product->getId());
				  $the_parent_product = $objectMan->create('Magento\Catalog\Model\Product')->load($parent_product_id);
				  $child_product_ids = $objectMan->create('Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable')->getChildrenIds($product->getId());

				  if(isset($parent_product_id[0]))
				  {
					$product_data[$i]["parent_id"] = $parent_product_id[0];
				  }
				  else {
					$product_data[$i]["parent_id"] = "";
				  }
				}

				// Get Price Incl Tax
				$product_data[$i]["price"] = $this->taxhelper->getTaxPrice($product, $product_data[$i]["price"], true, NULL, NULL, NULL, $this->mcapi->APIStoreID, NULL, true);

				// Get Special Price Incl Tax
				$product_data[$i]["special_price"] = $this->taxhelper->getTaxPrice($product, $product_data[$i]["special_price"], true, NULL, NULL, NULL, $this->mcapi->APIStoreID, NULL, true);

				// get lowest tier price / staffel
				$lowestTierPrice = $product->getResource()->getAttribute('tier_price')->getValue($product);
				$product_data[$i]["lowest_tier_price"] = $lowestTierPrice;

				// als price niet bestaat bij configurable dan van child pakken
				if($product_data[$i]["price"] == NULL && !empty($child_product_ids) && $product_data[$i]["type_id"] == "configurable"){
				  foreach($child_product_ids[0] as $child_product_id){
					$the_child_product = $objectMan->create('Magento\Catalog\Model\Product')->load($child_product_id);
					  $product_data[$i]["price"] = $this->taxhelper->getTaxPrice($the_child_product, $the_child_product->getFinalPrice(), true, NULL, NULL, NULL, $this->mcapi->APIStoreID, NULL, true);
					  break;
				  }
				}

				// als special_price niet bestaat bij configurable dan van child pakken
				if($product_data[$i]["special_price"] == NULL && !empty($child_product_ids) && $product_data[$i]["type_id"] == "configurable"){
				  foreach($child_product_ids[0] as $child_product_id){
					$the_child_product = $objectMan->create('Magento\Catalog\Model\Product')->load($child_product_id);
					  $product_data[$i]["special_price"] = $this->taxhelper->getTaxPrice($the_child_product, $the_child_product->getSpecialPrice(), true, NULL, NULL, NULL, $this->mcapi->APIStoreID, NULL, true);
					  break;
				  }
				}

				// als omschrijving niet bestaat bij simple dan van parent pakken
				if($product_data[$i]["description"] == "" && $product_data[$i]["parent_id"] != "" && $product_data[$i]["type_id"] != "configurable"){
				  $product_data[$i]["description"] = $the_parent_product->getDescription();
				}
				if($product_data[$i]["short_description"] == "" && $product_data[$i]["parent_id"] != "" && $product_data[$i]["type_id"] != "configurable"){
				  $product_data[$i]["short_description"] = $the_parent_product->getShortDescription();
				}

				// images
				$image_id = 1;
				if($product->getData('image') != NULL && $product->getData('image') != "no_selection"){
				  $product_data[$i]["mc:image_url_main"] = $product->getMediaConfig()->getMediaUrl($product->getData('image'));
				}
				else{
				  $product_data[$i]["mc:image_url_main"] = "";
				}

				$product_images = $product->getMediaGalleryImages();
				if (!empty($product_images) && sizeof($product_images) > 0 && is_array($product_images))
				{
					foreach ($product_images as $image)
					{
						$product_data[$i]["mc:image_url_".$image_id++.""] = $image->getUrl();
					}
				}

				//get image from parent if empty and not configurable
				if($product_data[$i]["mc:image_url_main"] === "" && $product_data[$i]["parent_id"] != "" && $product_data[$i]["type_id"] != "configurable"){
				  if($the_parent_product->getData('image') != "no_selection" && $the_parent_product->getData('image') != NULL){
					$product_data[$i]["mc:image_url_main"] = $the_parent_product->getMediaConfig()->getMediaUrl($the_parent_product->getData('image'));
				  }
				  else{
					$product_data[$i]["mc:image_url_main"] = "";
				  }
				}

				//get image from child if empty and configurable, loops through child products until it finds an image
				if($product_data[$i]["mc:image_url_main"] == "" && !empty($child_product_ids) && $product_data[$i]["type_id"] == "configurable"){
				  foreach($child_product_ids[0] as $child_product_id){
					$the_child_product = $objectMan->create('Magento\Catalog\Model\Product')->load($child_product_id);
					if($the_child_product->getData('image') != NULL && $the_child_product->getData('image') != "no_selection"){
					  $product_data[$i]["mc:image_url_main"] = $the_child_product->getMediaConfig()->getMediaUrl($the_child_product->getData('image'));
					  break;
					}
					else{
					  $product_data[$i]["mc:image_url_main"] = "";
					}
				  }
				}

				// link
				$product_data[$i]["mc:product_url"] = $product->getProductUrl();

				// Stock Status
				$product_data[$i]["stock_status"] = $product->getData('quantity_and_stock_status');

				// Stock quantity
				if($product->getExtensionAttributes()->getStockItem() != NULL){
				  $product_data[$i]["quantity"] = $product->getExtensionAttributes()->getStockItem()->getQty();
				}
				else{
				  $product_data[$i]["quantity"] = NULL;
				}

				// store id
				$product_data[$i]["store_id"] = $product->getStoreID();

				// get related products
				$related_products = array();
				$related_product_collection = $product->getRelatedProductIds();
				$related_products[$product->getId()]["store_id"] = $product_data[$i]["store_id"];
				if (!empty($related_product_collection) && sizeof($related_product_collection) > 0 && is_array($related_product_collection))
				{
					foreach($related_product_collection as $pdtid)
					{
						$related_products[$product->getId()]["products"][] = $pdtid;
					}
				}
				
				// get up sell products
				$upsell_products = array();
				$upsell_product_collection = $product->getUpSellProducts();
				if (!empty($upsell_product_collection) && sizeof($upsell_product_collection) > 0 && is_array($upsell_product_collection))
				{
					$upsell_products[$product->getId()]["store_id"] = $product_data[$i]["store_id"];
					foreach($upsell_product_collection as $pdtid)
					{
						$upsell_products[$product->getId()]["products"][] = $pdtid;
					}
				}
				
				// get cross sell products
				$crosssell_products = array();
				$crosssell_product_collection = $product->getCrossSellProducts();
				if (!empty($crosssell_product_collection) && sizeof($crosssell_product_collection) > 0 && is_array($crosssell_product_collection))
				{
					$crosssell_products[$product->getId()]["store_id"] = $product_data[$i]["store_id"];
					foreach($crosssell_product_collection as $pdtid)
					{
						$crosssell_products[$product->getId()]["products"][] = $pdtid;
					}
				}

				// Categories
				$category_data = array();
				$categories = array();
				$objectMan =  \Magento\Framework\App\ObjectManager::getInstance();
				foreach ($product->getCategoryIds() as $category_id)
				{
					$categories[] = $category_id;
					$cat = $objectMan->create('Magento\Catalog\Model\Category')->load($category_id);
					$category_data[$category_id] = $cat->getName();
				}
				$product_data[$i]["categories"] = json_encode(array_unique($categories));

				// Post data
				if (sizeof($category_data) > 0)
					$this->mcapi->QueueAPICall("update_magento_categories", $category_data);

				if (sizeof($product_data) > 0)
					$this->mcapi->QueueAPICall("update_magento_products", $product_data);

				if (sizeof($related_products) > 0)
				{
					$this->mcapi->QueueAPICall("update_magento_related_products", $related_products);
					unset($related_products);
				}
					
				if (sizeof($crosssell_products) > 0)
				{
					$this->mcapi->$mcAPI->QueueAPICall("update_magento_crosssell_products", $crosssell_products, 0);
					unset($crosssell_products);
				}
				
				if (sizeof($upsell_products) > 0)
				{
					$this->mcapi->$mcAPI->QueueAPICall("update_magento_upsell_products", $upsell_products, 0);
					unset($upsell_products);
				}
			}
			catch (\Magento\Framework\Exception\NoSuchEntityException $e)
			{
				$this->mcapi->DebugCall($e->getMessage());
			}
			catch (Exception $e)
			{
				$this->mcapi->DebugCall($e->getMessage());
			}
		}
    }
}
