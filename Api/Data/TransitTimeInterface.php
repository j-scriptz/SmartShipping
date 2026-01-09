<?php
/**
 * Jscriptz SmartShipping - Transit Time Data Interface
 *
 * Service contract for transit time data provided by shipping carriers.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Api\Data;

interface TransitTimeInterface
{
    public const CARRIER_CODE = 'carrier_code';
    public const METHOD_CODE = 'method_code';
    public const MIN_DAYS = 'min_days';
    public const MAX_DAYS = 'max_days';
    public const DELIVERY_DATE = 'delivery_date';
    public const DELIVERY_DAY = 'delivery_day';
    public const DELIVERY_TIME = 'delivery_time';
    public const GUARANTEED = 'guaranteed';
    public const CUTOFF_HOUR = 'cutoff_hour';

    /**
     * Get carrier code
     */
    public function getCarrierCode(): string;

    /**
     * Set carrier code
     */
    public function setCarrierCode(string $carrierCode): self;

    /**
     * Get method code
     */
    public function getMethodCode(): string;

    /**
     * Set method code
     */
    public function setMethodCode(string $methodCode): self;

    /**
     * Get minimum transit days
     */
    public function getMinDays(): int;

    /**
     * Set minimum transit days
     */
    public function setMinDays(int $minDays): self;

    /**
     * Get maximum transit days
     */
    public function getMaxDays(): int;

    /**
     * Set maximum transit days
     */
    public function setMaxDays(int $maxDays): self;

    /**
     * Get estimated delivery date (Y-m-d format)
     */
    public function getDeliveryDate(): ?string;

    /**
     * Set estimated delivery date
     */
    public function setDeliveryDate(?string $deliveryDate): self;

    /**
     * Get delivery day of week (e.g., "Monday")
     */
    public function getDeliveryDay(): ?string;

    /**
     * Set delivery day of week
     */
    public function setDeliveryDay(?string $deliveryDay): self;

    /**
     * Get delivery time (e.g., "10:30 A.M.")
     */
    public function getDeliveryTime(): ?string;

    /**
     * Set delivery time
     */
    public function setDeliveryTime(?string $deliveryTime): self;

    /**
     * Check if delivery is guaranteed
     */
    public function isGuaranteed(): bool;

    /**
     * Set guaranteed flag
     */
    public function setGuaranteed(bool $guaranteed): self;

    /**
     * Get cutoff hour for same-day shipping
     */
    public function getCutoffHour(): ?int;

    /**
     * Set cutoff hour
     */
    public function setCutoffHour(?int $cutoffHour): self;
}
