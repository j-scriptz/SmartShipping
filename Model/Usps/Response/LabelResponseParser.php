<?php
/**
 * Jscriptz SmartShipping - USPS Label Response Parser
 *
 * Parses the USPS Labels API response into a ShipmentLabel data object.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Response;

use Jscriptz\SmartShipping\Api\Data\ShipmentLabelInterface;
use Jscriptz\SmartShipping\Model\Shipment\ShipmentLabel;
use Jscriptz\SmartShipping\Model\Usps\Config\Source\MailClass;
use Magento\Framework\Exception\LocalizedException;

class LabelResponseParser
{
    /**
     * Parse label response into ShipmentLabel
     *
     * @param mixed[] $response API response data
     * @param string $labelFormat Requested label format
     * @return ShipmentLabelInterface
     * @throws LocalizedException
     */
    public function parse(array $response, string $labelFormat = 'PDF'): ShipmentLabelInterface
    {
        // Check for errors
        if (!empty($response['error'])) {
            throw new LocalizedException(
                __('USPS Label API error: %1', $this->extractErrorMessage($response))
            );
        }

        // Validate required fields
        if (empty($response['trackingNumber'])) {
            throw new LocalizedException(__('USPS Label response missing tracking number'));
        }

        if (empty($response['labelImage'])) {
            throw new LocalizedException(__('USPS Label response missing label image'));
        }

        $label = new ShipmentLabel();
        $label->setTrackingNumber($response['trackingNumber']);
        $label->setLabelImage($response['labelImage']);
        $label->setLabelFormat($labelFormat);
        $label->setCarrierCode('uspsv3');

        // Extract service type from mail class
        $mailClass = $response['mailClass'] ?? $response['packageDescription']['mailClass'] ?? '';
        $label->setServiceType($this->getServiceLabel($mailClass));

        // Extract postage
        $postage = $this->extractPostage($response);
        $label->setPostage($postage);

        // Extract zone if available
        if (!empty($response['zone'])) {
            $label->setZone($response['zone']);
        }

        // Extract weight
        $weight = $response['weight'] ?? $response['packageDescription']['weight'] ?? 0;
        $label->setWeight((float) $weight);

        return $label;
    }

    /**
     * Extract error message from response
     */
    private function extractErrorMessage(array $response): string
    {
        if (!empty($response['error']['message'])) {
            return $response['error']['message'];
        }

        if (!empty($response['error']['errors']) && is_array($response['error']['errors'])) {
            $messages = [];
            foreach ($response['error']['errors'] as $error) {
                if (!empty($error['message'])) {
                    $messages[] = $error['message'];
                }
            }
            if (!empty($messages)) {
                return implode('; ', $messages);
            }
        }

        if (!empty($response['message'])) {
            return $response['message'];
        }

        return 'Unknown error';
    }

    /**
     * Extract postage amount from response
     */
    private function extractPostage(array $response): float
    {
        // Direct postage field
        if (isset($response['postage'])) {
            return (float) $response['postage'];
        }

        // Nested in SKU
        if (!empty($response['SKU']['postage'])) {
            return (float) $response['SKU']['postage'];
        }

        // Look in price fields
        if (!empty($response['totalBasePrice'])) {
            return (float) $response['totalBasePrice'];
        }

        return 0.0;
    }

    /**
     * Get human-readable service label from mail class code
     */
    private function getServiceLabel(string $mailClass): string
    {
        $labels = [
            MailClass::GROUND_ADVANTAGE => 'USPS Ground Advantage',
            MailClass::PRIORITY_MAIL => 'Priority Mail',
            MailClass::PRIORITY_MAIL_EXPRESS => 'Priority Mail Express',
            MailClass::FIRST_CLASS_MAIL => 'First-Class Mail',
            MailClass::PARCEL_SELECT => 'Parcel Select',
            MailClass::MEDIA_MAIL => 'Media Mail',
            MailClass::LIBRARY_MAIL => 'Library Mail',
        ];

        return $labels[$mailClass] ?? $mailClass;
    }
}
