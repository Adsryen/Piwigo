<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (!defined('PHPWG_ROOT_PATH'))
{
  die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'admin/include/check_integrity.class.php');
include_once(PHPWG_ROOT_PATH.'admin/include/c13y_internal.class.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

if (isset($_GET['action']) and 'hide_newsletter_subscription' == $_GET['action'])
{
  userprefs_update_param('show_newsletter_subscription', 'false');
  exit();
}

include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');

$my_base_url = get_root_url().'admin.php?page=';

$tabsheet = new tabsheet();
$tabsheet->set_id('admin_home');
$tabsheet->select('');
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

if (isset($page['nb_pending_comments']))
{
  $message = l10n('User comments').' <i class="icon-chat"></i> ';
  $message.= '<a href="'.$link_start.'comments">';
  $message.= l10n('%d waiting for validation', $page['nb_pending_comments']);
  $message.= ' <i class="icon-right"></i></a>';
  
  $page['messages'][] = $message;
}

// any orphan photo?
$nb_orphans = $page['nb_orphans']; // already calculated in admin.php

if ($page['nb_photos_total'] >= 100000) // but has not been calculated on a big gallery, so force it now
{
  $nb_orphans = count_orphans();
}

if ($nb_orphans > 0)
{
  $orphans_url = PHPWG_ROOT_PATH.'admin.php?page=batch_manager&amp;filter=prefilter-no_album';

  $message = '<a href="'.$orphans_url.'"><i class="icon-heart-broken"></i>';
  $message.= l10n('Orphans').'</a>';
  $message.= '<span class="adminMenubarCounter">'.$nb_orphans.'</span>';

  $page['warnings'][] = $message;
}

// locked album ?
$query = '
SELECT COUNT(*)
  FROM '.CATEGORIES_TABLE.'
  WHERE visible =\'false\'
;';
list($locked_album) = pwg_db_fetch_row(pwg_query($query));
if ($locked_album > 0)
{
  $locked_album_url = PHPWG_ROOT_PATH.'admin.php?page=cat_options&section=visible';

  $message = '<a href="'.$locked_album_url.'"><i class="icon-cone"></i>';
  $message.= l10n('Locked album').'</a>';
  $message.= '<span class="adminMenubarCounter">'.$locked_album.'</span>';

  $page['warnings'][] = $message;
}

fs_quick_check();

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(array('intro' => 'intro.tpl'));

if ($conf['show_newsletter_subscription'] and userprefs_get_param('show_newsletter_subscription', true)) {
    $query = '
  SELECT registration_date 
    FROM '.USER_INFOS_TABLE.'
    WHERE registration_date IS NOT NULL  
    ORDER BY user_id ASC
    LIMIT 1
  ;';
    list($register_date) = pwg_db_fetch_row(pwg_query($query));

    $query = '
  SELECT COUNT(*)
    FROM '.CATEGORIES_TABLE.'
  ;';
    list($nb_cats) = pwg_db_fetch_row(pwg_query($query));

    $query = '
  SELECT COUNT(*)
    FROM '.IMAGES_TABLE.'
  ;';
    list($nb_images) = pwg_db_fetch_row(pwg_query($query));

    include_once(PHPWG_ROOT_PATH.'include/mdetect.php');
    $uagent_obj = new uagent_info();
    // To see the newsletter promote, the account must have 2 weeks ancient, 3 albums created and 30 photos uploaded

    if (!$uagent_obj->DetectIos() and strtotime($register_date) < strtotime('2 weeks ago') and $nb_cats >= 3 and $nb_images >= 30){
      $template->assign(
      array(
        'EMAIL' => $user['email'],
        'SUBSCRIBE_BASE_URL' => get_newsletter_subscribe_base_url($user['language']),
        'OLD_NEWSLETTERS_URL' => get_old_newsletters_base_url($user['language']),
        )
      );
    }

}


$stats = get_pwg_general_statitics();

$du_decimals = 1;
$du_gb = $stats['disk_usage']/(1024*1024);
if ($du_gb > 100)
{
  $du_decimals = 0;
}

$template->assign(
  array(
    'NB_PHOTOS' => $stats['nb_photos'],
    'NB_ALBUMS' => $stats['nb_categories'],
    'NB_TAGS' => $stats['nb_tags'],
    'NB_IMAGE_TAG' => $stats['nb_image_tag'],
    'NB_USERS' => $stats['nb_users'],
    'NB_GROUPS' => $stats['nb_groups'],
    'NB_RATES' => $stats['nb_rates'],
    'NB_VIEWS' => number_format_human_readable($stats['nb_views']),
    'NB_PLUGINS' => count($pwg_loaded_plugins),
    'STORAGE_USED' => str_replace(' ', '&nbsp;', l10n('%sGB', number_format($du_gb, $du_decimals))),
    'U_QUICK_SYNC' => PHPWG_ROOT_PATH.'admin.php?page=site_update&amp;site=1&amp;quick_sync=1&amp;pwg_token='.get_pwg_token(),
    'CHECK_FOR_UPDATES' => $conf['dashboard_check_for_updates'],
    )
  );

if ($conf['activate_comments'])
{
  $query = '
SELECT COUNT(*)
  FROM '.COMMENTS_TABLE.'
;';
  list($nb_comments) = pwg_db_fetch_row(pwg_query($query));
  $template->assign('NB_COMMENTS', $nb_comments);
} else {
  $template->assign('NB_COMMENTS', 0);
}

if ($conf['show_piwigo_latest_news'])
{
  $latest_news = get_piwigo_news();

  if (isset($latest_news['id']) and $latest_news['posted_on'] > time()-60*60*24*30)
  {
    $page['messages'][] = sprintf(
      '%s <a href="%s" title="%s" target="_blank"><i class="icon-bell"></i> %s</a>',
      l10n('Latest Piwigo news'),
      $latest_news['url'],
      time_since($latest_news['posted_on'], 'year').' ('.$latest_news['posted'].')',
      $latest_news['subject']
    );
  }
}

trigger_notify('loc_end_intro');

// +-----------------------------------------------------------------------+
// |                           get activity data                           |
// +-----------------------------------------------------------------------+

$nb_weeks = $conf['dashboard_activity_nb_weeks'];

//Count mondays
$mondays = 0;
//Get mondays number for the chart legend
$week_number = array();
//Array for sorting days in circle size
$temp_data = array();

$activity_last_weeks = array();
$date = new DateTime();

//Get data from $nb_weeks last weeks
while ($mondays < $nb_weeks)
{
  if ($date->format('D') == 'Mon')
  {
    $week_number[] = $date->format('W');
    $mondays += 1;
  }

  $date->sub(new DateInterval('P1D'));
}

$week_number = array_reverse($week_number);
$date_string = $date->format('Y-m-d');

if (!isset($_SESSION['cache_activity_last_weeks']) or $_SESSION['cache_activity_last_weeks']['calculated_on'] < strtotime('5 minutes ago'))
{
  $start_time = get_moment();

  $query = '
  SELECT
      DATE_FORMAT(occured_on , \'%Y-%m-%d\') AS activity_day,
      object,
      action,
      COUNT(*) AS activity_counter
    FROM `'.ACTIVITY_TABLE.'`
    WHERE occured_on >= \''.$date_string.'\'
    GROUP BY activity_day, object, action
  ;';
  $activity_actions = query2array($query);

  foreach ($activity_actions as $action)
  {
    // set the time to 12:00 (midday) so that it doesn't goes to previous/next day due to timezone offset
    $day_date = new DateTime($action['activity_day'].' 12:00:00');

    $week = 0;
    for ($i=0; $i < $nb_weeks; $i++)
    {
      if ($week_number[$i] == $day_date->format('W'))
      {
        $week = $i;
      }
    }
    $day_nb = $day_date->format('N');

    @$activity_last_weeks[$week][$day_nb]['details'][ucfirst($action['object'])][ucfirst($action['action'])] = $action['activity_counter'];
    @$activity_last_weeks[$week][$day_nb]['number'] += $action['activity_counter'];
    @$activity_last_weeks[$week][$day_nb]['date'] = format_date($day_date->getTimestamp());
  }

  $logger->debug('[admin/intro::'.__LINE__.'] recent activity calculated in '.get_elapsed_time($start_time, get_moment()));

  $_SESSION['cache_activity_last_weeks'] = array(
    'calculated_on' => time(),
    'data' => $activity_last_weeks,
  );
}

$activity_last_weeks = $_SESSION['cache_activity_last_weeks']['data'];


foreach($activity_last_weeks as $week => $i) 
{
  foreach($i as $day => $j) 
  {
    $details = $j['details'];
    ksort($details);
    $activity_last_weeks[$week][$day]['details'] = $details;
    if ($j['number'] > 0) 
    {
      $temp_data[] = array('x' => $j['number'], 'd'=>$day, 'w'=>$week); 
    }
  }
}

// Algorithm to sort days in circle size :
//  * Get the difference between sorted numbers of activity per day (only not null numbers)
//  * Split days max $circle_sizes time on the biggest difference (but not below 120%)
//  * Set the sizes according to the groups created

//Function to sort days by number of activity
function cmp_day($a, $b)
{
  if ($a['x'] == $b['x']) 
  {
    return 0;
  }
  return ($a['x'] < $b['x']) ? -1 : 1;
}

usort($temp_data, 'cmp_day');

//Get the percent difference
$diff_x = array();

for ($i=1; $i < count($temp_data); $i++) 
{ 
  $diff_x[] = $temp_data[$i]['x']/$temp_data[$i-1]['x']*100;
}

$split = 0;
//Split (split represented by -1)
if (count($diff_x) > 0) 
{
  while (max($diff_x) > 120) 
  {
    $diff_x[array_search(max($diff_x), $diff_x)] = -1;
    $split++;
  }
}

//Fill empty chart data for the template
$chart_data = array();
for ($i=0; $i < $nb_weeks; $i++) 
{ 
  for ($j=1; $j <= 7; $j++) 
  { 
    $chart_data[$i][$j] = 0;
  }
}

$size = 1;

if (isset($temp_data[0]))
{
  $chart_data[$temp_data[0]['w']][$temp_data[0]['d']] = $size;
}

//Set sizes in chart data
for ($i=1; $i < count($temp_data); $i++) 
{ 
  if ($diff_x[$i-1] == -1) 
  {
    $size++;
  }
  $chart_data[$temp_data[$i]['w']][$temp_data[$i]['d']] = $size;
}

//Assign data for the template
$template->assign('ACTIVITY_WEEK_NUMBER',$week_number);
$template->assign('ACTIVITY_LAST_WEEKS', $activity_last_weeks);
$template->assign('ACTIVITY_CHART_DATA',$chart_data);
$template->assign('ACTIVITY_CHART_NUMBER_SIZES',$size);

$day_labels = array();
for ($i=0; $i<=6; $i++)
{
  // first 3 letters of day name
  $day_labels[] = mb_substr($lang['day'][($i+1)%7], 0, 3);
}
$template->assign('DAY_LABELS', $day_labels);

// +-----------------------------------------------------------------------+
// |                           get storage data                            |
// +-----------------------------------------------------------------------+

$video_format = array('webm','webmv','ogg','ogv','mp4','m4v', 'mov');
$data_storage = array();

//Select files in Image_Table
$query = '
SELECT
  COUNT(*) AS ext_counter,
   SUBSTRING_INDEX(path,".",-1) AS ext,
   SUM(filesize) AS filesize
  FROM `'.IMAGES_TABLE.'`
  GROUP BY ext
;';

$file_extensions = query2array($query, 'ext');

foreach ($file_extensions as $ext => $ext_details)
{
  $type = null;
  if (in_array(strtolower($ext), $conf['picture_ext']))
  {
    $type = 'Photos';
  }
  elseif (in_array(strtolower($ext), $video_format))
  {
    $type = 'Videos';
  }
  else
  {
    $type = 'Other';
  }

  @$data_storage[$type]['total']['filesize'] += $ext_details['filesize'];
  @$data_storage[$type]['total']['nb_files'] += $ext_details['ext_counter'];

  @$data_storage[$type]['details'][strtoupper($ext)] = array(
    'filesize' => $ext_details['filesize'],
    'nb_files' => $ext_details['ext_counter'],
  );
}

//Select files from format table
$query = '
SELECT
    COUNT(*) AS ext_counter,
    ext,
    SUM(filesize) AS filesize
  FROM `'.IMAGE_FORMAT_TABLE.'`
  GROUP BY ext
;';

$file_extensions = query2array($query, 'ext');
foreach ($file_extensions as $ext => $ext_details)
{
  $type = 'Formats';

  @$data_storage[$type]['total']['filesize'] += $ext_details['filesize'];
  @$data_storage[$type]['total']['nb_files'] += $ext_details['ext_counter'];

  @$data_storage[$type]['details'][strtoupper($ext)] = array(
    'filesize' => $ext_details['filesize'],
    'nb_files' => $ext_details['ext_counter'],
  );
}

// Add cache size if requested and known.
if ($conf['add_cache_to_storage_chart'] && isset($conf['cache_sizes']))
{
  $cache_sizes = unserialize($conf['cache_sizes']);
  if (isset($cache_sizes))
  {
    if (isset($cache_sizes[0]) && isset($cache_sizes[0]['value']))
    {
      @$data_storage['Cache']['total']['filesize'] = $cache_sizes[0]['value']/1024;
    }
  }
}

//Calculate total storage
$total_storage = 0;
foreach ($data_storage as $value) 
{
  $total_storage += $value['total']['filesize'];
}

//Pass data to HTML
$template->assign('STORAGE_TOTAL',$total_storage);
$template->assign('STORAGE_CHART_DATA',$data_storage);

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'intro');

// Check integrity
$c13y = new check_integrity();
// add internal checks
new c13y_internal();
// check and display
$c13y->check();
$c13y->display();

?>
