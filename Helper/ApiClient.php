<?php

namespace MailCampaigns\Magento2Connector\Helper;

use Exception;
use InvalidArgumentException;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MailCampaigns\Magento2Connector\Api\ApiClientInterface;
use MailCampaigns\Magento2Connector\Api\ApiQueueHelperInterface;
use MailCampaigns\Magento2Connector\Api\ApiQueueInterface;
use MailCampaigns\Magento2Connector\Api\LogHelperInterface;

/**
 * MailCampaigns Api Client.
 */
class ApiClient extends AbstractHelper implements ApiClientInterface
{
    /**
     * MailCampaigns API base Uri.
     * @var string
     */
    protected const API_BASE_URI = 'https://api.mailcampaigns.nl/api/v1.1/rest';

    /**
     * @var int|null
     */
    protected $storeId;

    /**
     * @var ApiQueueHelperInterface
     */
    protected $queueHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @inheritDoc
     */
    public function __construct(
        Context $context,
        LogHelperInterface $logHelper,
        ApiQueueHelperInterface $queueHelper,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->_logger = $logHelper->getLogger();
        $this->queueHelper = $queueHelper;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): ?int
    {
        return $this->storeId;
    }

    /**
     * @inheritDoc
     */
    public function setStoreId(?int $storeId = null): ApiClientInterface
    {
        $this->storeId = $storeId;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function call(
        string $function,
        array $filters,
        bool $isQueueable = true,
        ?int $timeout = null
    ): array {
        $content = [];
        $failed = false;

        $this->addCredentials($content);

        $content['method'] = $function;
        $content['filters'] = $filters;

        // Encode array to a Json object.
        $contentJson = json_encode($content);

        // Set stream options.
        $options = [
            'http' => [
                'protocol_version' => 1.1,
                'method' => 'POST',
                'header' => "Content-type: application/json\r\n" .
                    "Connection: close\r\n" .
                    "Content-length: " . strlen($contentJson) . "\r\n",
                'content' => $contentJson
            ]
        ];

        // Add timeout if a value was given.
        if ($timeout) {
            $options['http']['timeout'] = $timeout;
        }

        $logMsg = sprintf('Api called `%s`.', $function);

        $this->_logger->addDebug($logMsg, [
            'content' => $contentJson
        ]);

        // Execute the Api call.
        // @codingStandardsIgnoreStart
        $res = file_get_contents(self::API_BASE_URI, false, stream_context_create($options));
        // @codingStandardsIgnoreEnd

        // In case something went wrong, log the error and add the call
        // to the queue to be tried again later on.
        if (!$res || !is_string($res)) {
            $failed = true;
            $resDecoded = [];
            $this->_logger->addError('Api call failed (reason unknown).');
        } else {
            $resDecoded = json_decode($res, true);

            if (isset($errResponse['Error'])) {
                $failed = true;

                $this->_logger->addError(
                    sprintf('Api call failed with error message: `%s`.', $resDecoded->Error)
                );
            }
        }

        if ($failed) {
            // Only add to queue when this call is flagged to be queueable.
            if ($isQueueable) {
                $this->queue($content);
            }
        }

        return $resDecoded;
    }

    /**
     * @inheritDoc
     */
    public function queue(array $content, ?string $function = null): ApiClientInterface
    {
        if ($function) {
            $content['method'] = $function;
        }

        // Method should be set by now.
        if (!isset($content['method'])) {
            throw new InvalidArgumentException('Function name is missing!');
        }

        $this->addCredentials($content);

        // Add new queue entry.
        $this->queueHelper->add(json_encode($content));

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function processQueuedCall(ApiQueueInterface $apiQueue): ApiClientInterface
    {
        $callData = json_decode($apiQueue->getStreamData(), true);

        try {
            // Validate the queued call.
            $this->queueHelper->validateQueuedCallData($callData);

            // Make the Api call.
            $this->call($callData['method'], $callData['filters'], false);
        } catch (ApiCredentialsNotSetException $e) {
            // Re-throw this exception.
            throw $e;
        } catch (Exception $e) {
            // Mark the queued call erroneous and persist so it won't be tried
            // again over and over.
            $this->queueHelper->save($apiQueue->setHasError(true));

            // Log the error.
            $message = sprintf('Failed to process queued call (%s).', $e->getMessage());
            $this->_logger->error($message, ['stream_data' => $apiQueue->getStreamData()]);

            return $this;
        }

        $this->_logger->addDebug('Successfully processed queued call, removing from queue..',
            ['stream_data' => $apiQueue->getStreamData()]);

        // The call can be removed from the queue now.
        $this->queueHelper->removeFromQueue($apiQueue);

        return $this;
    }

    /**
     * @param array $content
     * @return $this
     * @throws ApiCredentialsNotSetException
     */
    protected function addCredentials(array &$content)
    {
        $apiKey = $this->scopeConfig->getValue(
            'mailcampaigns_api/general/api_key',
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );

        $apiToken = $this->scopeConfig->getValue(
            'mailcampaigns_api/general/api_token',
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );

        if (!$apiKey || !$apiToken) {
            throw new ApiCredentialsNotSetException('API credentials not set (correctly).');
        }

        $content['api_key'] = $apiKey;
        $content['api_token'] = $apiToken;

        return $this;
    }
}
