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

/**
 * Something to show topics with tags
 */
class Tagsapi_Controller extends Action_Controller
{
	/**
	 * Dunno if I'll need these two, though let's put them here
	 */
	private $_id = null;
	private $_name = null;
	private $_poster = null;

	/**
	 * Entry point function for tags, permission checks, just makes sure its on
	 */
	public function pre_dispatch()
	{
		global $modSettings;

		require_once(SUBSDIR . '/TagsPoster.class.php');

		loadLanguage('Tags');

		// If tags are disabled, we don't go any further
		if (empty($modSettings['tags_enabled']))
			fatal_lang_error('feature_disabled', true);

		$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
		$this->_poster = new Tags_Poster($type);

		$this->_id = isset($_REQUEST['target']) ? (int) $_REQUEST['target'] : 0;

		if (!$this->_poster->canAccess($this->_id))
			fatal_lang_error('no_such_tag', false);

		$info = new Tags_Info();
		$details = $info->tagDetails($this->_id);

		if (empty($details) || empty($details['tag_text']))
			fatal_lang_error('no_such_tag', false);

		$this->_name = $details['tag_text'];
	}

	public function action_delete_api()
	{
		global $context, $txt, $topic, $modSettings;

		$context['sub_template'] = 'tags_action_delete';

		// In hashtag mode permissions are irrelevant
		if (!empty($modSettings['hashtag_mode']))
		{
			$context['xml_data']['error'] = $txt['tags_not_allowed_hashtag'];
			return;
		}

		if (!$this->_init_api())
			return;

		$tag_id = isset($_REQUEST['tag']) ? (int) $_REQUEST['tag'] : 0;

		if (empty($tag_id))
		{
			$context['xml_data']['error'] = $txt['no_such_tag'];
			return;
		}
		if (empty($topic))
			$context['xml_data']['result'] = (int) $this->_poster->removeTag($tag_id);
		else
			$context['xml_data']['result'] = (int) $this->_poster->removeTag($tag_id, $topic);

		if (empty($context['xml_data']['result']))
			$context['xml_data']['error'] = $txt['no_such_tag'];
	}

	public function action_add_api()
	{
		global $context, $txt, $topic, $modSettings;

		$context['sub_template'] = 'tags_action_add';

		// In hashtag mode permissions are irrelevant
		if (!empty($modSettings['hashtag_mode']))
		{
			$context['xml_data']['error'] = $txt['tags_not_allowed_hashtag'];
			return;
		}

		if (!$this->_init_api())
			return;

		$tags_text = $this->_poster->cleanPostedTags($_POST['tags']);

		if (empty($tags_text))
		{
			$context['xml_data']['error'] = $txt['empty_tag'];
			return;
		}

		$tags = $this->_poster->createTags($tags_text);
		$this->_poster->addTags($topic, $tags);

		require_once(SUBSDIR . '/TagsStyler.class.php');
		$styler = new Tags_Styler($this->_poster->getTargetTags($topic));
		$context['xml_data'] = $styler->prepareXmlTags();
	}

	public function action_search_api()
	{
		global $context;

		if (!empty($_GET['search']))
			$search = trim(Util::htmlspecialchars($_GET['search']));

		$context['sub_template'] = 'generic_xml';
		loadTemplate('Xml');

		if (!$this->_init_api('get', false) || empty($search))
		{
			$context['xml_data'] = array();
			return;
		}

		$context['xml_data'] = $this->_poster->apiSearchTags($search);
		
	}

	private function _init_api($csec = 'post', $permission_strict = true)
	{
		global $context, $txt;

		Template_Layers::getInstance()->removeAll();
		loadTemplate('Tags');
		loadLanguage('Tags');
		$context['xml_data']['result'] = 0;

		$session = checkSession($csec, '', false);
		if (!empty($session))
		{
			loadLanguage('Errors');
			$context['xml_data']['error'] = $txt[$session];
			return false;
		}

		if (($permission_strict && !$this->_poster->tagsAllowed()) || ($permission_strict && !allowedTo('add_tags_own')))
		{
			$context['xml_data']['error'] = $txt['not_allowed_delete_tag'];
			return false;
		}

		return true;
	}
}
