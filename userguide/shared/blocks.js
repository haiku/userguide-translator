const attr_trans_id = '_translation_id';
const attr_state = '_edit_state';

function clickHandler(e) {
	var node = e.target;
	if (node.nodeName.toLowerCase() == 'img') {
		imgClickHandler(node);
	} else {
		node = node.closest('[' + attr_trans_id + ']');
		if (node != null) {
			blockClickHandler(node);
		}
	}
}

function imgClickHandler(img) {
	var src = img.getAttribute("src");
	if (base_local != '.') {
		src = base_local + '/' + src;
	}
	var lang_param = (typeof lang === 'undefined') ? '' : ('&lang=' + lang);
	window.open(base_url + '/res_upload.php?path=' + encodeURIComponent(src) + lang_param,
		src, 'width=800,height=600,status=0,toolbar=0,location=0,menubar=0,personalbar=0,resizable=1,scrollbars=1');
}

function blockClickHandler(node) {
	if (window.edited_node != null) {
		edit_window.focus();
		return;
	}

	window.edited_node = node;

	var id = node.getAttribute(attr_trans_id);

	edit_window = window.open(base_url + edit_tool,
		edit_tool_ctx, 'width=650,height=400,status=0,toolbar=0,location=0,menubar=0,personalbar=0,resizable=1,scrollbars=0');
	window.original_text = source_strings[id];
	if (typeof translated_strings !== 'undefined') {
		window.translated_text = translated_strings[id];
	}
}

function endEditionEvent(clickOK) {
	if (window.edited_node == null)
		return;

	const node = window.edited_node;
	const id = node.getAttribute(attr_trans_id);

	if (clickOK) {
		const new_text = edit_window.document.getElementById('modified').value;
		if (new_text.trim() == '') {
			removeBlock(node, id);
		} else {
			const fuzzy =
				edit_window.document.getElementById('fuzzy_check').checked;
			sendEdition(node, id, new_text, fuzzy);
		}
	} else {
		cancelEdition(node, id);
	}
}

function closeEditWindow() {
	edit_window.close();
	edit_window = null;
	window.edited_node = null;
}

function serverRequestListener() {
	const resp = this.responseText;
	var send_ok = false;

	if (resp.substring(0, 7) == 'badxml ')
		edit_window.alert('The server rejected the change because of XML ' +
			"parsing errors :\n" + resp.substring(3) +
			"\n" + 'Check the XML tags.');
	else if (resp.substring(0, 7) == 'diffxml')
		edit_window.alert('The server rejected the change because the ' +
			'XML code used in it differs from the original string.' + "\n" +
			'Check the XML tags.');
	else if (resp.substring(0, 6) == 'interr')
		edit_window.alert('The original XML code seems corrupt. Please contact ' +
			'an administrator.' + "\n");
	else if (resp.substring(0, 2) != 'ok')
		edit_window.alert('There was an error sending the change. Please ' +
		'retry.' + "\n" + resp);
	else
		send_ok = true;

	editSaveFinished(this.userguide_string_id, this.userguide_new_text,
		this.userguide_fuzzy, send_ok);
}

function getBlockNodes(id) {
	return document.querySelectorAll('[' + attr_trans_id + '="' + id + '"]');
}
