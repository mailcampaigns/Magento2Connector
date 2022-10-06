<?php

namespace MailCampaigns\Magento2Connector\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Model\Logger;

class CustomerId implements SectionSourceInterface
{
    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param Session $customerSession
     * @param LogHelperInterface $logHelper
     */
    public function __construct(
        Session $customerSession,
        LogHelperInterface $logHelper
    ) {
        $this->logger = $logHelper->getLogger();
        $this->setCustomerSession($customerSession);
    }

    /**
     * @inheritDoc
     */
    public function getSectionData()
    {
        $customerId = null;

        try {
            $session = $this->getCustomerSession();

            if (!$session instanceof Session) {
                if (method_exists($this->logger, 'debug')) {
                    $this->logger->debug('Customer session not found.');
                }
                return [];
            }

            $customerId = $session->getCustomerId();

            if (null === $customerId) {
                if (method_exists($this->logger, 'debug')) {
                    $this->logger->debug('Customer id not set in session.');
                }
                return [];
            }
        } catch (\Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }

        if (method_exists($this->logger, 'debug')) {
            $this->logger->debug(sprintf(
                'Loaded customer id (%d) from session.',
                $customerId
            ));
        }

        return [
            'customerId' => $customerId
        ];
    }

    /**
     * @return Session
     */
    public function getCustomerSession()
    {
        return $this->_customerSession;
    }

    /**
     * @param Session $customerSession
     */
    public function setCustomerSession($customerSession)
    {
        $this->_customerSession = $customerSession;
    }
}
