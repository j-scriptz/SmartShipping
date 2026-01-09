<?php
/**
 * Jscriptz SmartShipping - USPS Label Client
 *
 * Creates shipping labels via the USPS Labels API v3.
 * Requires both OAuth2 token AND payment authorization token.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Client;

use Jscriptz\SmartShipping\Model\Usps\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class LabelClient
{
    public function __construct(
        private readonly Config $config,
        private readonly AuthClient $authClient,
        private readonly PaymentClient $paymentClient,
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create shipping label via USPS API
     *
     * @param mixed[] $requestData Built by LabelRequestBuilder
     * @param int|null $storeId
     * @return mixed[] API response
     * @throws LocalizedException
     */
    public function createLabel(array $requestData, ?int $storeId = null): array
    {
        $url = $this->config->getLabelUrl($storeId);

        // Get both required tokens
        $accessToken = $this->authClient->getAccessToken($storeId);
        $paymentToken = $this->paymentClient->getPaymentAuthToken($storeId);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[USPSv3 Label] Creating label', [
                'url' => $url,
                'mail_class' => $requestData['packageDescription']['mailClass'] ?? 'unknown',
                'weight' => $requestData['packageDescription']['weight'] ?? 'unknown',
            ]);
        }

        $this->curl->setHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'X-Payment-Authorization-Token' => $paymentToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        try {
            $this->curl->post($url, $this->json->serialize($requestData));
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('USPS Label API connection failed: %1', $e->getMessage())
            );
        }

        $statusCode = $this->curl->getStatus();
        $responseBody = $this->curl->getBody();

        if ($this->config->isDebugEnabled($storeId)) {
            // Don't log full label image (base64), just the tracking number
            $debugResponse = $this->json->unserialize($responseBody);
            if (!empty($debugResponse['labelImage'])) {
                $debugResponse['labelImage'] = '[BASE64_IMAGE_' . strlen($debugResponse['labelImage']) . '_bytes]';
            }
            $this->logger->debug('[USPSv3 Label] API Response', [
                'status' => $statusCode,
                'tracking' => $debugResponse['trackingNumber'] ?? 'N/A',
                'postage' => $debugResponse['postage'] ?? $debugResponse['totalBasePrice'] ?? 'N/A',
            ]);
        }

        if ($statusCode !== 200 && $statusCode !== 201) {
            $error = $this->parseError($responseBody, $statusCode);

            // Invalidate tokens if auth error
            if ($statusCode === 401 || $statusCode === 403) {
                $this->authClient->invalidateToken($storeId);
                $this->paymentClient->invalidateToken($storeId);
            }

            throw new LocalizedException(
                __('USPS Label creation failed: %1', $error)
            );
        }

        try {
            return $this->json->unserialize($responseBody);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Invalid USPS Label API response: %1', $e->getMessage())
            );
        }
    }

    /**
     * Cancel/void a label
     *
     * @param string $trackingNumber
     * @param int|null $storeId
     * @return bool
     * @throws LocalizedException
     */
    public function voidLabel(string $trackingNumber, ?int $storeId = null): bool
    {
        // USPS void endpoint: DELETE /labels/v3/label/{trackingNumber}
        $url = $this->config->getLabelUrl($storeId) . '/' . $trackingNumber;

        $accessToken = $this->authClient->getAccessToken($storeId);

        $this->curl->setHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ]);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[USPSv3 Label] Voiding label', [
                'tracking_number' => $trackingNumber,
            ]);
        }

        // Curl class doesn't have native DELETE, use setOption
        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, 'DELETE');

        try {
            $this->curl->get($url);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('USPS Label void failed: %1', $e->getMessage())
            );
        }

        $statusCode = $this->curl->getStatus();

        if ($statusCode === 200 || $statusCode === 204) {
            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[USPSv3 Label] Label voided successfully', [
                    'tracking_number' => $trackingNumber,
                ]);
            }
            return true;
        }

        $responseBody = $this->curl->getBody();
        $error = $this->parseError($responseBody, $statusCode);
        throw new LocalizedException(
            __('USPS Label void failed: %1', $error)
        );
    }

    /**
     * Parse error from response
     */
    private function parseError(string $responseBody, int $statusCode): string
    {
        try {
            $response = $this->json->unserialize($responseBody);

            // Check various error formats
            if (!empty($response['error']['message'])) {
                return $response['error']['message'];
            }

            if (!empty($response['error']['errors']) && is_array($response['error']['errors'])) {
                $messages = [];
                foreach ($response['error']['errors'] as $error) {
                    $msg = $error['message'] ?? '';
                    if (!empty($error['code'])) {
                        $msg = "[{$error['code']}] " . $msg;
                    }
                    if ($msg) {
                        $messages[] = $msg;
                    }
                }
                if (!empty($messages)) {
                    return implode('; ', $messages);
                }
            }

            if (!empty($response['message'])) {
                return $response['message'];
            }

            if (!empty($response['errors']) && is_array($response['errors'])) {
                $messages = array_column($response['errors'], 'message');
                if (!empty($messages)) {
                    return implode('; ', $messages);
                }
            }
        } catch (\Exception $e) {
            // Fall through to default
        }

        return 'HTTP ' . $statusCode . ' error';
    }
}
