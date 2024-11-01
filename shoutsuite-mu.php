<?php
/*
Plugin Name: ShoutSuite MU
Plugin URI: http://sagad.net/
Description: A WordPress MU Plugin to integrate ngeSHOUT and WordPress MU
Version: 1.2
Author: Sanny Gaddafi
Author URI: http://sagad.net
Original created: Dan Zarrella
Original Author URI: http://danzarrella.com
*/
$ts_version = 1.2;
$insource = 'shoutsuite';
if ( ! defined( 'WP_CONTENT_URL' ) )
      define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );

add_action('plugins_loaded', 'shoutbacks_install');
add_action('admin_menu', 'sb_menu');
//add_filter('publish_post', 'decodeShortURLs');
//add_filter('publish_post', 'ts_send_shout');

add_action('future_to_publish','decodeShortURLs');
add_action('new_to_publish','decodeShortURLs');
add_action('draft_to_publish','decodeShortURLs');

add_action('future_to_publish','ts_send_shout');
add_action('new_to_publish','ts_send_shout');
add_action('draft_to_publish','ts_send_shout');

add_filter('the_content', 'addShoutBacks');

add_action('loop_end', 'updateShoutBacks');
add_action('widgets_init', 'shoutsuite_widget_innit');

add_filter('cron_schedules', 'more_reccurences');
if (!wp_next_scheduled('shoutsuite_hourly_hook')) {
	wp_schedule_event( time(), 'hourly', 'shoutsuite_hourly_hook' );
}

if (!wp_next_scheduled('shoutsuite_5mins_hook')) {
	wp_schedule_event( time(), '5mins', 'shoutsuite_5mins_hook' );
}
add_action( 'shoutsuite_5mins_hook', 'shoutsuite_5mins' );

//shoutsuite_5mins();

function shoutsuite_5mins() {
	//echo "here";
	global $wpdb;	
	$table_name = $wpdb->prefix . "shorturls";

	//$delayed = mktime()-600;
	$buff = $wpdb->get_results("SELECT * FROM $table_name WHERE accessed>$delayed");
	foreach ($buff as $line) {
		if (!trim($line->pendekin)) {
				decodeShortURLs($line->postID);
			}
		getShoutBacks($line->postID);
	}
}


function more_reccurences() {
	return array('5mins' => array('interval' => 300, 'display' => 'Every 5 Minutes') );
}


function ts_send_shout($postID) {
	global $wpdb;
	if(!is_numeric($postID)) {
		$postID = $postID->ID;
	}
	$table_name = $wpdb->prefix . "shorturls";
	$line = $wpdb->get_row("select * from $table_name where postID=$postID");
   	if($line->postID==$postID) {
   	    $pendekin = $line->pendekin;
		$post = get_post($postID);
		if(get_option('shoutsuite_send_posts')) { shoutsuite_send(trim($post->post_title).' '.$pendekin); }
	}
}

function shoutsuite_hourly() {
	global $wpdb;
	if(get_option('shoutsuite_favorites')) {
		$favorites = parseShouts('favorites');	
		$table_name = $wpdb->prefix . "ts_favorites";
		addShouts($table_name, $favorites);				
	}
	if(get_option('shoutsuite_mine')) {
		$mine =  parseShouts();
		$table_name = $wpdb->prefix . "ts_mine";
		addShouts($table_name, $mine);		
	}
}	


function updateShoutBacks(){
	global $post, $wpdb;
	$table_name = $wpdb->prefix . "shorturls";
	$postID =$post->ID;
	$now = mktime();
	$line = $wpdb->get_row("select * from $table_name where postID=$postID");
	if($line->postID!=$postID) {
		$results = $wpdb->query("insert into $table_name (postID, accessed) values ($postID, $now)");	
	}else{
		$results = $wpdb->query("update $table_name set accessed=$now where postID = $postID");	
	}
	getShoutBacks($post->ID);
}

//decodeShortURLs($postID);
//getShoutBacks($postID);

function addShoutBacks($content){
if( is_single() ){
	global $wpdb, $post;

	$postID =$post->ID;
	$table_name = $wpdb->prefix . "shoutbacks";
 	$max_tbs = get_option('sb_max');
	if($max_tbs) { $limit = "limit $max_tbs";}
	$buff = $wpdb->get_results("SELECT * FROM $table_name WHERE postID = $postID order by datetime desc $limit");

	if(get_option('shoutbacks_enabled')){
	foreach ($buff as $line){
		$count++;
		$shout = strip_tags(stripslashes($line->shout));
		$author = $line->author;
		$dt = $line->datetime;
		$avatar = $line->avatar;
		$posted = date('m/d/y h:ia', $dt);
		
		if(get_option('shoutsuite_reshoutthis')){
			$rt = urlencode($shout);
			$tt_url = "http://ngeshout.com/home?status=ReShout+@$author:+$rt";
			$rt_this = WP_CONTENT_URL.'/mu-plugins/shoutsuite-mu/rs_this.gif';		  
			$rt_this_button = "<div style='float:right; margin-left:0px;'><a href='$tt_url'><img border='0' src='$rt_this'></a></div>";
		}
		
		$output .="<li style='display: block; height: 50px; margin-bottom: 10px;'>$rt_this_button";
		if(!get_option('sb_noavatars')) $output .="<img width='48' src='$avatar' style='float:left; margin-right:5px;'>";
		$output .="<b><A href='http://ngeshout.com/$author'>$author</a>:</b> $shout <span style='color:#009900;'>$posted</span></li>";
		
	}
	$table_name = $wpdb->prefix . "shorturls";
	$line = $wpdb->get_row("SELECT * FROM $table_name WHERE postID = $postID");
	if($line->pendekin=='') decodeShortURLs($postID);	
	$line = $wpdb->get_row("SELECT shoutthis, count FROM $table_name WHERE postID = $postID");
	$sb_count = $line->count;
	$shoutthis = $line->shoutthis;
	if($count>0){
		$output = "<div id='shoutbacks'><b>$count Total ShoutBacks :</b> (<a href='$shoutthis'>Shout this post</a>) <ul>".$output."</ul></div>";
	}
	else {
		$output = "<div id='shoutbacks'><b>No ShoutBacks  yet.</b> (<a href='$shoutthis'>Be the first to Shout this post</a>)</div>";
	}
	if(get_option('shoutsuite_shoutthis')){
		$fn = 'rs-'.$sb_count.'.png';
		if (file_exists(WP_CONTENT_URL.'/mu-plugins/shoutsuite-mu/'.$fn)){
			$src=WP_CONTENT_URL.'/mu-plugins/shoutsuite-mu/'.$fn;
		}	
		else {
			$src = WP_CONTENT_URL.'/mu-plugins/shoutsuite-mu/rs-gif.php?count='.$sb_count;
		}	
		$float = get_option('shoutsuite_shoutthis_float');	
		if(!$float){ $float = 'left'; }
		$content = "<a href='$shoutthis'><img border='0' style='float:$float; margin-right:5px;' src='$src'></a>".$content;
	}
	}
	return $content . $output;
	}
	else {
		return $content;
	}
}

function addShouts($table_name, $entries){
global $wpdb;
if(count($entries)>0){
foreach($entries as $entry){
		//print_r($entry);
		$shout = $wpdb->escape($entry['shout']);
		$author = $entry['author'];
		$link =  $entry['link'];
		$dt =  $entry['dt'];		
		$avatar =  $entry['avatar'];
		$q = "select * from $table_name where datetime=$dt and shout='$shout' and author='$author' and link='$link' limit 1";
		$jum = $wpdb->query($q);				
		$q = "insert into $table_name ( datetime, shout, author, link, avatar) values ($dt, '$shout', '$author', '$link', '$avatar') ";
		//echo $q."<br/>";
		if ($jum==0) $results = $wpdb->query($q);				
	}
	}
}



function parseAtom($data){
$chunks = split('<entry>', $data);
array_shift($chunks);
//print_r($chunks);
foreach($chunks as $chunk){
	$chunk = nl2br($chunk);
	$chunk = trim(str_replace( "</entry>", "", $chunk));
	$chunk = trim(str_replace( "<br /><br />", "\n", $chunk));
	$chunk = trim(str_replace( "<br />", "\n", $chunk));
	$chunk = trim(str_replace( "\n\n", "\n", $chunk));
	$chunk = trim(str_replace( "><", ">\n<", $chunk));
	$chunk = trim(ereg_replace( "> +<", ">\n<", $chunk));

	$lines = split("\n", $chunk);
//	print_r($lines);
	$author = trim(str_replace(array('<name>', '</name>'), '', $lines[8]));	
	$shout = trim(str_replace(array('<title>', '</title>', '<content type="html">', '</content>'), '', $lines[1]));	
	list($junk, $avatar) = split('href="', $lines[6]);
	$avatar = str_replace('" rel="image" type="image/png"/>', '', $avatar);
	$dt = $lines[3];
	list($date, $time) = split('T', $dt);
	$time = str_replace('Z', '', $time);
	list($hour, $minute, $second) = split(':', $time);
	list($year, $month, $day) = split('-', $date);
	$year = trim(str_replace('<published>', '', $year));
	$dt = mktime($hour, $minute, $second, $month, $day, $year);	
	
	list($junk, $link) = split('href="', $lines[5]);
	$link = str_replace('" rel="alternate" type="text/html"/>', '', $link);

	$ret['shout'] = strip_tags(html_entity_decode($shout));
	$ret['link'] = $link;
	$ret['author'] = $author;
	$ret['avatar'] = $avatar;
	$ret['dt'] = $dt;						
	$return[] = $ret;	
}
return $return;
}


function shoutbacks_install (){
   global $wpdb, $ts_version;
   $table_name = $wpdb->prefix . "shoutbacks";
   if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name){
   		$sql = "CREATE TABLE `".$table_name."` (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  postID int(11) NOT NULL,
  datetime int(11) NOT NULL,
  link varchar(255) NOT NULL,
  avatar varchar(255) NOT NULL,
  shout varchar(150) NOT NULL,
  author varchar(255) NOT NULL ,
   UNIQUE KEY id (id),
   UNIQUE KEY link (link)
);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		//echo "here";
   }
   
   $table_name = $wpdb->prefix . "ts_favorites";
   if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name){
   		$sql = "CREATE TABLE `".$table_name."` (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  datetime int(11) NOT NULL,
  link varchar(255) NOT NULL,
  avatar varchar(255) NOT NULL,
  shout varchar(150) NOT NULL,
  author varchar(255) NOT NULL ,
   UNIQUE KEY id (id),
   UNIQUE KEY link (link)
);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		//echo "here";
   }
   
   $table_name = $wpdb->prefix . "ts_mine";
   if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name){
   		$sql = "CREATE TABLE `".$table_name."` (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  datetime int(11) NOT NULL,
  link varchar(255) NOT NULL,
  avatar varchar(255) NOT NULL,
  shout varchar(150) NOT NULL,
  author varchar(255) NOT NULL ,
   UNIQUE KEY id (id),
   UNIQUE KEY link (link)
);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		//echo "here";
   }
   
   
   $table_name = $wpdb->prefix . "shorturls";
   if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name){
   		$sql = "CREATE TABLE `".$table_name."` (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  postID int(11) NOT NULL,
  count int(11) NOT NULL,
  accessed int(11) NOT NULL,
   shoutthis varchar(255) NOT NULL,
  pendekin varchar(255) NOT NULL,
   UNIQUE KEY id (id),
    UNIQUE KEY postID (postID)
   );";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
   }
	ts_setDefaults();
}
function clearShoutBacks(){
	global $wpdb;
	$table_name = $wpdb->prefix . "shoutbacks";
	$results = $wpdb->query("delete from $table_name");	
	$table_name = $wpdb->prefix . "shorturls";
	$results = $wpdb->query("delete from $table_name");	

}
function getShoutBacks($postID){
	global $wpdb;
	$table_name = $wpdb->prefix . "shorturls";
	$q = "select * from $table_name where postID=$postID";
	$line = $wpdb->get_row("select * from $table_name where postID=$postID");
	if(!$line->postID){
		decodeShortURLs($postID);
		$line = $wpdb->get_row("select * from $table_name where postID=$postID");
	}
	
	$shoutthis = str_replace('http://ngeshout.com/home?status=','',$line->shoutthis);
	$pendekin = $line->pendekin;
//	$turl = 'http://ngeshout.com/search/notice/rss?rpp=100&q=&ors=';
	$turl = 'http://ngeshout.com/search/notice/atom?q=';
	$turl .= urlencode(str_replace('http://','',$pendekin)).'+';
	$table_name = $wpdb->prefix . "shoutbacks";
	/*
	$line = $wpdb->get_row("select datetime from $table_name where postID=$postID order by datetime desc limit 1");
	$last = $line->datetime;
	*/
	$last = get_option('shoutsuite_updated_'.$postID);
	if(!$last){$last = 1; }
	$delay = get_option('shoutsuite_delay');
	
	if(!$delay){ $delay = 300; }
	if( ($last+$delay)>mktime()){ 
//		return false; 
	}
	update_option('shoutsuite_updated_'.$postID, mktime());
	$data = sb_get($turl);
	$entries = parseAtom($data);
	//print_r($entries);
	if(count($entries)>0){
	$own = get_option('shoutsuite_ngeshout_username');
	foreach($entries as $entry){
		//print_r($entry);
		$shout = $wpdb->escape($entry['shout']);
		$author = $entry['author'];
		$link =  $entry['link'];
		$add = true;
		if(!strstr($shout, $pendekin)) $add = false;
		if(strtolower($author)==strtolower($own)) $add = false;

		if($add){
			$dt =  $entry['dt'];		
			$avatar =  $entry['avatar'];
		
			$q = "select * from $table_name where postID=$postID and datetime=$dt and shout='$shout' and author='$author' and link='$link' limit 1";
			$jum = $wpdb->query($q);				
			$q = "insert into $table_name (postID, datetime, shout, author, link, avatar) values ($postID, $dt, '$shout', '$author', '$link', '$avatar') ";
			if ($jum==0) $results = $wpdb->query($q);		
		//$count++;
		}
	}
		$q = "select count(id) as c from $table_name where postID=$postID";	
		$line = $wpdb->get_row($q);
		$count = $line->c;
		//print_r($line);
		$table_name = $wpdb->prefix . "shorturls";
		$q = "update $table_name set count=$count where postID=$postID";
		$results = $wpdb->query($q);	
	}
	
	
}

function decodeShortURLs($postID){
	global $wpdb;
	if(!is_numeric($postID)) $postID = $postID->ID;

	$table_name = $wpdb->prefix . "shorturls";
	$line = $wpdb->get_row("select * from $table_name where postID=$postID");
	$url = get_permalink($postID);		
	if($line->postID==$postID){
		//if(trim($line->pendekin) return false;	
		if(!trim($line->pendekin)) $pendekin= pendekin($url);	else $pendekin= $line->pendekin;
		$update =true;
	}else{  
		$pendekin= pendekin($url);
	}
   	
	$from_name = get_option('shoutsuite_ngeshout_username');
	if($from_name) $from = "+from:+@$from_name";
	$post = get_post($postID);
	$title = urlencode($post->post_title);
	$tt_url = "http://ngeshout.com/home?status=$title+$pendekin$from";
	$q = "select * from $table_name where postID=$postID limit 1";
	$jum = $wpdb->query($q);				

	$insert = "INSERT INTO " . $table_name . " (postID, pendekin, shoutthis, accessed) " .
            "VALUES ($postID, '" . $wpdb->escape($pendekin) . "',
								'".$wpdb->escape($tt_url)."'	,
								".mktime()."
								)";

	$update = "UPDATE " . $table_name . " set 
								pendekin = '" . $wpdb->escape($pendekin) . "',
								shoutthis = '".$wpdb->escape($tt_url)."',
								accessed = ".mktime()."
								where postID = $postID limit 1
								";

  if ($jum==0) $results = $wpdb->query( $insert ); else $results = $wpdb->query( $update ); 

}



function sb_menu(){
		add_options_page('ShoutSuite Options', 'ShoutSuite', 8, __FILE__, 'sb_options');

}


function ts_setDefaults(){
global $insource;
	add_option('shoutbacks_enabled', 1);
	add_option('shoutsuite_reshoutthis', 1);
	add_option('shoutsuite_mine', 1);
	add_option('shoutsuite_favorites', 1);
	add_option('shoutsuite_recently', 1);
	add_option('shoutsuite_most', 1);
	add_option('shoutsuite_source', $insource);
}

function sb_options(){
global $ts_version, $insource;
if($_POST){
$page_options = split(',', $_POST['page_options']);
foreach($page_options as $opt){
	//print_r($_POST);
	update_option('sb_max', $_POST['sb_max']);
	update_option('shoutsuite_ngeshout_username', $_POST['shoutsuite_ngeshout_username']);
	update_option('shoutsuite_ngeshout_password', $_POST['shoutsuite_ngeshout_password']);
	update_option('shoutsuite_shoutthis_float', $_POST['shoutsuite_shoutthis_float']);
	update_option('shoutsuite_shoutthis_position', $_POST['shoutsuite_shoutthis_position']);
	update_option('shoutsuite_prefix', $_POST['shoutsuite_prefix']);
	//[sb_noavatars
	//
	
	if($_POST['sb_noavatars']){
		update_option('sb_noavatars', 1);
	}
	else {
		update_option('sb_noavatars', 0);
	}
		
	
	//shoutbacks_enabled
		if($_POST['shoutbacks_enabled']){
		update_option('shoutbacks_enabled', 1);
	}
	else {
		update_option('shoutbacks_enabled', 0);
	}
	
		//shoutbacks_enabled
		if($_POST['shoutsuite_send_posts']){
		update_option('shoutsuite_send_posts', 1);
	}
	else {
		update_option('shoutsuite_send_posts', 0);
	}
		//shoutsuite_shoutthis
		if($_POST['shoutsuite_shoutthis']){
		update_option('shoutsuite_shoutthis', 1);
	}
	else {
		update_option('shoutsuite_shoutthis', 0);
	}
	
		//shoutsuite_shoutthis
		if($_POST['shoutsuite_source']){
		update_option('shoutsuite_source', $_POST['shoutsuite_source']);
	}
	else {
		update_option('shoutsuite_source', $insource);
	}
	
				//shoutsuite_mine
		if($_POST['shoutsuite_reshoutthis']){
		update_option('shoutsuite_reshoutthis', 1);		
	}
	else {
		update_option('shoutsuite_reshoutthis', 0);
	}
	
		//shoutsuite_mine
		if($_POST['shoutsuite_mine']){
		update_option('shoutsuite_mine', 1);		
	}
	else {
		update_option('shoutsuite_mine', 0);
	}		
	
		//shoutsuite_favorites
		if($_POST['shoutsuite_favorites']){
		update_option('shoutsuite_favorites', 1);
	}
	else {
		update_option('shoutsuite_favorites', 0);
	}
	
		if($_POST['shoutsuite_most']){
		update_option('shoutsuite_most', 1);
	}
	else {
		update_option('shoutsuite_most', 0);
	}
	
		if($_POST['shoutsuite_recently']){
		update_option('shoutsuite_recently', 1);
	}
	else {
		update_option('shoutsuite_recently', 0);
	}
	
	
	if(($_POST['shoutsuite_favorites']) or ($_POST['shoutsuite_mine']) ){
		shoutsuite_hourly();
	}
	
	if($_POST['shoutsuite_clear']){
		clearShoutBacks();
	}
}
}
//shoutsuite_mine
?>
<div class="wrap">
<h2>ShoutSuite MU <?php echo $ts_version;?></h2>
<form method="post" action="">
<?php wp_nonce_field('update-options'); ?>
<table class="form-table">
	

<tr valign="top">
<th scope="row">Display ShoutBacks ?</th>
<td><INPUT TYPE="CHECKBOX" NAME="shoutbacks_enabled" <?php if(get_option('shoutbacks_enabled')){ echo "checked"; } ?>>
</td>
</tr>

<tr valign="top">
<th scope="row">Display Shout-This button?</th>
<td><INPUT TYPE="CHECKBOX" NAME="shoutsuite_shoutthis" <?php if(get_option('shoutsuite_shoutthis')){ echo "checked"; } ?>>
</td>
</tr>

<tr valign="top">
<th scope="row">Float Shout-this Button:</th>
<td>
<select name="shoutsuite_shoutthis_float">
<option value='left' <?php if(get_option('shoutsuite_shoutthis_float')=='left'){ echo 'selected'; } ?>>Left</option>
<option value='right' <?php if(get_option('shoutsuite_shoutthis_float')=='right'){ echo 'selected'; } ?>>Right</option>
</select>
</td>
</tr>


<tr valign="top">
<th scope="row">Display ReShout-This Buttons?</th>
<td><INPUT TYPE="CHECKBOX" NAME="shoutsuite_reshoutthis" <?php if(get_option('shoutsuite_reshoutthis')){ echo "checked"; } ?>>
</td>
</tr>

<tr valign="top">
<th scope="row">Enable Recently Shouted widget?</th>
<td><INPUT TYPE="CHECKBOX" NAME="shoutsuite_recently" <?php if(get_option('shoutsuite_recently')){ echo "checked"; } ?>>
</td>
</tr>

<tr valign="top">
<th scope="row">Enable Most Shouted widget?</th>
<td><INPUT TYPE="CHECKBOX" NAME="shoutsuite_most" <?php if(get_option('shoutsuite_most')){ echo "checked"; } ?>>
</td>
</tr>

<tr valign="top">
<th scope="row">Enable Favorite Shouts widget?</th>
<td><INPUT TYPE="CHECKBOX" NAME="shoutsuite_favorites" <?php if(get_option('shoutsuite_favorites')){ echo "checked"; } ?>>
</td>
</tr>

<tr valign="top">
<th scope="row">Enable My Shouts widget?</th>
<td><INPUT TYPE="CHECKBOX" NAME="shoutsuite_mine" <?php if(get_option('shoutsuite_mine')){ echo "checked"; } ?>>
</td>
</tr>

<tr valign="top">
<th scope="row">Maximum number of ShoutBacks  to display:</th>
<td><input type="text" size="3" maxlength="3" name="sb_max" value="<?php echo get_option('sb_max'); ?>" /></td>
</tr>

<tr valign="top">
<th scope="row">Turn off Avatars?</th>
<td><INPUT TYPE="CHECKBOX" NAME="sb_noavatars" <?php if(get_option('sb_noavatars')){ echo "checked"; } ?>>
</td>
</tr>

<tr valign="top">
<th scope="row">Send a Shout when you publish a post?</th>
<td><INPUT TYPE="CHECKBOX" NAME="shoutsuite_send_posts" <?php if(get_option('shoutsuite_send_posts')){ echo "checked"; } ?>>
</td>
</tr>

<tr valign="top">
<th scope="row">Source name?</th>
<td><INPUT TYPE="text" NAME="shoutsuite_source" maxlength="32"  value='<?php echo get_option('shoutsuite_source'); ?>' >
</td>
</tr>

<tr valign="top">
<th scope="row">PreFix to add to AutoShouts?</th>
<td><INPUT TYPE="text" NAME="shoutsuite_prefix"  maxlength="25"  value='<?php echo get_option('shoutsuite_prefix'); ?>' >
</td>
</tr>

<tr valign="top">
<th scope="row"><a href="http://ngeshout.com" target="_blank">ngeSHOUT</a> Nickname:</th>
<td><input type="text" maxlength="64"  size="20" name="shoutsuite_ngeshout_username" value="<?php echo get_option('shoutsuite_ngeshout_username'); ?>" /></td>
</tr>

<tr valign="top">
<th scope="row"><a href="http://ngeshout.com" target="_blank">ngeSHOUT</a> Password:</th>
<td><input type="password" size="20" name="shoutsuite_ngeshout_password" value="<?php echo get_option('shoutsuite_ngeshout_password'); ?>" /></td>
</tr>

<tr valign="top">
<th scope="row"><span style="color:#FF0000">Clear ShoutBacks  DB?</span></th>
<td><INPUT TYPE="CHECKBOX" NAME="shoutsuite_clear" >
</td>
</tr>

</table>
<p class="submit">
<input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
</p>
</form>
</div>

<?php
}
function pendekin($url){
	$myFile = 'http://pendek.in/?url='.urlencode($url);
	$handle = fopen($myFile, 'r');
	$note = fread($handle, 255);
	fclose($handle);
	return $note;
}

function sb_get($file){
	if(function_exists('curl_init')){ 
    $curl_handle = curl_init();
    curl_setopt($curl_handle,CURLOPT_URL,"$file");
    curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,2);
	curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
	if(stristr($file, 'ngeshout.com')){		
		$password = get_option('shoutsuite_ngeshout_password');
		if($password){
			$username = get_option('shoutsuite_ngeshout_username');
			curl_setopt($curl_handle,CURLOPT_USERPWD,"$username:$password");
		}
	}
    $data = curl_exec($curl_handle);
    $error = curl_error($curl_handle);
    curl_close($curl_handle);
	if(empty($error)){
		return $data;
	  }
	  else {
	  	return $error;	
	  }
	 }
	 else {
	 	return sb_get1($url);
	 }
}

function sb_get1($url)
{

   // get the host name and url path
   $parsedUrl = parse_url($url);
   $host = $parsedUrl['host'];
   if (isset($parsedUrl['path'])){
      $path = $parsedUrl['path'];
   } else {
      // the url is pointing to the host like http://www.mysite.com
      $path = '/';
   }

   if (isset($parsedUrl['query'])){
      $path .= '?' . $parsedUrl['query'];
   }

   if (isset($parsedUrl['port'])){
      $port = $parsedUrl['port'];
   } else {
      // most sites use port 80
      $port = '80';
	   }

   $timeout = 10;
   $response = '';

   // connect to the remote server
   $fp = @fsockopen($host, '80', $errno, $errstr, $timeout );

   if( !$fp ){
    //  echo "Cannot retrieve $url";
   } else {
      // send the necessary headers to get the file
      fputs($fp, "GET $path HTTP/1.0\r\n" .
                 "Host: $host\r\n" .
                 "User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.0.3) Gecko/20060426 Firefox/1.5.0.3\r\n" .
                 "Accept: */*\r\n" .
                 "Accept-Language: en-us,en;q=0.5\r\n" .
                 "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7\r\n" .
                 "Keep-Alive: 300\r\n" .
                 "Connection: keep-alive\r\n" .
                 "Referer: http://$host\r\n\r\n");

      // retrieve the response from the remote server
      while ( $line = fread( $fp, 4096 ) ){
         $response .= $line;
      }

      fclose( $fp );

      // strip the headers
      $pos      = strpos($response, "\r\n\r\n");
      $response = substr($response, $pos + 4);
   }

   // return the file content
   return $response;
}

// all the widget functions go in this function this is your main
function shoutsuite_widget_innit(){ if ( !function_exists('register_sidebar_widget') ) return;
global $post, $wpdb;


function shoutsuite_favorites_control(){
	if($_POST['ts_favorite_max']){ update_option('ts_favorite_max', $_POST['ts_favorite_max']); }
	$max = get_option('ts_favorite_max');
	if(!$max){$max=5; }
		
	?><p><label for='ts_favorite_max'>Display at most:  <input style="width: 100px;" type='text'  name='ts_favorite_max' id='ts_favorite_max' value='<?php echo $max; ?>' /></label> shouts.</p><?php
}

function shoutsuite_mine_control(){
	if($_POST['ts_mine_max']){ update_option('ts_mine_max', $_POST['ts_mine_max']); }
	$max = get_option('ts_mine_max');
	if(!$max){$max=5; }
		
	?><p><label for='ts_mine_max'>Display at most:  <input style="width: 100px;" type='text'  name='ts_mine_max' id='ts_mine_max' value='<?php echo $max; ?>' /></label> shouts.</p><?php
}

function shoutsuite_favorites_widget($args)
{ 
	global $wpdb;
    extract($args);
	echo $before_widget;
	echo $before_title . 'My Favorite Shouts' . $after_title;
	
	$max = get_option('ts_favorite_max');
	if(!$max){$max=5; }
	
	$table_name = $wpdb->prefix . "ts_favorites";
	$buff = $wpdb->get_results("SELECT * FROM $table_name order by datetime desc limit $max");
	
	foreach ($buff as $line){
		$shout = $line->shout;
		$link = $line->link;
		$author = $line->author;
		$dt = date('m/d/y h:ia', $line->datetime);
		$output .="<li class=\"favshout\"><a href='http://ngeshout.com/$author'>$author</a>: $shout <a href='$link'>$dt</a></li>";
	}
	echo "<ul id=\"favshout\">".$output."</ul>";
	echo $after_widget;
}

function shoutsuite_mine_widget($args)
{ 
	global $wpdb;
    extract($args);
	echo $before_widget;
	echo $before_title . 'My Shouts' . $after_title;
	
	$max = get_option('ts_mine_max');
	if(!$max){$max=5; }
	
	$table_name = $wpdb->prefix . "ts_mine";
	$buff = $wpdb->get_results("SELECT * FROM $table_name order by datetime desc limit $max");
	
	foreach ($buff as $line){
		$shout = $line->shout;
		$link = $line->link;
		$dt = date('m/d/y h:ia', $line->datetime);
		$output .="<li class=\"mineshout\">$shout <a href='$link'>$dt</a></li>";
	}
	echo "<ul id=\"mineshout\">".$output."</ul>";
	echo $after_widget;
}

function shoutsuite_recent_widget($args)
{ 
	global $wpdb;
    extract($args);
	echo $before_widget;
	echo $before_title . 'Recently Shouted' . $after_title;
	
	$max = get_option('recent_shouted_max');
	if(!$max){$max=5; }
	
	$table_name = $wpdb->prefix . "shoutbacks";
	$buff = $wpdb->get_results("SELECT distinct postID FROM $table_name order by datetime desc limit $max");
	
	foreach ($buff as $line){
		$post = get_post($line->postID); 
		$title = $post->post_title;
		$url = get_permalink($line->postID);		
		$output .="<li class=\"recentshout\"><a href='$url'>$title</a></li>";
	}
	echo "<ul id=\"recentshout\">".$output."</ul>";
	echo $after_widget;
}
function shoutsuite_recent_widget_control(){
	

	if($_POST['recent_shouted_max']){ update_option('recent_shouted_max', $_POST['recent_shouted_max']); }
	$max = get_option('recent_shouted_max');
	if(!$max){$max=5; }
		
	?><p><label for='recent_shouted_max'>Display at most:  <input style="width: 100px;" type='text'  name='recent_shouted_max' id='recent_shouted_max' value='<?php echo $max; ?>' /></label> posts.</p><?php
}


function shoutsuite_most_widget($args)
{ 
	global $wpdb;
    extract($args);
	echo $before_widget;
	echo $before_title . 'Most Shouted' . $after_title;
	
	$max = get_option('most_shouted_max');
	if(!$max){$max=5; }
	
	$table_name = $wpdb->prefix . "shorturls";
	$buff = $wpdb->get_results("SELECT * FROM $table_name order by count desc limit $max");
	
	foreach ($buff as $line){
		$count = $line->count;
		$post = get_post($line->postID); 
		$title = $post->post_title;
		$url = get_permalink($line->postID);		
		if ($title=='') $results = $wpdb->query("DELETE FROM `$table_name` WHERE `id` = ".$line->id." LIMIT 1;");	
		else $output .="<li class=\"mostshout\"><a href='$url'>$title</a> ($count Shouts)</li>";
	}
	echo "<ul id=\"mineshout\">".$output."</ul>";
	echo $after_widget;
}
function shoutsuite_most_widget_control(){
	

	if($_POST['most_shouted_max']){ update_option('most_shouted_max', $_POST['most_shouted_max']); }
	$max = get_option('most_shouted_max');
	if(!$max){$max=5; }
		
	?><p><label for='most_shouted_max'>Display at most:  <input style="width: 100px;" type='text'  name='most_shouted_max' id='most_shouted_max' value='<?php echo $max; ?>' /></label> posts.</p><?php
}

if(get_option('shoutsuite_recently')){
register_widget_control(array('Recently Shouted', 'widgets'), 'shoutsuite_recent_widget_control', 200, 150);
register_sidebar_widget(array('Recently Shouted','widgets'), 'shoutsuite_recent_widget');
}

if(get_option('shoutsuite_most')){
register_widget_control(array('Most Shouted', 'widgets'), 'shoutsuite_most_widget_control', 200, 150);
register_sidebar_widget(array('Most Shouted','widgets'), 'shoutsuite_most_widget');
}

if(get_option('shoutsuite_favorites')){
register_widget_control(array('My Favorite Shouts', 'widgets'), 'shoutsuite_favorites_control', 200, 150);
register_sidebar_widget(array('My Favorite Shouts','widgets'), 'shoutsuite_favorites_widget');
}

if(get_option('shoutsuite_mine')){
register_widget_control(array('My Shouts', 'widgets'), 'shoutsuite_mine_control', 200, 150);
register_sidebar_widget(array('My Shouts','widgets'), 'shoutsuite_mine_widget');
}

}

function shoutsuite_send($msg){
global $insource;
$username = get_option('shoutsuite_ngeshout_username');
$password = get_option('shoutsuite_ngeshout_password');
$prefix = urlencode(get_option('shoutsuite_prefix').' ');
$source = get_option('shoutsuite_source');
if (trim($source)=='') $source=urlencode($insource); else $source=urlencode($source);
$msg = $prefix.$msg;
if(($username) and ($password))  {
	$url = 'http://ngeshout.com/api/statuses/update.xml';
	$curl_handle = curl_init();
	curl_setopt($curl_handle, CURLOPT_URL, "$url");
	curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_handle, CURLOPT_POST, 1);
	curl_setopt($curl_handle, CURLOPT_POSTFIELDS, "source=$source&status=$msg");
	curl_setopt($curl_handle, CURLOPT_USERPWD, "$username:$password");
	$buffer = curl_exec($curl_handle);
	curl_close($curl_handle);
}

}


function parseShouts($type='shouts'){
	global $wpdb;	
	$user = get_option('shoutsuite_ngeshout_username');	
	if($type=='favorites'){$url = "http://ngeshout.com/api/favorites/$user.atom"; }
	else { $url = "http://ngeshout.com/api/statuses/user_timeline/$user.atom"; }
	//echo "parseShout $url";
	//	print_r($return);	
	$data =sb_get($url);
$chunks = split('<entry>', $data);
array_shift($chunks);
foreach($chunks as $chunk){	
	$chunk = nl2br($chunk);
	$chunk = trim(str_replace( "</entry>", "", $chunk));
	$chunk = trim(str_replace( "<br /><br />", "\n", $chunk));
	$chunk = trim(str_replace( "<br />", "\n", $chunk));
	$chunk = trim(str_replace( "\n\n", "\n", $chunk));
	$chunk = trim(str_replace( "><", ">\n<", $chunk));
	$chunk = trim(ereg_replace( "> +<", ">\n<", $chunk));

	$lines = split("\n", $chunk);
//	print_r($lines);
	$author = trim(str_replace(array('<name>', '</name>'), '', $lines[8]));	
	$shout = trim(str_replace(array('<title>', '</title>', '<content type="html">', '</content>'), '', $lines[0]));	
	list($junk, $avatar) = split('href="', $lines[6]);
	$avatar = str_replace('" rel="image" type="image/png"/>', '', $avatar);
	$ret['avatar'] = $avatar;
	$dt = $lines[3];
	list($date, $time) = split('T', $dt);
	$time = str_replace('Z', '', $time);
	list($hour, $minute, $second) = split(':', $time);
	list($year, $month, $day) = split('-', $date);
	$year = trim(str_replace('<published>', '', $year));
	$dt = mktime($hour, $minute, $second, $month, $day, $year);	
	$ret['dt'] = $dt;						
	
	list($junk, $link) = split('href="', $lines[5]);
	$link = str_replace('" rel="alternate" type="text/html"/>', '', $link);
	$ret['link'] = $link;	
	if($type=='shouts'){
		$ret['shout'] = trim(str_replace($user.':', '', strip_tags(html_entity_decode($shout))));
		$ret['author'] = $user;
	}
	else {
		$ret['shout'] = trim(str_replace($author.':', '', strip_tags(html_entity_decode($shout))));
		$ret['author'] = $author;
	}
	$return[] = $ret;	
}			
return $return;
}


//upgrade
  $installed_ver = get_option( "shoutsuite_db_version" );
  if ($installed_ver<>''){
	  if($installed_ver != $ts_version) {
		update_option( "shuotsuite_db_version", $ts_version );
	
		$table_name = $wpdb->prefix . "shorturls";
	
		$sql = "DROP TABLE `".$table_name."`;";
		$results = $wpdb->query($sql);
	
		$sql = "CREATE TABLE ".$table_name." (
		  id int(10) unsigned NOT NULL AUTO_INCREMENT,
		  postID int(11) NOT NULL,
		  count int(11) NOT NULL,
		  accessed int(11) NOT NULL,
		  shoutthis varchar(255) NOT NULL,
		  pendekin varchar(255) NOT NULL,
		  UNIQUE KEY id (id),
		  UNIQUE KEY postID (postID)
		  );";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		
	   }
	}
?>