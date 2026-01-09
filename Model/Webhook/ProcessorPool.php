<?php
/**
 * Jscriptz SmartShipping - Webhook Processor Pool
 *
 * Manages carrier-specific webhook processors.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Webhook;

use Jscriptz\SmartShipping\Api\WebhookProcessorInterface;

class ProcessorPool
{
    /**
     * @param WebhookProcessorInterface[] $processors
     */
    public function __construct(
        private readonly array $processors = []
    ) {
    }

    /**
     * Get processor for a carrier
     *
     * @throws \InvalidArgumentException If no processor exists for the carrier
     */
    public function get(string $carrierCode): WebhookProcessorInterface
    {
        if (!isset($this->processors[$carrierCode])) {
            throw new \InvalidArgumentException(
                "No webhook processor registered for carrier: {$carrierCode}"
            );
        }

        return $this->processors[$carrierCode];
    }

    /**
     * Check if a processor exists for a carrier
     */
    public function has(string $carrierCode): bool
    {
        return isset($this->processors[$carrierCode]);
    }

    /**
     * Get all registered carrier codes
     *
     * @return string[]
     */
    public function getCarrierCodes(): array
    {
        return array_keys($this->processors);
    }

    /**
     * Get all enabled processors
     *
     * @return WebhookProcessorInterface[]
     */
    public function getEnabledProcessors(): array
    {
        return array_filter(
            $this->processors,
            fn(WebhookProcessorInterface $processor) => $processor->isEnabled()
        );
    }
}
