const ping_delay = 60; // in seconds

function lockDocument(doc_id) {
	function pingServer() {
		var xml_http = new XMLHttpRequest();

		xml_http.open('GET', base_url + '/lock.php?doc_id=' + doc_id);
		xml_http.send(null);
	}

	setInterval(pingServer, ping_delay * 1000);
}

function formatText(s, lang = null) {
	return s.replace(/\{LANG_CODE\}/g, lang || window.lang || 'en');
}
