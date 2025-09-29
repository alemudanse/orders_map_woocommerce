/* global WOM_Driver, wp */

(function () {
	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				if (k === 'className') node.className = attrs[k];
				else if (k === 'text') node.textContent = attrs[k];
				else node.setAttribute(k, attrs[k]);
			});
		}
		(children || []).forEach(function (c) { if (c) node.appendChild(c); });
		return node;
	}

	function fetchOrders() {
		var url = WOM_Driver.root + '/driver/orders';
		return window.fetch(url, {
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': WOM_Driver.nonce
			}
		}).then(function (r) { return r.json(); });
	}

	function updateOrderStatus(orderId, status) {
		var url = WOM_Driver.root + '/driver/orders/' + orderId + '/status';
		return window.fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': WOM_Driver.nonce
			},
			body: JSON.stringify({ status: status })
		}).then(function (r) { return r.json(); });
	}

function initiatePod(orderId, method) {
	var url = WOM_Driver.root + '/driver/orders/' + orderId + '/pod/initiate';
	return window.fetch(url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': WOM_Driver.nonce },
		body: JSON.stringify({ method: method })
	}).then(function(r){ return r.json(); });
}

function requestCustomerLocation(orderId, method){
	var url = WOM_Driver.root + '/driver/orders/' + orderId + '/request-location';
	return window.fetch(url, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','X-WP-Nonce':WOM_Driver.nonce}, body: JSON.stringify({ method: method }) }).then(function(r){ return r.json(); });
}

function shareDriverLocation(orderId){
	if(!navigator.geolocation) { alert('Geolocation not supported'); return; }
	navigator.geolocation.getCurrentPosition(function(p){
		var url = WOM_Driver.root + '/driver/orders/' + orderId + '/location';
		window.fetch(url, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','X-WP-Nonce':WOM_Driver.nonce}, body: JSON.stringify({ lat: p.coords.latitude, lng: p.coords.longitude }) }).then(function(r){ return r.json(); }).then(function(){ alert('Driver location shared'); });
	});
}

	function render(rootId) {
		var root = document.getElementById(rootId);
		if (!root) return;
		root.innerHTML = '';

		var header = el('div', { className: 'wom-header' }, [
			el('h2', { text: 'My Deliveries' })
		]);
		var list = el('div', { className: 'wom-orders' });
		root.appendChild(header);
		root.appendChild(list);

		fetchOrders().then(function (orders) {
			list.innerHTML = '';
			if (!orders || !orders.length) {
				list.appendChild(el('div', { className: 'wom-empty', text: 'No assigned orders.' }));
				return;
			}
			orders.forEach(function (o) {
				var actions = el('div', { className: 'wom-actions' }, [
					button('En route', function () { doUpdate(o.id, 'en_route'); }),
					button('Delivered', function () { doUpdate(o.id, 'delivered'); }),
					button('Failed', function () { doUpdate(o.id, 'failed'); })
				]);
				var pod = el('div', { className: 'wom-pod' }, [
					label('Confirm Delivery via: '),
					select(['email','sms'], 'email', function (val) { pod._method = val; }),
					button('Send Link', function () { doPod(o.id, pod._method || 'email'); })
				]);
				var loc = el('div', { className: 'wom-loc' }, [
					label('Request Customer Location via: '),
					select(['email','sms'], 'email', function (val) { loc._method = val; }),
					button('Request Location', function () { doLoc(o.id, loc._method || 'email'); }),
					button('Share My Location', function(){ shareDriverLocation(o.id); })
				]);
				var card = el('div', { className: 'wom-card' }, [
					el('div', { className: 'wom-line', text: 'Order #' + o.number + ' — ' + (o.driverStatus || 'assigned') }),
					el('div', { className: 'wom-line', text: (o.customer && o.customer.name) ? o.customer.name : '' }),
					el('div', { className: 'wom-line', text: address(o) }),
					el('div', { className: 'wom-line' }, [ navLink(o) ]),
					actions,
					pod,
					loc
				]);
				list.appendChild(card);
			});
		});

		function doUpdate(orderId, status) {
			updateOrderStatus(orderId, status).then(function () { render(rootId); });
		}

		function address(o) {
			var s = o.shipping || {};
			return [s.address1, s.address2, s.city, s.postcode, s.country].filter(Boolean).join(', ');
		}

		function navLink(o){
			if(!o) return null;
			var addr = [ (o.shipping&&o.shipping.address1)||'', (o.shipping&&o.shipping.city)||'', (o.shipping&&o.shipping.postcode)||'', (o.shipping&&o.shipping.country)||'' ].filter(Boolean).join(' ');
			var gnav = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(addr);
			var a = el('a', { href: gnav, target: '_blank', rel: 'noopener' }, [ el('span', { text: 'Navigate' }) ]);
			return a;
		}

		function button(label, onClick) {
			var b = el('button', { className: 'button button-secondary', type: 'button', text: label });
			b.addEventListener('click', onClick);
			return b;
		}

		function label(text) {
			return el('label', { text: text });
		}

		function select(options, value, onChange) {
			var s = el('select');
			(options || []).forEach(function(opt){
				var o = el('option', { value: opt, text: opt.toUpperCase() });
				if (opt === value) o.selected = true;
				s.appendChild(o);
			});
			s.addEventListener('change', function(){ onChange && onChange(s.value); });
			return s;
		}

		function doPod(orderId, method) {
			initiatePod(orderId, method).then(function(){
				alert('Confirmation link sent via ' + method.toUpperCase());
			});
		}

		function doLoc(orderId, method){
			requestCustomerLocation(orderId, method).then(function(){
				alert('Location request sent via ' + method.toUpperCase());
			});
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		render('wom-driver-dashboard-root');
	});
})();

