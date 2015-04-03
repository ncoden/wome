<?php
// -------------------------------
// 	  WOME CONFIGURATION FILE
// -------------------------------

WOME::set_config([

'DOMAINS' => [
	//regex => name
	'himms.dev',
],

'SITES' => [
	'himms.dev' => [
		'directory' => '/Users/Nicos/Documents/Documents/Projets/Programmation/Web/2014/HIMMS/www/',
	],
],


//  DEFAUT PAGE CONFIG
// Don't delete defaut configuration.

'LANGUAGES' => [
	//name => datas
],


'PLUGINS' => [
	//name => true or false
],


'OPTIONS' => [
	//name => data
	'domains.enable_regex' => false,
	'domains.allow_all' => false,

	'language.defaut' => 'fr',

	'pages.defaut' => 'home',
	'pages.no_found' => 'page_no_found',
	'pages.no_page' => '/scripts',

	'path.hide_defaut' => false,
	'path.hide_defaut_language' => false,

	'user.logged_index' => 'logged',
	'user.id_index' => 'id',
	'user.personal_group' => 'user',

	'use_logs' => true,
	'use_WOME_client' => true,
	
	'use_cache' => false,
		//full, page, pieces, optimized
		'cache.mode' => 'optimized',
	
	'use_database' => true,
		'db.host' => 'localhost',
		'db.name' => 'HIMMS',
		'db.username' => 'root',
		'db.password' => '',
],

]);
?>
