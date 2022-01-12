<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

$page['tab'] = 'user_activity';
include(PHPWG_ROOT_PATH.'admin/include/user_tabs.inc.php');

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+
$template->set_filename('user_activity', 'user_activity.tpl');
$template->assign('ADMIN_PAGE_TITLE', l10n('User Activity logs'));

// +-----------------------------------------------------------------------+
// |                          sending html code                            |
// +-----------------------------------------------------------------------+
$template->assign(array(
  'PWG_TOKEN' => get_pwg_token(),
  'INHERIT' => $conf['inheritance_by_default'],
  'CACHE_KEYS' => get_admin_client_cache_keys(array('users')),
  ));

$query = '
SELECT 
    performed_by, 
    COUNT(*) as counter 
  FROM '.ACTIVITY_TABLE.'
  group by performed_by;';

$nb_lines_for_user = query2array($query, 'performed_by', 'counter');

if (count($nb_lines_for_user) > 0)
{
  $query = '
  SELECT 
      id, 
      username 
    FROM piwigo_users 
    WHERE id IN ('.implode(',', array_keys($nb_lines_for_user)).');';
}

$username_of = query2array($query, 'id', 'username');

$filterable_users = array();

foreach ($nb_lines_for_user as $id => $nb_line) {
  array_push(
    $filterable_users, 
    array(
      'id' => $id,
      'username' => isset($username_of[$id]) ? $username_of[$id] : 'user#'.$id,
      'nb_lines' => $nb_line,
    )
  );
}


$template->assign('ulist', $filterable_users);

$template->assign_var_from_handle('ADMIN_CONTENT', 'user_activity');

?>