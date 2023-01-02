<?php

namespace MailCampaigns\Magento2Connector\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session;

class CustomerId implements SectionSourceInterface
{
    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @param Session $customerSession
     */
    public function __construct(
        Session $customerSession
    ) {
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
                return [];
            }

            $customerId = $session->getCustomerId();

            if (null === $customerId) {
                return [];
            }
        } catch (\Exception $e) {
            throw $e;
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
