/** File purpose: App provides browser-side behaviour for the BloodBridge BD interface. */
document.querySelector('[data-nav-toggle]')?.addEventListener('click', (event) => {
    const navigation = document.querySelector('[data-nav]');
    const isOpen = navigation?.classList.toggle('open') || false;
    event.currentTarget.setAttribute('aria-expanded', String(isOpen));
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

document.querySelectorAll('[data-location-picker]').forEach((picker) => {
    const divisionSelect = picker.querySelector('[data-location-division]');
    const districtSelect = picker.querySelector('[data-location-district]');
    const upazilaSelect = picker.querySelector('[data-location-upazila]');
    const dataElement = picker.querySelector('[data-location-data]');

    if (!divisionSelect || !districtSelect || !upazilaSelect || !dataElement) return;

    let hierarchy = {};
    try {
        hierarchy = JSON.parse(dataElement.textContent || '{}');
    } catch (error) {
        return;
    }

    const initialDistrict = districtSelect.dataset.selected || '';
    const initialUpazila = upazilaSelect.dataset.selected || '';

    const setOptions = (select, values, placeholder, selectedValue = '') => {
        select.replaceChildren(new Option(placeholder, ''));
        values.forEach((value) => select.add(new Option(value, value)));
        select.disabled = values.length === 0;
        if (values.includes(selectedValue)) select.value = selectedValue;
    };

    const syncUpazilas = (selectedValue = '') => {
        const upazilas = hierarchy[divisionSelect.value]?.[districtSelect.value] || [];
        setOptions(
            upazilaSelect,
            upazilas,
            districtSelect.value ? 'Select upazila / area' : 'Select district first',
            selectedValue
        );
    };

    const syncDistricts = (selectedDistrict = '', selectedUpazila = '') => {
        const districts = Object.keys(hierarchy[divisionSelect.value] || {});
        setOptions(
            districtSelect,
            districts,
            divisionSelect.value ? 'Select district' : 'Select division first',
            selectedDistrict
        );
        syncUpazilas(selectedUpazila);
    };

    divisionSelect.addEventListener('change', () => syncDistricts());
    districtSelect.addEventListener('change', () => syncUpazilas());
    syncDistricts(initialDistrict, initialUpazila);
});

const requestForm = document.querySelector('[data-request-form]');
if (requestForm) {
    const sourceSelect = requestForm.querySelector('[data-source-select]');
    const hospitalField = requestForm.querySelector('[data-hospital-field]');
    const hospitalSelect = hospitalField?.querySelector('select');
    const syncHospitalField = () => {
        const needsHospital = sourceSelect?.value === 'Blood Bank';
        if (hospitalField) hospitalField.hidden = !needsHospital;
        if (hospitalSelect) hospitalSelect.required = needsHospital;
    };
    sourceSelect?.addEventListener('change', syncHospitalField);
    syncHospitalField();
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

const livePage = document.querySelector('[data-live-page]');
if (livePage) {
    let currentVersion = livePage.dataset.liveVersion || '';
    let formIsDirty = false;

    document.addEventListener('input', (event) => {
        if (event.target.closest('form')) formIsDirty = true;
    });
    document.addEventListener('change', (event) => {
        if (event.target.closest('form')) formIsDirty = true;
    });

    window.setInterval(async () => {
        if (document.hidden || formIsDirty || document.querySelector('input:focus, select:focus, textarea:focus')) return;

        try {
            const response = await fetch('live_updates.php', {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) return;

            const data = await response.json();
            if (currentVersion && data.version && data.version !== currentVersion) {
                window.location.reload();
                return;
            }
            currentVersion = data.version || currentVersion;
        } catch (error) {
            // A temporary network error should not interrupt the page.
        }
    }, 5000);
}
