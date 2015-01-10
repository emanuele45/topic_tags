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