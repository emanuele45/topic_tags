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
			$poster = new Tags_Poster('topics');

			$context['current_tags'] = $poster->getTargetTags($topic, true);
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
		global $modSettings;

		$this->init_tags_template(!empty($modSettings['hashtag_mode']));
		Template_Layers::getInstance()->addEnd('boardindex_tag_cloud');

		require_once(SUBSDIR . '/TagsInfo.class.php');
		$info = new Tags_Info();
		$this->styleTags($info->mostUsedTags());
	}

	function displayTargetCloud($type, $target, $where, $title)
	{
			global $modSettings, $context;

			$this->init_tags_template(!empty($modSettings['hashtag_mode']));
			Template_Layers::getInstance()->addBefore('tag_cloud', $where);

			$context['tags']['cloud_title'] = $title;

			require_once(SUBSDIR . '/TagsPoster.class.php');
			$poster = new Tags_Poster($type);
			$context['tags_list'] = $poster->getTargetTags($target);
			$this->styleTags($context['tags_list'], $target);
	}

	public function createHashLinks($body, $id_target)
	{
		global $context;

		// Protects hashes into links to avoid broken HTML
		// ...it would be cool to have hashes linked even inside links though...
		$links_callback_counter = 0;
		$links_callback = array();
		$type = $this->tagger_name;

		$tmp = preg_replace_callback('~(<a[^>]*>[^<]*<\/a>)~', function ($match) use (&$links_callback, &$links_callback_counter)
		{
			$links_callback['replace'][$links_callback_counter] = $match[0];
			$links_callback['find'][$links_callback_counter] = '<a~~~~~~~>' . ($links_callback_counter++) . '</a~~~~~~~>';

			return $links_callback['find'][$links_callback_counter];
		}, $body);

		$find = array();
		foreach ($context['tags_list']['tags'] as $tag)
			$find[] = '~(\s|<br />|^)#(' . preg_quote($tag['tag_text'], '~') . ')(\s|<br />|$)~';

		$tmp = preg_replace_callback($find, function ($match) use($id_target, $type)
		{
			global $context, $scripturl;

			if (!empty($match[2]) && isset($context['tags_list']['tags'][$match[2]]))
			{
				$tag = $context['tags_list']['tags'][$match[2]];
				return $match[1] . '<a data-target="' . $id_target . '" data-type="' . $type . '" id="tag_' . $tag['id_term'] . '" class="msg_tagsize' . round(10 * $tag['times_used'] / $context['tags_list']['max_used']) . '" href="' . $scripturl . '?action=tags;tag=' . $tag['id_term'] . '.0">#' . $tag['tag_text'] . '</a>' . $match[3];
			}
		}, $tmp);

		if (!empty($links_callback))
			$body = str_replace($links_callback['find'], $links_callback['replace'], $tmp);
		else
			$body = $tmp;

		return $body;
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

	function styleTags($tags, $id_target = false)
	{
		global $context, $scripturl;

		$context['current_tags'] = array();
		foreach ($tags['tags'] as $tag)
			$context['current_tags'][$tag['id_term']] = '<a' . ($id_target !== false ? ' data-target="' . $id_target . '"': '') . ' id="tag_' . $tag['id_term'] . '" class="tagsize' . round(10 * $tag['times_used'] / $tags['max_used']) . '" href="' . $scripturl . '?action=tags;tag=' . $tag['id_term'] . '.0">' . $tag['tag_text'] . '</a>';
	}

	function prepareXmlTags($tags)
	{
		$xml_data['result'] = array();
		foreach ($tags['tags'] as $tag)
		{
			$xml_data['result'][$tag['id_term']] = array(
				'tagsize' => round(10 * $tag['times_used'] / $tags['max_used']),
				'text' => $tag['tag_text']
			);
		}

		return $xml_data;
	}
}