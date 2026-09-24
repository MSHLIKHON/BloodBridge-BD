/** File purpose: Maps provides browser-side behaviour for the BloodBridge BD interface. */
(() => {
    const createMap = canvas => {
        if (!window.L) throw new Error('Map could not load. You may enter coordinates manually.');
        const map = L.map(canvas, {scrollWheelZoom: false}).setView([23.81, 90.41], 12);
        const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        tiles.on('tileerror', () => {
            const message = canvas.parentElement.querySelector('[data-map-message]');
            if (message) message.textContent = 'Street tiles are unavailable. Coordinates and nearby results still work.';
        });
        return map;
    };
    document.querySelectorAll('[data-map-picker]').forEach(picker => {
        const lat = picker.querySelector('[name=latitude]');
        const lng = picker.querySelector('[name=longitude]');
        const message = picker.querySelector('[data-map-message]');
        let map, marker;
        const point = (latitude, longitude) => {
            if(latitude < 20.5 || latitude > 26.7 || longitude < 88 || longitude > 92.7) {
                message.textContent = 'Choose a point within the Bangladesh service area.'; return;
            }
            lat.value = latitude.toFixed(7); lng.value = longitude.toFixed(7);
            if(map) {
                if(marker) marker.remove();
                marker=L.circleMarker([latitude,longitude],{radius:8}).addTo(map);
                map.setView([latitude,longitude],15);
            }
            message.textContent = 'Pin selected. Save the form to store this location.';
        };
        try {
            map = createMap(picker.querySelector('[data-map-canvas]'));
            if(lat.value !== '' && lng.value !== '') point(Number(lat.value),Number(lng.value));
            map.on('click',event=>point(event.latlng.lat,event.latlng.lng));
        } catch(error) {message.textContent=error.message;}
        [lat,lng].forEach(input=>input.addEventListener('change',()=>{
            if(lat.value!=='' && lng.value!=='' && Number.isFinite(Number(lat.value)) && Number.isFinite(Number(lng.value))) point(Number(lat.value),Number(lng.value));
        }));
        picker.querySelector('[data-map-clear]').addEventListener('click',()=>{
            lat.value='';lng.value='';if(marker)marker.remove();message.textContent='Pin cleared.';
        });
        picker.querySelector('[data-locate]').addEventListener('click',()=>{
            if(!navigator.geolocation) {message.textContent='Location is unavailable. Use the map or enter coordinates.';return;}
            message.textContent='Waiting for location permission…';
            navigator.geolocation.getCurrentPosition(position=>{
                point(position.coords.latitude,position.coords.longitude);
                message.textContent += ' Reported accuracy: '+Math.round(position.coords.accuracy)+' metres. Adjust the pin if needed.';
            },()=>{message.textContent='Location denied or unavailable. Click the map or enter coordinates.';},{enableHighAccuracy:true,timeout:10000,maximumAge:0});
        });
    });
    document.querySelectorAll('[data-nearby]').forEach(section=>{
        let map, markers=[];
        const message=section.querySelector('[data-map-message]');
        section.querySelector('button').addEventListener('click',async event=>{
            event.currentTarget.disabled=true;
            try {
                const response=await fetch('nearby.php?request_id='+encodeURIComponent(section.dataset.nearby));
                const data=await response.json();
                if(!response.ok)throw new Error(data.error || 'Nearby search unavailable.');
                if(!map)map=createMap(section.querySelector('[data-map-canvas]'));
                markers.forEach(marker=>marker.remove());markers=[];
                const origin=[data.origin.latitude,data.origin.longitude];
                markers.push(L.circle(origin,{radius:1000}).addTo(map));
                const list=section.querySelector('[data-nearby-list]');list.replaceChildren();
                data.donors.forEach(donor=>{
                    const item=document.createElement('li');item.textContent=donor.label+' · '+donor.blood_group+' · '+donor.area;list.appendChild(item);
                    const label=document.createElement('span');label.textContent=donor.label+' — approximate area';
                    markers.push(L.circleMarker([donor.latitude,donor.longitude],{radius:7}).addTo(map).bindPopup(label));
                });
                map.fitBounds(markers[0].getBounds(),{padding:[20,20]});
                message.textContent=data.donors.length+' available donor(s) found'+(data.limited?' (limited results)':'')+'. Markers show approximate areas, not exact addresses. Donors can respond to your request.';
            } catch(error) {message.textContent=error.message;}
            finally {event.currentTarget.disabled=false;}
        });
    });
})();
