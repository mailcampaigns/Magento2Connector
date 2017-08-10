<?php

namespace MailCampaigns\Connector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\ObjectManagerInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
	public function __construct(Context $context) 
	{
		parent::__construct($context);
	}
	
	public function getConfig($path, $store_id, $scopeType = ScopeInterface::SCOPE_STORE, $scopeCode = null)
	{
		return $this->scopeConfig->getValue($path, $scopeType, $store_id);
	}
	
	public function getScopeConfig($path, $scopeType = ScopeInterface::SCOPE_STORE, $scopeCode = null)
	{
		return $this->scopeConfig->getValue($path, $scopeType, $scopeCode);
	}
}