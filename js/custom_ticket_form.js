document.addEventListener('DOMContentLoaded', function () {

    console.log('[exportdata] script chargé, URL:', window.location.pathname);

    const extraFields = document.getElementById('exportdata-extra-fields');
    const urgencyField = document.querySelector('select[name="urgency"]');

    console.log('[exportdata] extraFields trouvé ?', extraFields);
    console.log('[exportdata] urgencyField trouvé ?', urgencyField);

    if (!extraFields || !urgencyField) {
        console.warn('[exportdata] arrêt : un des deux éléments est introuvable');
        return;
    }

    // 1. Type
    const typeSelect = document.querySelector('select[name="type"]');
    if (typeSelect) {
        Array.from(typeSelect.options).forEach(function (opt) {
            if (opt.text.trim() === 'Demande') opt.text = 'Service';
        });
    }

    // 2. Impact
    const impactSelect = document.querySelector('select[name="impact"]');
    if (impactSelect) {
        const impactLabels = {
            'Très faible': 'Sans impact',
            'Moyenne': 'Impact moyen',
            'Très élevée': 'Impact critique'
        };
        Array.from(impactSelect.options).forEach(function (opt) {
            const text = opt.text.trim();
            if (impactLabels[text]) opt.text = impactLabels[text];
        });
    }

    // 3. Urgence -> Priorité
    document.querySelectorAll('label').forEach(function (label) {
        if (label.textContent.trim() === 'Urgence') label.textContent = 'Priorité';
    });

    // 4. Déplacement
    let urgencyRow = urgencyField.closest('.row') || urgencyField.parentElement.parentElement;
    console.log('[exportdata] urgencyRow trouvé ?', urgencyRow);

    if (urgencyRow) {
        urgencyRow.insertAdjacentElement('afterend', extraFields);
        extraFields.style.display = '';
        console.log('[exportdata] déplacement effectué');
    } else {
        console.warn('[exportdata] impossible de trouver la ligne parente d\'Urgence');
    }
});