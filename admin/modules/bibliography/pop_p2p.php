<?php
/**
 * @Created by          : Heru Subekti (heroe_soebekti@yahoo.co.id)
 * @Date                : 2020-01-23 08:01
 * @File name           : pop_chart.php
 */

use SLiMS\Url;
/* Detail P2P result Pop Windows */

// key to authenticate
define('INDEX_AUTH', '1');
// key to get full database access
define('DB_ACCESS', 'fa');

// main system configuration
require '../../../sysconfig.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';

do_checkIP('smc');
do_checkIP('smc-bibliography');

// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
require LIB . 'modsxmlsenayan.inc.php';

// privileges checking
$can_write = utility::havePrivilege('bibliography', 'w');
if (!$can_write) {
  die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}

function cleanUrl($url)
{
  $Url = Url::parse($url);
  
  return $Url->getScheme() . '://' . // http or https
         // localhost, ip, or domain       
         $Url->getDomain() .
         // http standart port (80 & 443) or non http standart port
         (!is_null($Url->getPort()) ? ':' . $Url->getPort() : '') .
         // path
         (substr($Url->getPath(), -1) == '/' ? $Url->getPath() . '' : $Url->getPath() . '/');
}

// get servers
$server_q = $dbs->query('SELECT name, uri FROM mst_servers WHERE server_type = 1 ORDER BY name ASC');
while ($server = $server_q->fetch_assoc()) {
  if (Url::isValid($server['uri'])) $sysconf['p2pserver'][] = array('uri' => $server['uri'], 'name' => $server['name']);
}

$serverId = (int)$_GET['uri'];
if (!isset($sysconf['p2pserver'][$serverId])) die('<div id="alert alert-danger">' . __('P2P Server URL is not exists!') . '</div>');
$url = cleanUrl($sysconf['p2pserver'][$serverId]['uri']);

$detail_uri = $url . "/index.php?p=show_detail&inXML=true&id=" . ((int)$_GET['biblioID']);
// parse XML
$data = modsXMLsenayan($detail_uri, 'uri');

ob_start();

$rec_d = $data['records'][0];

?>
<table class="table">
<div class="btn-group pull-right">
	<table class="table table-condensed">
	<?php
	//print_r($rec_d);
	echo '<tr><td colspan="2"><strong>'.$rec_d['title'].'</strong></td></tr>';
	$author_str = '';
	foreach ($rec_d['authors'] as $key => $val) {
		$author_str .= $val['name'].' - <small>'.$val['author_type'].';</small>'."<br/>";
	}
	echo '<tr><td width="20%">'.__('Authors').'</td><td>'.$author_str.'</td></tr>';
	echo '<tr><td width="20%">'.__('Notes').'</td><td>'.$rec_d['notes'].'</td></tr>';
	echo '<tr><td width="20%">'.__('Edition').'</td><td>'.$rec_d['edition'].'</td></tr>';
	echo '<tr><td width="20%">'.__('ISBN/ISSN').'</td><td>'.$rec_d['isbn_issn'].'</td></tr>';
	echo '<tr><td width="20%">'.__('Publisher').'</td><td>'.$rec_d['publish_place'].': '.$rec_d['publisher'].'; '.$rec_d['publish_year'].'</td></tr>';
	echo '<tr><td width="20%">'.__('Collation').'</td><td>'.$rec_d['collation'].'</td></tr>';
	echo '<tr><td width="20%">'.__('Language').'</td><td>'.$rec_d['language']['name'].'</td></tr>';
	echo '<tr><td width="20%">'.__('GMD').'</td><td>'.$rec_d['gmd'].'</td></tr>';
	echo '<tr><td width="20%">'.__('Classification').'</td><td>'.$rec_d['classification'].'</td></tr>';
	echo '<tr><td width="20%">'.__('Call Number').'</td><td>'.$rec_d['call_number'].'</td></tr>';
	$topic_str = '';
	if(isset($rec_d['subjects'])){
		foreach ($rec_d['subjects'] as $key => $val) {
			$topic_str .= $val['term'].' -- ';
		}
	}
	echo '<tr><td width="20%">'.__('Topics').'</td><td>'.substr($topic_str,0,-4).'</td></tr>';
	echo '</table>';
	echo '<a class="btn btn-sm btn-info" target="_BLANK" href="'.$detail_uri.'/index.php?p=show_detail&id='.((int)$_GET['biblioID']).'">Original URi</a></div>'."<br/>";

/* main content end */
$content = ob_get_clean();
// include the page template
require SB.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';