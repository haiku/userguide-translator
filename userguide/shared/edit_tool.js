const update_delay = 500; // in ms

var preview_window = null;
var preview_button = null;
var editor = null;
var update_timer = null;
var translated_text;

var count = 0;

function keyDownAction() {
	var e = event;

	if(e.which == 9) {
		insertText("\t", '');
		return false;
	}

	return true;
}

function keyUpAction() {
	var e = event;
	if (!(37 <= e.which && e.which <= 40)) { // arrow keys
		window.clearTimeout(update_timer);
		update_timer = window.setTimeout(updateStatus, update_delay);
	}

	getCaretPosition();
}

function updateStatus() {
	update_timer = null;
	if (!preview_window)
		return;

	preview_window.document.write(formatText(editor.value
		.replace('<head>', '<head><base href="' + base_dir + '" />')));
	preview_window.document.close();
}

function togglePreview() {
	if (preview_window) {
		preview_window.close();
		preview_window = null;
		window.clearTimeout(update_timer);
	} else {
		preview_window = window.open('', 'Preview',
			'width=800,height=600,toolbar=0,scrollbar=1,resizable=1,status=0');
		updateStatus();
	}
}

function getCaretPosition() {
	var pos = editor.selectionStart;
	var line = 0, column = 0;

	while (true) {
		if (pos == 0)
			break;

		pos = editor.value.lastIndexOf("\n", pos - 1);
		if (pos < 0)
			break;

		if (column == 0)
			column = editor.selectionStart - pos;

		line++;
	}

	if (column == 0)
		column = editor.selectionStart + 1;

	document.getElementById('row').value = line + 1;
	document.getElementById('col').value = column;
}

function insertText(before, after) {
	// Inspired by http://www.massless.org/mozedit/
	try {
		var sel_length = editor.textLength;
		var sel_start = editor.selectionStart;
		var sel_end = editor.selectionEnd;
		var scroll = editor.scrollTop;

		if (sel_end == 1 || sel_end == 2)
			sel_end = sel_length;

		var part1 = editor.value.substring(0, sel_start);
		var part2 = editor.value.substring(sel_start, sel_end);
		var part3 = editor.value.substring(sel_end, sel_length);

		editor.value = part1 + before + part2 + after + part3;
		editor.selectionStart = sel_end + before.length;
		editor.selectionEnd = editor.selectionStart;
		editor.focus();
		editor.scrollTop = scroll;
	} catch (err) {
		editor.value += before + after;
	}

	return false;
}

function closeAlert() {
	document.getElementById('warning_box').style.display = "none";

	to_close = document.getElementsByClassName("warning");

	for (i = to_close.length - 1 ; i >= 0 ; i--) {
		to_close[i].className = "no-warning";
	}
}

window.onload = function() {
	editor = document.getElementById('edit_middle').getElementsByTagName('textarea')[0];
	editor.onkeyup = keyUpAction;
	editor.onkeydown = keyDownAction;
	editor.onfocus = getCaretPosition;
	editor.onmouseup = getCaretPosition;

	var functions_ok = 0;

	if (window.XMLHttpRequest)
		functions_ok++;

	if (functions_ok != 1) {
		window.alert('Your browser does not support XML HTTP requests. ' +
			'You will not be protected against concurrent edits!');
	} else {
		lockDocument(doc_id);
	}

	preview_button = document.getElementById('preview');
	preview_button.onclick = togglePreview;

	updateStatus();
}

function selectTitle(obj) {
	if (obj.value)
		insertText('<' + obj.value + '>', '</' + obj.value + '>');
	obj.value='';
}

function selectBox(obj) {
	if (obj.value)
		insertText('<div class="' + obj.value + '">', '</div>');
	obj.value='';
}

function selectPref(obj) {
	if (obj.value == 'text')
		insertText('<pre>', '</pre>');
	else if (obj.value == 'term')
		insertText('<pre class="terminal">', '</pre>');
	obj.value='';
}

function selectSpan(obj) {
	if (obj.value)
		insertText('<span class="' + obj.value + '">', '</span>');
	obj.value='';
}
