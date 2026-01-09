<?php
/**
 * Jscriptz SmartShipping - Label Provider Pool
 *
 * Pool of carrier-specific label providers.
 * Providers are registered via di.xml and retrieved by carrier code.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Shipment;

use Jscriptz\SmartShipping\Api\LabelProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class LabelProviderPool
{
    /**
     * @param LabelProviderInterface[] $providers Injected via di.xml
     */
    public function __construct(
        private readonly array $providers = []
    ) {
    }

    /**
     * Get label provider by carrier code
     *
     * @param string $carrierCode
     * @return LabelProviderInterface
     * @throws LocalizedException
     */
    public function get(string $carrierCode): LabelProviderInterface
    {
        if (!isset($this->providers[$carrierCode])) {
            throw new LocalizedException(
                __('No label provider registered for carrier: %1', $carrierCode)
            );
        }

        return $this->providers[$carrierCode];
    }

    /**
     * Check if a provider exists for carrier code
     *
     * @param string $carrierCode
     * @return bool
     */
    public function has(string $carrierCode): bool
    {
        return isset($this->providers[$carrierCode]);
    }

    /**
     * Get all registered carrier codes
     *
     * @return string[]
     */
    public function getCarrierCodes(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Check if label generation is available for a carrier
     *
     * @param string $carrierCode
     * @param int|null $storeId
     * @return bool
     */
    public function isAvailable(string $carrierCode, ?int $storeId = null): bool
    {
        if (!$this->has($carrierCode)) {
            return false;
        }

        return $this->providers[$carrierCode]->isAvailable($storeId);
    }

    /**
     * Get all available providers for a store
     *
     * @param int|null $storeId
     * @return LabelProviderInterface[]
     */
    public function getAvailableProviders(?int $storeId = null): array
    {
        $available = [];
        foreach ($this->providers as $carrierCode => $provider) {
            if ($provider->isAvailable($storeId)) {
                $available[$carrierCode] = $provider;
            }
        }
        return $available;
    }
}
