<?php
/**
 * Jscriptz SmartShipping - Process Pending Notifications Cron
 *
 * Processes tracking event notifications that haven't been sent yet.
 * Runs every 5 minutes to send customer email notifications.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Cron;

use Jscriptz\SmartShipping\Model\Webhook\NotificationService;
use Psr\Log\LoggerInterface;

class ProcessPendingNotifications
{
    private const BATCH_LIMIT = 100;

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute cron job to process pending tracking notifications
     */
    public function execute(): void
    {
        try {
            $processed = $this->notificationService->processPendingNotifications(self::BATCH_LIMIT);

            if ($processed > 0) {
                $this->logger->info('SmartShipping Cron: Processed pending notifications', [
                    'count' => $processed
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('SmartShipping Cron: Failed to process pending notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
