<?php
/**
 * Jscriptz SmartShipping - Product Attribute Source Model
 *
 * Provides a list of product attributes for dimension/weight mapping.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Config\Source;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttribute implements OptionSourceInterface
{
    private ?array $options = null;

    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $this->options = $this->buildOptions();
        }
        return $this->options;
    }

    /**
     * Build options from product attributes
     */
    private function buildOptions(): array
    {
        $options = [
            ['value' => '', 'label' => __('-- Use Default Value --')],
        ];

        // Common dimension/weight attributes to show first
        $priorityAttributes = [
            'weight' => 'Weight',
            'length' => 'Length',
            'width' => 'Width',
            'height' => 'Height',
            'depth' => 'Depth',
            'ts_dimensions_length' => 'Length (TS)',
            'ts_dimensions_width' => 'Width (TS)',
            'ts_dimensions_height' => 'Height (TS)',
        ];

        // Add priority attributes first if they exist
        foreach ($priorityAttributes as $code => $label) {
            try {
                $attribute = $this->attributeRepository->get($code);
                if ($attribute && $attribute->getAttributeId()) {
                    $options[] = [
                        'value' => $code,
                        'label' => $attribute->getDefaultFrontendLabel() ?: $label,
                    ];
                }
            } catch (\Exception $e) {
                // Attribute doesn't exist, skip
            }
        }

        // Add separator if we found priority attributes
        if (count($options) > 1) {
            $options[] = ['value' => '', 'label' => '──────────────'];
        }

        // Get all decimal/text attributes that could contain numeric values
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('frontend_input', ['text', 'weight', 'price'], 'in')
                ->addFilter('is_visible', 1)
                ->create();

            $attributes = $this->attributeRepository->getList($searchCriteria);

            $existingCodes = array_keys($priorityAttributes);

            foreach ($attributes->getItems() as $attribute) {
                $code = $attribute->getAttributeCode();

                // Skip if already in priority list or system attributes
                if (in_array($code, $existingCodes) || $this->isSystemAttribute($code)) {
                    continue;
                }

                $label = $attribute->getDefaultFrontendLabel();
                if ($label) {
                    $options[] = [
                        'value' => $code,
                        'label' => $label . ' (' . $code . ')',
                    ];
                }
            }
        } catch (\Exception $e) {
            // If attribute loading fails, just return what we have
        }

        return $options;
    }

    /**
     * Check if attribute is a system attribute we should skip
     */
    private function isSystemAttribute(string $code): bool
    {
        $systemAttributes = [
            'sku', 'name', 'description', 'short_description', 'price', 'special_price',
            'cost', 'status', 'visibility', 'tax_class_id', 'url_key', 'meta_title',
            'meta_keyword', 'meta_description', 'image', 'small_image', 'thumbnail',
            'media_gallery', 'gallery', 'category_ids', 'required_options', 'has_options',
            'created_at', 'updated_at', 'quantity_and_stock_status', 'options_container',
            'msrp', 'msrp_display_actual_price_type', 'gift_message_available',
        ];

        return in_array($code, $systemAttributes);
    }
}
