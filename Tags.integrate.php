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

/*********************************************************
 * Hooks functions
 *********************************************************/
class Tags_Integrate
{
	protected $styler = null;

	protected static function init($perm = false)
	{
		global $modSettings, $context;

		if (empty($modSettings['tags_enabled']))
			return false;

		require_once(SUBSDIR . '/Tags.subs.php');

		if ($perm && !tagsAllowed(true))
			return false;

		loadLanguage('Tags');
		if (!isset($context['tags']))
			$context['tags'] = array();

		return true;
	}

	public static function post_after()
	{
		global $modSettings, $topic, $context, $txt;

		if (!self::init(true))
			return;

		// Give the box only for new topics or when editing the first message
		if (empty($modSettings['hashtag_mode']) && (empty($topic) || $context['is_first_post']))
		{
			require_once(SUBSDIR . '/TagsStyler.class.php');
			$poster = new Tags_Poster('topics');
			$context['current_tags'] = $poster->getTargetTags($topic, true);

			$styler = new Tags_Styler($context['current_tags']);
			$styler->setTarget('topics', $topic);
			$styler->setTexts(array(
				'want_to_restore_tags' => $txt['want_to_restore_topic_tags'],
				'tags_will_be_deleted' => $txt['topic_tags_will_be_deleted']
			));

			$styler->postForm('postarea', array('add' => $poster->canAccess($topic) && tagsAllowed()));
		}
	}

	public static function mark_read_button()
	{
		if (!self::init())
			return;

		require_once(SUBSDIR . '/TagsInfo.class.php');
		$info = new Tags_Info();

		require_once(SUBSDIR . '/TagsStyler.class.php');
		$styler = new Tags_Styler($info->mostUsedTags());
		$styler->genericCloud();
	}

	public static function display_topic($topicinfo)
	{
		global $txt, $modSettings;

		if (!self::init())
			return;

		require_once(SUBSDIR . '/TagsPoster.class.php');
		$poster = new Tags_Poster('topic');
		$current_tags = $poster->getTargetTags($topicinfo['id_topic'], true);

		if (empty($current_tags))
			return;

		if (!empty($modSettings['hashtag_mode']))
		{
			add_integration_function('integrate_prepare_display_context', 'Tags_Integrate::prepare_display_context', false, false);
		}

		require_once(SUBSDIR . '/TagsStyler.class.php');
		self::$styler = new Tags_Styler($current_tags);
		self::$styler->setTarget('topics', $topicinfo['id_topic']);
		self::$styler->displayCloud('pages_and_buttons', $txt['this_topic_tags']);
	}

	public static function load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
	{
		global $modSettings;

		// In hashtag mode permissions are irrelevant
		if (!empty($modSettings['hashtag_mode']))
			return;

		loadLanguage('Tags');

		$permissionList['board']['add_tags'] = array(true, 'topic', 'make_posts');

		if (empty($modSettings['tags_enabled']))
			$hiddenPermissions[] = 'add_tags';
	}

	public static function load_illegal_guest_permissions()
	{
		global $context, $modSettings;

		// In hashtag mode permissions are irrelevant
		if (!empty($modSettings['hashtag_mode']))
			return;

		loadLanguage('Tags');

		$context['non_guest_permissions'][] = 'add_tags';
	}

	public static function create_topic($msgOptions, $topicOptions, $posterOptions)
	{
		global $modSettings;

		if (empty($modSettings['tags_enabled']))
			return;

		require_once(SUBSDIR . '/TagsPoster.class.php');
		$poster = new Tags_Poster('topics');

		if (!empty($modSettings['hashtag_mode']))
			return $poster->postHashed($msgOptions['body'], $topicOptions['id']);

		if (!tagsAllowed(true))
			return;

		// I want to have tags set only for the entire topic (and for now, attached only to the first msg)
		require_once(SUBSDIR . '/Messages.subs.php');
		$topic_info = basicMessageInfo($msgOptions['id'], false, true);
		if (!$topic_info['id_first_msg'])
			return;

		// Since this is only for new topics I can just check that
		if (!empty($_POST['tags']))
		{
			$poster->postNewTags($_POST['tags'], $topicOptions['id']);
		}
	}

	public static function create_post($msgOptions, $topicOptions, $posterOptions, $message_columns, $message_parameters)
	{
		global $modSettings;

		if (empty($modSettings['tags_enabled']) || empty($modSettings['hashtag_mode']) || empty($topicOptions['id']))
			return;

		require_once(SUBSDIR . '/TagsPoster.class.php');
		$poster = new Tags_Poster('topics');
		$poster->postHashed($msgOptions['body'], $topicOptions['id']);
	}

	public static function modify_post($messages_columns, $update_parameters, $msgOptions, $topicOptions, $posterOptions, $messageInts)
	{
/*		global $modSettings;

		if (empty($modSettings['tags_enabled']) || empty($modSettings['hashtag_mode']))
			return;

		require_once(SUBSDIR . '/TagsPoster.class.php');
		$poster = new Tags_Poster('topics');
		$possible_tags = $poster->cleanHashedTags($msgOptions['body']);

		$tag_ids = $poster->createTags($possible_tags);

		$poster->addTags($topicOptions['id'], $tag_ids);

		$poster->purgeTargetTags($topicOptions['id']);*/
	}

	public static function modify_post2($messages_columns, $update_parameters, $msgOptions, $topicOptions, $posterOptions, $messageInts)
	{
		global $modSettings;

		require_once(SUBSDIR . '/Tags.subs.php');

		if (empty($modSettings['tags_enabled']) || !tagsAllowed())
			return;

		// I want to have tags set only for the entire topic (and for now, attached only to the first msg)
		require_once(SUBSDIR . '/Messages.subs.php');
		$topic_info = basicMessageInfo($msgOptions['id'], false, true);
		if (!$topic_info['id_first_msg'])
			return;

		require_once(SUBSDIR . '/TagsPoster.class.php');
		$poster = new Tags_Poster('topics');
		$possible_tags = $poster->cleanPostedTags($_POST['tags']);

		// Remove goes before the empty check because if you have cleaned up the
		// input you want to remove everything
		$poster->removeTagsFromTopic($topicOptions['id']);

		if (empty($possible_tags))
			return;

		// Usual: do they exist? If so, ids please!
		$tag_ids = $poster->createTags($possible_tags);

		$poster->addTags($topicOptions['id'], $tag_ids);
	}

	public static function routine_maintenance()
	{
		global $context, $txt, $scripturl;

		loadLanguage('Tags');

		if (isset($_GET['done']) && $_GET['done'] == 'recounttags')
			$context['maintenance_finished'] = $txt['maintain_recounttags'];

		$context['routine_actions']['recounttags'] = array(
			'url' => $scripturl . '?action=admin;area=maintain;sa=routine;activity=recounttags',
			'title' => $txt['maintain_recounttags'],
			'description' => $txt['maintain_recounttags_info'],
			'submit' => $txt['maintain_run_now'],
			'hidden' => array(
				'session_var' => 'session_id',
			)
		);
	}

	public static function manage_maintenance(&$subActions)
	{
		require_once(ADMINDIR . '/ManageTags.controller.php');
		$subActions['routine']['activities']['recounttags'] = 'recountTags';
	}

	public static function prepare_display_context(&$output, &$message)
	{
		$output['body'] = self::$styler->createHashLinks($output['body']);
	}

	public static function remove_message($message)
	{
		require_once(SUBSDIR . '/Messages.subs.php');
		require_once(SUBSDIR . '/TagsPoster.class.php');
		$poster = new Tags_Poster('topics');

		$msg_info = basicMessageInfo($message, false, true);
		$tags = $poster->cleanHashedTags($msg_info['body']);
		_debug($tags,0,0,1);
		$tags_id = $poster->getTagsIdByName($tags);

		$poster->dropTagsFromTarget($tags_id, $msg_info['id_topic']);

		$poster->purgeTargetTags($msg_info['id_topic']);
	}
}
