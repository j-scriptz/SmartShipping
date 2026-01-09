<?php
/**
 * Jscriptz SmartShipping - FedEx Service Type Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ServiceType implements OptionSourceInterface
{
    /**
     * FedEx service codes and labels
     */
    public const SERVICES = [
        // Domestic Express
        'FIRST_OVERNIGHT' => 'FedEx First Overnight',
        'PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight',
        'STANDARD_OVERNIGHT' => 'FedEx Standard Overnight',
        'FEDEX_2_DAY_AM' => 'FedEx 2Day A.M.',
        'FEDEX_2_DAY' => 'FedEx 2Day',
        'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
        // Ground Services
        'FEDEX_GROUND' => 'FedEx Ground',
        'GROUND_HOME_DELIVERY' => 'FedEx Home Delivery',
        'SMART_POST' => 'FedEx Ground Economy',
        // International
        'INTERNATIONAL_FIRST' => 'FedEx International First',
        'INTERNATIONAL_PRIORITY' => 'FedEx International Priority',
        'INTERNATIONAL_ECONOMY' => 'FedEx International Economy',
        'FEDEX_INTERNATIONAL_PRIORITY_EXPRESS' => 'FedEx International Priority Express',
        // Freight (large shipments)
        'FEDEX_FREIGHT_PRIORITY' => 'FedEx Freight Priority',
        'FEDEX_FREIGHT_ECONOMY' => 'FedEx Freight Economy',
    ];

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::SERVICES as $code => $label) {
            $options[] = [
                'value' => $code,
                'label' => __($label),
            ];
        }
        return $options;
    }

    /**
     * Get service label by code
     */
    public function getServiceLabel(string $code): string
    {
        return self::SERVICES[$code] ?? $code;
    }

    /**
     * Get all service codes
     */
    public function getAllServiceCodes(): array
    {
        return array_keys(self::SERVICES);
    }
}
