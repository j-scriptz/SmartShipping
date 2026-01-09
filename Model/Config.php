<?php
/**
 * Jscriptz SmartShipping - Base Configuration
 *
 * Provides webhook-related configuration methods.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_PREFIX = 'smartshipping/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Get config value
     */
    private function getValue(string $path, ?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get config flag
     */
    private function getFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if webhook notifications are enabled
     */
    public function isNotificationEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('webhook/notification_enabled', $storeId);
    }

    /**
     * Get notification email sender
     */
    public function getNotificationSender(?int $storeId = null): string
    {
        return $this->getValue('webhook/notification_sender', $storeId) ?? 'general';
    }

    /**
     * Get notification email template
     */
    public function getNotificationTemplate(?int $storeId = null): string
    {
        return $this->getValue('webhook/notification_template', $storeId) ?? 'smartshipping_tracking_update';
    }

    /**
     * Get webhook callback URL
     *
     * Returns the URL that carriers should use to send webhook notifications.
     * Format: {base_url}/smartshipping/webhook/receive/carrier/{carrier_code}/
     *
     * @param string $carrierCode The carrier code (fedexv3, upsv3, uspsv3)
     * @param int|null $storeId Store ID
     * @return string
     */
    public function getWebhookCallbackUrl(string $carrierCode = '{carrier}', ?int $storeId = null): string
    {
        // Use custom URL if configured, otherwise generate default
        $customUrl = $this->getValue('webhook/callback_url', $storeId);
        if ($customUrl) {
            return str_replace('{carrier}', $carrierCode, $customUrl);
        }

        // Default callback URL format
        $baseUrl = rtrim($this->scopeConfig->getValue(
            'web/secure/base_url',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '', '/');

        return $baseUrl . '/smartshipping/webhook/receive/carrier/' . $carrierCode . '/';
    }

    /**
     * Get generic webhook callback URL (with placeholder)
     *
     * @param int|null $storeId Store ID
     * @return string
     */
    public function getGenericWebhookCallbackUrl(?int $storeId = null): string
    {
        return $this->getWebhookCallbackUrl('{carrier}', $storeId);
    }

    /**
     * Check if POD photos are enabled in admin config
     *
     * @param int|null $storeId Store ID
     * @return bool
     */
    public function isPodPhotoEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('webhook/include_pod_photos', $storeId);
    }

    /**
     * Get the event types that trigger notifications
     *
     * @param int|null $storeId Store ID
     * @return string[]
     */
    public function getNotificationEvents(?int $storeId = null): array
    {
        $events = $this->getValue('webhook/notification_events', $storeId);

        if (empty($events)) {
            return []; // Empty means all events
        }

        return explode(',', $events);
    }

    /**
     * Check if POD photos should be included in emails for a customer
     *
     * @param int|null $customerId Customer ID
     * @param int|null $storeId Store ID
     * @return bool
     */
    public function shouldIncludePodPhotos(?int $customerId, ?int $storeId = null): bool
    {
        // Check global config first
        if (!$this->isPodPhotoEnabled($storeId)) {
            return false;
        }

        // If no customer, use global setting
        if (!$customerId) {
            return true;
        }

        // Customer preference is checked by the notification service
        return true;
    }
}
