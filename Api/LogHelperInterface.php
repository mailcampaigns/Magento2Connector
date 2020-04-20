<?php

namespace MailCampaigns\Magento2Connector\Api;

use MailCampaigns\Magento2Connector\Model\Logger;

interface LogHelperInterface
{
    /**
     * Returns the logger instance.
     *
     * @return Logger
     */
    public function getLogger(): Logger;

    /**
     * Lazy loads the `url` (resource, which is the path to the log file in
     * this case).
     *
     * @return string
     */
    public function getUrl(): string;

    /**
     * Returns whether logging is enabled or not.
     *
     * @return bool
     */
    public function isLoggingEnabled(): bool;

    /**
     * Returns the configured (minimum) logging level.
     *
     * @return int
     */
    public function getCurrentLoggingLevel(): int;

    /**
     * Returns humanized (file) size as a string.
     *
     * @param int $bytes
     * @param int $decimals (optional)
     * @return string
     */
    public function humanizeSize(int $bytes, int $decimals = 2): string;
}
