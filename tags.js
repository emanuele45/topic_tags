/**
 * Topics Tags
 *
 * @author emanuele
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 0.0.1
 */

$(document).ready(function() {
	if (tags_allowed_delete)
	{
		$('#show_tags .atag').each(function() {
			var $element = $(this);
			generateDeleteGraphic($element);
		});
	}

	if (tags_allowed_add)
	{
		var $container = $('#show_tags'),
			target_id = $container.data('target'),
			target_type = $container.data('type');

		var $add = $('<a>').addClass('tag_add').attr('href', '#').click('bind', function(event) {
			var $add_form = $('<div>').addClass('tags_ajax_form roundframe');
			var $input = $('<input>').addClass('tags_ajax_input');
			var $cancel = $('<input>').addClass('tags_ajax_cancel').attr({type: 'submit', value: tags_generic_cancel}).bind('click', function(){
				$add_form.toggle('fast');
				$container.removeClass('tags_ajax_form_min');
			});
			var $save = $('<input>').addClass('tags_ajax_save').attr({type: 'submit', value: tags_generic_save}).bind('click', function() {
				var data = {};
				event.preventDefault();
				data[elk_session_var] = elk_session_id;
				data['tags'] = $input.val();

				$.ajax({
					type: "POST",
					url: elk_prepareScriptUrl(elk_scripturl) + 'action=tagsapi;sa=add;api;target=' + target_id + ';type=' + target_type,
					data: data,
					success: function(request) {
						if (typeof request == 'object')
						{
							if ($(request).find("error").text() != '')
								alert($(request).find("error").text());
							else
							{
								var $last_tag = null;
								var $request_tags = $(request).find("result");

								$request_tags.each(function() {
									var $the_tag = $('#show_tags #tag_' + $(this).attr('id_term'));

									if ($the_tag.length > 0)
									{
										$the_tag.removeClass(function(index, aclass) {
											return (aclass.match(/\btagsize\d{1,2}/g) || []).join(' ');
										}).addClass('tagsize' + $(this).attr('tagsize'));
										$last_tag = $the_tag;
									}
									else
									{
										var tag_id = $(this).attr('id_term'),
											tagsize = 'tagsize' + $(this).attr('tagsize'),
											tag_text = $(this).text();

										var $new_tag = $('<span class="atag"/>').hide().data('tagid', tag_id).append($('<a />').attr({
											id: 'tag_' + tag_id,
											class: tagsize,
											href: elk_scripturl + '?action=tags;tag=' + tag_id + '.0'
										}).data('target', target_id).data('type', target_type).text(tag_text));

										if ($last_tag != null)
											$last_tag.parent().parent().after($new_tag);
										else
											$container.prepend($new_tag);

										$new_tag.toggle('slow');
										$('#show_tags .tag_delete').remove();
										$('#show_tags .atag').each(function() {
											var $element = $(this);
											generateDeleteGraphic($element);
										});
									}
								});
								$add_form.toggle('slow');
								$container.removeClass('tags_ajax_form_min');
							}
						}
						else
							alert(tags_generic_ajax_error);
					},
					error: function(request) {
						alert(tags_generic_ajax_error);
					},
				});
			});

			$add_form.append($input).append($save).append($cancel);
			$add_form.hide();
			event.preventDefault();
			$(this).after($add_form);
			$container.addClass('tags_ajax_form_min');
			$add_form.toggle('fast');
		});
		$container.append($add);
	}
});

function restoreTags()
{
	var input_box = document.getElementById('input_tags');
	if (confirm(want_to_restore_tags))
		input_box.value = current_tags.join(', ');
}

function checkTags(elem, is_empty)
{
	if (is_empty)
		return;
	else
	{
		if (elem.value.replace(/^\s+|\s+$/g, '') == '')
			alert(tags_will_be_deleted);
	}
}

function generateDeleteGraphic($element)
{
	$element.wrap('<span />');
	var $a = $element.children('a');
	var id = $a.attr('id'),
		url = $a.attr('href');
	var $del = $('<a>').addClass('tag_delete').attr('href', '#').bind('click', function(event) {
		var data = {};

		event.preventDefault();
		data[elk_session_var] = elk_session_id;

		$.ajax({
			type: "POST",
			url: url + ';sa=delete;api' + ($a.data('target') ? ';target=' + $a.data('target') : '') + ';type=' + $a.data('type'),
			data: data,
			success: function(request) {
				if (typeof request == 'object')
				{
					if ($(request).find("error").text() != '')
						alert($(request).find("error").text());
					else if ($(request).find("result").text() == 1)
					{
						$del.hide(0, function() {
							$element.toggle('slow', function() {$element.parent().remove()});
						});
					}
					else
						alert(tags_generic_backend_error);
				}
				else
					alert(tags_generic_ajax_error);
			},
			error: function(request) {
				alert(tags_generic_ajax_error);
			},
		});
	});
	$element.after($del);
}

var oTagsSuggest = null;
function init_tags_autoSuggest(listItems)
{
	if (typeof listItems == 'undefined')
		listItems = [];

	oTagsSuggest = new smc_AutoSuggest({
		sSelf: 'oTagsSuggest',
		sSessionId: elk_session_id,
		sSessionVar: elk_session_var,
		iMinimumSearchChars: 2,
		sSuggestId: 'input_tags', // ???
		sControlId: 'input_tags',
		sRetrieveURL: '%scripturl%action=tagsapi;sa=search;search=%search%;%sessionVar%=%sessionID%;api;time=%time%',
		bItemList: true,
		sPostName: 'tags_autosuggest',
		sURLMask: 'action=tags;tag=%item_id%',
		sTextDeleteItem: autosuggest_delete_item,
		sItemListContainerId: 'tags_container',
		aListItems: listItems
	});

	setTimeout(tags_revealMaskedInput, 500);
}

function tags_revealMaskedInput()
{
	var $inputBox = $('#input_tags');
	if ($inputBox.attr('name') == 'tags')
	{
		setTimeout(tags_revealMaskedInput, 500);
		return;
	}

	$inputBox.after($('<input type="hidden" />').attr({
		name: 'tags_input_name',
		id: 'tags_input_name',
		value: $inputBox.attr('name')
	}));
}