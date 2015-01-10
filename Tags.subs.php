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