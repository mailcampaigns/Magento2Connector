<?php

namespace MailCampaigns\Magento2Connector\Model;

use DateInterval;
use DateTime;
use Exception;
use InvalidArgumentException;
use Magento\Framework\DataObject;
use Magento\Framework\Filesystem\Io\File;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;
use MailCampaigns\Magento2Connector\Api\LogReaderInterface;

class LogReader implements LogReaderInterface
{
    /** @var string Regular expression to interpret a log entry. */
    const ENTRY_REGEX = '/^\[(.*)] ([a-z]{1,16})\.([A-Z]{4,10}): (.*) ([\[|{].*[]|}]) \[(.*)]$/';

    /**
     * @var LogHelperInterface
     */
    protected $logHelper;

    /**
     * @var File
     */
    protected $file;

    public function __construct(LogHelperInterface $logHelper, File $file)
    {
        $this->logHelper = $logHelper;
        $this->file = $file;
    }

    /**
     * @inheritDoc
     */
    public function read(?string $start = null, ?string $end = null): array
    {
        $nowDt = new DateTime;

        // Set start of the period to filter on.
        $start = $start ? DateTime::createFromFormat('Y-m-d H:i:s', "{$start} 00:00:00")
            : (clone $nowDt)->sub(new DateInterval('P1M'));

        // Set end of the period to filter on.
        $end = $end ? DateTime::createFromFormat('Y-m-d H:i:s', "{$end} 23:59:59")
            : $nowDt;

        // Validate date range, config and log file.
        $this->validate($start, $end);

        // Read the whole file and split the content into lines (by newline chars).
        $lines = explode(PHP_EOL, $this->file->read($this->logHelper->getUrl()));

        // Count unfiltered number of lines.
        $totalLineCount = count($this->filter($lines));

        // Filter the lines including the start and end dates.
        $linesFiltered = $this->filter($lines, $start, $end);

        $response = new DataObject([
            'current_logging_level' => $this->logHelper->getCurrentLoggingLevel(),
            'total_line_count' => $totalLineCount,
            'line_count' => count($linesFiltered),
            'data' => $linesFiltered
        ]);

        return [$response->getData()];
    }

    /**
     * Throws an exception when validation fails.
     *
     * @param DateTime $start
     * @param DateTime $end
     * @return $this
     * @throws Exception
     */
    protected function validate(DateTime $start, DateTime $end): self
    {
        // Do some validation on inputs.
        if ($start > $end) {
            throw new InvalidArgumentException('Start can not be later than end date!');
        }

        // Check config setting; Do not return any data when logging is disabled (file
        // could still exist).
        if (!$this->logHelper->isLoggingEnabled()) {
            throw new LogReaderException('Logging feature is disabled. Enable this in the '
                . 'Magento admin.');
        }

        // Monolog calls the pointer to the resource a `Url`, so we'll do that
        // as well to be consistent.
        if (!$this->logHelper->getUrl()) {
            throw new LogReaderException('Could not determine stream url (file path).');
        }

        // The log file might not exist or have the wrong permissions set.
        if (!$this->file->fileExists($this->logHelper->getUrl())) {
            throw new LogReaderException('Log file not found or not readable!');
        }

        return $this;
    }

    /**
     * Filters out entries which fall out of the start/end date range.
     *
     * If no start and/or end is given, it will not filter on date but behave
     * the same way meaning it will filter out empty or invalid lines.
     *
     * @param $lines
     * @param DateTime|null $start
     * @param DateTime|null $end
     * @return array
     */
    protected function filter($lines, ?DateTime $start = null, ?DateTime $end = null): array
    {
        return array_filter($lines, function ($line) use ($start, $end) {
            // Match the log entry so we can use the entry's date for comparison.
            preg_match(self::ENTRY_REGEX, $line, $matches);

            // Skip last (empty) or somehow malformed line.
            if (!isset($matches[1])) {
                return false;
            }

            // Just return if no dates are given to filter on.
            if (!$start && !$end) {
                return true;
            }

            // Convert datetime string in log entry to a DateTime object.
            $entryDt = DateTime::createFromFormat('Y-m-d H:i:s', $matches[1]);

            // Returns true if log entry falls within the set date-range.
            return $entryDt >= $start && $entryDt <= $end;
        });
    }
}
