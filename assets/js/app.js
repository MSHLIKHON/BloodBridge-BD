document.querySelector('[data-nav-toggle]')?.addEventListener('click', () => {
    document.querySelector('[data-nav]')?.classList.toggle('open');
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const message = form.dataset.confirm || 'Are you sure?';
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});

const registrationForm = document.querySelector('[data-registration-form]');
if (registrationForm) {
    const roleSelect = registrationForm.querySelector('[data-role-select]');
    const bloodGroupField = registrationForm.querySelector('[data-blood-group-field]');
    const bloodGroupSelect = bloodGroupField?.querySelector('select');
    const syncBloodGroup = () => {
        const isDonor = roleSelect?.value === 'donor';
        if (bloodGroupField) bloodGroupField.hidden = !isDonor;
        if (bloodGroupSelect) bloodGroupSelect.required = isDonor;
    };
    roleSelect?.addEventListener('change', syncBloodGroup);
    syncBloodGroup();
}

const healthForm = document.querySelector('[data-health-form]');
if (healthForm) {
    const conditionSelect = healthForm.querySelector('[data-condition-select]');
    const conditionDetails = healthForm.querySelector('[data-condition-details]');
    const conditionTextarea = conditionDetails?.querySelector('textarea');
    const syncConditionDetails = () => {
        const hasCondition = conditionSelect?.value === '1';
        if (conditionDetails) conditionDetails.hidden = !hasCondition;
        if (conditionTextarea) conditionTextarea.required = hasCondition;
    };
    conditionSelect?.addEventListener('change', syncConditionDetails);
    syncConditionDetails();
}
