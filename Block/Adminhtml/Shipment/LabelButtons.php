<?php
/**
 * Jscriptz SmartShipping - Label Buttons Block
 *
 * Renders View/Download label buttons on shipment view page.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Block\Adminhtml\Shipment;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\Shipment;

class LabelButtons extends Template
{
    private ?Shipment $shipment = null;

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get current shipment
     */
    public function getShipment(): ?Shipment
    {
        if ($this->shipment !== null) {
            return $this->shipment;
        }

        // Try registry first
        $shipment = $this->registry->registry('current_shipment');
        if ($shipment) {
            $this->shipment = $shipment;
            return $this->shipment;
        }

        // Fall back to request parameter
        $shipmentId = (int) $this->getRequest()->getParam('shipment_id');
        if ($shipmentId) {
            try {
                $this->shipment = $this->shipmentRepository->get($shipmentId);
                return $this->shipment;
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Check if shipment has a label
     */
    public function hasLabel(): bool
    {
        $shipment = $this->getShipment();
        if (!$shipment) {
            return false;
        }

        $label = $shipment->getShippingLabel();
        return !empty($label);
    }

    /**
     * Get view label URL (opens in new tab)
     */
    public function getViewLabelUrl(): string
    {
        $shipment = $this->getShipment();
        if (!$shipment) {
            return '';
        }

        return $this->getUrl('smartshipping/shipment/getLabel', [
            'shipment_id' => $shipment->getId(),
            'mode' => 'view',
        ]);
    }

    /**
     * Get download label URL
     */
    public function getDownloadLabelUrl(): string
    {
        $shipment = $this->getShipment();
        if (!$shipment) {
            return '';
        }

        return $this->getUrl('smartshipping/shipment/getLabel', [
            'shipment_id' => $shipment->getId(),
            'mode' => 'download',
        ]);
    }

    /**
     * Get label format for display
     */
    public function getLabelFormat(): string
    {
        $shipment = $this->getShipment();
        if (!$shipment) {
            return 'PDF';
        }

        $label = $shipment->getShippingLabel();
        if (empty($label)) {
            return 'PDF';
        }

        // Detect format from content
        if (str_starts_with($label, '%PDF')) {
            return 'PDF';
        }
        if (str_starts_with($label, "\x89PNG")) {
            return 'PNG';
        }
        if (str_starts_with($label, '^XA')) {
            return 'ZPL';
        }

        return 'PDF';
    }
}
