(function(){
	function init(){
		var el = document.getElementById('wom-admin-map');
		if(!el || !(window.google && google.maps)) return;
		var map = new google.maps.Map(el, { center: { lat: 51.505, lng: -0.09 }, zoom: 11 });
		var selection = new Set();
		var markers = [];

		function loadPoints(){
			var b = map.getBounds(); if(!b) { setTimeout(loadPoints, 200); return; }
			var url = new URL(WOM_AdminMap.root + '/admin/orders-for-map', window.location.origin);
			url.searchParams.set('bounds[south]', b.getSouthWest().lat());
			url.searchParams.set('bounds[west]', b.getSouthWest().lng());
			url.searchParams.set('bounds[north]', b.getNorthEast().lat());
			url.searchParams.set('bounds[east]', b.getNorthEast().lng());
			fetch(url.toString(), { headers: { 'X-WP-Nonce': WOM_AdminMap.nonce }, credentials: 'same-origin' })
				.then(function(r){ return r.json(); })
				.then(function(points){
					markers.forEach(function(m){ m.setMap(null); }); markers=[]; selection.clear(); updateToolbar();
					var bounds = new google.maps.LatLngBounds();
					(points||[]).forEach(function(p){
						var m = new google.maps.Marker({ position: { lat: p.lat, lng: p.lng }, map: map });
						m.addListener('click', function(){ toggleSelect(p.id, m); });
						markers.push(m); bounds.extend(m.getPosition());
					});
					if(!bounds.isEmpty()) map.fitBounds(bounds);
				});
		}

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
	}
	if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();

