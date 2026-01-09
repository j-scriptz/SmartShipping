<?php
/**
 * Jscriptz SmartShipping - UPS Service Types Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ServiceType implements OptionSourceInterface
{
    /**
     * UPS Service Codes for US Domestic
     * @see https://developer.ups.com/api/reference/rating/appendix
     */
    public const SERVICES = [
        '01' => 'Next Day Air',
        '02' => '2nd Day Air',
        '03' => 'Ground',
        '12' => '3 Day Select',
        '13' => 'Next Day Air Saver',
        '14' => 'Next Day Air Early',
        '59' => '2nd Day Air A.M.',
        '65' => 'Saver',
        // SurePost services
        '93' => 'SurePost Less Than 1 lb',
        '94' => 'SurePost 1 lb or Greater',
        '95' => 'SurePost BPM',
        '96' => 'SurePost Media Mail',
        // International
        '07' => 'Worldwide Express',
        '08' => 'Worldwide Expedited',
        '11' => 'Standard',
        '54' => 'Worldwide Express Plus',
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
                'label' => $label . ' (' . $code . ')',
            ];
        }
        return $options;
    }

    /**
     * Get service label by code
     */
    public function getServiceLabel(string $code): string
    {
        return self::SERVICES[$code] ?? 'Unknown Service';
    }
}
