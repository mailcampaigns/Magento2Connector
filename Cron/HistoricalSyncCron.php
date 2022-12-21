<?php

namespace MailCampaigns\Magento2Connector\Cron;

use Exception;
use LogicException;
use Magento\Cron\Model\Schedule;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\CustomerSynchronizerInterface;
use MailCampaigns\Magento2Connector\Api\OrderSynchronizerInterface;
use MailCampaigns\Magento2Connector\Api\ProductSynchronizerInterface;
use MailCampaigns\Magento2Connector\Api\SubscriberSynchronizerInterface;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;
use MailCampaigns\Magento2Connector\Model\ApiPage;

class HistoricalSyncCron extends AbstractCron
{
    /**
     * @var int Timestamp of starting time of the execution.
     */
    protected $startTime;

    /**
     * @var int
     */
    protected static $timeoutInterval = 55;

    /**
     * @var array|CustomerSynchronizerInterface[]
     */
    protected $synchronizers;

    public function __construct(
        ApiHelperInterface $apiHelper,
        CustomerSynchronizerInterface $customerSynchronizer,
        SubscriberSynchronizerInterface $subscriberSynchronizer,
        ProductSynchronizerInterface $productSynchronizer,
        OrderSynchronizerInterface $orderSynchronizer
    ) {
        parent::__construct($apiHelper);

        $this->synchronizers = [
            'customer/customer' => $customerSynchronizer,
            'newsletter/subscriber_collection' => $subscriberSynchronizer,
            'catalog/product' => $productSynchronizer,
            'sales/order' => $orderSynchronizer,
            'sales/order/products' => $orderSynchronizer
        ];
    }

    /**
     * @inheritDoc
     */
    public function execute(Schedule $schedule): void
    {
        try {
            $this->startTime = time();
            $pages = $this->apiPageHelper->getPages();

            // Silently stop here if there are no pages to be processed.
            if ($pages->count() < 1) {
                return;
            }

            // Loop through pending pages and continue as long as there are pages left
            // to process or timeout for this run has been reached.
            do {
                /** @var ApiPage $page */
                foreach ($pages as $page) {
                    $this->validateApiPage($page);

                    // Get the synchronizer instance.
                    $synchronizer = $this->synchronizers[$page->getCollection()];

                    // Perform the historical synchronization.
                    $synchronizer->historicalSync($page);

                    if ($this->hasTimedOut()) {
                        break;
                    }
                }

                // Nap time..
                usleep(50000);

                $pages = $this->apiPageHelper->getPages();
            } while (!$this->hasTimedOut() && $pages->count() > 0);
        } catch (ApiCredentialsNotSetException $e) {
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param ApiPage $page
     * @return $this
     */
    protected function validateApiPage(ApiPage $page): self
    {
        if (!array_key_exists($page->getCollection(), $this->synchronizers)) {
            $msgFormat = 'No synchronizer found: Unknown collection name `%s` (page record #%d)!';
            throw new LogicException(sprintf($msgFormat, $page->getCollection(), $page->getId()));
        }

        return $this;
    }

    /**
     * @return bool
     */
    protected function hasTimedOut(): bool
    {
        $now = time();

        $timedOut = $now > ($this->startTime + self::$timeoutInterval);

        return $timedOut;
    }
}
