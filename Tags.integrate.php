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
class Tags
{
	protected static function init($perm = false)
	{
		global $modSettings;

		if (empty($modSettings['tags_enabled']))
			return false;

		require_once(SUBSDIR . '/Tags.subs.php');

		if ($perm && !tagsAllowed(true))
			return false;

		return true;
	}

	public static function post_after()
	{
		if (!self::init(true))
			return;

		tags_postPage();
	}

	public static function mark_read_button()
	{
		if (!self::init())
			return;

		tags_BICloud();
	}

	public static function display_topic($topicinfo)
	{
		if (!self::init())
			return;

		tags_DisplayCloud();
	}

	public static function load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
	{
		global $modSettings, $modSettings;

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
		global $context, $modSettings;

		if (empty($modSettings['tags_enabled']))
			return;

		require_once(SUBSDIR . '/Tags.subs.php');

		if (!empty($modSettings['hashtag_mode']))
			return posting_hashed_tags($msgOptions['body'], $topicOptions['id']);

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
			tags_new_topics();
		}
	}

	public static function create_post($msgOptions, $topicOptions, $posterOptions, $message_columns, $message_parameters)
	{
		global $modSettings;

		if (empty($modSettings['tags_enabled']) || empty($modSettings['hashtag_mode']) || empty($topicOptions['id']))
			return;

		require_once(SUBSDIR . '/Tags.subs.php');
		posting_hashed_tags($msgOptions['body'], $topicOptions['id']);
	}

	public static function modify_post($messages_columns, $update_parameters, $msgOptions, $topicOptions, $posterOptions, $messageInts)
	{
/*		global $modSettings;

		if (empty($modSettings['tags_enabled']) || empty($modSettings['hashtag_mode']))
			return;

		$possible_tags = cleanHashedTags($msgOptions['body']);

		$tag_ids = createTags($possible_tags);

		addTags($topicOptions['id'], $tag_ids);

		purgeTopicTags($topicOptions['id']);*/
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

		$possible_tags = cleanPostedTags();

		// Remove goes before the empty check because if you have cleaned up the
		// input you want to remove everything
		removeTagsFromTopic($topicOptions['id']);

		if (empty($possible_tags))
			return;

		// Usual: do they exist? If so, ids please!
		$tag_ids = createTags($possible_tags);

		addTags($topicOptions['id'], $tag_ids);
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
		require_once(SUBSDIR . '/Tags.subs.php');
		$subActions['routine']['activities']['recounttags'] = 'recountTags';
	}

	public static function prepare_display_context(&$output, &$message)
	{
		global $modSettings, $context, $topic, $scripturl, $links_callback, $links_callback_counter;

		if (empty($modSettings['tags_enabled']) || empty($modSettings['hashtag_mode']))
			return;

		if (empty($context['current_tags']))
			return;

		require_once(SUBSDIR . '/Tags.subs.php');

		$output['body'] = tags_protect_hashes($output['body']);
	}

	public static function remove_message($message)
	{
		require_once(SUBSDIR . '/Messages.subs.php');
		require_once(SUBSDIR . '/Tags.subs.php');

		$msg_info = basicMessageInfo($message, false, true);
		$tags = cleanHashedTags($msg_info['body']);
		_debug($tags,0,0,1);
		$tags_id = getTagsByName($tags);

		dropTagFromTopic($tags_id, $msg_info['id_topic']);

		purgeTopicTags($msg_info['id_topic']);
	}
}
