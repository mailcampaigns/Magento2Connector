<?php

namespace MailCampaigns\Magento2Connector\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Model\Logger;

class CustomerId implements SectionSourceInterface
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param \Magento\Customer\Model\Session $customerSession
     * @param LogHelperInterface $logHelper
     */
    public function __construct(\Magento\Customer\Model\Session $customerSession,
                                LogHelperInterface $logHelper)
    {
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

            if (!$session instanceof \Magento\Customer\Model\Session) {
                $this->logger->debug('Customer session not found.');
                return [];
            }

            $customerId = $session->getCustomerId();

            if (null === $customerId) {
                $this->logger->debug('Customer id not set in session.');
                return [];
            }
        } catch (\Exception $e) {
            // Log and re-throw the exception.
            $this->logger->addException($e);
            throw $e;
        }

        $this->logger->debug(sprintf('Loaded customer id (%d) from session.',
            $customerId));

        return [
            'customerId' => $customerId
        ];
    }

    /**
     * @return \Magento\Customer\Model\Session
     */
    public function getCustomerSession()
    {
        return $this->_customerSession;
    }

    /**
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function setCustomerSession($customerSession)
    {
        $this->_customerSession = $customerSession;
    }
}
