<?php

namespace MailCampaigns\Magento2Connector\Model;

use Exception;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\ObjectManager\FactoryInterface;
use Magento\Framework\Validator\EmailAddress;
use Magento\Newsletter\Model\ResourceModel\Subscriber as SubscriberResourceModel;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MailCampaigns\Magento2Connector\Api\SubscriberManagementInterface;
use Zend_Validate;

class SubscriberManagement implements SubscriberManagementInterface
{
    public const SORT_DIRECTIONS = [
        AbstractDb::SORT_ORDER_ASC,
        AbstractDb::SORT_ORDER_DESC
    ];

    /**
     * @var array Fields which can be sorted on.
     */
    public static $sortableFields = ['change_status_at', 'subscriber_email',
        'customer_id', 'subscriber_id', 'subscriber_status'];

    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var CustomerUrl
     */
    protected $_customerUrl;

    /**
     * @var SubscriberResourceModel
     */
    protected $subscriberResourceModel;

    /**
     * Subscriber collection
     *
     * @var Collection
     */
    protected $_subscriberCollection;

    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @var AccountManagementInterface
     */
    protected $customerAccountManagement;

    /**
     * @var FactoryInterface
     */
    protected $_subscriberFactory;

    /**
     * @var Resource
     */
    protected $_resource;

    /**
     * @var ResourceConnection
     */
    protected $_connection;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        CustomerUrl $customerUrl,
        SubscriberResourceModel $subscriberResourceModel,
        Collection $subscriberCollection,
        Session $customerSession,
        AccountManagementInterface $customerAccountManagement,
        SubscriberFactory $subscriberFactory
    ) {
        $this->_objectManager = $context->getObjectManager();
        $this->_storeManager = $storeManager;
        $this->_customerUrl = $customerUrl;
        $this->subscriberResourceModel = $subscriberResourceModel;
        $this->_subscriberCollection = $subscriberCollection;
        $this->_customerSession = $customerSession;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->_subscriberFactory = $subscriberFactory;
    }

    /**
     * @inheritDoc
     */
    public function subscribe(string $email)
    {
        try {
            $this->validateEmailFormat($email);
            $this->validateGuestSubscription();
            $this->validateEmailAvailable($email);

            /** @var Subscriber $subscriber */
            $subscriber = $this->_subscriberFactory->create()->loadByEmail($email);

            if ($subscriber->isSubscribed()) {
                throw new LocalizedException(
                    __('This email address is already subscribed.')
                );
            }

            $status = $this->_subscriberFactory->create()->subscribe($email);

            if ($status == Subscriber::STATUS_NOT_ACTIVE) {
                $message = __('The confirmation request has been sent.');
            } else {
                $message = __('Thank you for your subscription.');
            }

            $result = new DataObject;

            $result->setData([
                'success' => true,
                'message' => $message,
            ]);

            return $result;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function search(
        $storeIds = [],
        $subscriberEmail = '',
        $modifiedSince = '',
        $sortField = '',
        $sortDirection = '',
        $pageNumber = 0,
        $pageSize = 0
    ) {
        try {
            $subscribers = $this->_subscriberCollection;

            if (count($storeIds) > 0) {
                $subscribers->addFieldToFilter('store_id', ['in' => $storeIds]);
            }

            if ($subscriberEmail !== '') {
                $subscribers->addFieldToFilter('subscriber_email', $subscriberEmail);
            }

            if ($modifiedSince !== '') {
                $dtFormatted = date("Y-m-d H:i:s", strtotime($modifiedSince));

                $subscribers->addFieldToFilter('change_status_at', [
                    'gt' => $dtFormatted
                ]);
            }

            if (!in_array(strtoupper($sortDirection), self::SORT_DIRECTIONS, true)) {
                $sortDirection = AbstractDb::SORT_ORDER_ASC;
            }

            // Default sort on first field in array.
            if (!in_array($sortField, self::$sortableFields)) {
                $sortField = self::$sortableFields[0];
            }

            $subscribers->addOrder($sortField, strtoupper($sortDirection));

            if ($pageSize >= 0 && $pageSize <= 250) {
                $subscribers = $subscribers
                    ->setCurPage($pageNumber)
                    ->setPageSize($pageSize);
            }

            $subscribers
                ->useOnlySubscribed()
                ->loadWithFilter();

            return $subscribers->getData();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Validates that the email address isn't being used by a different account.
     * Reference: vendor/magento/module-newsletter/Controller/Subscriber/NewAction.php
     *
     * @param string $email
     * @throws LocalizedException
     * @return void
     */
    protected function validateEmailAvailable($email)
    {
        $websiteId = $this->_storeManager->getStore()->getWebsiteId();
        if ($this->_customerSession->getCustomerDataObject()->getEmail() !== $email
            && !$this->customerAccountManagement->isEmailAvailable($email, $websiteId)
        ) {
            throw new LocalizedException(
                __('This email address is already assigned to another user.')
            );
        }
    }

    /**
     * Validates that if the current user is a guest, that they can subscribe to a newsletter.
     * Reference: vendor/magento/module-newsletter/Controller/Subscriber/NewAction.php
     *
     * @throws LocalizedException
     * @return void
     */
    protected function validateGuestSubscription()
    {
        if ($this->_objectManager->get(ScopeConfigInterface::class)
                ->getValue(
                    Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG,
                    ScopeInterface::SCOPE_STORE
                ) != 1
            && !$this->_customerSession->isLoggedIn()
        ) {
            throw new LocalizedException(
                __(
                    'Sorry, but the administrator denied subscription for guests. Please <a href="%1">register</a>.',
                    $this->_customerUrl->getRegisterUrl()
                )
            );
        }
    }

    /**
     * Validates the format of the email address
     * Reference: vendor/magento/module-newsletter/Controller/Subscriber/NewAction.php
     *
     * @param string $email
     * @throws LocalizedException
     * @return void
     */
    protected function validateEmailFormat($email)
    {
        if (!Zend_Validate::is($email, EmailAddress::class)) {
            throw new LocalizedException(__('Please enter a valid email address.'));
        }
    }
}
