<?php
/**
 * Jscriptz SmartShipping - FedEx Label Response Parser
 *
 * Parses the FedEx Ship API response into a ShipmentLabel data object.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Response;

use Jscriptz\SmartShipping\Api\Data\ShipmentLabelInterface;
use Jscriptz\SmartShipping\Model\Shipment\ShipmentLabel;
use Jscriptz\SmartShipping\Model\Fedex\Config\Source\ServiceType;
use Magento\Framework\Exception\LocalizedException;

class LabelResponseParser
{
    public function __construct(
        private readonly ServiceType $serviceType
    ) {
    }

    /**
     * Parse label response into ShipmentLabel
     *
     * @param mixed[] $response API response data
     * @param string $labelFormat Requested label format (PDF, PNG, ZPLII, etc.)
     * @return ShipmentLabelInterface
     * @throws LocalizedException
     */
    public function parse(array $response, string $labelFormat = 'PDF'): ShipmentLabelInterface
    {
        // Check for API-level errors
        if (!empty($response['errors'])) {
            throw new LocalizedException(
                __('FedEx Ship API error: %1', $this->extractErrorMessage($response))
            );
        }

        // Navigate to shipment data
        $shipment = $this->extractShipmentData($response);
        $pieceResponse = $this->extractPieceResponse($shipment);

        // Get tracking number
        $trackingNumber = $pieceResponse['masterTrackingNumber']
            ?? $pieceResponse['trackingNumber']
            ?? null;

        if (empty($trackingNumber)) {
            throw new LocalizedException(__('FedEx response missing tracking number'));
        }

        // Get label image
        $labelImage = $this->extractLabelImage($pieceResponse);
        if (empty($labelImage)) {
            throw new LocalizedException(__('FedEx response missing label image'));
        }

        $label = new ShipmentLabel();
        $label->setTrackingNumber($trackingNumber);
        $label->setLabelImage($labelImage);
        $label->setLabelFormat($this->normalizeFormat($labelFormat));
        $label->setCarrierCode('fedexv3');

        // Extract service type
        $serviceCode = $shipment['serviceType'] ?? '';
        $serviceName = $shipment['serviceName'] ?? $this->serviceType->getServiceLabel($serviceCode);
        $label->setServiceType($serviceName);

        // Extract postage/charges
        $postage = $this->extractPostage($pieceResponse);
        $label->setPostage($postage);

        // Extract weight if available
        $weight = $this->extractWeight($pieceResponse);
        $label->setWeight($weight);

        // FedEx doesn't return zone in ship response, set to null
        $label->setZone(null);

        return $label;
    }

    /**
     * Extract shipment data from response
     *
     * @throws LocalizedException
     */
    private function extractShipmentData(array $response): array
    {
        // Standard Ship API response structure
        if (!empty($response['output']['transactionShipments'][0])) {
            return $response['output']['transactionShipments'][0];
        }

        // Alternative response structure
        if (!empty($response['transactionShipments'][0])) {
            return $response['transactionShipments'][0];
        }

        throw new LocalizedException(__('FedEx response missing shipment data'));
    }

    /**
     * Extract piece response (package data) from shipment
     *
     * @throws LocalizedException
     */
    private function extractPieceResponse(array $shipment): array
    {
        if (!empty($shipment['pieceResponses'][0])) {
            return $shipment['pieceResponses'][0];
        }

        // Alternative structure
        if (!empty($shipment['completedShipmentDetail']['completedPackageDetails'][0])) {
            return $shipment['completedShipmentDetail']['completedPackageDetails'][0];
        }

        throw new LocalizedException(__('FedEx response missing package data'));
    }

    /**
     * Extract base64-encoded label image from piece response
     */
    private function extractLabelImage(array $pieceResponse): ?string
    {
        // Standard structure: packageDocuments array
        if (!empty($pieceResponse['packageDocuments'])) {
            foreach ($pieceResponse['packageDocuments'] as $doc) {
                if (isset($doc['encodedLabel'])) {
                    return $doc['encodedLabel'];
                }
                // Alternative field name
                if (isset($doc['parts'][0]['image'])) {
                    return $doc['parts'][0]['image'];
                }
            }
        }

        // Alternative structure: label directly on piece
        if (!empty($pieceResponse['label']['parts'][0]['image'])) {
            return $pieceResponse['label']['parts'][0]['image'];
        }

        // Another alternative
        if (!empty($pieceResponse['encodedLabel'])) {
            return $pieceResponse['encodedLabel'];
        }

        return null;
    }

    /**
     * Extract postage/charges from piece response
     */
    private function extractPostage(array $pieceResponse): float
    {
        // Net charge is the actual amount charged
        if (isset($pieceResponse['netCharge'])) {
            return (float) $pieceResponse['netCharge'];
        }

        // Net rate amount
        if (isset($pieceResponse['netRateAmount'])) {
            return (float) $pieceResponse['netRateAmount'];
        }

        // Base rate as fallback
        if (isset($pieceResponse['baseRateAmount'])) {
            return (float) $pieceResponse['baseRateAmount'];
        }

        // Look in package rating
        if (!empty($pieceResponse['packageRating']['effectiveNetDiscount'])) {
            return (float) $pieceResponse['packageRating']['effectiveNetDiscount'];
        }

        if (!empty($pieceResponse['packageRating']['packageRateDetails'][0]['netCharge'])) {
            return (float) $pieceResponse['packageRating']['packageRateDetails'][0]['netCharge'];
        }

        return 0.0;
    }

    /**
     * Extract weight from piece response
     */
    private function extractWeight(array $pieceResponse): float
    {
        // Weight in piece response
        if (isset($pieceResponse['weight']['value'])) {
            return (float) $pieceResponse['weight']['value'];
        }

        if (isset($pieceResponse['billedWeight']['value'])) {
            return (float) $pieceResponse['billedWeight']['value'];
        }

        // Alternative structure
        if (isset($pieceResponse['packageWeight']['value'])) {
            return (float) $pieceResponse['packageWeight']['value'];
        }

        return 0.0;
    }

    /**
     * Extract error message from response
     */
    private function extractErrorMessage(array $response): string
    {
        if (!empty($response['errors']) && is_array($response['errors'])) {
            $messages = [];
            foreach ($response['errors'] as $error) {
                $code = $error['code'] ?? '';
                $message = $error['message'] ?? 'Unknown error';
                $messages[] = $code ? "{$code}: {$message}" : $message;
            }
            return implode('; ', $messages);
        }

        if (!empty($response['message'])) {
            return $response['message'];
        }

        return 'Unknown FedEx error';
    }

    /**
     * Normalize label format for consistency
     */
    private function normalizeFormat(string $format): string
    {
        $map = [
            'ZPLII' => 'ZPL',
            'ZPL' => 'ZPL',
            'EPL2' => 'EPL',
            'EPL' => 'EPL',
            'PDF' => 'PDF',
            'PNG' => 'PNG',
        ];

        return $map[strtoupper($format)] ?? strtoupper($format);
    }
}
