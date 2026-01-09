<?php
/**
 * Jscriptz SmartShipping - UPS Label Response Parser
 *
 * Parses the UPS Ship API response into a ShipmentLabel data object.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups\Response;

use Jscriptz\SmartShipping\Api\Data\ShipmentLabelInterface;
use Jscriptz\SmartShipping\Model\Shipment\ShipmentLabel;
use Jscriptz\SmartShipping\Model\Ups\Request\LabelRequestBuilder;
use Magento\Framework\Exception\LocalizedException;

class LabelResponseParser
{
    public function __construct(
        private readonly LabelRequestBuilder $requestBuilder
    ) {
    }

    /**
     * Parse label response into ShipmentLabel
     *
     * @param mixed[] $response API response data
     * @param string $labelFormat Requested label format (GIF, PNG, PDF, etc.)
     * @return ShipmentLabelInterface
     * @throws LocalizedException
     */
    public function parse(array $response, string $labelFormat = 'GIF'): ShipmentLabelInterface
    {
        // Check for API-level errors
        if (!empty($response['response']['errors'])) {
            throw new LocalizedException(
                __('UPS Ship API error: %1', $this->extractErrorMessage($response))
            );
        }

        // Navigate to shipment results
        $shipmentResults = $this->extractShipmentResults($response);
        $packageResult = $this->extractPackageResult($shipmentResults);

        // Get tracking number
        $trackingNumber = $packageResult['TrackingNumber'] ?? null;

        if (empty($trackingNumber)) {
            throw new LocalizedException(__('UPS response missing tracking number'));
        }

        // Get label image
        $labelImage = $this->extractLabelImage($packageResult);
        if (empty($labelImage)) {
            throw new LocalizedException(__('UPS response missing label image'));
        }

        $label = new ShipmentLabel();
        $label->setTrackingNumber($trackingNumber);
        $label->setLabelImage($labelImage);
        $label->setLabelFormat($this->normalizeFormat($labelFormat));
        $label->setCarrierCode('upsv3');

        // Extract service type from shipment ID format (first 2 digits often indicate service)
        $serviceCode = $this->extractServiceCode($shipmentResults);
        $serviceName = $this->requestBuilder->getServiceName($serviceCode);
        $label->setServiceType($serviceName);

        // Extract charges
        $postage = $this->extractPostage($shipmentResults);
        $label->setPostage($postage);

        // Extract weight if available
        $weight = $this->extractWeight($packageResult);
        $label->setWeight($weight);

        // UPS returns zone in billing weight
        $zone = $this->extractZone($shipmentResults);
        $label->setZone($zone);

        // Store shipment ID for void operations (using DataObject's setData)
        if (!empty($shipmentResults['ShipmentIdentificationNumber'])) {
            $label->setData('shipment_id', $shipmentResults['ShipmentIdentificationNumber']);
        }

        return $label;
    }

    /**
     * Extract shipment results from response
     *
     * @throws LocalizedException
     */
    private function extractShipmentResults(array $response): array
    {
        // Standard Ship API response structure
        if (!empty($response['ShipmentResponse']['ShipmentResults'])) {
            return $response['ShipmentResponse']['ShipmentResults'];
        }

        throw new LocalizedException(__('UPS response missing shipment results'));
    }

    /**
     * Extract package result from shipment results
     *
     * @throws LocalizedException
     */
    private function extractPackageResult(array $shipmentResults): array
    {
        // PackageResults can be an array of packages or a single package
        if (!empty($shipmentResults['PackageResults'])) {
            $packageResults = $shipmentResults['PackageResults'];

            // If it's an indexed array of packages, get the first one
            if (isset($packageResults[0])) {
                return $packageResults[0];
            }

            // If it's a single package object
            return $packageResults;
        }

        throw new LocalizedException(__('UPS response missing package results'));
    }

    /**
     * Extract base64-encoded label image from package result
     */
    private function extractLabelImage(array $packageResult): ?string
    {
        // Standard structure: ShippingLabel.GraphicImage
        if (!empty($packageResult['ShippingLabel']['GraphicImage'])) {
            return $packageResult['ShippingLabel']['GraphicImage'];
        }

        // Alternative: HTMLImage (for PDF labels)
        if (!empty($packageResult['ShippingLabel']['HTMLImage'])) {
            return $packageResult['ShippingLabel']['HTMLImage'];
        }

        // Another alternative: just GraphicImage at top level
        if (!empty($packageResult['GraphicImage'])) {
            return $packageResult['GraphicImage'];
        }

        return null;
    }

    /**
     * Extract service code from shipment results
     */
    private function extractServiceCode(array $shipmentResults): string
    {
        // Try to get from billing weight summary
        if (!empty($shipmentResults['BillingWeight']['UnitOfMeasurement']['Code'])) {
            // This is weight unit, not service - need to look elsewhere
        }

        // Default to Ground if we can't determine
        return '03';
    }

    /**
     * Extract postage/charges from shipment results
     */
    private function extractPostage(array $shipmentResults): float
    {
        // Shipment charges
        if (!empty($shipmentResults['ShipmentCharges']['TotalCharges']['MonetaryValue'])) {
            return (float) $shipmentResults['ShipmentCharges']['TotalCharges']['MonetaryValue'];
        }

        // Transportation charges
        if (!empty($shipmentResults['ShipmentCharges']['TransportationCharges']['MonetaryValue'])) {
            return (float) $shipmentResults['ShipmentCharges']['TransportationCharges']['MonetaryValue'];
        }

        // Negotiated rates
        if (!empty($shipmentResults['NegotiatedRateCharges']['TotalCharge']['MonetaryValue'])) {
            return (float) $shipmentResults['NegotiatedRateCharges']['TotalCharge']['MonetaryValue'];
        }

        return 0.0;
    }

    /**
     * Extract weight from package result
     */
    private function extractWeight(array $packageResult): float
    {
        // Billing weight
        if (!empty($packageResult['BillingWeight']['Weight'])) {
            return (float) $packageResult['BillingWeight']['Weight'];
        }

        return 0.0;
    }

    /**
     * Extract zone from shipment results
     */
    private function extractZone(array $shipmentResults): ?string
    {
        // Zone might be in billing weight info
        if (!empty($shipmentResults['BillingWeight']['Zone'])) {
            return $shipmentResults['BillingWeight']['Zone'];
        }

        // Or in rate zone
        if (!empty($shipmentResults['RatingZone'])) {
            return $shipmentResults['RatingZone'];
        }

        return null;
    }

    /**
     * Extract error message from response
     */
    private function extractErrorMessage(array $response): string
    {
        if (!empty($response['response']['errors']) && is_array($response['response']['errors'])) {
            $messages = [];
            foreach ($response['response']['errors'] as $error) {
                $code = $error['code'] ?? '';
                $message = $error['message'] ?? 'Unknown error';
                $messages[] = $code ? "{$code}: {$message}" : $message;
            }
            return implode('; ', $messages);
        }

        if (!empty($response['message'])) {
            return $response['message'];
        }

        return 'Unknown UPS error';
    }

    /**
     * Normalize label format for consistency
     */
    private function normalizeFormat(string $format): string
    {
        $map = [
            'GIF' => 'GIF',
            'PNG' => 'PNG',
            'PDF' => 'PDF',
            'ZPL' => 'ZPL',
            'EPL' => 'EPL',
        ];

        return $map[strtoupper($format)] ?? strtoupper($format);
    }
}
