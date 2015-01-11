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

class Tags_Info
{
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

	function searchTags($fragment)
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