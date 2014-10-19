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
function post_page_tags()
{
	global $topic, $context, $modSettings, $txt;

	if (empty($modSettings['tags_enabled']) || !tagsAllowed(true))
		return;

	// Give the box only for new topics or when editing the first message
	if (empty($modSettings['hashtag_mode']) && (empty($topic) || $context['is_first_post']))
	{
		Template_Layers::getInstance()->addAfter('tags_posting', 'postarea');
		init_tags_template();

		loadJavaScriptFile('suggest.js');

		$context['current_tags'] = topicTags($topic, true);
		if (!empty($context['current_tags']))
		{
			addJavascriptVar('current_tags', 'var current_tags = new Array(' . implode(', ', $context['current_tags']) . ');', true);
			addJavascriptVar('want_to_restore_tags', $txt['want_to_restore_tags'], true);
			addJavascriptVar('tags_will_be_deleted', $txt['tags_will_be_deleted'], true);

		}

		addJavascriptVar('autosuggest_delete_item', $txt['autosuggest_delete_item'], true);
	}
}

function boardindex_tag_cloud()
{
	global $modSettings;

	if (empty($modSettings['tags_enabled']))
		return;

	init_tags_template();
	Template_Layers::getInstance()->addEnd('boardindex_tag_cloud');

	styleTags(mostUsedTags());
}

function topic_tag_cloud($topic_selects, $topic_tables, $topic_parameters)
{
	global $topic, $modSettings, $context;

	if (empty($modSettings['tags_enabled']))
		return;

	init_tags_template(!empty($modSettings['hashtag_mode']));
	Template_Layers::getInstance()->addBefore('topic_tag_cloud', 'pages_and_buttons');

	$context['tags_list'] = topicTags($topic);
	styleTags($context['tags_list'], $topic);
}

function add_tags_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
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

function add_tags_illegal_permissions()
{
	global $context, $modSettings;

	// In hashtag mode permissions are irrelevant
	if (!empty($modSettings['hashtag_mode']))
		return;

	loadLanguage('Tags');

	$context['non_guest_permissions'][] = 'add_tags';
}

function posting_tags($msgOptions, $topicOptions, $posterOptions)
{
	global $context, $modSettings;

	if (!empty($modSettings['hashtag_mode']))
		return posting_hashed_tags($msgOptions, $topicOptions, $posterOptions, array(), array());

	if (empty($modSettings['tags_enabled']) || !tagsAllowed(true))
		return;

	// I want to have tags set only for the entire topic (and for now, attached only to the first msg)
	require_once(SUBSDIR . '/Messages.subs.php');
	$topic_info = basicMessageInfo($msgOptions['id'], false, true);
	if (!$topic_info['id_first_msg'])
		return;

	// Since this is only for new topics I can just check that
	if (!empty($_POST['tags']))
	{
		$possible_tags = cleanPostedTags();

		// Do any of them already exist? (And grab all the ids at the same time)
		$tag_ids = createTags($possible_tags);

		addTags($topicOptions['id'], $tag_ids);
	}
}

function posting_hashed_tags($msgOptions, $topicOptions, $posterOptions, $message_columns, $message_parameters)
{
	global $modSettings;

	if (empty($modSettings['tags_enabled']) || empty($modSettings['hashtag_mode']) || empty($topicOptions['id']))
		return;

	$possible_tags = cleanHashedTags($msgOptions['body']);

	// Do any of them already exist? (And grab all the ids at the same time)
	$tag_ids = createTags($possible_tags);

	if (!empty($tag_ids))
		addTags($topicOptions['id'], $tag_ids);
}

function editing_hashed_tags($messages_columns, $update_parameters, $msgOptions, $topicOptions, $posterOptions, $messageInts)
{
	global $modSettings;

	if (empty($modSettings['tags_enabled']) || empty($modSettings['hashtag_mode']))
		return;

	$possible_tags = cleanHashedTags($msgOptions['body']);

	$tag_ids = createTags($possible_tags);

	addTags($topicOptions['id'], $tag_ids);

	purgeTopicTags($topicOptions['id']);
}

function cleanHashedTags($message)
{
	// This one is necessary cleanup potential tags hidden in bbc
	// (e.g. [something #123]) I know something like this exists, so better remove it!
	$message = preg_replace('~\[[^\]]*#[^\]]*\]~', '', $message);

	// @todo the regexp is kinda crappy, I have to find a better one...
	// testing string:
	// #asd #222 #a.dsa #as1.dd  #asd2#das #asd3 #asd4 #ads5
	// expected returns:
	//  asd
	//  222
	//  as1
	//  asd2
	//  asd3
	//  asd4
	//  asd5
	preg_match_all('~([\s^]#(\w{2,}[^\s])|[\s^]#(\w{2,}[^\w]))~', ' ' . str_replace('<br />', ' ', $message), $matches);

	if (!empty($matches[2]))
	{
		$return = preg_replace('~[^\w]$~', '', $matches[2]);
		return array_unique($return);
	}
	else
		return array();
}

function editing_tags($messages_columns, $update_parameters, $msgOptions, $topicOptions, $posterOptions, $messageInts)
{
	global $modSettings;

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

function add_routine_tags_recount()
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

function add_tags_maintenance_activity(&$subActions)
{
	$subActions['routine']['activities']['recounttags'] = 'recountTags';
}

function prepare_display_hashed_tags(&$output, &$message)
{
	global $modSettings, $context, $topic, $scripturl, $links_callback, $links_callback_counter;

	if (empty($modSettings['tags_enabled']) || empty($modSettings['hashtag_mode']))
		return;

	if (empty($context['current_tags']))
		return;

	// Protects hashes into links to avoid broken HTML
	// ...it would be cool to have hashes linked even inside links though...
	$links_callback_counter = 0;
	$links_callback = array();
	$tmp = preg_replace_callback('~(<a[^>]*>[^<]*<\/a>)~', create_function('$match', '
		global $links_callback, $links_callback_counter;
		$links_callback[\'replace\'][$links_callback_counter] = $match[0];
		$links_callback[\'find\'][$links_callback_counter] = \'<a~~~~~~~>\' . ($links_callback_counter++) . \'</a~~~~~~~>\';

		return $links_callback[\'find\'][$links_callback_counter];'), $output['body']);

	$find = array();
	$replace = array();
	foreach ($context['tags_list']['tags'] as $tag)
		$find[] = '~(\s|<br />|^)#(' . preg_quote($tag['tag_text']) . ')(\s|<br />|$)~';

	$tmp = preg_replace_callback($find, create_function('$match', '
		global $context, $topic, $scripturl;
		if (!empty($match[2]) && isset($context[\'tags_list\'][\'tags\'][$match[2]]))
		{
			$tag = $context[\'tags_list\'][\'tags\'][$match[2]];
			return $match[1] . \'<a data-topic="\' . $topic . \'" id="tag_\' . $tag[\'id_term\'] . \'" class="msg_tagsize\' . round(10 * $tag[\'times_used\'] / $context[\'tags_list\'][\'max_used\']) . \'" href="\' . $scripturl . \'?action=tags;tag=\' . $tag[\'id_term\'] . \'.0">#\' . $tag[\'tag_text\'] . \'</a>\' . $match[3];
		}'), $tmp);

	if (!empty($links_callback))
		$output['body'] = str_replace($links_callback['find'], $links_callback['replace'], $tmp);
	else
		$output['body'] = $tmp;
}

/*********************************************************
 * End of Hooks functions
 *********************************************************/

/*********************************************************
 * Real subs
 *********************************************************/
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

function cleanPostedTags()
{
	if (empty($_POST['tags']))
		return array();

	$possible_tags = explode(',', $_POST['tags']);

	// a bit of cleanup
	foreach ($possible_tags as &$tag)
		$tag = trim(Util::htmlspecialchars($tag));
	$possible_tags = array_unique($possible_tags);

	return $possible_tags;
}

function topicTags($topic_id, $only_tags = false)
{
	$topic_id = (int) $topic_id;

	if (empty($topic_id))
		return;

	$db = database();

	$request = $db->query('', '
		SELECT tt.id_term, tt.tag_text, tt.times_used
		FROM {db_prefix}tag_terms as tt
		LEFT JOIN {db_prefix}tag_relation as tr ON (tr.id_term = tt.id_term)
		WHERE tr.id_topic = {int:current_topic}',
		array(
			'current_topic' => $topic_id
		)
	);
	$tags = array();
	$highest_usage = 1;
	if ($only_tags)
	{
		while ($row = $db->fetch_assoc($request))
		{
			$highest_usage = max($highest_usage, $row['times_used']);
			$tags[$row['id_term']] = $row['tag_text'];
		}
		$db->free_result($request);

		return $tags;
	}
	else
	{
		while ($row = $db->fetch_assoc($request))
		{
			$highest_usage = max($highest_usage, $row['times_used']);
			$tags[$row['tag_text']] = $row;
		}
		$db->free_result($request);

		ksort($tags);

		return array('tags' => $tags, 'max_used' => $highest_usage);
	}
}

function mostUsedTags($limit = null)
{
	global $modSettings;

	if (empty($limit))
		$limit = $modSettings['maximum_number_tags'];

	$db = database();

	$request = $db->query('', '
		SELECT id_term, tag_text, times_used
		FROM {db_prefix}tag_terms
		WHERE times_used > 0
		ORDER BY times_used DESC
		LIMIT {int:maximum}',
		array(
			'maximum' => $limit
		)
	);
	$tags = array();
	$highest_usage = 1;
	while ($row = $db->fetch_assoc($request))
	{
		$highest_usage = max($highest_usage, $row['times_used']);
		$tags[$row['tag_text']] = $row;
	}
	$db->free_result($request);

	ksort($tags);

	return array('tags' => $tags, 'max_used' => $highest_usage);
}

function tagDetails($tag_id)
{
	$db = database();

	$request = $db->query('', '
		SELECT id_term, tag_text
		FROM {db_prefix}tag_terms
		WHERE id_term = {int:tag_id}',
		array(
			'tag_id' => $tag_id,
		)
	);

	$tag_details = $db->fetch_assoc($request);
	$db->free_result($request);

	return $tag_details;
}

function addTags($topic, $tag_ids)
{
	global $modSettings;

	$db = database();

	$inserts = array();
	foreach ($tag_ids as $tag)
		$inserts[] = array($tag, $topic);

	$request = $db->query('', '
		SELECT id_term
		FROM {db_prefix}tag_relation
		WHERE id_term IN ({array_int:tag_ids})
			AND id_topic = {int:id_topic}',
		array(
			'tag_ids' => $tag_ids,
			'id_topic' => $topic,
		)
	);
	$exiting_tags = array();
	while ($row = $db->fetch_assoc($request))
		$exiting_tags[] = $row['id_term'];
	$db->free_result($request);

	$db->insert('ignore',
		'{db_prefix}tag_relation',
		array('id_term' => 'int', 'id_topic' => 'int'),
		$inserts,
		array('id_term', 'id_topic')
	);

	if (!empty($modSettings['hashtag_mode']))
	{
		$db->query('', '
			UPDATE {db_prefix}tag_relation
			SET times_mentioned = times_mentioned + 1
			WHERE id_term IN ({array_int:tag_ids})
				AND id_topic = {int:id_topic}',
			array(
				'tag_ids' => $tag_ids,
				'id_topic' => $topic,
			)
		);
	}

	$new_tags = array_diff($tag_ids, $exiting_tags);
	if (empty($new_tags))
		return;

	$db->query('', '
		UPDATE {db_prefix}tag_terms
		SET times_used = times_used + 1
		WHERE id_term IN ({array_int:selected_tags})',
		array(
			'selected_tags' => $new_tags,
		)
	);
}

function removeTagsFromTopic($id_topic)
{
	global $modSettings;

	$db = database();

	$id_topic = (int) $id_topic;

	if (empty($id_topic))
		return false;

	$request = $db->query('', '
		SELECT id_term
		FROM {db_prefix}tag_relation
		WHERE id_topic = {int:current_topic}',
		array(
			'current_topic' => $id_topic
		)
	);
	$tags = array();
	while ($row = $db->fetch_assoc($request))
		$tags[] = $row['id_term'];
	$db->free_result($request);

	if (empty($tags))
		return;

	$db->query('', '
		UPDATE {db_prefix}tag_terms
		SET times_used = CASE WHEN times_used <= 1 THEN 0 ELSE times_used - 1 END
		WHERE id_term IN ({array_int:selected_tags})',
		array(
			'selected_tags' => $tags
		)
	);

	if (!empty($modSettings['hashtag_mode']))
	{
		$db->query('', '
			UPDATE {db_prefix}tag_relation
			SET times_mentioned = times_mentioned - 1
			WHERE id_term IN ({array_int:tag_ids})
				AND id_topic = {int:id_topic}',
			array(
				'tag_ids' => $tags,
				'id_topic' => $id_topic,
			)
		);
	}
	else
	{
		$db->query('', '
			DELETE
			FROM {db_prefix}tag_relation
			WHERE id_topic = {int:current_topic}',
			array(
				'current_topic' => $id_topic
			)
		);
	}
}

function purgeTopicTags($id_topic)
{
	if (empty($id_topic))
		return;

	$db = database();

	$db->query('', '
		DELETE
		FROM {db_prefix}tag_relation
		WHERE id_topic = {int:current_topic}
			AND times_mentioned < 1',
		array(
			'current_topic' => $id_topic
		)
	);
}

function removeTag($tag_id, $topic_id = false)
{
	$db = database();

	if ($topic_id !== false)
	{
		$db->query('', '
			DELETE
			FROM {db_prefix}tag_relation
			WHERE id_term = {int:current_tag}
				AND id_topic = {int:current_topic}',
			array(
				'current_tag' => $tag_id,
				'current_topic' => $topic_id
			)
		);

		$tag = tagDetails($tag_id);
		if (empty($tag))
			return false;

		$db->query('', '
			UPDATE {db_prefix}tag_terms
			SET times_used = CASE WHEN times_used <= 1 THEN 0 ELSE times_used - 1 END
			WHERE id_term = {int:tags}',
			array(
				'tags' => $tag_id,
			)
		);

		return true;
	}
	else
	{
		$db->query('', '
			DELETE
			FROM {db_prefix}tag_relation
			WHERE id_term = {int:current_tag}',
			array(
				'current_tag' => $tag_id
			)
		);

		$tag = tagDetails($tag_id);
		if (empty($tag))
			return false;

		$db->query('', '
			DELETE
			FROM {db_prefix}tag_terms
			WHERE id_term = {int:current_tag}',
			array(
				'current_tag' => $tag_id
			)
		);

		return true;
	}
}

function createTags($tags, $matching = false)
{
	$db = database();

	$tags = is_array($tags) ? $tags : array($tags);
	if (empty($tags))
		return;

	$inserts = array(array_unique($tags));

	$db->insert('ignore',
		'{db_prefix}tag_terms',
		array('tag_text' => 'string-60'),
		$inserts,
		array('tag_text')
	);

	$request = $db->query('', '
		SELECT id_term' . ($matching ? ', tag_text, times_used' : '') . '
		FROM {db_prefix}tag_terms
		WHERE tag_text IN ({array_string:tags})
		LIMIT {int:count_tags}',
		array(
			'tags' => $tags,
			'count_tags' => count($tags),
		)
	);

	$ids = array();
	if ($matching)
	{
		while ($row = $db->fetch_assoc($request))
			$ids[$row['id_term']] = $row;
	}
	else
	{
		while ($row = $db->fetch_assoc($request))
			$ids[] = $row['id_term'];
	}
	$db->free_result($request);

	return $ids;
}

function countTaggedTopics($tag_id)
{
	$db = database();

	// @todo this should become a column in the tag_terms table, but since it implies also moderation, I'll do it later
	$request = $db->query('', '
		SELECT times_used
		FROM {db_prefix}tag_terms as tt
		LEFT JOIN {db_prefix}tag_relation as tr ON (tt.id_term = tr.id_term)
		WHERE tr.id_topic = {int:current_tag}',
		array(
			'current_tag' => $tag_id,
		)
	);
	list($count) = $db->fetch_row($request);
	$db->free_result($request);

	return $count;
}

function tagsIndexTopics($id_term, $id_member, $start, $per_page, $sort_by, $sort_column, $indexOptions)
{
	global $settings;

	$db = database();

	$topics = array();
	$topic_ids = array();

	// Extra-query for the pages after the first
	$ids_query = $start > 0;
	if ($ids_query && $per_page > 0)
	{
		$request = $db->query('', '
			SELECT t.id_topic
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}tag_relation AS tr ON (tr.id_topic = t.id_topic)' . ($sort_by === 'last_poster' ? '
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)' : (in_array($sort_by, array('starter', 'subject')) ? '
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)' : '')) . ($sort_by === 'starter' ? '
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)' : '') . ($sort_by === 'last_poster' ? '
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' : '') . '
			WHERE tr.id_term = {int:current_tag}' . (!$indexOptions['only_approved'] ? '' : '
				AND (t.approved = {int:is_approved}' . ($id_member == 0 ? '' : ' OR t.id_member_started = {int:current_member}') . ')') . '
			ORDER BY ' . ($indexOptions['include_sticky'] ? 'is_sticky' . ($indexOptions['fake_ascending'] ? '' : ' DESC') . ', ' : '') . $sort_column . ($indexOptions['ascending'] ? '' : ' DESC') . '
			LIMIT {int:start}, {int:maxindex}',
			array(
				'current_tag' => $id_term,
				'current_member' => $id_member,
				'is_approved' => 1,
				'id_member_guest' => 0,
				'start' => $start,
				'maxindex' => $per_page,
			)
		);
		$topic_ids = array();
		while ($row = $db->fetch_assoc($request))
			$topic_ids[] = $row['id_topic'];
		$db->free_result($request);
	}

	// And now, all you ever wanted on message index...
	// and some you wish you didn't! :P
	if (!$ids_query || !empty($topic_ids))
	{
		$request = $db->query('substring', '
			SELECT
				t.id_topic, t.num_replies, t.locked, t.num_views, t.num_likes, t.is_sticky, t.id_poll, t.id_previous_board,
				' . ($id_member == 0 ? '0' : 'IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1') . ' AS new_from,
				t.id_last_msg, t.approved, t.unapproved_posts, ml.poster_time AS last_poster_time,
				ml.id_msg_modified, ml.subject AS last_subject, ml.icon AS last_icon,
				ml.poster_name AS last_member_name, ml.id_member AS last_id_member, ' . ($indexOptions['include_avatars'] ? 'meml.avatar,' : '') . '
				IFNULL(meml.real_name, ml.poster_name) AS last_display_name, t.id_first_msg,
				mf.poster_time AS first_poster_time, mf.subject AS first_subject, mf.icon AS first_icon,
				mf.poster_name AS first_member_name, mf.id_member AS first_id_member,
				IFNULL(memf.real_name, mf.poster_name) AS first_display_name, ' . (!empty($indexOptions['previews']) ? '
				SUBSTRING(ml.body, 1, ' . ($indexOptions['previews'] + 256) . ') AS last_body,
				SUBSTRING(mf.body, 1, ' . ($indexOptions['previews'] + 256) . ') AS first_body,' : '') . 'ml.smileys_enabled AS last_smileys, mf.smileys_enabled AS first_smileys' . (!empty($settings['avatars_on_indexes']) ? ',
				IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type' : '') . '
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
				LEFT JOIN {db_prefix}tag_relation as tr ON (tr.id_topic = t.id_topic)' . ($id_member == 0 ? '' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})') . (!empty($settings['avatars_on_indexes']) ? '
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = ml.id_member)' : '') . '
			WHERE ' . ($ids_query ? 't.id_topic IN ({array_int:topic_list})' : 'tr.id_term = {int:current_tag}') . (!$indexOptions['only_approved'] ? '' : '
				AND (t.approved = {int:is_approved}' . ($id_member == 0 ? '' : ' OR t.id_member_started = {int:current_member}') . ')') . '
			ORDER BY ' . ($ids_query ? 'FIND_IN_SET(t.id_topic, {string:find_set_topics})' : ($indexOptions['include_sticky'] ? 'is_sticky' . ($indexOptions['fake_ascending'] ? '' : ' DESC') . ', ' : '') . $sort_column . ($indexOptions['ascending'] ? '' : ' DESC')) . '
			LIMIT ' . ($ids_query ? '' : '{int:start}, ') . '{int:maxindex}',
			array(
				'current_tag' => $id_term,
				'current_member' => $id_member,
				'topic_list' => $topic_ids,
				'is_approved' => 1,
				'find_set_topics' => implode(',', $topic_ids),
				'start' => $start,
				'maxindex' => $per_page,
			)
		);

		// lets take the results
		while ($row = $db->fetch_assoc($request))
			$topics[] = $row;

		$db->free_result($request);
	}
	return $topics;
}

function recountTags()
{
	global $context, $txt, $modSettings;

	$db = database();

	$context['page_title'] = $txt['not_done_title'];
	$context['continue_countdown'] = 3;
	$context['continue_post_data'] = '';
	$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recounttags;' . $context['session_var'] . '=' . $context['session_id'];
	$context['sub_template'] = 'not_done';
	$next_start = !empty($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
	$next_hstart = !empty($_REQUEST['hstart']) ? (int) $_REQUEST['hstart'] : 0;
	$step = !empty($_REQUEST['step']) ? (int) $_REQUEST['step'] : 0;
	$done = false;

	// This is going to be looooong: it has to check all the messages, one by one,
	// even those that don't exist any more
	if (!empty($modSettings['hashtag_mode']))
	{
		// First time here, let's start fresh
		if (empty($next_hstart))
		{
			$db->query('truncate_table', '
				TRUNCATE {db_prefix}tag_relation');
			$db->query('truncate_table', '
				TRUNCATE {db_prefix}tag_terms');
		}

		$request = $db->query('', '
			SELECT id_msg, id_topic, body
			FROM {db_prefix}messages
			WHERE id_msg > {int:start}
			LIMIT {int:limit}',
			array(
				'start' => $next_hstart,
				'limit' => 100,
			)
		);

		// Let's start here, so we don't take in consideration the previous queries
		$start = microtime(true);
		if ($db->num_rows($request) != 0)
		{
			while ($row = $db->fetch_assoc($request))
			{
				// 3, 2, 1... take a break!
				if (microtime(true) - $start > 3)
					break;

				$next_hstart = $row['id_msg'];
				$possible_tags = cleanHashedTags($row['body']);
				if (empty($possible_tags))
					continue;

				$tag_ids = createTags($possible_tags);

				addTags($row['id_topic'], $tag_ids);
			}
			$db->free_result($request);

			$context['continue_get_data'] .= ';hstart=' . $next_hstart;
			$context['continue_percent'] = round(100 * $next_hstart / $modSettings['totalMessages']);
			return;
		}
		else
		{
			$db->query('', '
				DELETE
				FROM {db_prefix}tag_relation
				WHERE times_mentioned < 1'
			);
			$db->free_result($request);
		}
		$done = true;
	}
	else
	{
		$request = $db->query('', '
			SELECT id_term, COUNT(*) as times_used
			FROM {db_prefix}tag_relation
			WHERE id_term > {int:start}
			GROUP BY id_term
			LIMIT {int:limit}',
			array(
				'start' => $next_start,
				'limit' => 100
			)
		);
		$request2 = $db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}tag_terms',
			array()
		);
		list($max_tags) = $db->fetch_row($request2);
		$db->free_result($request2);

		// Let's start here, so we don't take in consideration the previous queries
		$start = microtime(true);
		if ($db->num_rows($request) != 0)
		{
			while ($row = $db->fetch_assoc($request))
			{
				// 3 seconds
				if (microtime(true) - $start > 3)
					break;

				$db->query('', '
					UPDATE {db_prefix}tag_terms
					SET times_used = {int:times_used}
					WHERE id_term = {int:current_tag}',
					array(
						'times_used' => $row['times_used'],
						'current_tag' => $row['id_term'],
					)
				);
				$next_start = $row['id_term'];
			}
			$db->free_result($request);

			$context['continue_get_data'] = ';start=' . $next_start;
			$context['continue_percent'] = round(100 * $_REQUEST['start'] / $max_tags);
			return;
		}
		else
			$db->free_result($request);
		$done = true;
	}

	if ($done)
	{
		$db->free_result($request);
		redirectexit('action=admin;area=maintain;sa=routine;done=recounttags');
	}
}

function tagsAllowed($new_topic = false)
{
	global $topic, $user_info;

	// In hashtag mode permissions are irrelevant
	if (!empty($modSettings['hashtag_mode']))
		return true;

	if ($new_topic && allowedTo('add_tags_own'))
		return true;

	if (!empty($topic))
	{
		require_once(SUBSDIR . '/Topic.subs.php');
		list($topic_starter, ) = topicStatus($topic);

		if (($user_info['id'] == $topic_starter && !allowedTo('add_tags_own')) || ($user_info['id'] != $topic_starter && !allowedTo('add_tags_any')))
			return false;
	}
	elseif (!allowedTo('add_tags_any'))
		return false;

	return true;
}

function apiSearchTags($fragment)
{
	if (empty($fragment) || strlen($fragment) < 2)
		return array();

	$db = database();
	$beginning = true;
	$have_results = false;
	$xml_data = array();

	while (!$have_results)
	{
		$request = $db->query('', '
			SELECT id_term, tag_text
			FROM {db_prefix}tag_terms
			WHERE tag_text LIKE {string:search_term}',
			array(
				'search_term' => ($beginning ? '%' : '') . $fragment . '%',
			)
		);

		// If no results on the second round, useless to proceed
		if ($db->num_rows($request) == 0 && !$beginning)
			$have_results = true;
		elseif ($db->num_rows($request) > 0)
			$have_results = true;
		$beginning = !$beginning;

		$xml_data = array(
			'items' => array(
				'identifier' => 'item',
				'children' => array(),
			),
		);

		if ($have_results)
		{
			while ($row = $db->fetch_assoc($request))
				$xml_data['items']['children'][] = array(
					'attributes' => array(
						'id' => $row['id_term'],
					),
					'value' => $row['tag_text'],
				);
		}
		$db->free_result($request);
	}

	return $xml_data;
}
