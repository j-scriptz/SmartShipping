<?php
/**
 * Jscriptz SmartShipping - USPS Mail Class Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class MailClass implements OptionSourceInterface
{
    public const MAIL_CLASSES = [
        'USPS_GROUND_ADVANTAGE' => 'USPS Ground Advantage',
        'PRIORITY_MAIL' => 'Priority Mail',
        'PRIORITY_MAIL_EXPRESS' => 'Priority Mail Express',
        'PARCEL_SELECT' => 'Parcel Select',
        'PARCEL_SELECT_LIGHTWEIGHT' => 'Parcel Select Lightweight',
        'MEDIA_MAIL' => 'Media Mail',
        'LIBRARY_MAIL' => 'Library Mail',
        'BOUND_PRINTED_MATTER' => 'Bound Printed Matter',
        'FIRST_CLASS_MAIL' => 'First-Class Mail',
        'PRIORITY_MAIL_INTERNATIONAL' => 'Priority Mail International',
        'PRIORITY_MAIL_EXPRESS_INTERNATIONAL' => 'Priority Mail Express International',
        'FIRST_CLASS_PACKAGE_INTERNATIONAL_SERVICE' => 'First-Class Package International Service',
        'GLOBAL_EXPRESS_GUARANTEED' => 'Global Express Guaranteed',
    ];

    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::MAIL_CLASSES as $code => $label) {
            $options[] = [
                'value' => $code,
                'label' => __($label),
            ];
        }
        return $options;
    }

    public function toArray(): array
    {
        $options = [];
        foreach (self::MAIL_CLASSES as $code => $label) {
            $options[$code] = __($label);
        }
        return $options;
    }

    public function getMailClassLabel(string $code): string
    {
        return self::MAIL_CLASSES[$code] ?? $code;
    }
}
