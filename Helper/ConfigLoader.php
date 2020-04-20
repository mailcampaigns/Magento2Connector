<?php

namespace MailCampaigns\Magento2Connector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

// todo deze class kan weg, alleen nog gebruikt door phtml bestanden (2), nadat die aangepast zijn mag dit bestand weg
class ConfigLoader extends AbstractHelper
{
    public function getConfig($path, $store_id, $scopeType = ScopeInterface::SCOPE_STORE)
    {
        return $this->scopeConfig->getValue($path, $scopeType, $store_id);
    }

    public function getScopeConfig($path, $scopeType = ScopeInterface::SCOPE_STORE, $scopeCode = null)
    {
        return $this->scopeConfig->getValue($path, $scopeType, $scopeCode);
    }
}
