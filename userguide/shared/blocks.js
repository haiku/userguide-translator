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
