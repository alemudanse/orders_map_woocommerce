(function(){
	function init(){
		var el = document.getElementById('wom-admin-map');
		if(!el || typeof L === 'undefined') return;
		var map = L.map(el).setView([51.505, -0.09], 11);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; OpenStreetMap contributors'
		}).addTo(map);

		var selection = new Set();
		var markers = [];

		function loadPoints(){
			var b = map.getBounds();
			var url = new URL(WOM_AdminMap.root + '/admin/orders-for-map', window.location.origin);
			url.searchParams.set('bounds[south]', b.getSouth());
			url.searchParams.set('bounds[west]', b.getWest());
			url.searchParams.set('bounds[north]', b.getNorth());
			url.searchParams.set('bounds[east]', b.getEast());
			return fetch(url.toString(), { headers: { 'X-WP-Nonce': WOM_AdminMap.nonce }, credentials: 'same-origin' })
				.then(function(r){ return r.json(); })
				.then(function(points){
					markers.forEach(function(m){ map.removeLayer(m); });
					markers = [];
					selection.clear();
					var bounds = [];
					(points || []).forEach(function(p){
						var marker = L.marker([p.lat, p.lng]).addTo(map);
						marker.bindPopup('<strong>Order #' + p.number + '</strong><br>' + (p.address||'') + '<br>Status: ' + p.status + '<br>Driver: ' + (p.assignedDriver||'-'));
						marker.on('click', function(){ toggleSelect(p.id, marker); });
						marker._orderId = p.id;
						markers.push(marker);
						bounds.push([p.lat, p.lng]);
					});
					if(bounds.length) map.fitBounds(bounds, { padding: [20,20] });
				});
		}

		function toggleSelect(orderId, marker){
			if(selection.has(orderId)) { selection.delete(orderId); marker.setOpacity(1); }
			else { selection.add(orderId); marker.setOpacity(0.6); }
			updateToolbar();
		}

		function updateToolbar(){
			var bar = document.getElementById('wom-admin-toolbar');
			if(!bar){
				bar = document.createElement('div');
				bar.id = 'wom-admin-toolbar';
				bar.style.marginTop = '8px';
				el.parentNode.insertBefore(bar, el.nextSibling);
				bar.innerHTML = '<label>Driver ID <input type="number" id="wom-driver-id" style="width:100px"/></label> ' +
					'<button class="button" id="wom-assign">Assign</button> <button class="button" id="wom-unassign">Unassign</button> '+
					'<span id="wom-count"></span>';
				bar.querySelector('#wom-assign').addEventListener('click', doAssign);
				bar.querySelector('#wom-unassign').addEventListener('click', doUnassign);
			}
			bar.querySelector('#wom-count').textContent = selection.size + ' selected';
		}

		function doAssign(){
			var driverId = parseInt(document.getElementById('wom-driver-id').value,10) || 0;
			if(!driverId || selection.size===0) return;
			fetch(WOM_AdminMap.root + '/admin/assign', {
				method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': WOM_AdminMap.nonce },
				body: JSON.stringify({ order_ids: Array.from(selection), driver_id: driverId })
			}).then(function(){ loadPoints(); });
		}
		function doUnassign(){
			if(selection.size===0) return;
			fetch(WOM_AdminMap.root + '/admin/unassign', {
				method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': WOM_AdminMap.nonce },
				body: JSON.stringify({ order_ids: Array.from(selection) })
			}).then(function(){ loadPoints(); });
		}

		map.on('moveend', loadPoints);
		loadPoints();
	}
	if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();

