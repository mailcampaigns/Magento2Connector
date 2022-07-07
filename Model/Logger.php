<?php

namespace MailCampaigns\Magento2Connector\Model;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Logger\Monolog;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monolog\DateTimeImmutable;

class Logger extends Monolog
{
    /**
     * @var bool
     */
    protected $cnfLoggingEnabled;

    /**
     * @var int
     */
    protected $cnfLoggingLevel;

    public function __construct(
        $name,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $config,
        array $handlers = [],
        array $processors = []
    ) {
        parent::__construct($name, $handlers, $processors);

        $this->cnfLoggingEnabled = $config->isSetFlag(
            'mailcampaigns_api/development/logging_enabled',
            ScopeInterface::SCOPE_STORE
        );

        // Read logging level setting from config.
        $this->cnfLoggingLevel = $config->getValue(
            'mailcampaigns_api/development/logging_level',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @inheritDoc
     */
    public function addRecord(int $level, string $message, array $context = [], DateTimeImmutable $datetime = null) : bool
    {
        if (!$this->cnfLoggingEnabled || $this->cnfLoggingLevel <= 0) {
            return false;
        }

        return $level >= $this->cnfLoggingLevel ? parent::addRecord(
            $level,
            $message,
            $context
        ) : false;
    }

    /**
     * Logs an exception.
     *
     * @param Exception $e
     * @return $this
     */
    public function addException(Exception $e): self
    {
        $this->addError('Caught exception: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
            'trace' => json_encode($this->capTrace($e->getTrace()))
        ]);

        return $this;
    }

    /**
     * Limit the number of steps in the trace.
     *
     * @param $trace
     * @return array
     */
    protected function capTrace($trace)
    {
        $maxSteps = 12;

        if (is_array($trace) && count($trace) > $maxSteps) {
            return array_slice($trace, 0, $maxSteps);
        }

        return $trace;
    }
}
