<?php

namespace MailCampaigns\Magento2Connector\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MailCampaigns\Magento2Connector\Api\LoggerProviderInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Model\Logger;

class LogHelper implements LogHelperInterface
{
    /**
     * @var string
     */
    protected $url;

    /**
     * @var LoggerProviderInterface
     */
    protected $loggerProvider;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $config;

    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $config,
        LoggerProviderInterface $loggerProvider
    ) {
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->loggerProvider = $loggerProvider;
    }

    /**
     * @inheritDoc
     */
    public function getLogger(): Logger
    {
        return $this->loggerProvider->getInstance();
    }

    /**
     * @inheritDoc
     */
    public function isLoggingEnabled(): bool
    {
        return $this->config->getValue(
            'mailcampaigns_api/development/logging_enabled',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getDefaultStoreView()->getId()
        );
    }

    /**
     * @inheritDoc
     */
    public function getCurrentLoggingLevel(): int
    {
        return $this->config->getValue(
            'mailcampaigns_api/development/logging_level',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getDefaultStoreView()->getId()
        );
    }

    /**
     * @inheritDoc
     */
    public function getUrl(): string
    {
        if (!$this->url) {
            $handlers = $this->loggerProvider->getInstance()->getHandlers();

            // Technically there might be multiple handlers set, but in this case
            // there should only be one, so this would do it.
            foreach ($handlers as $handler) {
                if (!$this->url && $handler->getUrl()) {
                    $this->url = $handler->getUrl();
                }
            }
        }

        return $this->url;
    }

    /**
     * @inheritDoc
     */
    public function humanizeSize(int $bytes, int $decimals = 2): string
    {
        $sz = 'BKMGTP';
        $factor = (int)floor((strlen($bytes) - 1) / 3);

        if (isset($sz[$factor])) {
            return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $sz[$factor];
        }

        return $bytes . 'B';
    }
}
