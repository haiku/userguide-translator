const attr_trans_id = '_translation_id';
const attr_state = '_edit_state';

const edit_tool = '/shared/edit_tool.html';
const edit_tool_ctx = 'EditBlock';

var edited_node = null;
var linked_nodes = new Array();
var original_text;

function sendEdition(node, id, new_text, not_mark) {
	source_strings[id] = new_text;

	var xml_http = new XMLHttpRequest();

	var encoded_text = encodeURI(new_text).replace(/&/g, '%26');

	xml_http.open('POST', base_url + '/block_edit.php', true);
	xml_http.setRequestHeader('Content-Type',
		'application/x-www-form-urlencoded');
	xml_http.addEventListener("load", serverRequestListener);
	xml_http.send('edit_doc=' + doc_id + '&edit_string=' + id +
		'&edit_text=' + encoded_text + '&dont_mark_fuzzy=' + (not_mark ? '1' : '0'));

	xml_http.userguide_string_id = id;
	xml_http.userguide_new_text = new_text;
	xml_http.userguide_fuzzy = !not_mark;
}

function cancelEdition(node, id) {
	node.innerHTML = formatText(source_strings[id]);
	closeEditWindow();
}

var removeBlock = cancelEdition;

function editSaveFinished(id, new_text, fuzzy, send_ok) {
	edit_window.focus();

	for (var i = 0 ; i < linked_nodes[id].length ; i++) {
		linked_nodes[id][i].innerHTML = formatText(new_text);
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

	closeEditWindow();

	// Refresh the statistics
	xml_http = new XMLHttpRequest();
	xml_http.open('GET', base_url + '/update_stats.php?doc_id=' + doc_id, true);
	xml_http.send(null);
}

function setProperties(node) {
	if (node == null)
		return;

	if (node.getAttribute) { // Avoid special nodes
		if (node.getAttribute(attr_trans_id) != null) {
			var id = node.getAttribute(attr_trans_id);

			if (source_strings[id]) {
				if (linked_nodes[id] == null) {
					linked_nodes[id] = [ node ];
				} else {
					linked_nodes[id].push(node);
				}
			}

			return;
		}
	}

	for (var i = 0 ; i < node.childNodes.length ; i++) {
		setProperties(node.childNodes[i]);
	}
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
		lockDocument(doc_id);
	}

	setProperties(document.getElementsByTagName('body')[0]);
	document.addEventListener('click', clickHandler);
}
