<?php
/**
 * Topics Tags
 *
 * @author emanuele
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 0.0.1
 */

if (!defined('ELK'))
	die('No access...');

class Tags_Styler
{
	function tags_postPage()
	{
		global $modSettings, $topic, $context, $txt;

		// Give the box only for new topics or when editing the first message
		if (empty($modSettings['hashtag_mode']) && (empty($topic) || $context['is_first_post']))
		{
			Template_Layers::getInstance()->addAfter('tags_posting', 'postarea');
			$this->init_tags_template();

			loadJavaScriptFile('suggest.js');
			require_once(SUBSDIR . '/TagsPoster.class.php');
			$poster = new Tags_Poster();

			$context['current_tags'] = $poster->topicTags($topic, true);
			if (!empty($context['current_tags']))
			{
				addJavascriptVar('current_tags', 'var current_tags = new Array(' . implode(', ', $context['current_tags']) . ');', true);
				addJavascriptVar('want_to_restore_tags', $txt['want_to_restore_tags'], true);
				addJavascriptVar('tags_will_be_deleted', $txt['tags_will_be_deleted'], true);

			}

			addJavascriptVar('autosuggest_delete_item', $txt['autosuggest_delete_item'], true);
		}
	}

	function tags_BICloud()
	{
		$this->init_tags_template(!empty($modSettings['hashtag_mode']));
		Template_Layers::getInstance()->addEnd('boardindex_tag_cloud');

		require_once(SUBSDIR . '/TagsPoster.class.php');
		$poster = new Tags_Poster();
		$this->styleTags($poster->mostUsedTags());
	}

	function tags_DisplayCloud()
	{
			global $topic, $modSettings, $context;

			$this->init_tags_template(!empty($modSettings['hashtag_mode']));
			Template_Layers::getInstance()->addBefore('topic_tag_cloud', 'pages_and_buttons');

			require_once(SUBSDIR . '/TagsPoster.class.php');
			$poster = new Tags_Poster();
			$context['tags_list'] = $poster->topicTags($topic);
			$this->styleTags($context['tags_list'], $topic);
	}

	function init_tags_template($minimal = false)
	{
		global $txt, $topic;

		loadCSSFile('tags.css');
		loadLanguage('Tags');
		loadTemplate('Tags');

		// If in hash mode there is no need for the following
		if ($minimal)
			return;

		loadJavaScriptFile('tags.js');

		addJavascriptVar('tags_allowed_delete', (int) tagsAllowed());
		addJavascriptVar('tags_allowed_add', (int) (!empty($topic) && tagsAllowed()));
		addJavascriptVar('tags_generic_save', $txt['save'], true);
		addJavascriptVar('tags_generic_cancel', $txt['modify_cancel'], true);
		addJavascriptVar('tags_generic_ajax_error', $txt['tags_generic_ajax_error'], true);
		addJavascriptVar('tags_generic_backend_error', $txt['tags_generic_backend_error'], true);
	}

	function styleTags($tags, $topic_id = false)
	{
		global $context, $scripturl;

		$context['current_tags'] = array();
		foreach ($tags['tags'] as $tag)
			$context['current_tags'][$tag['id_term']] = '<a' . ($topic_id !== false ? ' data-topic="' . $topic_id . '"': '') . ' id="tag_' . $tag['id_term'] . '" class="tagsize' . round(10 * $tag['times_used'] / $tags['max_used']) . '" href="' . $scripturl . '?action=tags;tag=' . $tag['id_term'] . '.0">' . $tag['tag_text'] . '</a>';
	}

	function prepareXmlTags($tags, $topic_id = false)
	{
		global $context, $scripturl;

		$context['xml_data']['result'] = array();
		foreach ($tags['tags'] as $tag)
			$context['xml_data']['result'][$tag['id_term']] = array(
				'tagsize' => round(10 * $tag['times_used'] / $tags['max_used']),
				'text' => $tag['tag_text']
			);
	}
}