(function () {
    'use strict';

    function updatePurchaseOrder(vendor) {
        if (!vendor || !vendor.matches('[data-vendor]')) return;

        var form = vendor.closest('form');
        var purchaseOrder = form && form.querySelector('[data-single-vendor-purchase-order]');
        if (!purchaseOrder) return;

        var selected = vendor.options[vendor.selectedIndex];
        var orders = selected ? (selected.getAttribute('data-purchase-orders') || '') : '';
        purchaseOrder.value = orders.split(',')[0].trim();
        purchaseOrder.placeholder = vendor.value ? 'No SAP purchase order found' : 'Select a vendor first';
    }

    function initialise(root) {
        (root || document).querySelectorAll('[data-vendor]').forEach(updatePurchaseOrder);
    }

    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-vendor]')) updatePurchaseOrder(event.target);
    });

    if (window.jQuery) {
        window.jQuery(document).on('select2:select select2:clear', '[data-vendor]', function () {
            updatePurchaseOrder(this);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initialise(document); }, { once: true });
    } else {
        initialise(document);
    }

    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) return;
                if (node.matches && node.matches('[data-vendor]')) updatePurchaseOrder(node);
                if (node.querySelectorAll) initialise(node);
            });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });
}());
