<?php

namespace MailCampaigns\Magento2Connector\Cron;

use Exception;
use Magento\Cron\Model\Schedule;
use Magento\Newsletter\Model\ResourceModel\Subscriber as SubscriberResourceModel;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\StoreManagerInterface;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
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
        StoreManagerInterface $storeManager,
        SubscriberResourceModel $subscriberResourceModel,
        SubscriberFactory $subscriberFactory,
        Subscriber $subscriber
    ) {
        parent::__construct($apiHelper);
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
        } catch (Exception $e) {
            throw $e;
        }
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
