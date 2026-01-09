<?php
/**
 * Jscriptz SmartShipping - FedEx Packaging Type Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PackagingType implements OptionSourceInterface
{
    /**
     * FedEx package types
     */
    public const PACKAGES = [
        'YOUR_PACKAGING' => 'Your Packaging',
        'FEDEX_ENVELOPE' => 'FedEx Envelope',
        'FEDEX_PAK' => 'FedEx Pak',
        'FEDEX_BOX' => 'FedEx Box',
        'FEDEX_SMALL_BOX' => 'FedEx Small Box',
        'FEDEX_MEDIUM_BOX' => 'FedEx Medium Box',
        'FEDEX_LARGE_BOX' => 'FedEx Large Box',
        'FEDEX_EXTRA_LARGE_BOX' => 'FedEx Extra Large Box',
        'FEDEX_TUBE' => 'FedEx Tube',
        'FEDEX_10KG_BOX' => 'FedEx 10kg Box',
        'FEDEX_25KG_BOX' => 'FedEx 25kg Box',
    ];

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::PACKAGES as $code => $label) {
            $options[] = [
                'value' => $code,
                'label' => __($label),
            ];
        }
        return $options;
    }

    /**
     * Get package label by code
     */
    public function getPackageLabel(string $code): string
    {
        return self::PACKAGES[$code] ?? $code;
    }
}
