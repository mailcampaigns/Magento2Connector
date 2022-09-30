<?php

namespace MailCampaigns\Magento2Connector\Cron;

use Exception;
use LogicException;
use Magento\Cron\Model\Schedule;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\CustomerSynchronizerInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Api\OrderSynchronizerInterface;
use MailCampaigns\Magento2Connector\Api\ProductSynchronizerInterface;
use MailCampaigns\Magento2Connector\Api\SubscriberSynchronizerInterface;
use MailCampaigns\Magento2Connector\Helper\ApiCredentialsNotSetException;
use MailCampaigns\Magento2Connector\Model\ApiPage;
use MailCampaigns\Magento2Connector\Model\Logger;

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
     * @var Logger
     */
    protected $logger;

    /**
     * @var array|CustomerSynchronizerInterface[]
     */
    protected $synchronizers;

    public function __construct(
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper,
        CustomerSynchronizerInterface $customerSynchronizer,
        SubscriberSynchronizerInterface $subscriberSynchronizer,
        ProductSynchronizerInterface $productSynchronizer,
        OrderSynchronizerInterface $orderSynchronizer
    ) {
        parent::__construct($apiHelper, $logHelper);

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

            if (method_exists($this->logger, 'addDebug')) {
                $this->logger->addDebug('Historical sync cron stopping..');
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

        if (method_exists($this->logger, 'addDebug')) {
            $this->logger->addDebug('Cron timeout reached: ' . ($timedOut ? 'yes' : 'no'), [
                'timeout_interval' => self::$timeoutInterval,
                'start_time' => $this->startTime,
                'timeout_at' => $this->startTime + self::$timeoutInterval,
                'time_left' => $this->startTime + self::$timeoutInterval - $now,
                'current_time' => $now,
            ]);
        }

        return $timedOut;
    }
}
