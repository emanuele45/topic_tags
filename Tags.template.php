<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

function template_tags_posting_above()
{
	global $context, $txt, $settings;

	echo '
		<dl class="post_header">
			<dt class="clear">', $txt['tags'], '</dt>
			<dd>
				<input id="input_tags" onblur="checkTags(this, ', empty($context['current_tags']) ? 'true' : 'false', ');" id="tags" type="text" name="tags" value="', !empty($context['current_tags']) ? implode(', ', $context['current_tags']) : '', '" tabindex="', $context['tabindex']++, '" size="75" class="input_text" placeholder="', $txt['tags'], '" />';

	if (!empty($context['current_tags']))
		echo '
		<a onclick="restoreTags();return false;" href="#"><img src="', $settings['images_url'], '/icons/assist.png" alt="', $txt['restore_tags'], '"/></a>';

	echo '
				<div id="tags_container"></div>
			</dd>
		</dl>';
}

function template_topic_tag_cloud_above()
{
	template_create_tag_cloud('this_topic_tags');
}

function template_boardindex_tag_cloud_above()
{
	template_create_tag_cloud('most_frequent_tags');
}

function template_create_tag_cloud($text)
{
	global $context, $txt, $settings, $options;

	if (!empty($context['current_tags']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', (empty($text) ? $txt['tags'] : $txt[$text]), '
			<img id="tagsupshrink" src="', $settings['images_url'], '/collapse.png" alt="*" title="', $txt['hide'], '" style="vertical-align: bottom; cursor: pointer;"/>
			</h3>
		</div>
		<div ', !empty($context['current_topic']) ? 'data-topic="' . $context['current_topic'] . '"' : '', 'id="show_tags" class="', empty($options['collapse_tags_cloud']) ? '' : 'tags_hidden ', 'roundframe">
			', template_tag_cloud(), '
		</div>';

		addInlineJavascript('
		// Create the tags toggle.
		var smfTagCloudToggle = new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: ' . (empty($options['collapse_tags_cloud']) ? 'false' : 'true') . ',
			aSwappableContainers: [
				\'show_tags\'
			],
			aSwapImages: [
				{
					sId: \'tagsupshrink\',
					srcExpanded: elk_images_url + \'/collapse.png\',
					altExpanded: ' . JavaScriptEscape($txt['hide']) . ',
					srcCollapsed: elk_images_url + \'/expand.png\',
					altCollapsed: ' . JavaScriptEscape($txt['show']) . '
				}
			],
			oThemeOptions: {
				bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ',
				sOptionName: \'collapse_tags_cloud\',
				sSessionVar: elk_session_var,
				sSessionId: elk_session_id
			},
			oCookieOptions: {
				bUseCookie: ' . ($context['user']['is_guest'] ? 'true' : 'false') . ',
				sCookieName: \'tagsupshrink\'
			}
		});', true);
	}
}

function template_tags_action_add()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>
	<action>';
	if (!empty($context['xml_data']['error']))
		echo '
		<result><![CDATA[', cleanXml($context['xml_data']['result']), ']]></result>
		<error><![CDATA[', cleanXml($context['xml_data']['error']), ']]></error>';
	else
		foreach ($context['xml_data']['result'] as $id_term => $values)
			echo '
		<result id_term="', $id_term, '" tagsize="', $values['tagsize'], '"><![CDATA[', cleanXml($values['text']), ']]></result>';
	echo '
	</action>
</elk>';
}

function template_tag_cloud()
{
	global $context;

	$tags = '';
	foreach ($context['current_tags'] as $id => $tag)
		$tags .= '
		<span data-tagid="' . $id . '" class="atag">' . $tag . '</span>';

	return $tags;
}

function template_tags_action_delete()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<elk>
	<action>
		<result><![CDATA[', cleanXml($context['xml_data']['result']), ']]></result>';
	if (!empty($context['xml_data']['error']))
		echo '
		<error><![CDATA[', cleanXml($context['xml_data']['error']), ']]></error>';
	echo '
	</action>
</elk>';
}