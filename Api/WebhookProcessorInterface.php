<?php
/**
 * Jscriptz SmartShipping - Webhook Processor Interface
 *
 * Interface for carrier-specific webhook processors.
 * Each carrier (FedEx, UPS, USPS) implements this to handle their webhook format.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Api;

use Jscriptz\SmartShipping\Api\Data\TrackingEventInterface;
use Magento\Framework\App\RequestInterface;

interface WebhookProcessorInterface
{
    /**
     * Get the carrier code this processor handles
     */
    public function getCarrierCode(): string;

    /**
     * Validate the webhook signature
     *
     * @param RequestInterface $request The incoming HTTP request
     * @param string $rawBody The raw request body
     * @return bool True if signature is valid
     */
    public function validateSignature(RequestInterface $request, string $rawBody): bool;

    /**
     * Process the webhook payload and create tracking events
     *
     * @param array $payload Decoded webhook payload
     * @return TrackingEventInterface[] Array of created tracking events
     */
    public function process(array $payload): array;

    /**
     * Parse the raw webhook body into a usable array
     *
     * @param string $rawBody Raw request body (usually JSON)
     * @return array Parsed payload
     * @throws \InvalidArgumentException If payload cannot be parsed
     */
    public function parsePayload(string $rawBody): array;

    /**
     * Check if this processor is enabled
     */
    public function isEnabled(): bool;

    /**
     * Get the security token/secret for HMAC validation
     */
    public function getSecurityToken(): string;
}
