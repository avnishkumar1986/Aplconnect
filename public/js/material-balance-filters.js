(function () {
    'use strict';

    var company = document.getElementById('balance-company');
    var plant = document.getElementById('balance-plant');
    var group = document.getElementById('balance-material-group');
    var material = document.getElementById('balance-material');
    if (!company || !plant || !material) return;

    var form = material.closest('form');
    var submitting = false;

    var plantOptions = Array.prototype.slice.call(plant.options, 1).map(function (option) { return option.cloneNode(true); });
    var materialOptions = Array.prototype.slice.call(material.options).map(function (option) { return option.cloneNode(true); });

    function refreshPlants(keepSelection) {
        var selected = keepSelection ? (plant.dataset.selected || plant.value) : '';
        plant.replaceChildren(new Option('All plants', ''));
        plantOptions.filter(function (option) {
            return !company.value || option.dataset.company === company.value;
        }).forEach(function (option) { plant.appendChild(option.cloneNode(true)); });
        if (selected && Array.prototype.some.call(plant.options, function (option) { return option.value === selected; })) plant.value = selected;
        plant.dataset.selected = '';
        if (window.jQuery) window.jQuery(plant).trigger('change.select2');
    }

    function refreshMaterials(keepSelection) {
        if (!group) return;
        var selected = keepSelection ? (material.dataset.selected || material.value) : '';
        material.replaceChildren(new Option('— Select material —', ''));
        materialOptions.filter(function (option) {
            return option.dataset.group === group.value;
        }).forEach(function (option) { material.appendChild(option.cloneNode(true)); });
        if (selected && Array.prototype.some.call(material.options, function (option) { return option.value === selected; })) material.value = selected;
        if (!material.value && material.options.length > 1) material.selectedIndex = 1;
        material.dataset.selected = '';
        material.disabled = !group.value;
        if (window.jQuery) window.jQuery(material).trigger('change.select2');
    }

    company.addEventListener('change', function () { refreshPlants(false); });
    if (group) group.addEventListener('change', function () { refreshMaterials(false); });
    material.addEventListener('change', function () {
        if (!form || submitting || !material.value) return;
        submitting = true;
        form.requestSubmit();
    });
    function bindSelect2Dependencies() {
        if (!window.jQuery || material.dataset.balanceFilterBound === 'true') return;
        material.dataset.balanceFilterBound = 'true';
        window.jQuery(company).on('select2:select select2:clear', function () { refreshPlants(false); });
        if (group) window.jQuery(group).on('select2:select select2:clear', function () { refreshMaterials(false); });
        window.jQuery(material).on('select2:select', function () {
            if (!form || submitting || !material.value) return;
            submitting = true;
            form.requestSubmit();
        });
    }
    bindSelect2Dependencies();
    document.addEventListener('DOMContentLoaded', bindSelect2Dependencies, { once: true });
    refreshPlants(true);
    if (group) refreshMaterials(true);
}());
