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

class Tags_Poster
{
	protected $tagger = 1;

	public function __construct($id_action)
	{
		$this->tagger = (int) $id_action;
	}

	public function tags_new_topics($msgOptions, $topicOptions, $posterOptions)
	{
		$possible_tags = $this->cleanPostedTags();

		// Do any of them already exist? (And grab all the ids at the same time)
		$tag_ids = $this->createTags($possible_tags);

		$this->addTags($topicOptions['id'], $tag_ids);
	}

	public function postHashed($body, $id_target)
	{
		$possible_tags = $this->cleanHashedTags($body);

		// Do any of them already exist? (And grab all the ids at the same time)
		$tag_ids = $this->createTags($possible_tags);

		if (!empty($tag_ids))
			$this->addTags($id_target, $tag_ids);
	}

	function editing_hashed_tags($messages_columns, $update_parameters, $msgOptions, $topicOptions, $posterOptions, $messageInts)
	{
		global $modSettings;

		if (empty($modSettings['tags_enabled']) || empty($modSettings['hashtag_mode']))
			return;

		$possible_tags = $this->cleanHashedTags($msgOptions['body']);

		$tag_ids = $this->createTags($possible_tags);

		$this->addTags($topicOptions['id'], $tag_ids);

		$this->purgeTopicTags($topicOptions['id']);
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

	function tags_protect_hashes($body)
	{
		global $modSettings, $context, $topic, $scripturl, $links_callback, $links_callback_counter;

		// Protects hashes into links to avoid broken HTML
		// ...it would be cool to have hashes linked even inside links though...
		$links_callback_counter = 0;
		$links_callback = array();
		$tmp = preg_replace_callback('~(<a[^>]*>[^<]*<\/a>)~', create_function('$match', '
			global $links_callback, $links_callback_counter;
			$links_callback[\'replace\'][$links_callback_counter] = $match[0];
			$links_callback[\'find\'][$links_callback_counter] = \'<a~~~~~~~>\' . ($links_callback_counter++) . \'</a~~~~~~~>\';

			return $links_callback[\'find\'][$links_callback_counter];'), $body);

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
			$body = str_replace($links_callback['find'], $links_callback['replace'], $tmp);
		else
			$body = $tmp;

		return $body;
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
			WHERE tr.id_target = {int:current_topic}
				AND type = {int:tagger}',
			array(
				'current_topic' => $topic_id,
				'tagger' => $this->tagger,
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

	function getTagsByName($tags)
	{
		$db = database();

		$request = $db->query('', '
			SELECT id_term
			FROM {db_prefix}tag_terms
			WHERE tag_text IN ({array_string:tags})',
			array(
				'tags' => $tags,
			)
		);

		$tags_id = array();
		while ($row = $db->fetch_assoc($request))
			$tags_id[] = $row['id_term'];
		$db->free_result($request);

		return $tags_id;
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
				AND id_target = {int:id_topic}
				AND type = {int:tagger}',
			array(
				'tag_ids' => $tag_ids,
				'id_topic' => $topic,
				'tagger' => $this->tagger,
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
					AND id_target = {int:id_topic}
					AND type = {int:tagger}',
				array(
					'tag_ids' => $tag_ids,
					'id_topic' => $topic,
					'tagger' => $this->tagger,
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
			WHERE id_target = {int:current_topic}
				AND type = {int:tagger}',
			array(
				'current_topic' => $id_topic,
				'tagger' => $this->tagger,
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
					AND id_target = {int:id_topic}
					AND type = {int:tagger}',
				array(
					'tag_ids' => $tags,
					'id_topic' => $id_topic,
					'tagger' => $this->tagger,
				)
			);
		}
		else
		{
			$db->query('', '
				DELETE
				FROM {db_prefix}tag_relation
				WHERE id_target = {int:current_topic}
					AND type = {int:tagger}',
				array(
					'current_topic' => $id_topic,
					'tagger' => $this->tagger,
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
			WHERE id_target = {int:current_topic}
				AND type = {int:tagger}
				AND times_mentioned < 1',
			array(
				'current_topic' => $id_topic,
				'tagger' => $this->tagger,
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
					AND id_target = {int:current_topic}
					AND type = {int:tagger}',
				array(
					'current_tag' => $tag_id,
					'current_topic' => $topic_id,
					'tagger' => $this->tagger,
				)
			);

			$tag = $this->tagDetails($tag_id);
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

			$tag = $this->tagDetails($tag_id);
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

	function dropTagsFromTopic($tags_id, $topic_id)
	{
		$db = database();

		$db->query('', '
			UPDATE {db_prefix}tag_relation
			SET times_mentioned = CASE WHEN times_mentioned <= 1 THEN 0 ELSE times_mentioned - 1 END
			WHERE id_term IN ({arra_int:tags})
				AND id_target = {int:current_topic}
				AND type = {int:tagger}',
			array(
				'tags' => $tag_id,
				'current_topic' => $topic_id,
				'tagger' => $this->tagger,
			)
		);
	}

	function createTags($tags, $matching = false)
	{
		$db = database();

		$tags = is_array($tags) ? $tags : array($tags);
		if (empty($tags))
			return;

		$tags = array_unique((array) $tags);
		$inserts = array();
		foreach ($tags as $tag)
			$inserts[] = array($tag);

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
			WHERE tr.id_target = {int:current_tag}
				AND type = {int:tagger}',
			array(
				'current_tag' => $tag_id,
				'tagger' => $this->tagger,
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
}