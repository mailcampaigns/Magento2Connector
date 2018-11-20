<?php

namespace MailCampaigns\Connector\Cron;

class SyncCron {

 	protected $helper;
	protected $resource;
	protected $connection;
	protected $objectmanager;
	protected $customerrepository;
	protected $countryinformation;
	protected $productrepository;
	protected $tn__mc_api_pages;
	protected $tn__mc_api_queue;
	protected $mcapi;

    public function __construct(
   	\MailCampaigns\Connector\Helper\Data $dataHelper,
		\Magento\Framework\App\ResourceConnection $Resource,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcApi,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\Directory\Api\CountryInformationAcquirerInterface $countryInformation
    ) {
        $this->resource 				= $Resource;
		$this->helper 				= $dataHelper;
		$this->mcapi 				= $mcApi;
		$this->objectmanager 		= $objectManager;
		$this->customerrepository 	= $customerRepository;
		$this->countryinformation	= $countryInformation;
		$this->productrepository	= $productRepository;
    }

    public function execute()
	{
		//database connection
		$this->connection = $this->resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);

		//tables
		$this->tn__mc_api_pages = $this->resource->getTableName('mc_api_pages');
		$this->tn__mc_api_queue = $this->resource->getTableName('mc_api_queue');

		// Process one page per each cron
		$sql        = "SELECT * FROM `".$this->tn__mc_api_pages."`";
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
				$customersCollection->setPageSize(100);

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
			}

			if ($row["collection"] == "newsletter/subscriber_collection")
			{
				// one transaction
				// get mailing list for this store
				$subscriber_data = array();
				$mailinglistCollection = $this->objectmanager->create('Magento\Newsletter\Model\Subscriber')->setStoreId($this->mcapi->APIStoreID)->getCollection();
				$mailinglistCollection->setPageSize(100);

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
			}

			if ($row["collection"] == "catalog/product")
			{
				// one transaction
				// loop trough all products for this store
				$product_data = array();
				$related_products = array();
				$i = 0;

				$productsCollection = $this->objectmanager->create('Magento\Catalog\Model\Product')->setStoreId($this->mcapi->APIStoreID)->getCollection();
				$productsCollection->setPageSize(10);
				$pages = $productsCollection->getLastPageNumber();

				$productsCollection->setCurPage($currentPage);
				$productsCollection->load();

				foreach ($productsCollection as $product)
				{
					try
					{
						// Load data
						$product = $this->objectmanager->create('Magento\Catalog\Model\Product')->load($product->getId());

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
						if (!empty($product_images) && sizeof($product_images) > 0 && is_array($product_images))
						{
							$product_data[$i]["mc:image_url_".$image_id++.""] = $image->getUrl();
						}

						// link
						$product_data[$i]["mc:product_url"] = $product->getProductUrl();

						// store id
						$product_data[$i]["store_id"] = $this->mcapi->APIStoreID;

						// product parent id
						if($product->getId() != "")
						{
							$objectMan =  \Magento\Framework\App\ObjectManager::getInstance();
							$parent_product = $objectMan->create('Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable')->getParentIdsByChild($product->getId());
							if(isset($parent_product[0]))
							{
								$product_data[$i]["parent_id"] = $parent_product[0];
							}
						}
						
						// Categories
						$category_data = array();
						$objectMan =  \Magento\Framework\App\ObjectManager::getInstance();
						foreach ($product->getCategoryIds() as $category_id)
						{	
							$categories[] = $category_id;
							$cat = $objectMan->create('Magento\Catalog\Model\Category')->load($category_id);
							$category_data[$category_id] = $cat->getName();
						}
						$product_data[$i]["categories"] = json_encode(array_unique($categories));

						// get related products
						$related_product_collection = $product->getRelatedProductIds();
						$related_products[$product->getId()]["store_id"] = $this->mcapi->APIStoreID;
						if (!empty($related_product_collection) && sizeof($related_product_collection) > 0 && is_array($related_product_collection))
						{
							foreach($related_product_collection as $pdtid)
							{
								$related_products[$product->getId()]["products"][] = $pdtid;
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
				$response = $this->mcapi->QueueAPICall("update_magento_related_products", $related_products);

				//clear collection and free memory
				$productsCollection->clear();

				unset($related_products);
				unset($product_data);
			}

			if ($row["collection"] == "sales/order")
			{
				// get all orders
				$mc_import_data = array();
				$ordersCollection = $this->objectmanager->create('Magento\Sales\Model\Order')->setStoreId($this->mcapi->APIStoreID)->getCollection();
				$ordersCollection->setPageSize(50);
				$pages = $ordersCollection->getLastPageNumber();

				$ordersCollection->setCurPage($currentPage);
				$ordersCollection->load();
				foreach ($ordersCollection as $order)
				{
					try
					{
						$mc_order_data = (array)$order->getData();
            			$shipping = (array)$order->getShippingAddress()->getData();

						if ($mc_order_data["store_id"] == $this->mcapi->APIStoreID || $mc_order_data["store_id"] == 0)
						{
							$mc_import_data[] = array(
                				"store_id" => $mc_order_data["store_id"],
								"order_id" => $mc_order_data["entity_id"],
								"order_name" => $mc_order_data["increment_id"],
								"order_status" => $mc_order_data["status"],
								"order_total" => $mc_order_data["grand_total"],
								"customer_id" => $mc_order_data["customer_id"],
								//"visitor_id" => $mc_order_data["visitor_id"],
								"quote_id" => $mc_order_data["quote_id"],
								"customer_email" => $mc_order_data["customer_email"],
								"firstname" => $mc_order_data["customer_firstname"],
								"lastname" => $mc_order_data["customer_lastname"],
								"middlename" => $mc_order_data["customer_middlename"],
								"dob" => $mc_order_data["customer_dob"],
								"telephone" => $shipping["telephone"],
								"street" => $shipping["street"],
								"postcode" => $shipping["postcode"],
								"city" => $shipping["city"],
								"region" => $shipping["region"],
								"country_id" => $shipping["country_id"],
								"company" => $shipping["company"],
								"created_at" => $mc_order_data["created_at"],
								"updated_at" => $mc_order_data["updated_at"]
								);
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

				$response = $this->mcapi->QueueAPICall("update_magento_multiple_orders", $mc_import_data);
				unset($mc_import_data);

				//clear collection and free memory
				$ordersCollection->clear();
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

				$pagesize = 50;

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
			}

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

			// break on timeout 60 seconds
			if (time() > ($starttime + 60)) break;
		}

		return $this;
    }
}
