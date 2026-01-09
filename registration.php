<?php
/**
 * Jscriptz SmartShipping - Combined Shipping Module
 *
 * Universal shipping module with USPS, UPS, and FedEx carriers
 * plus Smart Shipping UI features for Luma and Hyva themes.
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Jscriptz_SmartShipping',
    __DIR__
);
