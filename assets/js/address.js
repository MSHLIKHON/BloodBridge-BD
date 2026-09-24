/** File purpose: Address provides browser-side behaviour for the BloodBridge BD interface. */
document.querySelectorAll('[data-address-picker]').forEach(picker => {
    const data = JSON.parse(picker.querySelector('[data-address-data]').textContent);
    const division = picker.querySelector('[data-address-division]');
    const district = picker.querySelector('[data-address-district]');
    const area = picker.querySelector('[data-address-upazila]');
    const fill = (select, values) => {
        const placeholder = select.options[0].textContent;
        select.replaceChildren(new Option(placeholder, ''));
        values.forEach(value => select.add(new Option(value, value)));
    };
    division.addEventListener('change', () => {
        fill(district, Object.keys(data[division.value] || {}));
        fill(area, []);
    });
    district.addEventListener('change', () => fill(area, data[division.value]?.[district.value] || []));
});
