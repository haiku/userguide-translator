const ping_delay = 60; // in seconds

const color_border = '#FF9900';
const color_hover = '#FFFFCC';

const attr_trans_id = '_translation_id';
const attr_state = '_edit_state';

var edited_node = null;
var linked_nodes = new Array();
var original_text;

function endEditionEvent(clickOK) {
	if (window.edited_node == null)
		return;

	var id = window.edited_node.getAttribute(attr_trans_id);
	var next_node = null;

	var new_text = edit_window.document.getElementById('modified').value;

	if (clickOK && new_text) {
		var not_mark = edit_window.document.getElementById('not_mark').checked;

		source_strings[id] = new_text;

		var xml_http = new XMLHttpRequest();

		var encoded_text = encodeURI(new_text).replace(/&/g, '%26');

		xml_http.open('POST', base_url + '/block_edit.php', true);
		xml_http.setRequestHeader('Content-Type',
			'application/x-www-form-urlencoded');
		xml_http.addEventListener("load", editSaveFinished);
		xml_http.send('edit_doc=' + doc_id + '&edit_string=' + id +
			'&edit_text=' + encoded_text + '&dont_mark_fuzzy=' + (not_mark ? '1' : '0'));

		xml_http.userguide_string_id = id;
		xml_http.userguide_new_text = new_text;
		return;
	} else {
		window.edited_node.innerHTML = formatText(source_strings[id]);
	}

	edit_window.close();
	edit_window = null;
	window.edited_node = null;
}

function editSaveFinished() {
	edit_window.focus();

	var resp = this.responseText,
		id = this.userguide_string_id,
		new_text = this.userguide_new_text;

	var send_ok;
	if (resp.substring(0, 7) == 'badxml ')
		edit_window.alert('The server rejected the translation because of XML ' +
			"parsing errors :\n" + this.responseText.substring(3) +
			"\n" + 'Check the XML tags used in your translation.');
	else if (resp.substring(0, 6) == 'interr')
		edit_window.alert('The original XML code seems corrupt. Please contact ' +
		'an administrator.' + "\n");
	else if (resp.substring(0, 2) != 'ok')
		edit_window.alert('There was an error sending the translation. Please ' +
		'retry.' + "\n" + this.responseText);
	else
		send_ok = true;

	for (var i = 0 ; i < linked_nodes[id].length ; i++) {
		linked_nodes[id][i].innerHTML = formatText(new_text);
		linked_nodes[id][i].style.backgroundColor = null;
		if (send_ok) {
			linked_nodes[id][i].removeAttribute(attr_state);
		} else {
			linked_nodes[id][i].setAttribute(attr_state, 'error');
		}
	}

	if (!send_ok) {
		edit_window.focus();
		return;
	}

	edit_window.close();
	edit_window = null;
	window.edited_node = null;

	// Refresh the statistics
	xml_http = new XMLHttpRequest();
	xml_http.open('GET', base_url + '/update_stats.php?doc_id=' + doc_id, true);
	xml_http.send(null);
}

function pingServer() {
	var xml_http = new XMLHttpRequest();

	xml_http.open('GET', base_url + '/lock.php?doc_id=' + doc_id);
	xml_http.send(null);

	window.setTimeout(pingServer, ping_delay * 1000);
}

function mouseOverEvent(e) {
	this.style.backgroundColor = color_hover;
}

function mouseOutEvent(e) {
	this.style.backgroundColor = null;
}

function mouseClickEvent(e) {
	if (window.edited_node != null) {
		edit_window.focus();
		return false;
	}

	window.edited_node = this;

	var id = this.getAttribute(attr_trans_id);

	edit_window = window.open(base_url + '/shared/edit_tool.html',
		'Edit Block', 'width=600,height=300,toolbar=0,status=0');
	window.original_text = source_strings[id];

	return true;
}

function imgMouseClickEvent(e) {
	var src = this.getAttribute("src");
	if (base_local != '.') {
		src = base_local + '/' + src;
	}
	window.open(base_url + '/res_upload.php?path=' + encodeURIComponent(src),
		src, 'width=800,height=600,status=0,toolbar=0,location=0,menubar=0,directories=0,resizable=1,scrollbars=1');

	return true;
}

function setProperties(node) {
	if (node == null)
		return;

	if (node.getAttribute) { // Avoid special nodes
		if (node.getAttribute(attr_trans_id) != null) {
			var id = node.getAttribute(attr_trans_id);

			if (source_strings[id]) {
				node.style.border = '1px dotted ' + color_border;
				node.onmouseover = mouseOverEvent;
				node.onmouseout = mouseOutEvent;
				node.onclick = mouseClickEvent;

				if (linked_nodes[id] == null) {
					linked_nodes[id] = [ node ];
				} else {
					linked_nodes[id].push(node);
				}
			}

			return;
		} else if (node.tagName.toLowerCase() == "img") {
			node.style.padding = "2px";
			node.style.border = "1px dotted " + color_border;
			node.onmouseover = mouseOverEvent;
			node.onmouseout = mouseOutEvent;
			node.onclick = imgMouseClickEvent;
		}
	}

	for (var i = 0 ; i < node.childNodes.length ; i++) {
		setProperties(node.childNodes[i]);
	}
}

function formatText(s) {
	return s.replace(/\{LANG_CODE\}/g, 'en');
}

window.onload = function() {
	var functions_ok = 0;

	if (window.XMLHttpRequest)
		functions_ok++;

	if (encodeURI)
		functions_ok++;

	if (functions_ok != 2) {
		window.alert('Your browser does not support some JavaScript ' +
			'functions which are needed for this page to work correctly. ' +
			"\nBrowser known to work : Safari 4, Firefox/BeZillaBrowser 2.x, " + "3.x.");
		return;
	} else {
		window.setTimeout(pingServer, ping_delay * 1000);
	}

	setProperties(document.getElementsByTagName('body')[0]);
}
