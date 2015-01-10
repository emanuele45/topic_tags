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

class ManageTags_Controller extends Action_Controller
{
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
				$this->postHashed($row['body'], $row['id_topic']);
			}
			$db->free_result($request);

			$context['continue_get_data'] .= ';hstart=' . $next_hstart;
			$context['continue_percent'] = round(100 * $next_hstart / $modSettings['totalMessages']);
			return;
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
		list ($max_tags) = $db->fetch_row($request2);
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