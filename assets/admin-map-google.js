(function(){
	function init(){
		var el = document.getElementById('wom-admin-map');
		if(!el || !(window.google && google.maps)) return;
		var map = new google.maps.Map(el, { center: { lat: 51.505, lng: -0.09 }, zoom: 11 });
		var selection = new Set();
		var markers = [];
		var storeMarker = null;

		function loadPoints(){
			var b = map.getBounds(); if(!b) { setTimeout(loadPoints, 200); return; }
			var url = new URL(WOM_AdminMap.root + '/admin/orders-for-map', window.location.origin);
			url.searchParams.set('bounds[south]', b.getSouthWest().lat());
			url.searchParams.set('bounds[west]', b.getSouthWest().lng());
			url.searchParams.set('bounds[north]', b.getNorthEast().lat());
			url.searchParams.set('bounds[east]', b.getNorthEast().lng());
			url.searchParams.set('live', '1');
			fetch(url.toString(), { headers: { 'X-WP-Nonce': WOM_AdminMap.nonce }, credentials: 'same-origin' })
				.then(function(r){ if(!r.ok){ throw new Error('Request failed: ' + r.status); } return r.json(); })
				.then(function(points){
					markers.forEach(function(m){ m.setMap(null); }); markers=[]; selection.clear(); updateToolbar();
					var bounds = new google.maps.LatLngBounds();
					(points||[]).forEach(function(p){
						var m = new google.maps.Marker({ position: { lat: p.lat, lng: p.lng }, map: map, title: 'Order #' + p.number });
						m.addListener('click', function(){ toggleSelect(p.id, m); });
						markers.push(m); bounds.extend(m.getPosition());
						if(p.driverLat && p.driverLng){
							var dm = new google.maps.Marker({ position: { lat: p.driverLat, lng: p.driverLng }, map: map, icon: { path: google.maps.SymbolPath.CIRCLE, scale: 6, fillColor: '#2684ff', fillOpacity: 1, strokeColor: '#0052cc', strokeWeight: 1 }, title: 'Driver for #' + p.number });
							markers.push(dm); bounds.extend(dm.getPosition());
						}
						if(p.customerLat && p.customerLng){
							var cm = new google.maps.Marker({ position: { lat: p.customerLat, lng: p.customerLng }, map: map, icon: { path: google.maps.SymbolPath.CIRCLE, scale: 6, fillColor: '#34a853', fillOpacity: 1, strokeColor: '#137333', strokeWeight: 1 }, title: 'Customer for #' + p.number });
							markers.push(cm); bounds.extend(cm.getPosition());
						}
					});
					if(!bounds.isEmpty()) map.fitBounds(bounds);
				})
				.catch(function(err){
					var bar = document.getElementById('wom-admin-toolbar');
					if(!bar){ updateToolbar(); bar = document.getElementById('wom-admin-toolbar'); }
					if(bar){
						var msg = document.createElement('div');
						msg.style.color = '#b32d2e';
						msg.textContent = 'Could not load orders for map (' + (err && err.message ? err.message : 'unknown error') + ').';
						bar.appendChild(msg);
					}
				});
		}

		// Periodically refresh for live positions
		setInterval(loadPoints, 20000);

		function toggleSelect(id, m){
			if(selection.has(id)) { selection.delete(id); m.setOpacity(1); }
			else { selection.add(id); m.setOpacity(0.6); }
			updateToolbar();
		}

		function updateToolbar(){
			var bar = document.getElementById('wom-admin-toolbar');
			if(!bar){
				bar = document.createElement('div'); bar.id='wom-admin-toolbar'; bar.style.marginTop='8px';
				el.parentNode.insertBefore(bar, el.nextSibling);
				bar.innerHTML = '<label>Driver ID <input type="number" id="wom-driver-id" style="width:100px"/></label> <button class="button" id="wom-assign">Assign</button> <button class="button" id="wom-unassign">Unassign</button> <span id="wom-count"></span>';
				bar.querySelector('#wom-assign').addEventListener('click', doAssign);
				bar.querySelector('#wom-unassign').addEventListener('click', doUnassign);
			}
			bar.querySelector('#wom-count').textContent = selection.size + ' selected';
		}

		function doAssign(){
			var driverId = parseInt(document.getElementById('wom-driver-id').value,10) || 0;
			if(!driverId || selection.size===0) return;
			fetch(WOM_AdminMap.root + '/admin/assign', { method:'POST', credentials:'same-origin', headers:{ 'Content-Type':'application/json', 'X-WP-Nonce': WOM_AdminMap.nonce }, body: JSON.stringify({ order_ids: Array.from(selection), driver_id: driverId }) }).then(function(){ loadPoints(); });
		}
		function doUnassign(){
			if(selection.size===0) return;
			fetch(WOM_AdminMap.root + '/admin/unassign', { method:'POST', credentials:'same-origin', headers:{ 'Content-Type':'application/json', 'X-WP-Nonce': WOM_AdminMap.nonce }, body: JSON.stringify({ order_ids: Array.from(selection) }) }).then(function(){ loadPoints(); });
		}

		map.addListener('idle', loadPoints);
		loadPoints();

		// Load store marker once
		fetch(WOM_AdminMap.root + '/admin/store-location', { headers: { 'X-WP-Nonce': WOM_AdminMap.nonce }, credentials: 'same-origin' })
			.then(function(r){ if(!r.ok){ return null; } return r.json(); })
			.then(function(s){ if(!s || !s.lat){ return; } if(storeMarker){ storeMarker.setMap(null); } storeMarker = new google.maps.Marker({ position: { lat: s.lat, lng: s.lng }, map: map, label: 'S', title: 'Store: ' + (s.address||'') }); });
	}
	if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();

