<?php

namespace MailCampaigns\Magento2Connector\Cron;

use Exception;
use Magento\Cron\Model\Schedule;
use Magento\Quote\Model\Quote;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Api\QuoteSynchronizerInterface;
use MailCampaigns\Magento2Connector\Model\ApiStatus;
use MailCampaigns\Magento2Connector\Model\ResourceModel;

class QuoteCron extends AbstractCron
{
    /**
     * @var QuoteSynchronizerInterface
     */
    protected $synchronizer;

    /**
     * @var ResourceModel\Quote
     */
    protected $quoteResourceModel;

    public function __construct(
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper,
        QuoteSynchronizerInterface $synchronizer,
        ResourceModel\Quote $quoteResourceModel
    ) {
        parent::__construct($apiHelper, $logHelper);
        $this->synchronizer = $synchronizer;
        $this->quoteResourceModel = $quoteResourceModel;
    }

    /**
     * @inheritDoc
     */
    public function execute(Schedule $schedule): void
    {
        try {
            $syncStartTs = $this->apiStatusHelper->getSyncStart(ApiStatus::TYPE_QUOTE_CRON);
            $syncStartStr = gmdate('Y-m-d H:i:s', $syncStartTs);

            // Send Api a status update.
            $this->apiStatusHelper->updateStatus(ApiStatus::TYPE_QUOTE_CRON);

            // Get quotes that are due to be synchronized.
            $quotes = $this->quoteResourceModel->getQuotesToSynchronize($syncStartStr);

            /** @var Quote $quote */
            foreach ($quotes as $quote) {
                $this->synchronizer->synchronize($quote, $quote->getStoreId());
            }
        } catch (Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }
    }
}
