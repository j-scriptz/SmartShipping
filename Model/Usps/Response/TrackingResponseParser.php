<?php
/**
 * Jscriptz SmartShipping - USPS Tracking Response Parser
 *
 * Parses the USPS Tracking API v3 response into a structured format.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Response;

class TrackingResponseParser
{
    /**
     * Parse tracking response into structured data
     *
     * @param mixed[] $response API response
     * @return mixed[] Parsed tracking info
     */
    public function parse(array $response): array
    {
        $tracking = [
            'tracking_number' => $response['trackingNumber'] ?? '',
            'status' => $this->parseStatus($response),
            'status_description' => $response['statusSummary'] ?? $response['status'] ?? '',
            'carrier' => 'USPS',
            'service_type' => $response['mailClass'] ?? $response['serviceType'] ?? '',
            'expected_delivery_date' => $response['expectedDeliveryDate'] ?? null,
            'expected_delivery_time' => $response['expectedDeliveryTime'] ?? null,
            'delivery_date' => $response['actualDeliveryDate'] ?? null,
            'origin' => $this->parseLocation($response['originCity'] ?? '', $response['originState'] ?? '', $response['originZIPCode'] ?? ''),
            'destination' => $this->parseLocation($response['destinationCity'] ?? '', $response['destinationState'] ?? '', $response['destinationZIPCode'] ?? ''),
            'events' => $this->parseEvents($response['trackingEvents'] ?? []),
        ];

        // Add additional details if available
        if (!empty($response['weight'])) {
            $tracking['weight'] = $response['weight'];
        }

        if (!empty($response['signedForByName'])) {
            $tracking['signed_by'] = $response['signedForByName'];
        }

        return $tracking;
    }

    /**
     * Parse overall status
     */
    private function parseStatus(array $response): string
    {
        // Check for delivered
        if (!empty($response['actualDeliveryDate'])) {
            return 'delivered';
        }

        // Check status summary
        $statusSummary = strtolower($response['statusSummary'] ?? '');

        if (strpos($statusSummary, 'delivered') !== false) {
            return 'delivered';
        }

        if (strpos($statusSummary, 'out for delivery') !== false) {
            return 'out_for_delivery';
        }

        if (strpos($statusSummary, 'in transit') !== false) {
            return 'in_transit';
        }

        if (strpos($statusSummary, 'picked up') !== false) {
            return 'picked_up';
        }

        if (strpos($statusSummary, 'pre-shipment') !== false || strpos($statusSummary, 'label created') !== false) {
            return 'label_created';
        }

        if (strpos($statusSummary, 'exception') !== false || strpos($statusSummary, 'alert') !== false) {
            return 'exception';
        }

        return 'unknown';
    }

    /**
     * Parse location into formatted string
     */
    private function parseLocation(string $city, string $state, string $zip): string
    {
        $parts = array_filter([$city, $state, $zip]);
        return implode(', ', $parts);
    }

    /**
     * Parse tracking events
     */
    private function parseEvents(array $events): array
    {
        $parsed = [];

        foreach ($events as $event) {
            $parsed[] = [
                'date' => $event['eventDate'] ?? null,
                'time' => $event['eventTime'] ?? null,
                'datetime' => $this->parseDateTime($event['eventDate'] ?? '', $event['eventTime'] ?? ''),
                'description' => $event['event'] ?? $event['eventType'] ?? '',
                'location' => $this->parseLocation(
                    $event['eventCity'] ?? '',
                    $event['eventState'] ?? '',
                    $event['eventZIPCode'] ?? ''
                ),
                'event_code' => $event['eventCode'] ?? null,
            ];
        }

        return $parsed;
    }

    /**
     * Parse date and time into ISO format
     */
    private function parseDateTime(string $date, string $time): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            $dateStr = $date;
            if (!empty($time)) {
                $dateStr .= ' ' . $time;
            }

            $dt = new \DateTime($dateStr);
            return $dt->format('c');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if package was delivered
     */
    public function isDelivered(array $response): bool
    {
        return !empty($response['actualDeliveryDate']);
    }

    /**
     * Get latest event description
     */
    public function getLatestEvent(array $response): string
    {
        $events = $response['trackingEvents'] ?? [];
        if (empty($events)) {
            return $response['statusSummary'] ?? 'No tracking information available';
        }

        $latest = $events[0];
        return $latest['event'] ?? $latest['eventType'] ?? '';
    }
}
