<?php

namespace MailCampaigns\Magento2Connector\Cron;

use DateInterval;
use DateTime;
use Magento\Cron\Model\Schedule;
use Magento\Framework\Filesystem\Io\File;
use MailCampaigns\Magento2Connector\Api\ApiHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogReaderInterface;

class LogRotationCron extends AbstractCron
{
    /**
     * @var LogHelperInterface
     */
    protected $logHelper;

    /**
     * @var LogReaderInterface
     */
    protected $logReader;

    /**
     * @var DateTime
     */
    protected $startRangeDt;

    /**
     * @var File
     */
    protected $file;

    public function __construct(
        ApiHelperInterface $apiHelper,
        LogHelperInterface $logHelper,
        LogReaderInterface $logReader,
        File $file
    ) {
        parent::__construct($apiHelper, $logHelper);
        $this->logger = $logHelper->getlogger();
        $this->logHelper = $logHelper;
        $this->logReader = $logReader;
        $this->file = $file;
        $this->startRangeDt = (new DateTime)->sub(new DateInterval('P7D'));
    }

    public function execute(Schedule $schedule): void
    {
        $filePath = $this->logHelper->getUrl();

        // Stop here in case logging is not enabled.
        if (!$this->logHelper->isLoggingEnabled()) {
            return;
        }

        // Make sure the log file exists and we can at modify it.
        if (!$this->file->fileExists($filePath) || !$this->file->isWriteable($filePath)) {
            return;
        }

        /** @codingStandardsIgnoreStart There is no Magento framework alternative for filesize(). */
        $fileSize = filesize($filePath);
        /** @codingStandardsIgnoreEnd */

        $hFileSize = $this->logHelper->humanizeSize($fileSize);

        if (method_exists($this->logger, 'addDebug')) {
            $this->logger->addDebug('Current log file size: ' . $hFileSize);
        }

        $log = $this->logReader->read($this->startRangeDt->format('Y-m-d H:i:s'))[0];
        $cntDiff = $log['total_line_count'] - $log['line_count'];

        if ($cntDiff > 0) {
            // Replace log file with filtered lines.
            $newData = implode(PHP_EOL, $log['data']) . PHP_EOL;

            $this->file->write($filePath, $newData);

            // Log the truncation event.
            $msg = 'Truncated log, number of entries removed: ' . $cntDiff;
            if (method_exists($this->logger, 'addDebug')) {
                $this->logger->addDebug($msg);
            }
        }
    }
}
