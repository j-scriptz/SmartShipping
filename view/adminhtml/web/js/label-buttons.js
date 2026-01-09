/**
 * Jscriptz SmartShipping - Label Buttons
 *
 * Replaces Magento's Print Shipping Label button with View/Download buttons
 */
define(['jquery', 'domReady!'], function($) {
    'use strict';

    return function(config) {
        var printBtn = $('button[title="Print Shipping Label"]');
        if (!printBtn.length) {
            return;
        }

        var viewUrl = config.viewUrl;
        var downloadUrl = config.downloadUrl;
        var format = config.format || 'PDF';

        // Create container for our buttons
        var container = $('<div class="smartshipping-label-buttons"></div>').css({
            'display': 'flex',
            'gap': '10px',
            'flex-wrap': 'wrap',
            'margin': '15px 0'
        });

        // View in Browser button (orange)
        var viewBtn = $('<a></a>')
            .attr('href', viewUrl)
            .attr('target', '_blank')
            .attr('rel', 'noopener')
            .css({
                'display': 'inline-flex',
                'align-items': 'center',
                'padding': '10px 20px',
                'background': '#eb5202',
                'border': '1px solid #eb5202',
                'color': '#fff',
                'font-weight': '600',
                'font-size': '13px',
                'text-decoration': 'none',
                'border-radius': '3px',
                'cursor': 'pointer'
            })
            .html('<span>View in Browser (' + format + ')</span>');

        // Download button (dark)
        var downloadBtn = $('<a></a>')
            .attr('href', downloadUrl)
            .css({
                'display': 'inline-flex',
                'align-items': 'center',
                'padding': '10px 20px',
                'background': '#514943',
                'border': '1px solid #514943',
                'color': '#fff',
                'font-weight': '600',
                'font-size': '13px',
                'text-decoration': 'none',
                'border-radius': '3px',
                'cursor': 'pointer'
            })
            .html('<span>Download Label</span>');

        container.append(viewBtn).append(downloadBtn);

        // Replace the original button's parent paragraph with our container
        printBtn.closest('p').replaceWith(container);
    };
});
