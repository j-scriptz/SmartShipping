<?php
/**
 * Jscriptz SmartShipping - Webhook Receive Controller
 *
 * CSRF-exempt endpoint for receiving carrier tracking webhooks.
 * Route: /smartshipping/webhook/receive/carrier/{carrier_code}/
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Controller\Webhook;

use Jscriptz\SmartShipping\Api\TrackingEventRepositoryInterface;
use Jscriptz\SmartShipping\Model\Webhook\ProcessorPool;
use Jscriptz\SmartShipping\Model\Webhook\NotificationService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

class Receive implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly ProcessorPool $processorPool,
        private readonly TrackingEventRepositoryInterface $eventRepository,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Skip CSRF validation - use HMAC signature validation instead
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Skip CSRF validation - use HMAC signature validation instead
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Process incoming webhook
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $carrierCode = $this->request->getParam('carrier');

        if (!$carrierCode) {
            $this->logger->warning('Webhook received without carrier code');
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => 'Missing carrier parameter'
            ]);
        }

        try {
            $processor = $this->processorPool->get($carrierCode);

            if (!$processor->isEnabled()) {
                $this->logger->info("Webhook received for disabled carrier: {$carrierCode}");
                return $result->setHttpResponseCode(503)->setData([
                    'success' => false,
                    'message' => 'Webhook processing disabled for this carrier'
                ]);
            }

            // Get raw body for signature validation
            $rawBody = file_get_contents('php://input');

            // Validate HMAC signature
            if (!$processor->validateSignature($this->request, $rawBody)) {
                $this->logger->warning("Invalid webhook signature for carrier: {$carrierCode}");
                return $result->setHttpResponseCode(401)->setData([
                    'success' => false,
                    'message' => 'Invalid signature'
                ]);
            }

            // Parse and process the payload
            $payload = $processor->parsePayload($rawBody);
            $events = $processor->process($payload);

            // Save events and trigger notifications
            $savedCount = 0;
            foreach ($events as $event) {
                // Check for duplicates
                if ($this->eventRepository->eventExists(
                    $event->getTrackingNumber(),
                    $event->getEventCode(),
                    $event->getEventTimestamp()
                )) {
                    $this->logger->debug("Duplicate event skipped: {$event->getTrackingNumber()} - {$event->getEventCode()}");
                    continue;
                }

                $this->eventRepository->save($event);
                $savedCount++;

                // Queue email notification (async)
                $this->notificationService->queueNotification($event);
            }

            $this->logger->info("Webhook processed for {$carrierCode}: {$savedCount} events saved");

            return $result->setHttpResponseCode(200)->setData([
                'success' => true,
                'events_processed' => $savedCount
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->logger->error("Unknown carrier for webhook: {$carrierCode}");
            return $result->setHttpResponseCode(404)->setData([
                'success' => false,
                'message' => 'Unknown carrier'
            ]);
        } catch (\Exception $e) {
            $this->logger->error("Webhook processing error: " . $e->getMessage(), [
                'carrier' => $carrierCode,
                'exception' => $e
            ]);
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => 'Internal error'
            ]);
        }
    }
}
