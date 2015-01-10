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
	protected $id_tagger = 1;
	protected $tagger = null;

	public function __construct($action)
	{
		$this->initTagger($action);
		$this->id_tagger = $this->tagger->getTypeId();
		$this->tagger_name = $action;
	}

	public function canAccess($id_target)
	{
		return $this->tagger->canAccess($id_target);
	}

	protected function initTagger($action)
	{
		require_once(SUBSDIR . '/Taggers/Tagger.interface.php');
		$file = SUBSDIR . '/Taggers/' . ucfirst($action) . '.tagger.php';

		if (!file_exists($file))
		{
			// This is the only place where "topic" should appear in this file
			// being the only notification we are sure it's always present,
			// it's the default one in case of errors.
			return $this->initTagger('topics');
		}

		require_once($file);
		$class = ucfirst($action) . '_Tagger';
		$this->tagger = new $class();
	}

	public function postNewTags($tags, $target_id)
	{
		$possible_tags = $this->cleanPostedTags($tags);

		// Do any of them already exist? (And grab all the ids at the same time)
		$tag_ids = $this->createTags($possible_tags);

		$this->addTags($target_id, $tag_ids);
	}

	public function postHashed($body, $id_target)
	{
		$possible_tags = $this->cleanHashedTags($body);

		// Do any of them already exist? (And grab all the ids at the same time)
		$tag_ids = $this->createTags($possible_tags);

		if (!empty($tag_ids))
			$this->addTags($id_target, $tag_ids);
	}

	/**
	 * @todo apparently unused.
	 */
	function editing_hashed_tags($messages_columns, $update_parameters, $msgOptions, $topicOptions, $posterOptions, $messageInts)
	{
		global $modSettings;

		if (empty($modSettings['tags_enabled']) || empty($modSettings['hashtag_mode']))
			return;

		$possible_tags = $this->cleanHashedTags($msgOptions['body']);

		$tag_ids = $this->createTags($possible_tags);

		$this->addTags($topicOptions['id'], $tag_ids);

		$this->purgeTargetTags($topicOptions['id']);
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

	function cleanPostedTags($tags)
	{
		if (empty($tags))
			return array();

		$possible_tags = explode(',', $tags);

		// a bit of cleanup
		foreach ($possible_tags as &$tag)
			$tag = trim(Util::htmlspecialchars($tag));
		$possible_tags = array_filter(array_unique($possible_tags));

		return $possible_tags;
	}

	/**
	 * 
	 */
	function getTargetTags($id_target, $only_text = false)
	{
		$id_target = (int) $id_target;

		if (empty($id_target))
			return;

		$db = database();

		$request = $db->query('', '
			SELECT tt.id_term, tt.tag_text, tt.times_used
			FROM {db_prefix}tag_terms as tt
				LEFT JOIN {db_prefix}tag_relation as tr ON (tr.id_term = tt.id_term)
			WHERE tr.id_target = {int:current_target}
				AND type = {int:tagger}',
			array(
				'current_target' => $id_target,
				'tagger' => $this->id_tagger,
			)
		);
		$tags = array();
		$highest_usage = 1;

		if ($only_text)
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

	function getTagsIdByName($tags)
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

	function addTags($id_target, $tag_ids)
	{
		global $modSettings;

		$db = database();

		$inserts = array();
		foreach ($tag_ids as $tag)
			$inserts[] = array($tag, $id_target);

		$request = $db->query('', '
			SELECT id_term
			FROM {db_prefix}tag_relation
			WHERE id_term IN ({array_int:tag_ids})
				AND id_target = {int:id_target}
				AND type = {int:tagger}',
			array(
				'tag_ids' => $tag_ids,
				'id_target' => $id_target,
				'tagger' => $this->id_tagger,
			)
		);
		$exiting_tags = array();
		while ($row = $db->fetch_assoc($request))
			$exiting_tags[] = $row['id_term'];
		$db->free_result($request);

		$db->insert('ignore',
			'{db_prefix}tag_relation',
			array('id_term' => 'int', 'id_target' => 'int'),
			$inserts,
			array('id_term', 'id_target')
		);

		if (!empty($modSettings['hashtag_mode']))
		{
			$db->query('', '
				UPDATE {db_prefix}tag_relation
				SET times_mentioned = times_mentioned + 1
				WHERE id_term IN ({array_int:tag_ids})
					AND id_target = {int:id_target}
					AND type = {int:tagger}',
				array(
					'tag_ids' => $tag_ids,
					'id_target' => $id_target,
					'tagger' => $this->id_tagger,
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

	function removeTagsFromTarget($id_target)
	{
		global $modSettings;

		$db = database();

		$id_target = (int) $id_target;

		if (empty($id_target))
			return false;

		$request = $db->query('', '
			SELECT id_term
			FROM {db_prefix}tag_relation
			WHERE id_target = {int:id_target}
				AND type = {int:tagger}',
			array(
				'id_target' => $id_target,
				'tagger' => $this->id_tagger,
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
					AND id_target = {int:id_target}
					AND type = {int:tagger}',
				array(
					'tag_ids' => $tags,
					'id_target' => $id_target,
					'tagger' => $this->id_tagger,
				)
			);
		}
		else
		{
			$db->query('', '
				DELETE
				FROM {db_prefix}tag_relation
				WHERE id_target = {int:id_target}
					AND type = {int:tagger}',
				array(
					'id_target' => $id_target,
					'tagger' => $this->id_tagger,
				)
			);
		}
	}

	function purgeTargetTags($id_target)
	{
		if (empty($id_target))
			return;

		$db = database();

		$db->query('', '
			DELETE
			FROM {db_prefix}tag_relation
			WHERE id_target = {int:current_target}
				AND type = {int:tagger}
				AND times_mentioned < 1',
			array(
				'current_target' => $id_target,
				'tagger' => $this->id_tagger,
			)
		);
	}

	function removeTag($tag_id, $id_target = false)
	{
		$db = database();

		if ($id_target !== false)
		{
			$db->query('', '
				DELETE
				FROM {db_prefix}tag_relation
				WHERE id_term = {int:current_tag}
					AND id_target = {int:current_target}
					AND type = {int:tagger}',
				array(
					'current_tag' => $tag_id,
					'current_target' => $id_target,
					'tagger' => $this->id_tagger,
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

	function dropTagsFromTarget($tags_id, $id_target)
	{
		$db = database();

		$db->query('', '
			UPDATE {db_prefix}tag_relation
			SET times_mentioned = CASE WHEN times_mentioned <= 1 THEN 0 ELSE times_mentioned - 1 END
			WHERE id_term IN ({arra_int:tags})
				AND id_target = {int:current_target}
				AND type = {int:tagger}',
			array(
				'tags' => (array) $tags_id,
				'current_target' => $id_target,
				'tagger' => $this->id_tagger,
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

	function countTaggedTargets($tag_id)
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
				'tagger' => $this->id_tagger,
			)
		);
		list ($count) = $db->fetch_row($request);
		$db->free_result($request);

		return $count;
	}

	function countTag($tag_id)
	{
		$db = database();

		// @todo this should become a column in the tag_terms table, but since it implies also moderation, I'll do it later
		$request = $db->query('', '
			SELECT SUM(times_used)
			FROM {db_prefix}tag_terms as tt
				LEFT JOIN {db_prefix}tag_relation as tr ON (tt.id_term = tr.id_term)
			WHERE tr.id_target = {int:current_tag}',
			array(
				'current_tag' => $tag_id,
			)
		);
		list ($count) = $db->fetch_row($request);
		$db->free_result($request);

		return $count;
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