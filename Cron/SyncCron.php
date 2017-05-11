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
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\Directory\Api\CountryInformationAcquirerInterface $countryInformation
    ) {
        $this->resource 				= $Resource;
		$this->helper 				= $dataHelper;
		$this->mcapi 				= $mcapi;
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
			$currentPage 			= $row["page"];
			$this->APIStoreID 		= $row["store_id"];
			$this->APIKey 			= $this->helper->getConfig('mailcampaignsapi/general/api_key', $this->mcapi->APIStoreID);
			$this->APIToken 			= $this->helper->getConfig('mailcampaignsapi/general/api_token', $this->mcapi->APIStoreID);
			
			if ($row["collection"] == "customer/customer")
			{
				// one transaction
				// get all customers
				$customer_data = array();
				$customersCollection = $this->objectmanager->create('Magento\Customer\Model\Customer')->getCollection();
				$customersCollection->addAttributeToSelect('*');				
				$customersCollection->setPageSize(100);
				
				$pages = $customersCollection->getLastPageNumber();

				$customersCollection->setCurPage($currentPage);
				$customersCollection->load();
						
				// Loop trough customers
				foreach ($customersCollection as $customer)
				{
					$tmpdata = $customer->getData();
					
					$address_data = array();
					$customerAddressId = $customer->getDefaultBilling();
					if ($customerAddressId)
					{
						$address 		= $this->objectmanager->create('Magento\Customer\Model\Address')->load($customerAddressId);
						$address_data 	= $address->getData();
						
						$country_id 	= $address_data["country_id"];
						$country 		= $this->countryinformation->getCountryInfo($country_id);
						$country_name 	= $country->getFullNameLocale();
						
						$address_data["country_name"] = $country_name;
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
					if ($tmp_customer_data["store_id"] == 0 || $tmp_customer_data["store_id"] == $this->APIStoreID) $customer_data[] = $tmp_customer_data;		
				}
								
				// Queue data
				$this->mcapi->QueueAPICall("update_magento_customers", $customer_data, 0);
				
				// Clear collection and free memory
				$customersCollection->clear();	
				unset($customer_data);
			}
			else
			if ($row["collection"] == "newsletter/subscriber_collection")
			{
				// one transaction
				// get mailing list for this store
				$subscriber_data = array();
				$mailinglistCollection = $this->objectmanager->create('Magento\Newsletter\Model\Subscriber')->getCollection();
				$mailinglistCollection->setPageSize(100);
				
				$pages = $mailinglistCollection->getLastPageNumber();
				$mailinglistCollection->setCurPage($currentPage);
				$mailinglistCollection->load();
			
				foreach($mailinglistCollection->getItems() as $subscriber)
				{
					$tmp = $subscriber->getData();
					if ($tmp["store_id"] == $this->APIStoreID || $tmp["store_id"] == 0)
					{
						$subscriber_data[] = $tmp;
					}
				}
				
				$this->mcapi->QueueAPICall("update_magento_mailing_list", $subscriber_data, 0);
				
				//clear collection and free memory
				$mailinglistCollection->clear();
				unset($subscriber_data);
			}	
			else
			if ($row["collection"] == "catalog/product")
			{
				// one transaction
				// loop trough all products for this store
				$product_data = array(); 
				$related_products = array();
				$i = 0;

				$productsCollection = $this->objectmanager->create('Magento\Catalog\Model\Product')->getCollection();
				$productsCollection->setPageSize(10);
				$pages = $productsCollection->getLastPageNumber();
				
				$productsCollection->setCurPage($currentPage);
				$productsCollection->load();
		 
				foreach ($productsCollection as $product)
				{
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
						$product_data[$i]["mc:image_url_".$image_id++.""] = $image->getUrl();
					} 
	
					// link
					$product_data[$i]["mc:product_url"] = $product->getProductUrl();
					
					// store id
					$product_data[$i]["store_id"] = $this->APIStoreID;
					
					// get related products
					$related_product_collection = $product->getRelatedProductIds();
					$related_products[$product->getId()]["store_id"] = $this->APIStoreID;;
					foreach($related_product_collection as $pdtid)
					{
						$related_products[$product->getId()]["products"][] = $pdtid;
					}
					
					$i++;
				}				
				
				$response = $this->mcapi->QueueAPICall("update_magento_products", $product_data, 0);			
				$response = $this->mcapi->QueueAPICall("update_magento_related_products", $related_products, 0);			
	
				//clear collection and free memory
				$productsCollection->clear();
				
				unset($related_products);			
				unset($product_data);	
			}
			else
			if ($row["collection"] == "sales/order")
			{
				// get all orders
				$mc_import_data = array();
				$ordersCollection = $this->objectmanager->create('Magento\Sales\Model\Order')->getCollection();
				$ordersCollection->setPageSize(50);
				$pages = $ordersCollection->getLastPageNumber();
				
				$ordersCollection->setCurPage($currentPage);
				$ordersCollection->load();
				foreach ($ordersCollection as $order)
				{
					$mc_order_data = (array)$order->getData();
					
					if ($mc_order_data["store_id"] == $this->APIStoreID || $mc_order_data["store_id"] == 0)
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
							"created_at" => $mc_order_data["created_at"],
							"updated_at" => $mc_order_data["updated_at"]
							);
					}
				}
							
				$response = $this->mcapi->QueueAPICall("update_magento_multiple_orders", $mc_import_data);
				unset($mc_import_data);
				
				//clear collection and free memory
				$ordersCollection->clear();
			}
			else
			if ($row["collection"] == "sales/order/products")
			{
				//tables
				$this->tn__mc_api_pages = $this->resource->getTableName('mc_api_pages');
				$this->tn__mc_api_queue = $this->resource->getTableName('mc_api_queue');
				
				// Get table names
				$tn__sales_flat_quote 					= $this->resource->getTableName('quote');
				$tn__sales_flat_order 					= $this->resource->getTableName('sales_order');
				$tn__sales_flat_order_item 				= $this->resource->getTableName('sales_order_item');
				$tn__sales_flat_quote_item 				= $this->resource->getTableName('quote_item');
				$tn__catalog_category_product 			= $this->resource->getTableName('catalog_category_product');
				$tn__catalog_category_entity_varchar 	= $this->resource->getTableName('catalog_category_entity_varchar');
				$tn__eav_entity_type 					= $this->resource->getTableName('eav_entity_type');
				$tn__catalog_category_entity 			= $this->resource->getTableName('catalog_category_entity');
				
				$pagesize = 15;
				
				// order items
				$sql        = "SELECT COUNT(*) AS pages FROM `".$tn__sales_flat_order."` AS o INNER JOIN ".$tn__sales_flat_order_item." AS oi ON oi.order_id = o.entity_id WHERE o.store_id = ".$this->APIStoreID." OR o.store_id = 0";
				$pages 		= ceil($this->connection->fetchOne($sql) / $pagesize);
				
				$sql        = "SELECT o.entity_id as order_id, o.store_id, oi.product_id as product_id, oi.qty_ordered, oi.price, oi.name, oi.sku, o.customer_id
				FROM `".$tn__sales_flat_order."` AS o
				INNER JOIN ".$tn__sales_flat_order_item." AS oi ON oi.order_id = o.entity_id
				WHERE o.store_id = ".$this->APIStoreID." OR o.store_id = 0
				ORDER BY  `o`.`updated_at` DESC
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
					if ($tmp_row["product_id"] > 0)
					{
						$product 	= $this->productrepository->getById($tmp_row["product_id"]);
						$categories = $product->getCategoryIds();
					}
					
					$mc_import_data[$i]["categories"] = json_encode($categories);
					$i++;
				}	
				
				// post items
				$response = $this->mcapi->QueueAPICall("update_magento_order_products", $mc_import_data);		
				
				// clear
				unset($mc_import_data);				
			}
			
			// Remove job if finished
			if (($row["page"]+1) > $pages)
			{
				$sql = "DELETE FROM `".$this->tn__mc_api_pages."` WHERE id = ".$row["id"]."";
				$this->connection->query($sql);
			}
			else
			// Update job if not finished
			{
				$sql = "UPDATE `".$this->tn__mc_api_pages."` SET page = ".($row["page"]+1).", total = ".(int)$pages.", datetime = ".time()." WHERE id = ".$row["id"]."";
				$this->connection->query($sql);
			}	
		}
			
		return $this;
    }
}