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

class Topics_Tagger implements Tagger
{
	public function getTypeId()
	{
		return 1;
	}

	public function canAccess($target)
	{
		global $modSettings;

		require_once(SUBSDIR . '/Topic.subs.php');

		if ($modSettings['postmod_active'])
		{
			$tmp = $modSettings['postmod_active'];
			$modSettings['postmod_active'] = !allowedTo('approve_posts');
		}

		// A bit more than necessary, but topicsList uses query_see_board
		$details = topicsList(array($target));

		if (isset($tmp))
			$modSettings['postmod_active'] = $tmp;

		return !empty($details);
	}
}