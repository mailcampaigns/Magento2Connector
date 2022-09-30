<?php

namespace MailCampaigns\Magento2Connector\Cron;

use Exception;
use Magento\Cron\Model\Schedule;
use Magento\Framework\Data\Collection;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Api\OrderSynchronizerInterface;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;
use MailCampaigns\Magento2Connector\Model\ApiStatus;

class OrderCron extends AbstractCron
{
    /**
     * @var OrderSynchronizerInterface
     */
    protected $synchronizer;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    public function __construct(
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper,
        OrderSynchronizerInterface $synchronizer,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($apiHelper, $logHelper);
        $this->synchronizer = $synchronizer;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute(Schedule $schedule): void
    {
        try {
            $syncStartTs = $this->apiStatusHelper->getSyncStart(ApiStatus::TYPE_ORDER_CRON);
            $syncStartStr = gmdate('Y-m-d H:i:s', $syncStartTs);

            $this->apiStatusHelper->updateStatus(ApiStatus::TYPE_ORDER_CRON);

            $orders = $this->collectionFactory->create()
                ->addFieldToFilter(['updated_at', 'created_at'], [['gteq' => $syncStartStr],
                    ['gteq' => $syncStartStr]])
                ->setOrder('updated_at', Collection::SORT_ORDER_DESC);

            /** @var Order $order */
            foreach ($orders as $order) {
                $this->synchronizer->synchronize($order, $order->getStoreId());
            }
        } catch (ApiCredentialsNotSetException $e) {
            // Just add a debug message to the filelog.
            if (method_exists($this->logger, 'addDebug')) {
                $this->logger->addDebug($e->getMessage());
            }
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }
    }
}
