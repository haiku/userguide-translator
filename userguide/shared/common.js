const ping_delay = 60; // in seconds

function HTTPRequest(method, endpoint, params = null, listeners = null) {
	var xml_http = new XMLHttpRequest();
	var url = base_url + '/' + endpoint;
	var body = null;
	var paramString = null;

	if (params != null) {
		var paramArray = [];
		for (const [k,v] of Object.entries(params)) {
			paramArray.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
		}
		paramString = paramArray.join('&');

		if (method == 'GET') {
			url = url + '?' + paramString;
		} else if (method == 'POST') {
			body = paramString;
		}
	}

	if (listeners != null) {
		for (const [k,v] of Object.entries(listeners)) {
			xml_http.addEventListener(k, v);
		}
	}

	xml_http.open(method, url, true);
	if (method == 'POST') {
		xml_http.setRequestHeader('Content-Type',
			'application/x-www-form-urlencoded');
	}
	xml_http.send(body);

	return xml_http;
}

function lockDocument(doc_id) {
	function pingServer() {
		HTTPRequest('GET', 'lock.php', {doc_id: doc_id});
	}

	setInterval(pingServer, ping_delay * 1000);
}

function formatText(s, lang = null) {
	return s.replace(/\{LANG_CODE\}/g, lang || window.lang || 'en');
}
