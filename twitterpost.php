<?php
/*
Plugin Name: Twitter Post
Plugin URI: http://cobaia.net/twitterpost
Description: Enable the user show the new post on twitter.
Version: 0.1
Author: Vinícius Krolow
Author URI: http://cobaia.net/
*/
define('TWITTERPOST_URL', 'http://twitter.com/');
define('TWITTERPOST_MESSAGE', 'statuses/update.json');
define('TWITTERPOST_LOGIN', 'statuses/user_timeline.json');
define('TWITTERPOST_MAX_LENGHT', 140);
define('TWITTERPOST_VERSION', '0.1');

global $twitterpost_variables;
$twitterpost_variables = array('title' => __('[title]'),'author' => __('[author]'),'url' => __('[url]'));

add_action('publish_post', 'twitterpost_send_post');

function twitterpost_send_post() {
	$options = get_option('twitterpost');
	if (!empty($options)) {
		$message = twitterpost_prepare_message($options);
		if (twitterpost_valid_message($message)) {
			twitterpost_send_status($options, $message);
		}
	}
}

function twitterpost_valid_message() {
	return $_POST['originalaction'] == 'post' && strlen($message) <= TWITTER_MAX_LENGHT;
}

function twitterpost_prepare_message($options) {
	global $twitterpost_variables;
	$post = get_post($_POST['post_ID']);
	$title = $post->post_title;
	$url = get_permalink($_POST['post_ID']);
	$author = get_author_name($post->post_author);
	$message = $options['twitter_message'];
	foreach ($twitterpost_variables as $key => $variable) {
		$message = str_replace($variable, ${$key}, $message);
	}

	return $message;
}

function twitterpost_verify_user($username, $password) {
	return twitterpost_request(TWITTERPOST_URL . TWITTERPOST_LOGIN, $username, $password);
}

function twitterpost_send_status($options, $message) {
	twitterpost_request(TWITTERPOST_URL . TWITTERPOST_MESSAGE, $options['twitter_username'], $options['twitter_password'], $message);
}

function twitterpost_request($uri, $username, $password = null, $message = null) {
	require_once(ABSPATH.WPINC.'/class-snoopy.php');
	$snoop = new Snoopy();
	$snoop->agent = 'TwitterPost wordpress plugin http://cobaia.net/twitterpost';
	$snoop->rawheaders = array(
		'X-Twitter-Client' => 'twitterpost',
		'X-Twitter-Client-Version' => TWITTERPOST_VERSION, 
		'X-Twitter-Client-URL' => 'http://cobaia.net/twitterpost'
	);	
	$snoop->user = $username;
	if (!is_null($password)) {
		$snoop->pass = $password;
	}
	if (!is_null($message)) {
		$snoop->submit($uri, array('status' => $message, 'source' => 'twitterpost'));
	} else {
		$snoop->fetch($uri);
	}
	if (@strpos('200',$snoop->response_code)) {
		$results = json_decode($snoop->results);
		return sprintf(__('Sorry, login failed. Error message from Twitter: %s', 'twitterpost'), $results->error);
	} else {
		return true;
	}
}
/**
 * Admin
 */
add_action('admin_menu', 'twitterpost_menu');

function twitterpost_menu() {
	add_options_page('Twitter Post Options', 'Twitter Post', 8, __FILE__, 'twitterpost_options');
}

function _twitterpost_save() {
	$error = array();
	if (empty($_POST['twitter_username'])) {
		$error[] = __('Need set your twitter username');
	} 
	if (empty($_POST['twitter_password'])) {
		$error[] = __('Need set your twitter password');
	}
	if (empty($_POST['twitter_message'])) {
		$error[] = __('Need set your message');
	}
	if (strlen($_POST['twitter_message']) >= 100) {
		$error[] = __('The max lenght of message is 100!');
	}
	if (count($error) == 0) {
		if (is_bool(twitterpost_verify_user($_POST['twitter_username'], $_POST['twitter_password']))) {
			unset($_POST['savesettings']);
			delete_option('twitterpost');
			add_option('twitterpost', $_POST);
			return __('Options saved with success');
		} else {
			$error[] = __('Not a valid username and password');
		}
	}
	
	return implode('<br />', $error);
}

function twitterpost_options() {
	$values = get_option('twitterpost');
	if ( 'POST' == strtoupper($_SERVER['REQUEST_METHOD']) ) {
		$response = _twitterpost_save();
		$values = $_POST;
	}
	$output = '<div class="wrap">';
  	$output .= '<h2>Twitter Post '. __('Settings', 'twitterpost') . '</h2>';
  	$output .= '<p>'. __('With this plugin you can wenever create one post send one text for your twitter!') .'</p>';
  	if (isset($response)) {
  		$output .= '<p class="alter response">'. $response .'</p>';
  	}
  	$output .= '<form action="" method="post">';
  	$output .= '<table class="form-table">';
  	$output .= '<tr class="form-field">
						<th scope="row">
							<label for="twitter_username">'. __('Twitter Username:') .'</label>
						</th>
						<td>
							<input id="twitter_username" type="text" value="'. $values['twitter_username'] .'" name="twitter_username"/>
						</td>
				</tr>';
  	$output .= '<tr class="form-field">
						<th scope="row">
							<label for="twitter_password">'. __('Twitter Password:') .'</label>
						</th>
						<td>
							<input id="twitter_password" type="password" name="twitter_password"/>
						</td>
				</tr>';
  	$output .= '<tr class="form-field">
						<th scope="row">
							<label for="twitter_message">'. __('Twitter Message:') .'</label>
						</th>
						<td>
							<textarea id="twitter_message" name="twitter_message">'. $values['twitter_message'] .'</textarea>
						</td>
				</tr>';
  	$output .= '<tr class="form-field">
						<th scope="row">
							<!-- -->
						</th>
						<td>
							<strong>'. __('You can use these variables to replace:') .'</strong> '. __('[title]') .','. __('[author]') .','. __('[url]') .'
						</td>
				</tr>';
  	$output .= '</table><p class="submit">
<input id="savetwitterpost" class="button-primary" type="submit" value="Save settings" name="savesettings"/>
</p>';
  		
  	echo $output;
}
?>