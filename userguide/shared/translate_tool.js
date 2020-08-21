const edit_tool = '/shared/translate_tool.html';
const edit_tool_ctx = 'TranslateBlock';

var edit_window = null;
var edited_node = null;
var original_text;
var translated_text;
var all_nodes = new Array();

var title_id = 0;
var title_insert = false;
var first_title = null;

function sendEdition(node, id, trans, mark_fuzzy) {
	var xml_http = new XMLHttpRequest();

	var encoded_source = encodeURI(source_strings[id]).replace(/&/g, '%26').replace(/\+/g, '%2B');
	var encoded_translation = encodeURI(trans).replace(/&/g, '%26').replace(/\+/g, '%2B');

	xml_http.open('POST', base_url + '/translate.php', true);
	xml_http.addEventListener("load", serverRequestListener);
	xml_http.setRequestHeader('Content-Type',
		'application/x-www-form-urlencoded');
	xml_http.send('translate_lang=' + lang + '&translate_doc=' + doc_id +
		'&translate_string=' + id + '&translate_text=' + encoded_translation +
		'&translate_source=' + encoded_source + '&is_fuzzy=' + (mark_fuzzy ? '1' : '0'));

	xml_http.userguide_string_id = id;
	xml_http.userguide_new_text = trans;
	xml_http.userguide_fuzzy = mark_fuzzy;
}

function cancelEdition(node, id) {
	var text = translated_strings[id] == '' ? source_strings[id] : translated_strings[id];
	node.innerHTML = formatText(text);
	node.setAttribute(attr_state, getBlockState(id));
	closeEditWindow();
}

function removeBlock(node, id) {
	// TODO: currently gives an error due to empty text, but could be used
	// to remove a translation
	sendEdition(node, id, '', false);
}

function editSaveFinished(id, trans, fuzzy, send_ok) {
	edit_window.focus();

	if (!send_ok) {
		window.edited_node.setAttribute(attr_state, 'error');
		return;
	}

	var next_node;

	is_fuzzy[id] = fuzzy;
	translated_strings[id] = trans;
	trans = formatText(trans);
	const state = getBlockState(id);

	for (const node of getBlockNodes(id)) {
		node.innerHTML = trans;
		node.setAttribute(attr_state, state);
	}

	if (edit_window.document.getElementById('auto_cont').checked) {
		var current_id = window.edited_node.getAttribute('_internal_id');
		while (current_id < all_nodes.length) {
			var t_id = all_nodes[current_id].getAttribute(attr_trans_id);
			if (translated_strings[t_id] == '') {
				next_node = all_nodes[current_id];
				break;
			}
			current_id++;
		}
	}

	translateBlockDone(next_node);
}

function translateBlockDone(next_node) {
	if (next_node) {
		window.edited_node = next_node;
		var id = next_node.getAttribute(attr_trans_id);
		window.original_text = source_strings[id];
		window.translated_text = translated_strings[id];
		window.setTimeout(edit_window.refreshAll, 0);
	} else {
		closeEditWindow();
	}
}

function getBlockState(id) {
	if (translated_strings[id] == '') {
		return 'untranslated';
	} else if (is_fuzzy[id]) {
		return 'fuzzy';
	}
	return '';
}

function setProperties(node) {
	if (node == null)
		return;

	if (node.getAttribute) { // Avoid special nodes
		if (node.getAttribute(attr_trans_id) != null) {
			var id = node.getAttribute(attr_trans_id);

			if (source_strings[id]) {
				var node_name = node.tagName.toLowerCase();

				if (node_name != "title") { // We can't touch it
					const state = getBlockState(id);
					if (translated_strings[id] != '') {
						node.innerHTML = formatText(translated_strings[id]);
					}
					node.setAttribute(attr_state, state);
					node.setAttribute('_internal_id', all_nodes.length);
					all_nodes.push(node);
				}

				if (title_id == 0 && node_name == "title") {
					title_id = id;
					title_insert = true;
				} else if (id == title_id) {
					title_insert = false;
				}

				if (first_title == null && (node_name == "h1" || node_name == "h2")) {
					first_title = node;
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
	}

	setProperties(document.getElementsByTagName('html')[0]);

	if (title_insert) {
		// The translatable text used in the <title> tag is not used anywhere else.
		// Since the translate tool does not allow translating <title>, we must insert
		// this text somewhere.
		var new_title = document.createElement("h1");
		new_title.setAttribute(attr_trans_id, title_id);
		new_title.appendChild(document.createTextNode(source_strings[title_id]));

		if (first_title != null && first_title.parentNode != null) {
			first_title.parentNode.insertBefore(new_title, first_title);
		} else {
			document.body.insertBefore(new_title, document.body.firstChild);
		}

		setProperties(new_title);
	}

	document.addEventListener('click', clickHandler);
}
