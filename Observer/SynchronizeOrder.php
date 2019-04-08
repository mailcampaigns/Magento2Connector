<?php

namespace MailCampaigns\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

class SynchronizeOrder implements ObserverInterface
{
    protected $logger;
  	protected $resource;
  	protected $connection;
  	protected $helper;
  	protected $storemanager;
  	protected $objectmanager;
  	protected $productrepository;
	protected $taxhelper;
  	protected $mcapi;

    public function __construct(
		\MailCampaigns\Connector\Helper\Data $dataHelper,
		\Magento\Framework\App\ResourceConnection $Resource,
		\MailCampaigns\Connector\Helper\MailCampaigns_API $mcapi,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\Catalog\Helper\Data $taxHelper,
        Logger $logger
    ) {
		$this->resource 				= $Resource;
		$this->logger 				= $logger;
		$this->helper 				= $dataHelper;
		$this->mcapi 				= $mcapi;
		$this->storemanager 			= $storeManager;
		$this->objectmanager 		= $objectManager;
		$this->productrepository	= $productRepository;
		$this->taxhelper 			= $taxHelper;
    }

    public function execute(EventObserver $observer)
    {
		//database connection
		$this->connection = $this->resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);

		// set vars
		$this->mcapi->APIWebsiteID 		= $observer->getWebsite();
      	$this->mcapi->APIStoreID 		= $observer->getStore();
		$this->mcapi->APIKey 			= $this->helper->getConfig('mailcampaignsapi/general/api_key', $this->mcapi->APIStoreID);
  		$this->mcapi->APIToken 			= $this->helper->getConfig('mailcampaignsapi/general/api_token', $this->mcapi->APIStoreID);
		$this->mcapi->ImportOrders 		= $this->helper->getConfig('mailcampaignsrealtimesync/general/import_orders',$this->mcapi->APIStoreID);

  		if ($this->mcapi->ImportOrders == 1)
		{
			try
			{
				// Retrieve the order being updated from the event observer
				$order = $observer->getEvent()->getOrder();
				$shipping = $order->getShippingAddress()->getData();
				// $billing = $order->getBillingAddress()->getData();
				$mc_order_data = $order;

				if ($mc_order_data["entity_id"] > 0)
				{
					// get price including VAT
					$mc_order_data["grand_total"] = $this->taxhelper->getTaxPrice($product, $mc_order_data["grand_total"], true, NULL, NULL, NULL, $this->mcapi->APIStoreID, NULL, true);
					
          			$mc_data = array(
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

					$this->mcapi->DirectOrQueueCall("update_magento_orders", $mc_data);

					// Get table names
					$tn__sales_flat_quote 					= $this->resource->getTableName('quote');
					$tn__sales_flat_order 					= $this->resource->getTableName('sales_order');
					$tn__sales_flat_order_item 				= $this->resource->getTableName('sales_order_item');
					$tn__sales_flat_quote_item 				= $this->resource->getTableName('quote_item');
					$tn__catalog_category_product 			= $this->resource->getTableName('catalog_category_product');
					$tn__catalog_category_entity_varchar 	= $this->resource->getTableName('catalog_category_entity_varchar');
					$tn__eav_entity_type 					= $this->resource->getTableName('eav_entity_type');
					$tn__catalog_category_entity 			= $this->resource->getTableName('catalog_category_entity');

					// order items
					$sql        = "SELECT o.entity_id as order_id, o.store_id, oi.product_id as product_id, oi.qty_ordered, oi.price, oi.name, oi.sku, o.customer_id
					FROM `".$tn__sales_flat_order."` AS o
					INNER JOIN `".$tn__sales_flat_order_item."` AS oi ON oi.order_id = o.entity_id
					WHERE o.entity_id = ".$mc_order_data["entity_id"]."
					ORDER BY  `o`.`updated_at` DESC";
					$rows       = $this->connection->fetchAll($sql);

					$mc_import_data = array(); $i = 0;
					foreach ($rows as $row)
					{
						foreach ($row as $key => $value)
						{
							if (!is_numeric($key)) $mc_import_data[$i][$key] = $value;
						}
					
						// get categories
						$categories = array();
						$category_data = array();
						if ($row["product_id"] > 0)
						{
							try
							{
								$objectMan =  \Magento\Framework\App\ObjectManager::getInstance();
								$product 	= $this->productrepository->getById($row["product_id"]);
								
								// Get Price Incl Tax
								$mc_import_data[$i]["price"] = $this->taxhelper->getTaxPrice($product, $mc_import_data[$i]["price"], true, NULL, NULL, NULL, $this->mcapi->APIStoreID, NULL, true);
								
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

					if ($i > 0)
					{
						$response = $this->mcapi->DirectOrQueueCall("update_magento_categories", $category_data);
						$response = $this->mcapi->DirectOrQueueCall("update_magento_order_products", $mc_import_data);
					}
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
