<?php
/**
 * 
 */

require_once "{$CONFIG->pluginspath}facebookservice/vendors/facebook-php-sdk/src/facebook.php";

function facebookservice_use_fbconnect() {
	return get_plugin_setting('sign_on', 'facebookservice') == 'yes';
}

function facebookservice_authorize() {
	$facebook = facebookservice_api();
	if (!$session = $facebook->getSession()) {
		register_error(elgg_echo('facebookservice:authorize:error'));
		forward('pg/settings/plugins');
	}
	
	// only one user to be authorized per account
	$values = array(
		'plugin:settings:facebookservice:access_token' => $session['access_token'],
		'plugin:settings:facebookservice:uid' => $session['uid'],
	);
	
	if ($users = get_entities_from_private_setting_multi($values, 'user', '', 0, '', 0)) {
		foreach ($users as $user) {
			// revoke access
			clear_plugin_usersetting('access_token', $user->getGUID(), 'facebookservice');
			clear_plugin_usersetting('uid', $user->getGUID(), 'facebookservice');
		}
	}
	
	// register user's access tokens
	set_plugin_usersetting('access_token', $session['access_token'], 'facebookservice');
	set_plugin_usersetting('uid', $session['uid'], 'facebookservice');
	
	system_message(elgg_echo('facebookservice:authorize:success'));
	forward('pg/settings/plugins');
}

function facebookservice_revoke() {
	// unregister user's private settings
	clear_plugin_usersetting('access_token');
	clear_plugin_usersetting('uid');
	
	system_message(elgg_echo('facebookservice:revoke:success'));
	forward('pg/settings/plugins');
}

function facebookservice_api() {
	return new Facebook(array(
		'appId' => get_plugin_setting('api_key', 'facebookservice'),
		'secret' => get_plugin_setting('api_secret', 'facebookservice'),
	));
}

function facebookservice_get_authorize_url($next='') {
	global $CONFIG;
	
	if (!$next) {
		// default to login page
		$next = "{$CONFIG->site->url}pg/facebookservice/login";
	}
	
	$facebook = facebookservice_api();
	return $facebook->getLoginUrl(array(
		'next' => $next,
		'req_perms' => 'offline_access,email',
	));
}

function facebookservice_login() {
	// sanity check
	if (!facebookservice_use_fbconnect()) {
		forward();
	}
	
	$facebook = facebookservice_api();
	if (!$session = $facebook->getSession()) {
		forward();
	}
	
	// attempt to find user
	$values = array(
		'plugin:settings:facebookservice:access_token' => $session['access_token'],
		'plugin:settings:facebookservice:uid' => $session['uid'],
	);
	
	if (!$users = get_entities_from_private_setting_multi($values, 'user', '', 0, '', 0)) {
		var_dump($session);exit;
	} elseif (count($users) == 1) {
		login($users[0]);
		
		system_message(elgg_echo('facebookservice:login:success'));
		forward();
	}
	
	// register login error
	register_error(elgg_echo('facebookservice:login:error'));
	forward();
}