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
	protected $_tagList = array();
	protected $_target = null;

	public function __construct($tags_list = null)
	{
		$this->_tagList = $tags_list;
	}

	function postForm($where, $permissions = array())
	{
		Template_Layers::getInstance()->addAfter('tags_posting', $where);
		$this->init_tags_template(false, $permissions);

		loadJavaScriptFile('suggest.js');

		if (!empty($this->_tagList))
		{
			addJavascriptVar('current_tags', 'var current_tags = new Array(' . implode(', ', $this->_tagList) . ');', true);
			addJavascriptVar('want_to_restore_tags', $this->_txt('want_to_restore_tags'), true);
			addJavascriptVar('tags_will_be_deleted', $this->_txt('tags_will_be_deleted'), true);
		}

		addJavascriptVar('autosuggest_delete_item', $this->_txt('autosuggest_delete_item'), true);
	}

	protected function _txt($index)
	{
		global $txt;

		if (isset($this->_strings[$index]))
			return $this->_strings[$index];
		else
			return $txt[$index];
	}

	public function setTexts($strings)
	{
		foreach ($strings as $key => $val)
		{
			$this->_strings[$key] = $val;
		}
	}

	function genericCloud($title = null)
	{
		global $modSettings, $context;

		$this->setCloudTitle($title);

		$this->init_tags_template(!empty($modSettings['hashtag_mode']));
		Template_Layers::getInstance()->addEnd('generic_tag_cloud');

		$context['current_tags'] = $this->styleTags($this->_tagList);
	}

	protected function setCloudTitle($title)
	{
		global $context, $txt;

		if ($title !== null)
			$context['tags']['cloud_title'] = $title;
		else
			$context['tags']['cloud_title'] = $txt['most_frequent_tags'];
	}

	function displayCloud($where, $title)
	{
		global $modSettings, $context;

		$this->init_tags_template(!empty($modSettings['hashtag_mode']));
		Template_Layers::getInstance()->addBefore('tag_cloud', $where);

		$this->setCloudTitle($title);

		$context['current_tags'] = $this->styleTags($this->_tagList, $this->_target);
	}

	public function setTarget($target_type = null, $target = null)
	{
		$this->_target = $target;
		$this->_target_type = $target_type;
	}

	public function createHashLinks($body)
	{
		// Protects hashes into links to avoid broken HTML
		// ...it would be cool to have hashes linked even inside links though...
		$links_callback_counter = 0;
		$links_callback = array();

		$data = $this->_target !== null ? 'data-target="' . (int) $this->_target . '"' : '';
		$data = $this->_target_type !== null ? 'data-type="' . $this->_target_type . '"' : '';

		$tmp = preg_replace_callback('~(<a[^>]*>[^<]*<\/a>)~', function ($match) use (&$links_callback, &$links_callback_counter)
		{
			$links_callback['replace'][$links_callback_counter] = $match[0];
			$links_callback['find'][$links_callback_counter] = '<a~~~~~~~>' . ($links_callback_counter++) . '</a~~~~~~~>';

			return $links_callback['find'][$links_callback_counter];
		}, $body);

		$find = array();
		foreach ($this->_tagList['tags'] as $tag)
			$find[] = '~(\s|<br />|^)#(' . preg_quote($tag['tag_text'], '~') . ')(\s|<br />|$)~';

		$tags = $this->_tagList;['tags'];
		$max_used = $this->_tagList['max_used'];
		$tmp = preg_replace_callback($find, function ($match) use($data, $tags, $max_used)
		{
			global $scripturl;

			if (!empty($match[2]) && isset($tags[$match[2]]))
			{
				$tag = $tags[$match[2]];
				return $match[1] . '<a ' . $data . ' id="tag_' . $tag['id_term'] . '" class="msg_tagsize' . round(10 * $tag['times_used'] / $max_used) . '" href="' . $scripturl . '?action=tags;tag=' . $tag['id_term'] . '.0">#' . $tag['tag_text'] . '</a>' . $match[3];
			}
		}, $tmp);

		if (!empty($links_callback))
			$body = str_replace($links_callback['find'], $links_callback['replace'], $tmp);
		else
			$body = $tmp;

		return $body;
	}

	function init_tags_template($minimal = false, $permissions = array())
	{
		loadCSSFile('tags.css');
		loadLanguage('Tags');
		loadTemplate('Tags');

		// If in hash mode there is no need for the following
		if ($minimal)
			return;

		loadJavaScriptFile('tags.js');

		if (isset($permissions['add']))
			$add = (int) !empty($permissions['add']);
		else
			$add = tagsAllowed();

		addJavascriptVar('tags_allowed_delete', (int) tagsAllowed());
		addJavascriptVar('tags_allowed_add', $add);
		addJavascriptVar('tags_generic_save', $this->_txt('save'), true);
		addJavascriptVar('tags_generic_cancel', $this->_txt('modify_cancel'), true);
		addJavascriptVar('tags_generic_ajax_error', $this->_txt('tags_generic_ajax_error'), true);
		addJavascriptVar('tags_generic_backend_error', $this->_txt('tags_generic_backend_error'), true);
	}

	function styleTags($tags, $id_target = false)
	{
		global $scripturl;

		$current_tags = array();
		$data = $id_target !== false ? ' data-target="' . $id_target . '"': '';

		foreach ($tags['tags'] as $tag)
		{
			$current_tags[$tag['id_term']] = '<a' . $data . ' id="tag_' . $tag['id_term'] . '" class="tagsize' . round(10 * $tag['times_used'] / $tags['max_used']) . '" href="' . $scripturl . '?action=tags;tag=' . $tag['id_term'] . '.0">' . $tag['tag_text'] . '</a>';
		}

		return $current_tags;
	}

	function prepareXmlTags()
	{
		$xml_data['result'] = array();
		foreach ($this->_tagList['tags'] as $tag)
		{
			$xml_data['result'][$tag['id_term']] = array(
				'tagsize' => round(10 * $tag['times_used'] / $this->_tagList['max_used']),
				'text' => $tag['tag_text']
			);
		}

		return $xml_data;
	}
}