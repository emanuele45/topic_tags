<?php
/**
 * Colored Names
 *
 * @author  emanuele
 * @license BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 0.0.3
 */

global $hooks, $mod_name;
$hooks = array(
	// Tags
	array(
		'integrate_action_post_after',
		'Tags_Integrate::post_after',
		'SOURCEDIR/Tags.integrate.php',
	),
	array(
		'integrate_create_topic',
		'Tags_Integrate::create_topic',
		'SOURCEDIR/Tags.integrate.php',
	),
	// @todo verify which one is the correct one
	array(
		'integrate_modify_post',
		'Tags_Integrate::modify_post2',
		'SOURCEDIR/Tags.integrate.php',
	),
	array(
		'integrate_modify_post',
		'Tags_Integrate::modify_post',
		'SOURCEDIR/Tags.integrate.php',
	),
	array(
		'integrate_mark_read_button',
		'Tags_Integrate::mark_read_button',
		'SOURCEDIR/Tags.integrate.php'
	),
	array(
		'integrate_display_topic',
		'Tags_Integrate::display_topic',
		'SOURCEDIR/Tags.integrate.php'
	),
	array(
		'integrate_routine_maintenance',
		'Tags_Integrate::routine_maintenance',
		'SOURCEDIR/Tags.integrate.php'
	),
	array(
		'integrate_sa_manage_maintenance',
		'Tags_Integrate::manage_maintenance',
		'SOURCEDIR/Tags.integrate.php',
	),
	array(
		'integrate_load_permissions',
		'Tags_Integrate::load_permissions',
		'SOURCEDIR/Tags.integrate.php',
	),
	array(
		'integrate_load_illegal_guest_permissions',
		'Tags_Integrate::load_illegal_guest_permissions',
		'SOURCEDIR/Tags.integrate.php',
	),
	array(
		'integrate_create_post',
		'Tags_Integrate::create_post',
		'SOURCEDIR/Tags.integrate.php',
	),
	array(
		'integrate_prepare_display_context',
		'Tags_Integrate::prepare_display_context',
		'SOURCEDIR/Tags.integrate.php',
	),
	array(
		'integrate_remove_message',
		'Tags_Integrate::remove_message',
		'SOURCEDIR/Tags.integrate.php',
	),
);
$mod_name = 'Colored Names';

// ---------------------------------------------------------------------------------------------------------------------
define('ELK_INTEGRATION_SETTINGS', serialize(array(
	'integrate_menu_buttons' => 'install_menu_button',)));

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('ELK'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('ELK'))
	exit('<b>Error:</b> Cannot install - please verify you put this in the same place as ElkArte\'s index.php.');

if (ELK == 'SSI')
{
	// Let's start the main job
	install_mod();
	// and then let's throw out the template! :P
	obExit(null, null, true);
}
else
{
	setup_hooks();
}

function install_mod ()
{
	global $context, $mod_name;

	$context['mod_name'] = $mod_name;
	$context['sub_template'] = 'install_script';
	$context['page_title_html_safe'] = 'Install script of the mod: ' . $mod_name;
	if (isset($_GET['action']))
		$context['uninstalling'] = $_GET['action'] == 'uninstall' ? true : false;
	$context['html_headers'] .= '
	<style type="text/css">
    .buttonlist ul {
      margin:0 auto;
			display:table;
		}
	</style>';

	// Sorry, only logged in admins...
	isAllowedTo('admin_forum');

	if (isset($context['uninstalling']))
		setup_hooks();
}

function setup_hooks ()
{
	global $context, $hooks, $smcFunc;

	$integration_function = empty($context['uninstalling']) ? 'add_integration_function' : 'remove_integration_function';
	foreach ($hooks as $hook)
		$integration_function($hook[0], $hook[1], $hook[2]);

	if (empty($context['uninstalling']))
	{
		updateSettings(array('prefix_style' => '<span class="topicprefix">{prefix_link}</span>&nbsp;'));

		$db_table = db_table();

		$db_table->db_add_column(
			'{db_prefix}members',
			array(
				'name' => 'plain_real_name',
				'type' => 'varchar',
				'size' => 255,
				'default' => ''
			)
		);
		$db_table->db_add_column(
			'{db_prefix}members',
			array(
				'name' => 'colored_names',
				'type' => 'TEXT'
			)
		);
	}
	else
	{
		$db = database();
		$db->query('', '
			UPDATE {db_prefix}members
			SET real_name = CASE WHEN plain_real_name != {string:empty}
					THEN plain_real_name
					ELSE real_name
					END',
			array(
				'empty' => '',
			)
		);
	}


	$context['installation_done'] = true;
}

function install_menu_button (&$buttons)
{
	global $boardurl, $context;

	$context['sub_template'] = 'install_script';
	$context['current_action'] = 'install';

	$buttons['install'] = array(
		'title' => 'Installation script',
		'show' => allowedTo('admin_forum'),
		'href' => $boardurl . '/install.php',
		'active_button' => true,
		'sub_buttons' => array(
		),
	);
}

function template_install_script ()
{
	global $boardurl, $context;

	echo '
	<div class="tborder login"">
		<div class="cat_bar">
			<h3 class="catbg">
				Welcome to the install script of the mod: ' . $context['mod_name'] . '
			</h3>
		</div>
		<span class="upperframe"><span></span></span>
		<div class="roundframe centertext">';
	if (!isset($context['installation_done']))
		echo '
			<strong>Please select the action you want to perform:</strong>
			<div class="buttonlist">
				<ul>
					<li>
						<a class="active" href="' . $boardurl . '/install.php?action=install">
							<span>Install</span>
						</a>
					</li>
					<li>
						<a class="active" href="' . $boardurl . '/install.php?action=uninstall">
							<span>Uninstall</span>
						</a>
					</li>
				</ul>
			</div>';
	else
		echo '<strong>Database adaptation successful!</strong>';

	echo '
		</div>
		<span class="lowerframe"><span></span></span>
	</div>';
}
?>