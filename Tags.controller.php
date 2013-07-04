<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 */

if (!defined('ELKARTE'))
	die('No access...');

/**
 * Something to show topics with tags
 */
class Tags_Controller
{
	/**
	 * Dunno if I'll need these two, though let's put them here
	 */
	private $_id = null;
	private $_name = null;
	private $_topics_per_page = null;
	private $_messages_per_page = null;
	private $_sort_method = null;

	/**
	 * Entry point function for likes, permission checks, just makes sure its on
	 */
	public function pre_dispatch()
	{
		global $modSettings;

		if (isset($_REQUEST['api']))
			return;

		loadLanguage('Tags');

		// If tags are disabled, we don't go any further
		if (empty($modSettings['tags_enabled']))
			fatal_lang_error('feature_disabled', true);

		if (strpos($_REQUEST['tag'], '.') !== false)
			list ($this->_id, ) = explode('.', $_REQUEST['tag']);
		else
			$this->_id = $_REQUEST['tag'];
		// Now make absolutely sure it's a number.
		$this->_id = (int) $this->_id;

		if (empty($this->_id))
			fatal_lang_error('no_such_tag', false);

		require_once(SUBSDIR . '/Tags.subs.php');

		$details = tagDetails($this->_id);

		if (empty($details) || empty($details['tag_text']))
			fatal_lang_error('no_such_tag', false);

		$this->_name = $details['tag_text'];
	}

	/**
	 * List all topics associated to a certain tag
	 * This will mimic MessageIndex as much as possible, let's see how it comes
	 */
	public function action_index()
	{
		global $txt, $scripturl, $board, $modSettings, $context;
		global $options, $settings, $user_info;

		// Fairly often, we'll work with boards. Current board, child boards.
		// @todo: probaly not here
		require_once(SUBSDIR . '/Boards.subs.php');
		require_once(SUBSDIR . '/MessageIndex.subs.php');

		loadTemplate('MessageIndex');
		loadJavascriptFile('topic.js');

		$context['name'] = $this->_name;
		// @todo: implement tag description
		$context['description'] = '';
		$template_layers = Template_Layers::getInstance();

		// How many topics do we have in total?
		$total_topics = countTaggedTopics($this->_id);

		// View all the topics, or just a few?
		$this->_topics_per_page = empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $modSettings['defaultMaxTopics'];
		$this->_messages_per_page = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
		$maxindex = $this->_topics_per_page;

		// Right, let's only index normal stuff!
		if (count($_GET) > 1)
		{
			$session_name = session_name();
			foreach ($_GET as $k => $v)
			{
				if (!in_array($k, array('board', 'start', $session_name)))
					$context['robot_no_index'] = true;
			}
		}
		if (!empty($_REQUEST['start']) && (!is_numeric($_REQUEST['start']) || $_REQUEST['start'] % $this->_messages_per_page != 0))
			$context['robot_no_index'] = true;

		// If we can view unapproved messages and there are some build up a list.
		// @todo for the moment let's disable this, I'll come back later
		if (false && allowedTo('approve_posts') && ($board_info['unapproved_topics'] || $board_info['unapproved_posts']))
		{
			$untopics = $board_info['unapproved_topics'] ? '<a href="' . $scripturl . '?action=moderate;area=postmod;sa=topics;brd=' . $board . '">' . $board_info['unapproved_topics'] . '</a>' : 0;
			$unposts = $board_info['unapproved_posts'] ? '<a href="' . $scripturl . '?action=moderate;area=postmod;sa=posts;brd=' . $board . '">' . ($board_info['unapproved_posts'] - $board_info['unapproved_topics']) . '</a>' : 0;
			$context['unapproved_posts_message'] = sprintf($txt['there_are_unapproved_topics'], $untopics, $unposts, $scripturl . '?action=moderate;area=postmod;sa=' . ($board_info['unapproved_topics'] ? 'topics' : 'posts') . ';brd=' . $board);
		}

		// We only know these.
		$sort_methods = messageIndexSort();
		$known_sorts = array_keys($sort_methods);

		// They didn't pick one, default to by last post descending.
		if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
		{
			$this->_sort_method = array_pop($known_sorts);
			$ascending = isset($_REQUEST['asc']);
		}
		// Otherwise default to ascending.
		else
		{
			$this->_sort_method = $_REQUEST['sort'];
			$ascending = !isset($_REQUEST['desc']);
		}
		$sort_column = $sort_methods[$this->_sort_method];

		$context['start'] = &$_REQUEST['start'];
		$start = (int) $context['start'];

		// Set a canonical URL for this page.
		$context['canonical_url'] = $scripturl . '?action=tags;tag=' . $this->_id . '.' . $start;

		$context['links'] = array(
			'first' => $start >= $this->_topics_per_page ? $scripturl . '?action=tags;tag=' . $this->_id . '.0' : '',
			'prev' => $start >= $this->_topics_per_page ? $scripturl . '?action=tags;tag=' . $this->_id . '.' . ($start - $this->_topics_per_page) : '',
			'next' => $start + $this->_topics_per_page < $total_topics ? $scripturl . '?action=tags;tag=' . $this->_id . '.' . ($start + $this->_topics_per_page) : '',
			'last' => $start + $this->_topics_per_page < $total_topics ? $scripturl . '?action=tags;tag=' . $this->_id . '.' . (floor(($total_topics - 1) / $this->_topics_per_page) * $this->_topics_per_page) : '',
			// @todo Hierarchy is not yet implemented...later :P
// 			'up' => $board_info['parent'] == 0 ? $scripturl . '?' : $scripturl . '?action=tags;tag=' . $board_info['parent'] . '.0'
		);

		$context['linktree'][] = array(
			'url' => $context['canonical_url'],
			'name' => $this->_name,
		);

		$context['page_info'] = array(
			'current_page' => $start / $this->_topics_per_page + 1,
			'num_pages' => floor(($total_topics - 1) / $this->_topics_per_page) + 1
		);

		// Make sure the starting place makes sense and construct the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=tags;tag=' . $board . '.%1$d' . (isset($_REQUEST['sort']) ? ';sort=' . $this->_sort_method . ($ascending ? '' : ';desc') : ''), $start, $total_topics, $this->_topics_per_page, array('prev_next' => true));

		// Mark current and parent boards as seen.
		if (!$user_info['is_guest'])
		{
			// We can't know they read it if we allow prefetches.
			if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
			{
				ob_end_clean();
				header('HTTP/1.1 403 Prefetch Forbidden');
				die;
			}

			// Mark tags as read.
			// @todo mark the tag as read may be expensive, but the function should be present
		}
// 		else
			$context['is_marked_notify'] = false;

		// 'Print' the header and board info.
		$context['page_title'] = $this->_name;

		// Set the variables up for the template.
		// @todo: this is all disabled for the time being
		$context['can_mark_notify'] = false && allowedTo('mark_notify') && !$user_info['is_guest'];
		$context['can_post_new'] = false && allowedTo('post_new') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_topics'));
		$context['can_post_poll'] = false && $modSettings['pollMode'] == '1' && allowedTo('poll_post') && $context['can_post_new'];
		$context['can_moderate_forum'] = false && allowedTo('moderate_forum');
		$context['can_approve_posts'] = false && allowedTo('approve_posts');

		// Prepare child tagss for display.
		// @todo not yet
// 		require_once(SUBSDIR . '/BoardIndex.subs.php');
// 		$boardIndexOptions = array(
// 			'include_categories' => false,
// 			'base_level' => $board_info['child_level'] + 1,
// 			'parent_id' => $board_info['id'],
// 			'set_latest_post' => false,
// 			'countChildPosts' => !empty($modSettings['countChildPosts']),
// 		);
// 		$context['boards'] = getBoardIndex($boardIndexOptions);

		// Nosey, nosey - who's viewing this board?
		if (!empty($settings['display_who_viewing']))
		{
			require_once(SUBSDIR . '/Who.subs.php');
			formatViewers($this->_id, 'tags');
		}

		$context['sort_direction'] = $ascending ? 'up' : 'down';
		$txt['starter'] = $txt['started_by'];

		foreach ($sort_methods as $key => $val)
			$context['topics_headers'][$key] = '<a href="' . $scripturl . '?action=tags;tag=' . $this->_id . '.' . $start . ';sort=' . $key . ($this->_sort_method == $key && $context['sort_direction'] == 'up' ? ';desc' : '') . '">' . $txt[$key] . ($this->_sort_method == $key ? '<img class="sort" src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.png" alt="" />' : '') . '</a>';

		// Calculate the fastest way to get the topics.
		if ($start > ($total_topics - 1) / 2)
		{
			$ascending = !$ascending;
			$fake_ascending = true;
			$maxindex = $total_topics < $start + $maxindex + 1 ? $total_topics - $start : $maxindex;
			$start = $total_topics < $start + $maxindex + 1 ? 0 : $total_topics - $start - $maxindex;
		}
		else
			$fake_ascending = false;

		// Setup the default topic icons...
		$stable_icons = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'poll', 'moved', 'recycled', 'wireless', 'clip');
		$context['icon_sources'] = array();
		foreach ($stable_icons as $icon)
			$context['icon_sources'][$icon] = 'images_url';

		$topic_ids = array();
		$context['topics'] = array();

		$indexOptions = array(
			'include_sticky' => !empty($modSettings['enableStickyTopics']),
			'only_approved' => $modSettings['postmod_active'] && !allowedTo('approve_posts'),
			'previews' => empty($modSettings['preview_characters']) ? 0 : $modSettings['preview_characters'],
			'include_avatars' => !empty($settings['avatars_on_indexes']),
			'ascending' => $ascending,
			'fake_ascending' => $fake_ascending
		);

		$topics_info = tagsIndexTopics($this->_id, $user_info['id'], $start, $this->_topics_per_page, $this->_sort_method, $sort_column, $indexOptions);

		// Prepare for links to guests (for search engines)
		$context['pageindex_multiplier'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

		// Begin 'printing' the message index for current board.
		foreach ($topics_info as $row)
		{
			if ($row['id_poll'] > 0 && $modSettings['pollMode'] == '0')
				continue;

			$topic_ids[] = $row['id_topic'];

			// Does the theme support message previews?
			if (!empty($settings['message_index_preview']) && !empty($modSettings['preview_characters']))
			{
				// Limit them to $modSettings['preview_characters'] characters
				$row['first_body'] = strip_tags(strtr(parse_bbc($row['first_body'], $row['first_smileys'], $row['id_first_msg']), array('<br />' => '&#10;')));
				if (Util::strlen($row['first_body']) > $modSettings['preview_characters'])
					$row['first_body'] = Util::substr($row['first_body'], 0, $modSettings['preview_characters']) . '...';

				$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], $row['last_smileys'], $row['id_last_msg']), array('<br />' => '&#10;')));
				if (Util::strlen($row['last_body']) > $modSettings['preview_characters'])
					$row['last_body'] = Util::substr($row['last_body'], 0, $modSettings['preview_characters']) . '...';

				// Censor the subject and message preview.
				censorText($row['first_subject']);
				censorText($row['first_body']);

				// Don't censor them twice!
				if ($row['id_first_msg'] == $row['id_last_msg'])
				{
					$row['last_subject'] = $row['first_subject'];
					$row['last_body'] = $row['first_body'];
				}
				else
				{
					censorText($row['last_subject']);
					censorText($row['last_body']);
				}
			}
			else
			{
				$row['first_body'] = '';
				$row['last_body'] = '';
				censorText($row['first_subject']);

				if ($row['id_first_msg'] == $row['id_last_msg'])
					$row['last_subject'] = $row['first_subject'];
				else
					censorText($row['last_subject']);
			}

			// Decide how many pages the topic should have.
			if ($row['num_replies'] + 1 > $this->_messages_per_page)
			{
				$pages = '&#171; ';

				// We can't pass start by reference.
				$start = -1;
				$pages .= constructPageIndex($scripturl . '?topic=' . $row['id_topic'] . '.%1$d', $start, $row['num_replies'] + 1, $this->_messages_per_page, true, array('prev_next' => false));

				// If we can use all, show all.
				if (!empty($modSettings['enableAllMessages']) && $row['num_replies'] + 1 < $modSettings['enableAllMessages'])
					$pages .= ' &nbsp;<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;all">' . $txt['all'] . '</a>';
				$pages .= ' &#187;';
			}
			else
				$pages = '';

			// We need to check the topic icons exist...
			if (!empty($modSettings['messageIconChecks_enable']))
			{
				if (!isset($context['icon_sources'][$row['first_icon']]))
					$context['icon_sources'][$row['first_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['first_icon'] . '.png') ? 'images_url' : 'default_images_url';
				if (!isset($context['icon_sources'][$row['last_icon']]))
					$context['icon_sources'][$row['last_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['last_icon'] . '.png') ? 'images_url' : 'default_images_url';
			}
			else
			{
				if (!isset($context['icon_sources'][$row['first_icon']]))
					$context['icon_sources'][$row['first_icon']] = 'images_url';
				if (!isset($context['icon_sources'][$row['last_icon']]))
					$context['icon_sources'][$row['last_icon']] = 'images_url';
			}

			if (!empty($settings['avatars_on_indexes']))
			{
				// Allow themers to show the latest poster's avatar along with the topic
				if (!empty($row['avatar']))
				{
					if ($modSettings['avatar_action_too_large'] == 'option_html_resize' || $modSettings['avatar_action_too_large'] == 'option_js_resize')
					{
						$avatar_width = !empty($modSettings['avatar_max_width_external']) ? ' width:' . $modSettings['avatar_max_width_external'] . 'px;' : '';
						$avatar_height = !empty($modSettings['avatar_max_height_external']) ? ' height:' . $modSettings['avatar_max_height_external'] . 'px;' : '';
					}
					else
					{
						$avatar_width = '';
						$avatar_height = '';
					}
				}
			}

			// 'Print' the topic info.
			$context['topics'][$row['id_topic']] = array(
				'id' => $row['id_topic'],
				'first_post' => array(
					'id' => $row['id_first_msg'],
					'member' => array(
						'username' => $row['first_member_name'],
						'name' => $row['first_display_name'],
						'id' => $row['first_id_member'],
						'href' => !empty($row['first_id_member']) ? $scripturl . '?action=profile;u=' . $row['first_id_member'] : '',
						'link' => !empty($row['first_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['first_id_member'] . '" title="' . $txt['profile_of'] . ' ' . $row['first_display_name'] . '" class="preview">' . $row['first_display_name'] . '</a>' : $row['first_display_name']
					),
					'time' => relativeTime($row['first_poster_time']),
					'timestamp' => forum_time(true, $row['first_poster_time']),
					'subject' => $row['first_subject'],
					'preview' => $row['first_body'],
					'icon' => $row['first_icon'],
					'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
					'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['first_subject'] . '</a>'
				),
				'last_post' => array(
					'id' => $row['id_last_msg'],
					'member' => array(
						'username' => $row['last_member_name'],
						'name' => $row['last_display_name'],
						'id' => $row['last_id_member'],
						'href' => !empty($row['last_id_member']) ? $scripturl . '?action=profile;u=' . $row['last_id_member'] : '',
						'link' => !empty($row['last_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['last_id_member'] . '">' . $row['last_display_name'] . '</a>' : $row['last_display_name']
					),
					'time' => relativeTime($row['last_poster_time']),
					'timestamp' => forum_time(true, $row['last_poster_time']),
					'subject' => $row['last_subject'],
					'preview' => $row['last_body'],
					'icon' => $row['last_icon'],
					'icon_url' => $settings[$context['icon_sources'][$row['last_icon']]] . '/post/' . $row['last_icon'] . '.png',
					'href' => $scripturl . '?topic=' . $row['id_topic'] . ($user_info['is_guest'] ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')),
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($user_info['is_guest'] ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')) . '" ' . ($row['num_replies'] == 0 ? '' : 'rel="nofollow"') . '>' . $row['last_subject'] . '</a>'
				),
				'is_sticky' => !empty($modSettings['enableStickyTopics']) && !empty($row['is_sticky']),
				'is_locked' => !empty($row['locked']),
				'is_poll' => $modSettings['pollMode'] == '1' && $row['id_poll'] > 0,
				'is_hot' => !empty($modSettings['useLikesNotViews']) ? $row['num_likes'] >= $modSettings['hotTopicPosts'] : $row['num_replies'] >= $modSettings['hotTopicPosts'],
				'is_very_hot' => !empty($modSettings['useLikesNotViews']) ? $row['num_likes'] >= $modSettings['hotTopicVeryPosts'] : $row['num_replies'] >= $modSettings['hotTopicVeryPosts'],
				'is_posted_in' => false,
				'icon' => $row['first_icon'],
				'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
				'subject' => $row['first_subject'],
				'new' => $row['new_from'] <= $row['id_msg_modified'],
				'new_from' => $row['new_from'],
				'newtime' => $row['new_from'],
				'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
				'pages' => $pages,
				'replies' => comma_format($row['num_replies']),
				'views' => comma_format($row['num_views']),
				'likes' => comma_format($row['num_likes']),
				'approved' => $row['approved'],
				'unapproved_posts' => $row['unapproved_posts'],
			);
			if (!empty($settings['avatars_on_indexes']))
				$context['topics'][$row['id_topic']]['last_post']['member']['avatar'] = array(
					'name' => $row['avatar'],
					'image' => $row['avatar'] == '' ? ($row['id_attach'] > 0 ? '<img class="avatar" src="' . (empty($row['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $row['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $row['filename']) . '" alt="" />' : '') : (stristr($row['avatar'], 'http://') ? '<img class="avatar" src="' . $row['avatar'] . '" style="' . $avatar_width . $avatar_height . '" alt="" />' : '<img class="avatar" src="' . $modSettings['avatar_url'] . '/' . htmlspecialchars($row['avatar']) . '" alt="" />'),
					'href' => $row['avatar'] == '' ? ($row['id_attach'] > 0 ? (empty($row['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $row['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $row['filename']) : '') : (stristr($row['avatar'], 'http://') ? $row['avatar'] : $modSettings['avatar_url'] . '/' . $row['avatar']),
					'url' => $row['avatar'] == '' ? '' : (stristr($row['avatar'], 'http://') ? $row['avatar'] : $modSettings['avatar_url'] . '/' . $row['avatar'])
				);

			determineTopicClass($context['topics'][$row['id_topic']]);
		}

		// Fix the sequence of topics if they were retrieved in the wrong order. (for speed reasons...)
		if ($fake_ascending)
			$context['topics'] = array_reverse($context['topics'], true);

		if (!empty($modSettings['enableParticipation']) && !$user_info['is_guest'] && !empty($topic_ids))
		{
			$topics_participated_in = topicsParticipation($user_info['id'], $topic_ids);
			foreach ($topics_participated_in as $participated)
			{
				$context['topics'][$participated['id_topic']]['is_posted_in'] = true;
				$context['topics'][$participated['id_topic']]['class'] = 'my_' . $context['topics'][$participated['id_topic']]['class'];
			}
		}

		$options['display_quick_mod'] = false;
		$context['current_board'] = 0;
		$context['jump_to'] = array(
			'label' => addslashes(un_htmlspecialchars($txt['jump_to'])),
			'board_name' => htmlspecialchars(strtr(strip_tags($this->_name), array('&amp;' => '&'))),
			'child_level' => 0,
		);

		// Is Quick Moderation active/needed?
		// @todo disabled that too
		if (false && !empty($options['display_quick_mod']) && !empty($context['topics']))
		{
			$context['can_markread'] = $context['user']['is_logged'];
			$context['can_lock'] = allowedTo('lock_any');
			$context['can_sticky'] = allowedTo('make_sticky') && !empty($modSettings['enableStickyTopics']);
			$context['can_move'] = allowedTo('move_any');
			$context['can_remove'] = allowedTo('remove_any');
			$context['can_merge'] = allowedTo('merge_any');
			// Ignore approving own topics as it's unlikely to come up...
			$context['can_approve'] = $modSettings['postmod_active'] && allowedTo('approve_posts') && !empty($board_info['unapproved_topics']);
			// Can we restore topics?
			$context['can_restore'] = allowedTo('move_any') && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $this->_id;

			// Set permissions for all the topics.
			foreach ($context['topics'] as $t => $topic)
			{
				$started = $topic['first_post']['member']['id'] == $user_info['id'];
				$context['topics'][$t]['quick_mod'] = array(
					'lock' => allowedTo('lock_any') || ($started && allowedTo('lock_own')),
					'sticky' => allowedTo('make_sticky') && !empty($modSettings['enableStickyTopics']),
					'move' => allowedTo('move_any') || ($started && allowedTo('move_own')),
					'modify' => allowedTo('modify_any') || ($started && allowedTo('modify_own')),
					'remove' => allowedTo('remove_any') || ($started && allowedTo('remove_own')),
					'approve' => $context['can_approve'] && $topic['unapproved_posts']
				);
				$context['can_lock'] |= ($started && allowedTo('lock_own'));
				$context['can_move'] |= ($started && allowedTo('move_own'));
				$context['can_remove'] |= ($started && allowedTo('remove_own'));
			}

			// Can we use quick moderation checkboxes?
			if ($options['display_quick_mod'] == 1)
				$context['can_quick_mod'] = $context['user']['is_logged'] || $context['can_approve'] || $context['can_remove'] || $context['can_lock'] || $context['can_sticky'] || $context['can_move'] || $context['can_merge'] || $context['can_restore'];
			// Or the icons?
			else
				$context['can_quick_mod'] = $context['can_remove'] || $context['can_lock'] || $context['can_sticky'] || $context['can_move'];
		}

		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1)
		{
			$context['qmod_actions'] = array('approve', 'remove', 'lock', 'sticky', 'move', 'merge', 'restore', 'markread');
			call_integration_hook('integrate_quick_mod_actions');
		}

// 		if (!empty($context['boards']) && (!empty($options['show_children']) || $start == 0))
// 			$template_layers->add('display_child_boards');

		// If there are children, but no topics and no ability to post topics...
// 		$context['no_topic_listing'] = !empty($context['boards']) && empty($context['topics']) && !$context['can_post_new'];
		$context['no_topic_listing'] = false;
		$template_layers->add('pages_and_buttons');

		addJavascriptVar('notification_board_notice', $context['is_marked_notify'] ? $txt['notification_disable_board'] : $txt['notification_enable_board'], true);

		// Build the message index button array.
		$context['normal_buttons'] = array();
// 		$context['normal_buttons'] = array(
// 			'new_topic' => array('test' => 'can_post_new', 'text' => 'new_topic', 'image' => 'new_topic.png', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0', 'active' => true),
// 			'post_poll' => array('test' => 'can_post_poll', 'text' => 'new_poll', 'image' => 'new_poll.png', 'lang' => true, 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0;poll'),
// 			'notify' => array('test' => 'can_mark_notify', 'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify', 'image' => ($context['is_marked_notify'] ? 'un' : ''). 'notify.png', 'lang' => true, 'custom' => 'onclick="return notifyboardButton(this);"', 'url' => $scripturl . '?action=notifyboard;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';board=' . $context['current_board'] . '.' . $start . ';' . $context['session_var'] . '=' . $context['session_id']),
// 		);

		// They can only mark read if they are logged in and it's enabled!
// 		if (!$user_info['is_guest'] && $settings['show_mark_read'])
// 			$context['normal_buttons']['markread'] = array('text' => 'mark_read_short', 'image' => 'markread.png', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=board;board=' . $context['current_board'] . '.0;' . $context['session_var'] . '=' . $context['session_id'], 'custom' => 'onclick="return markboardreadButton(this);"');

		// Allow adding new buttons easily.
		call_integration_hook('integrate_tagindex_buttons');
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
			$context['xml_data']['result'] = (int) removeTag($tag_id);
		else
			$context['xml_data']['result'] = (int) removeTag($tag_id, $topic);

		if (empty($context['xml_data']['result']))
			$context['xml_data']['error'] = $txt['no_such_tag'];
	}

	public function action_add_api()
	{
		global $context, $txt, $topic;

		$context['sub_template'] = 'tags_action_add';

		// In hashtag mode permissions are irrelevant
		if (!empty($modSettings['hashtag_mode']))
		{
			$context['xml_data']['error'] = $txt['tags_not_allowed_hashtag'];
			return;
		}

		if (!$this->_init_api())
			return;

		$tags_text = cleanPostedTags();

		if (empty($tags_text))
		{
			$context['xml_data']['error'] = $txt['empty_tag'];
			return;
		}

		$tags = createTags($tags_text);
		addTags($topic, $tags);
		prepareXmlTags(topicTags($topic), $topic);
	}

	public function action_search_api()
	{
		global $context, $txt, $topic;

		if (!empty($_GET['search']))
			$search = trim(Util::htmlspecialchars($_GET['search']));

		$context['sub_template'] = 'generic_xml';
		loadTemplate('Xml');

		if (!$this->_init_api('get', false) || empty($search))
		{
			$context['xml_data'] = array();
			return;
		}

		$context['xml_data'] = apiSearchTags($search);
		
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

		require_once(SUBSDIR . '/Tags.subs.php');

		if (($permission_strict && !tagsAllowed()) || ($permission_strict && !allowedTo('add_tags_own')))
		{
			$context['xml_data']['error'] = $txt['not_allowed_delete_tag'];
			return false;
		}

		return true;
	}
}