<?php

namespace MailCampaigns\Magento2Connector\Cron;

use Exception;
use Magento\Cron\Model\Schedule;
use Magento\Newsletter\Model\ResourceModel\Subscriber as SubscriberResourceModel;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\StoreManagerInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;

class StatusCron extends AbstractCron
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var SubscriberResourceModel
     */
    protected $subscriberResourceModel;

    /**
     * @var SubscriberFactory
     */
    protected $subscriberFactory;

    /**
     * @var Subscriber
     */
    protected $subscriber;

    public function __construct(
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper,
        StoreManagerInterface $storeManager,
        SubscriberResourceModel $subscriberResourceModel,
        SubscriberFactory $subscriberFactory,
        Subscriber $subscriber
    ) {
        parent::__construct($apiHelper, $logHelper);
        $this->storeManager = $storeManager;
        $this->subscriberResourceModel = $subscriberResourceModel;
        $this->subscriberFactory = $subscriberFactory;
        $this->subscriber = $subscriber;
    }

    /**
     * @inheritDoc
     */
    public function execute(Schedule $schedule): void
    {
        try {
            $stores = $this->storeManager->getStores();

            foreach ($stores as $store) {
                $this->storeManager->setCurrentStore($store);
                $response = $this->apiHelper->getUpdates($store->getId());

                // Get the array containing subscriber data from response.
                if (true === isset($response['message'])) {
                    $updates = json_decode($response['message'], true);
                }

                // Stop here if there are no updates to process at this moment.
                if (false === isset($updates) || count($updates) < 1) {
                    continue;
                }

                // Add log entry for debugging purposes.
                $this->logUpdate($store->getId(), $updates);

                foreach ($updates as $update) {
                    // Gather the needed data to create or update a subscriber.
                    $email = $update['E-mail'];
                    $status = $this->mapStatus($update);

                    // Load the subscriber object (model) by email address.
                    $subscriber = $this->subscriber->loadByEmail($email);

                    // Create a new subscriber first if none was found.
                    if (false === ($subscriber->getId() > 1)) {
                        $subscriber = $this->subscriberFactory->create();
                    }

                    // Set the received values and save the subscriber.
                    $subscriber
                        ->setStatus($status)
                        ->setEmail($email);

                    $this->subscriberResourceModel->save($subscriber);
                }
            }
        } catch (ApiCredentialsNotSetException $e) {
            // Just add a debug message to the filelog.
            $this->logger->addDebug($e->getMessage());
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }
    }

    /**
     * Adds a log entry describing the update.
     *
     * @param int $storeId
     * @param array $update
     * @return $this
     */
    protected function logUpdate(int $storeId, array $update): self
    {
        $logMsg = sprintf(
            'Received status update for %d subscriber(s) (store #%d).',
            count($update),
            $storeId
        );

        $this->logger->addDebug($logMsg, ['update_data' => $update]);

        return $this;
    }

    /**
     * Maps MailCampaigns to Magento subscriber status.
     *
     * @param array $update
     * @return int
     */
    protected function mapStatus(array $update): int
    {
        $active = (int)$update['active'];
        $status = (int)$update['status'];

        // Map status.
        if ($active === 0) {
            return Subscriber::STATUS_NOT_ACTIVE;
        } elseif ($status === 0) {
            return Subscriber::STATUS_UNSUBSCRIBED;
        } elseif ($status === 1) {
            return Subscriber::STATUS_SUBSCRIBED;
        }

        return 0;
    }
}
