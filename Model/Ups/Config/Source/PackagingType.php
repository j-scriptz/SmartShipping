<?php
/**
 * Jscriptz SmartShipping - UPS Packaging Types Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PackagingType implements OptionSourceInterface
{
    /**
     * UPS Packaging Type Codes
     * @see https://developer.ups.com/api/reference/rating/appendix
     */
    public const TYPES = [
        '00' => 'Unknown',
        '01' => 'UPS Letter',
        '02' => 'Customer Supplied Package',
        '03' => 'Tube',
        '04' => 'PAK',
        '21' => 'UPS Express Box',
        '24' => 'UPS 25KG Box',
        '25' => 'UPS 10KG Box',
        '30' => 'Pallet',
        '2a' => 'Small Express Box',
        '2b' => 'Medium Express Box',
        '2c' => 'Large Express Box',
        '56' => 'Flats',
        '57' => 'Parcels',
        '58' => 'BPM',
        '59' => 'First Class',
        '60' => 'Priority',
        '61' => 'Machineables',
        '62' => 'Irregulars',
        '63' => 'Parcel Post',
        '64' => 'BPM Parcel',
        '65' => 'Media Mail',
        '66' => 'BPM Flat',
        '67' => 'Standard Flat',
    ];

    /**
     * Common packaging types for dropdown
     */
    public const COMMON_TYPES = [
        '02' => 'Customer Supplied Package',
        '01' => 'UPS Letter',
        '03' => 'Tube',
        '04' => 'PAK',
        '21' => 'UPS Express Box',
        '24' => 'UPS 25KG Box',
        '25' => 'UPS 10KG Box',
    ];

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::COMMON_TYPES as $code => $label) {
            $options[] = [
                'value' => $code,
                'label' => $label,
            ];
        }
        return $options;
    }

    /**
     * Get packaging label by code
     */
    public function getPackagingLabel(string $code): string
    {
        return self::TYPES[$code] ?? 'Unknown';
    }
}
