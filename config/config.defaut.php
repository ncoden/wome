<?php
// -------------------------------
// 	  WOME CONFIGURATION FILE
// -------------------------------

$this->set_config([

'DOMAINS' => [
	//regex => name
	'test.org',
	'powerpark.org',
	'bookino.org',
],


//  DEFAUT PAGE CONFIG
// Don't delete defaut configuration.


'DIRECTORIES' => [
	'cache' => 'cache',				
	'css' => 'css',
	'fichiers' => 'datas',
	'images' => 'img',
	'javascript' => 'js',
	'langues' => 'langues',		
	'pages' => 'pages',
	'plugins' => 'plugins',
	'polices' => 'fonts',
	'scripts' => 'scripts',
],


'LANGUAGES' => [
	//name => datas
	'en' => ['English', 'Language'],
	'fr' => ['Français', 'Langue'],
],


'PLUGINS' => [
	//name => true or false
	'plugin' => function(){return true;},
],


'OPTIONS' => [
	//name => data
	'domains.enable_regex' => false,
	'domains.allow_all' => false,
	
	'use_logs' => true,
	'use_WOME_client' => true,
	
	'use_cache' => false,
		'cache_each_parts' => false,
		
		'defaut_cache_groupe' => '',
		'start_cache_name' => 'wome_start',
		'end_cache_name' => 'wome_end',
	
	'use_database' => true,
		'db_host' => 'localhost',
		'db_name' => 'bookino',
		'db_username' => 'root',
		'db_password' => '',
	
	'pages.defaut_url' => '/home',
	'pages.no_page_url' => '/scripts',
	
	'defaut_page_url' => '/home',
	'hide_defaut_url' => true,
		'hide_language_in_defaut_url' => true,
		'force_hide_defaut_url' => true,
	'script_page_url' => '/script',
	
	'defaut_title' => function($_WOME){
		global $l;
		
		if(isset($l['PAGE:TITRE'][$_page['nom']])){
			return $l['PAGE:TITRE'][$_page['nom']].' - '.$_wome['site'];
		}else{
			return $_page['titre'] = $_wome['site'];
		};
	},
	'404_page' => 'ERREUR404',
	
	'optimize_accounts' => true,
	'groupe_user' => 'user',
	'user_unique_index' => 'id',
	'user_login_index' => 'connecte',
	'user_groupe_index' => 'groupe',
	
	'user_save_language' => true,
		'user_language_index' => 'langue',
		
	'cookie_language' => 'WOME_language',
	'cookie_account' => 'WOME_connexion',
],

]);
?>