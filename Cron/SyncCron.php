<?php

namespace MailCampaigns\Connector\Cron;

class SyncCron {

 	protected $helper;
	protected $resource;
	protected $connection;
	protected $tn__mc_api_pages;
	protected $tn__mc_api_queue;
	protected $mcapi;
	
	protected $objectmanager;
	protected $customerrepository;
	protected $countryinformation;
	protected $productrepository;
	protected $taxhelper;

    public function __construct(
   		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\Magento\Framework\App\ResourceConnection $Resource,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcApi,
		
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
		\Magento\Directory\Api\CountryInformationAcquirerInterface $countryInformation,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\Catalog\Helper\Data $taxHelper
    ) {
        $this->resource 				= $Resource;
		$this->helper 				= $dataHelper;
		$this->mcapi 				= $mcApi;
		
		$this->objectmanager 		= $objectManager;
		$this->customerrepository 	= $customerRepository;
		$this->countryinformation	= $countryInformation;
		$this->productrepository	= $productRepository;
		$this->taxhelper 			= $taxHelper;
    }

    public function execute()
	{
		//database connection
		$this->connection = $this->resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);

		//tables
		$this->tn__mc_api_pages = $this->resource->getTableName('mc_api_pages');
		$this->tn__mc_api_queue = $this->resource->getTableName('mc_api_queue');

		// Process one page per each cron
		$sql        = "SELECT * FROM `".$this->tn__mc_api_pages."` ORDER BY id ASC";
		$rows       = $this->connection->fetchAll($sql);
		$starttime 	= time();
		$pages 		= 0;

		// Loop through queue list
		foreach ($rows as $row)
		{
			$currentPage 			 = $row["page"];

			$this->mcapi->APIStoreID = $row["store_id"];
			$this->mcapi->APIKey 	 = $this->helper->getConfig('mailcampaignsapi/general/api_key', $this->mcapi->APIStoreID);
			$this->mcapi->APIToken 	 = $this->helper->getConfig('mailcampaignsapi/general/api_token', $this->mcapi->APIStoreID);

			if ($row["collection"] == "customer/customer")
			{
				// one transaction
				// get all customers
				$customer_data = array();
				$customersCollection = $this->objectmanager->create('Magento\Customer\Model\Customer')->setStoreId($this->mcapi->APIStoreID)->getCollection();
				$customersCollection->addAttributeToSelect('*');
				$customersCollection->setPageSize($this->helper->getConfig('mailcampaignshistoricalsync/general/import_customers_amount', $this->mcapi->APIStoreID));

				$pages = $customersCollection->getLastPageNumber();

				$customersCollection->setCurPage($currentPage);
				$customersCollection->load();

				// Loop trough customers
				foreach ($customersCollection as $customer)
				{
					try
					{
						$tmpdata = $customer->getData();

						$address_data = array();
						$customerAddressId = $customer->getDefaultBilling();
						if ($customerAddressId)
						{
							$address 		= $this->objectmanager->create('Magento\Customer\Model\Address')->load($customerAddressId);
							$address_data 	= $address->getData();

							//$country_id 	= $address_data["country_id"] ?? '';
							//$country 		= $this->countryinformation->getCountryInfo($country_id);
							//$country_name 	= $country->getFullNameLocale();
							//$address_data["country_name"] = $country_name;
						}

						unset($address_data["attributes"]);
						unset($address_data["entity_id"]);
						unset($address_data["parent_id"]);
						unset($address_data["is_active"]);
						unset($address_data["created_at"]);
						unset($address_data["updated_at"]);
						unset($address_data["increment_id"]);

						$tmpdata = array_merge($tmpdata, $address_data);
						$tmp_customer_data = array_filter($tmpdata, 'is_scalar');	// ommit sub array levels
						if (!isset($tmp_customer_data["store_id"]))
						{
							$customer_data[] = $tmp_customer_data;
						}
						else
						if ($tmp_customer_data["store_id"] == 0 || $tmp_customer_data["store_id"] == $this->mcapi->APIStoreID)
						{
							$customer_data[] = $tmp_customer_data;
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

				// Queue data
				$this->mcapi->QueueAPICall("update_magento_customers", $customer_data);

				// Clear collection and free memory
				$customersCollection->clear();
				unset($customer_data);
				
				// Remove job if finished
				if (($row["page"]+1) > $pages)
				{
					$sql = "DELETE FROM `".$this->tn__mc_api_pages."` WHERE id = ".$row["id"]."";
					$this->connection->query($sql);
	
					$mc_import_data = array("store_id" => $row["store_id"], "collection" => $row["collection"], "page" => ($row["page"]+1), "total" => (int)$pages, "datetime" => time(), "finished" => 1);
					$this->mcapi->QueueAPICall("update_magento_progress", $mc_import_data);
				}
				else
				// Update job if not finished
				{
					$sql = "UPDATE `".$this->tn__mc_api_pages."` SET page = ".($row["page"]+1).", total = ".(int)$pages.", datetime = ".time()." WHERE id = ".$row["id"]."";
					$this->connection->query($sql);
	
					$mc_import_data = array("store_id" => $row["store_id"], "collection" => $row["collection"], "page" => ($row["page"]+1), "total" => (int)$pages, "datetime" => time(), "finished" => 0);
					$this->mcapi->QueueAPICall("update_magento_progress", $mc_import_data);
				}
			}

			if ($row["collection"] == "newsletter/subscriber_collection")
			{
				// one transaction
				// get mailing list for this store
				$subscriber_data = array();
				$mailinglistCollection = $this->objectmanager->create('Magento\Newsletter\Model\Subscriber')->setStoreId($this->mcapi->APIStoreID)->getCollection();
				$mailinglistCollection->setPageSize($this->helper->getConfig('mailcampaignshistoricalsync/general/import_mailing_list_amount', $this->mcapi->APIStoreID));

				$pages = $mailinglistCollection->getLastPageNumber();
				$mailinglistCollection->setCurPage($currentPage);
				$mailinglistCollection->load();

				foreach($mailinglistCollection->getItems() as $subscriber)
				{
					try
					{
						$tmp = $subscriber->getData();
						if (!isset($tmp["store_id"]))
						{
							$subscriber_data[] = $tmp;
						}
						else
						if ($tmp["store_id"] == $this->mcapi->APIStoreID || $tmp["store_id"] == 0)
						{
							$subscriber_data[] = $tmp;
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

				$this->mcapi->QueueAPICall("update_magento_mailing_list", $subscriber_data);

				//clear collection and free memory
				$mailinglistCollection->clear();
				unset($subscriber_data);
				
				// Remove job if finished
				if (($row["page"]+1) > $pages)
				{
					$sql = "DELETE FROM `".$this->tn__mc_api_pages."` WHERE id = ".$row["id"]."";
					$this->connection->query($sql);
	
					$mc_import_data = array("store_id" => $row["store_id"], "collection" => $row["collection"], "page" => ($row["page"]+1), "total" => (int)$pages, "datetime" => time(), "finished" => 1);
					$this->mcapi->QueueAPICall("update_magento_progress", $mc_import_data);
				}
				else
				// Update job if not finished
				{
					$sql = "UPDATE `".$this->tn__mc_api_pages."` SET page = ".($row["page"]+1).", total = ".(int)$pages.", datetime = ".time()." WHERE id = ".$row["id"]."";
					$this->connection->query($sql);
	
					$mc_import_data = array("store_id" => $row["store_id"], "collection" => $row["collection"], "page" => ($row["page"]+1), "total" => (int)$pages, "datetime" => time(), "finished" => 0);
					$this->mcapi->QueueAPICall("update_magento_progress", $mc_import_data);
				}
			}

			if ($row["collection"] == "catalog/product")
			{
				// one transaction
				// loop trough all products for this store
				$product_data = array();
				$related_products = array();
				$i = 0;

				$productsCollection = $this->objectmanager->create('Magento\Catalog\Model\Product')->setStoreId($this->mcapi->APIStoreID)->getCollection();
				$productsCollection->setPageSize($this->helper->getConfig('mailcampaignshistoricalsync/general/import_products_amount', $this->mcapi->APIStoreID));
				$pages = $productsCollection->getLastPageNumber();

				$productsCollection->setCurPage($currentPage);
				$productsCollection->load();

				foreach ($productsCollection as $product)
				{
					try
					{
						// Load data
						//$product = $this->objectmanager->create('Magento\Catalog\Model\Product')->load($product->getId());
						$product = $this->productrepository->getById($product->getId(), false, $this->mcapi->APIStoreID);

						$attributes = $product->getAttributes();
						foreach ($attributes as $attribute)
						{
							$data = $product->getData($attribute->getAttributeCode());
							if (!is_array($data)) $product_data[$i][$attribute->getAttributeCode()] = $data;
						}

						// product parent id
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
						$product_data[$i]["store_id"] = $this->mcapi->APIStoreID;

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

						// get related products
						$related_products = array();
						$related_product_collection = $product->getRelatedProductIds();
						$related_products[$product->getId()]["store_id"] = $this->mcapi->APIStoreID;
						if (!empty($related_product_collection) && sizeof($related_product_collection) > 0 && is_array($related_product_collection))
						{
							foreach($related_product_collection as $pdtid)
							{
								$related_products[$product->getId()]["products"][] = $pdtid;
							}
						}
						
						// get up sell products
						$upsell_products = array();
						$upsell_product_collection = $product->getUpSellProductIds();
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
						$crosssell_product_collection = $product->getCrossSellProductIds();
						if (!empty($crosssell_product_collection) && sizeof($crosssell_product_collection) > 0 && is_array($crosssell_product_collection))
						{
							$crosssell_products[$product->getId()]["store_id"] = $product_data[$i]["store_id"];
							foreach($crosssell_product_collection as $pdtid)
							{
								$crosssell_products[$product->getId()]["products"][] = $pdtid;
							}
						}

						$i++;
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

				$response = $this->mcapi->QueueAPICall("update_magento_categories", $category_data);
				$response = $this->mcapi->QueueAPICall("update_magento_products", $product_data);
				
				if (sizeof($related_products) > 0)
				{
					$this->mcapi->QueueAPICall("update_magento_related_products", $related_products);
					unset($related_products);
				}
				
				if (sizeof($crosssell_products) > 0)
				{
					$this->mcapi->QueueAPICall("update_magento_crosssell_products", $crosssell_products, 0);
					unset($crosssell_products);
				}
				
				if (sizeof($upsell_products) > 0)
				{
					$this->mcapi->QueueAPICall("update_magento_upsell_products", $upsell_products, 0);
					unset($upsell_products);
				}

				//clear collection and free memory
				$productsCollection->clear();

				unset($related_products);
				unset($product_data);
				
				
				// Remove job if finished
				if (($row["page"]+1) > $pages)
				{
					$sql = "DELETE FROM `".$this->tn__mc_api_pages."` WHERE id = ".$row["id"]."";
					$this->connection->query($sql);
	
					$mc_import_data = array("store_id" => $row["store_id"], "collection" => $row["collection"], "page" => ($row["page"]+1), "total" => (int)$pages, "datetime" => time(), "finished" => 1);
					$this->mcapi->QueueAPICall("update_magento_progress", $mc_import_data);
				}
				else
				// Update job if not finished
				{
					$sql = "UPDATE `".$this->tn__mc_api_pages."` SET page = ".($row["page"]+1).", total = ".(int)$pages.", datetime = ".time()." WHERE id = ".$row["id"]."";
					$this->connection->query($sql);
	
					$mc_import_data = array("store_id" => $row["store_id"], "collection" => $row["collection"], "page" => ($row["page"]+1), "total" => (int)$pages, "datetime" => time(), "finished" => 0);
					$this->mcapi->QueueAPICall("update_magento_progress", $mc_import_data);
				}
			}

			if ($row["collection"] == "sales/order")
			{
				// get all orders
				$i = 0;
				$mc_send_data = array();
				$mc_import_data = array();
				$ordersCollection = $this->objectmanager->create('Magento\Sales\Model\Order')->setStoreId($this->mcapi->APIStoreID)->getCollection();
				$ordersCollection->setPageSize($this->helper->getConfig('mailcampaignshistoricalsync/general/import_order_amount', $this->mcapi->APIStoreID));
				$pages = $ordersCollection->getLastPageNumber();

				$ordersCollection->setCurPage($currentPage);
				$ordersCollection->load();
				foreach ($ordersCollection as $order)
				{
					try
					{
						$mc_order_data = (array)$order->getData();

						$address = array();
						if(is_object($order->getShippingAddress()))
						{
							$address = (array)$order->getShippingAddress()->getData();
						}
						else
						if(is_object($order->getBillingAddress()))
						{
							$address = (array)$order->getBillingAddress()->getData();
						}

						if ($mc_order_data["store_id"] == $this->mcapi->APIStoreID || $mc_order_data["store_id"] == 0)
						{
							if(isset($mc_order_data["store_id"]))				{ $mc_data["store_id"] =		$mc_order_data["store_id"]				;}
							if(isset($mc_order_data["entity_id"]))				{ $mc_data["order_id"] =		$mc_order_data["entity_id"]				;}
							if(isset($mc_order_data["increment_id"]))			{ $mc_data["order_name"] =		$mc_order_data["increment_id"]			;}
							if(isset($mc_order_data["status"]))					{ $mc_data["order_status"] =	$mc_order_data["status"]				;}
							if(isset($mc_order_data["grand_total"]))			{ $mc_data["order_total"] =		$mc_order_data["grand_total"]			;}
							if(isset($mc_order_data["customer_id"]))			{ $mc_data["customer_id"] =		$mc_order_data["customer_id"]			;}
							if(isset($mc_order_data["quote_id"]))				{ $mc_data["quote_id"] =		$mc_order_data["quote_id"]				;}
							if(isset($mc_order_data["customer_email"]))			{ $mc_data["customer_email"] =	$mc_order_data["customer_email"]		;}
							if(isset($mc_order_data["customer_firstname"]))		{ $mc_data["firstname"] =		$mc_order_data["customer_firstname"]	;}
							if(isset($mc_order_data["customer_lastname"]))		{ $mc_data["lastname"] =		$mc_order_data["customer_lastname"]		;}
							if(isset($mc_order_data["customer_middlename"]))	{ $mc_data["middlename"] =		$mc_order_data["customer_middlename"]	;}
							if(isset($mc_order_data["customer_dob"]))			{ $mc_data["dob"] =				$mc_order_data["customer_dob"]			;}
							if(isset($address["telephone"]))					{ $mc_data["telephone"] =		$address["telephone"]					;}
							if(isset($address["street"]))						{ $mc_data["street"] =			$address["street"]						;}
							if(isset($address["postcode"]))						{ $mc_data["postcode"] =		$address["postcode"]					;}
							if(isset($address["city"]))							{ $mc_data["city"] =			$address["city"]						;}
							if(isset($address["region"]))						{ $mc_data["region"] =			$address["region"]						;}
							if(isset($address["country_id"]))					{ $mc_data["country_id"] =		$address["country_id"]					;}
							if(isset($address["company"]))						{ $mc_data["company"] =			$address["company"]						;}
							if(isset($mc_order_data["created_at"]))				{ $mc_data["created_at"] =		$mc_order_data["created_at"]			;}
							if(isset($mc_order_data["updated_at"]))				{ $mc_data["updated_at"] =		$mc_order_data["updated_at"]			;}
							if(is_object($order->getShippingAmount()))			$mc_data["shipping_amount"] = $order->getShippingAmount();
							if(is_object($order->getShippingInclTax()))			$mc_data["shipping_amount_incl_tax"] = $order->getShippingInclTax();
							if(is_object($order->getBaseSubtotalInclTax()))		$mc_data["subtotalInclTaxExclDiscount"] = $order->getBaseSubtotalInclTax();
							if(is_object($order->getDiscountAmount()))			$mc_data["discount"] = $order->getDiscountAmount();
							
							$mc_send_data[$i][] = $mc_data;
							$i++;
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

				$response = $this->mcapi->QueueAPICall("update_magento_multiple_orders", $mc_send_data);
				unset($mc_import_data);
				unset($mc_data);
				unset($mc_send_data);

				//clear collection and free memory
				$ordersCollection->clear();
				
				// Remove job if finished
				if (($row["page"]+1) > $pages)
				{
					$sql = "DELETE FROM `".$this->tn__mc_api_pages."` WHERE id = ".$row["id"]."";
					$this->connection->query($sql);
	
					$mc_import_data = array("store_id" => $row["store_id"], "collection" => $row["collection"], "page" => ($row["page"]+1), "total" => (int)$pages, "datetime" => time(), "finished" => 1);
					$this->mcapi->QueueAPICall("update_magento_progress", $mc_import_data);
				}
				else
				// Update job if not finished
				{
					$sql = "UPDATE `".$this->tn__mc_api_pages."` SET page = ".($row["page"]+1).", total = ".(int)$pages.", datetime = ".time()." WHERE id = ".$row["id"]."";
					$this->connection->query($sql);
	
					$mc_import_data = array("store_id" => $row["store_id"], "collection" => $row["collection"], "page" => ($row["page"]+1), "total" => (int)$pages, "datetime" => time(), "finished" => 0);
					$this->mcapi->QueueAPICall("update_magento_progress", $mc_import_data);
				}
			}

			if ($row["collection"] == "sales/order/products")
			{
				//tables
				//$this->tn__mc_api_pages = $this->resource->getTableName('mc_api_pages');
				//$this->tn__mc_api_queue = $this->resource->getTableName('mc_api_queue');

				// Get table names
				$tn__sales_flat_quote 					= $this->resource->getTableName('quote');
				$tn__sales_flat_order 					= $this->resource->getTableName('sales_order');
				$tn__sales_flat_order_item 				= $this->resource->getTableName('sales_order_item');
				$tn__sales_flat_quote_item 				= $this->resource->getTableName('quote_item');
				$tn__catalog_category_product 			= $this->resource->getTableName('catalog_category_product');
				$tn__catalog_category_entity_varchar 	= $this->resource->getTableName('catalog_category_entity_varchar');
				$tn__eav_entity_type 					= $this->resource->getTableName('eav_entity_type');
				$tn__catalog_category_entity 			= $this->resource->getTableName('catalog_category_entity');

				$pagesize = $this->helper->getConfig('mailcampaignshistoricalsync/general/import_order_products_amount', $this->mcapi->APIStoreID);

				// order items
				$sql        = "SELECT COUNT(*) AS pages FROM `".$tn__sales_flat_order."` AS o INNER JOIN ".$tn__sales_flat_order_item." AS oi ON oi.order_id = o.entity_id WHERE o.store_id = ".$this->mcapi->APIStoreID." OR o.store_id = 0";
				$pages 		= ceil($this->connection->fetchOne($sql) / $pagesize);

				$sql        = "SELECT o.entity_id as order_id, o.store_id, oi.product_id as product_id, oi.qty_ordered, oi.price, oi.name, oi.sku, o.customer_id
				FROM `".$tn__sales_flat_order."` AS o
				INNER JOIN ".$tn__sales_flat_order_item." AS oi ON oi.order_id = o.entity_id
				WHERE o.store_id = ".$this->mcapi->APIStoreID." OR o.store_id = 0
				ORDER BY  `o`.`created_at` ASC
				LIMIT ".$pagesize." OFFSET ".(($row["page"]-1) * $pagesize)."
				";

				$tmp_rows       = $this->connection->fetchAll($sql);
				$mc_import_data = array(); $i = 0;
				foreach ($tmp_rows as $tmp_row)
				{
					foreach ($tmp_row as $key => $value)
					{
						if (!is_numeric($key))
						{
							$mc_import_data[$i][$key] = $value;
						}
					}

					// get categories
					$categories = array();
					$category_data = array();
					if ($tmp_row["product_id"] > 0)
					{
						try
						{
							$objectMan =  \Magento\Framework\App\ObjectManager::getInstance();
							$product 	= $this->productrepository->getById($tmp_row["product_id"]);
							foreach ($product->getCategoryIds() as $category_id)
							{
								$categories[] = $category_id;
								$cat = $objectMan->create('Magento\Catalog\Model\Category')->load($category_id);
								$category_data[$category_id] = $cat->getName();
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

					$mc_import_data[$i]["categories"] = json_encode($categories);
					$i++;
				}

				// post items
				$response = $this->mcapi->QueueAPICall("update_magento_categories", $category_data);
				$response = $this->mcapi->QueueAPICall("update_magento_order_products", $mc_import_data);

				// clear
				unset($mc_import_data);
				
				// Remove job if finished
				if (($row["page"]+1) > $pages)
				{
					$sql = "DELETE FROM `".$this->tn__mc_api_pages."` WHERE id = ".$row["id"]."";
					$this->connection->query($sql);
	
					$mc_import_data = array("store_id" => $row["store_id"], "collection" => $row["collection"], "page" => ($row["page"]+1), "total" => (int)$pages, "datetime" => time(), "finished" => 1);
					$this->mcapi->QueueAPICall("update_magento_progress", $mc_import_data);
				}
				else
				// Update job if not finished
				{
					$sql = "UPDATE `".$this->tn__mc_api_pages."` SET page = ".($row["page"]+1).", total = ".(int)$pages.", datetime = ".time()." WHERE id = ".$row["id"]."";
					$this->connection->query($sql);
	
					$mc_import_data = array("store_id" => $row["store_id"], "collection" => $row["collection"], "page" => ($row["page"]+1), "total" => (int)$pages, "datetime" => time(), "finished" => 0);
					$this->mcapi->QueueAPICall("update_magento_progress", $mc_import_data);
				}
			}


			// break on timeout 60 seconds
			if (time() > ($starttime + 60)) break;
		}

		return $this;
    }
}
