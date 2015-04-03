<?php
	//WOME BETA 0.4 server (WOMEB04S)
	//Website Oriented Multilanguage Engine - server core for BETA 0.4
	//Copyright WOME © 2013-2014 Nicolas Coden. All rights reserved

ini_set('xdebug.var_display_max_depth', '10');

class	WOME
{
	//MAIN VARIABLES
	public static				$_wome = [];		//informations about Wome
	public static				$_site = [];		//informations about the current website
	public static				$_config = [];		//the wome and website configuration
	public static				$_page = [];		//informations about the current page and the return
	public static				$_user = [];		//informations about the user (connected or not)

	//OTHER VARIABLES
	private static				$db = null;
	private static				$included = [];
	private static				$plugins = [];
	private static				$languages = [];
	private static				$redirect = false;
	private static				$log_scope = null;

	private static				$return = [];
	
	private static				$buffer = [];
	private static				$cache_mode = null;
	private static				$cache_file = null;
	private static				$cache_dir = null;
	private static				$cache_pieces = [];
	private static				$cache_next_datas = [];

	//MAIN
	public static function		init(){
		//initialize variables
		WOME::$_wome = [
			'version' => 'BO2',
		];
		WOME::$_site = [
			'logs' => [],
		];

		chdir('../');

		//start !
		WOME::log('STARTING WOME...');
		WOME::log(null, 'loading site datas');
		WOME::init_http_datas();
		WOME::log(null, 'reading wome configuration');
		WOME::init_config();
		WOME::log(null, 'authentificating user');
		WOME::init_user();

		do
		{
			//defaut values
			WOME::$cache_next_datas = [true, 'defaut', null, null];
			WOME::$_page['content'] = null;
			WOME::$_page['js'] = null;
			WOME::$buffer = [
				'content' => null,
				'js' => null,
			];

			//loading page
			WOME::log('LOADING PAGE...');
			WOME::log(null, 'loading plugins');
			WOME::init_plugins();

			WOME::log('PAGE PROCESSING', WOME::$_page['path']);

			if (WOME::$redirect == false)
			{
				$log_key = WOME::log(null, 'detecting language... ');
				WOME::init_language();
				WOME::log_complete($log_key, '"'.WOME::$_page['language'].'"');
	
				$log_key = WOME::log(null, 'detecting page... ');
				WOME::init_page();
				if (isset(WOME::$_page['name']))
					WOME::log_complete($log_key, '"'.WOME::$_page['name'].'"');
			}
			else
				WOME::$redirect = false;

			WOME::log(null, 'executing scripts and forms');
			WOME::init_scripts_forms_files();

			if (WOME::$_page['path'] != WOME::get_option('pages.no_page'))
			{
				$log_key = WOME::log(null, 'language redirection... ');
				WOME::init_lang_redirect();
				WOME::log_complete($log_key, '"'.WOME::$_page['language'].'"');
	
				$log_key = WOME::log(null, 'page redirection... ');
				WOME::init_page_redirect();
				WOME::log_complete($log_key, '"'.WOME::$_page['name'].'"');

				WOME::log(null, 'executing page');
				WOME::exec_page();
			}
		}
		while (WOME::$redirect !== false);

		WOME::log(null, 'loading view');
		WOME::init_content();

		WOME::log(null, 'finish !');
		//WOME::print_logs();
	}

	//INITIALISATION
	//	(WOME start)
	private static function		init_http_datas(){
		//current url, previous url and domain
		WOME::$_site['url'] = '//'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		WOME::$_site['address'] = parse_url(WOME::$_site['url']);
		WOME::$_page['path'] = WOME::$_site['address']['path'];
		if (isset($_SERVER['HTTP_REFERER'])){
			WOME::$_site['prev_url'] = $_SERVER['HTTP_REFERER'];
			WOME::$_site['prev_address'] = parse_url(WOME::$_site['prev_url']);
			WOME::$_site['from_site'] = (WOME::$_site['address']['host'] == WOME::$_site['prev_address']['host']);
		}
		else
		{
			WOME::$_site['prev_url'] = null;
			WOME::$_site['prev_address'] = null;
			WOME::$_site['from_site'] = false;
		};

		//ajax informations
		WOME::$_site['post'] = WOME::in_array($_POST, 'datas', []);
		WOME::$_site['from_ajax'] = isset($_SERVER['HTTP_X_REQUESTED_WITH']);
		if (WOME::$_site['from_ajax'])
			WOME::$_site['ajax_url'] = WOME::in_array(WOME::$_site['post'], 'ajax_url');
		else
			WOME::$_site['ajax_url'] = null;
	}
	private static function		init_config(){
		//include the wome configuration
		if (file_exists('config.php'))
			include('config.php');
		else
			WOME::error(100);

		//detect the current website
		$domains = WOME::get_config('DOMAINS');
		if (is_array($domains))
		{
			$enable_regex = WOME::get_option('domains.enable_regex');
			foreach($domains as $pattern => $domain)															//for each domain name,
			{
				$domain = WOME::get_value($domain);

				if (((!$enable_regex && $pattern === WOME::$_site['address']['host'])							//if the pattern is equal to the domain
					|| ($enable_regex && preg_match('/^'.$pattern.'$/', WOME::$_site['address']['host'])))		//or if the pattern match with the domain
				|| (is_integer($pattern)																		//or if there isn't pattern given
					&& ($domain == WOME::$_site['address']['host'] || WOME::get_option('domains.allow_all'))))	//	and the current domain is the name
				{
					WOME::$_site['name'] = $domain;			//so the website is on this domain
					break;
				};
			}
		};

		//if there is no domain found, error 200
		if (!isset(WOME::$_site['name']))
			WOME::error(200);
		else
		{
			//set the website directory
			if (WOME::get_config(['SITES', WOME::$_site['name'], 'directory']) != null)
				WOME::$_site['directory'] = WOME::get_config(['SITES', WOME::$_site['name'], 'directory']);
			else
				WOME::$_site['directory'] = '../'.WOME::$_site['name'].'/';

			//include the website configuration is a scope
			if (!file_exists(WOME::$_site['directory']) || !is_dir(WOME::$_site['directory']))
				WOME::error(111);
			else
			{
				if (file_exists(WOME::$_site['directory'].'config.php'))
				{
					//include the config
					$function = function(){
						include(WOME::$_site['directory'].'config.php');
					};
					$function();
				}
				else
					WOME::error(110);
			}
		}
	}
	private static function		init_user(){
		WOME::$_user[WOME::get_option('user.logged_index')] = false;

		//call user function
		$user = WOME::get_config('USER');
		if (is_array($user))
			WOME::$_user = array_merge(WOME::$_user, $user);

		//set groups
		$groups = WOME::get_config('GROUPS');
		if (is_array($groups))
			WOME::$_user['groups'] = WOME::get_ultimate_list($groups);
		else
			WOME::$_user['groups'] = [];
	}
	private static function		init_plugins(){
		foreach(WOME::get_config('PLUGINS') as $index => $value){
			$plugin_name = null;

			if (is_integer($index) && !isset($plugins[$value]))
				$plugin_name = WOME::get_value($value);
			else if (is_callable($value) && !isset($plugins[$index]) && $value())
				$plugin_name = $index;
			
			if ($plugin_name != null)
			{
				$path = 'plugins/'.$plugin_name.'/'.$plugin_name.'.php';
				if (file_exists($path) && !in_array($path, WOME::$included))
				{
					//include the script file
					include($path);
					WOME::$included[] = $path;
				}
			}
		}

		//lanch the load event
		WOME::plugin_event('onload');
	}

	//	(WOME processing)
	private static function		init_scripts_forms_files(){
		//	if form submit (without js)
		if (is_array($_POST)
		&&	isset($_POST['wome-name']) && is_string($_POST['wome-name'])
		&&	isset($_POST['wome-type']) && is_string($_POST['wome-type']))
		{
			WOME::$return = WOME::init_script_form_file($_POST);
		}
		else
		{
			//else if ajax submit
			//for each post, check the syntax
			foreach($_POST as $number => $datas)
				WOME::$return[$number] = WOME::init_script_form_file($datas);
		}
	}
	private static function		init_script_form_file($datas){
		if (is_array($datas)
		&&	isset($datas['wome-name']) && is_string($datas['wome-name']) 
		&&	isset($datas['wome-type']) && is_string($datas['wome-type']))
		{
			$name = $datas['wome-name'];
			$type = $datas['wome-type'];
			unset($datas['wome-name']);
			unset($datas['wome-type']);
			if (isset($datas['wome-data']))
			{
				$data = $datas['wome-data'];
				unset($datas['wome-data']);
			}

			//SCRIPT
			if ($type == 'script')
				return (WOME::script_exec($name, $datas));

			//FORM or FIELD
			if ($type == 'form' || $type == 'field')
			{
				//load the form
				$form = new WOME_FORM($name);
				if ($form)
				{
					if ($type == 'form')
					{
						$validated = $form->check($datas, $errors);
						if ($validated === true)
							return (true);
						else
							return ($errors);
					}

					if ($type == 'field')
					{
						foreach($datas as $index => $value);
						$error = $form->check_field($index, $value, $datas);
						return ($error);
					}
				}
				return (false);
			}

			//FILE
			if ($type == 'file' && isset($data))
			{
				//if the file exists
				if ($name != '' && isset($_SESSION['_files'][$name]))
				{
					$path = WOME::$_site['directory'].'uploads/'.$name;
					if (file_exists($path))
					{
						file_put_contents($path, base64_decode($data), 0777);
						return ($name);
					}
				}
				else
				{
					//find a key
					do {
						$name = WOME::rand_str(16);
						$path = WOME::$_site['directory'].'uploads/'.$name;
					} while (file_exists($path));
					//write the file and return key
					$_SESSION['_files'][$name] = '';
					file_put_contents($path, base64_decode($data), 0777);
					return ($name);
				}
			}
		}
		return (false);
	}

	//	(PAGE processing)
	private static function		init_language(){
		//the language is :
		WOME::$_page['language'] = null;
		$languages = WOME::get_config('LANGUAGES');

		//		the url language
		if (is_array($languages) && !empty($languages))
		{
			$url_parsed = explode('/', WOME::$_page['path']);
			if (isset($url_parsed[1]) && WOME::in_ultimate_array($languages, $url_parsed[1]))
			{
				WOME::$_page['language'] = $url_parsed[1];
				unset($url_parsed[1]);
				WOME::$_page['path'] = implode('/', $url_parsed);
			}
			else
			{
				//	or the defaut language
				$defaut_language = WOME::get_option('language.defaut');
				if ($defaut_language != null && WOME::in_ultimate_array($languages, $defaut_language))
					WOME::$_page['language'] = WOME::get_option('language.defaut');
				else
				{
					//or the first language given
					foreach($languages as $index => $value)
					{
						WOME::$_page['language'] = WOME::get_ultimate_value($index, $value);
						WOME::$_config['OPTIONS']['language.defaut'] = WOME::$_page['language'];
						break;
					}
				}
			}
		}
		
		//import the language
		if (WOME::$_page['language'] != null)
			WOME::import_language(WOME::$_page['language']);
	}
	private static function		init_page(){
		//defaut path
		if (WOME::$_page['path'] == '/' && WOME::get_option('pages.defaut') != null)
		{
			WOME::set_page(WOME::get_option('pages.defaut'));
		}
		else
		{
			//remove the last "/"
			while (substr(WOME::$_page['path'], -1) == '/')
				WOME::$_page['path'] = substr(WOME::$_page['path'], 0, -1);

			//find the path
			WOME::set_path();
		}
	}
	private static function		init_lang_redirect(){
		//new language
		$new_language = null;
		$languages = WOME::get_config('LANGUAGES');

		//	the language choosed (in a cookie)
		$cookie_name = WOME::get_option('language.cookie_name');
		if (isset($_COOKIE[$cookie_name]) && WOME::in_ultimate_array($languages, $_COOKIE[$cookie_name]))
			$new_language = $_COOKIE[$cookie_name];
		else if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
		{
			//or the navigator language
			preg_match_all('/(\W|^)([a-z]{2})([^a-z]|$)/six', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $parse);
			if (is_array($parse))
			{
				$size = count($parse[2]);
				for ($i = 0; $i < $size; $i++)
				{
					if (WOME::in_ultimate_array($languages, $parse[2][$i]))
					{
						$new_language = $parse[2][$i];
						break;
					}
				}
			}
		}

		//if the language must change
		if ($new_language != null 
			&& $new_language != WOME::$_page['language'] 
			&& WOME::import_language($new_language))
		{
			WOME::set_language($new_language);

			//page no found : retry with the new language
			if (WOME::$_page['name'] == false)
				WOME::set_path();
		}

		//options : hide defaut language
		if (WOME::get_option('path.hide_defaut_language') !== true
			|| (WOME::$_page['language'] != WOME::get_option('language.defaut')
				&& (WOME::get_option('path.hide_defaut') !== true
					|| WOME::$_page['name'] != WOME::get_option('pages.defaut'))))
			WOME::$_page['path'] = '/'.WOME::$_page['language'].WOME::$_page['path'];
	}
	private static function		init_page_redirect(){
		//redirection to the 404 page
		if (!isset(WOME::$_page['name']) || WOME::$_page['name'] == false)
		{
			$nofound_page = WOME::get_option('pages.no_found');
			if ($nofound_page != null)
				WOME::set_page($nofound_page);
		}
		
		//redirections
		$redirections = WOME::get_config('REDIRECTIONS');
		foreach($redirections as $index => $value)
		{
			if (is_integer($index) || $index == WOME::$_page['name'])
			{
				if(is_callable($value))
					$return = $value();
				else
					WOME::set_page($value);
			}
		}

		//option : hide defaut path
		if (WOME::get_option('path.hide_defaut') === true
			&& WOME::$_page['name'] == WOME::get_option('pages.defaut'))
			WOME::$_page['path'] = '/';
	}

	private static function		exec_page(){
		if (WOME::$_page														//if the page exist
		&&	WOME::user_in_group(WOME::$_page['properties']['access']))			//and the access is allowed
		{
			//defaut values and read configuration

			$use_cache = WOME::get_option('use_cache') && is_integer(WOME::$_page['properties']['cache']);
			if ($use_cache)
				WOME::$cache_mode = WOME::get_option('cache.mode');
			else
				WOME::$cache_mode = false;

			//check the cache
			WOME::$_page['read_cache'] = $use_cache;
			if (WOME::$cache_mode == 'full')
				WOME::$_page['read_cache'] = WOME::initcache_check_file();
			if (WOME::$cache_mode == 'pieces' || WOME::$cache_mode == 'optimized')
				WOME::$_page['read_cache'] = WOME::initcache_check_pieces();

			if (WOME::$_page['read_cache'] == true)
			{
				//read the cache
				if (WOME::$cache_mode == 'full')
					WOME::initcache_read_file();
				if (WOME::$cache_mode == 'pieces' || WOME::$cache_mode == 'optimized')
					WOME::initcache_read_pieces();
			}
			//else
			if (WOME::$cache_mode == 'page'
			||	WOME::$_page['read_cache'] == false)
			{
				//clear datas
				//cache_pieces stock now the list of pieces to write
				WOME::$cache_pieces = [];

				//execute content
				ob_start();
				if (WOME::$_page['properties']['content'])
					WOME::get_config('CONTENT');
				else
					WOME::print_page();
			}

			//receipt the buffer
			WOME::cache();
			ob_end_clean();

			if (!(WOME::$redirect))
			{
				//save the cache
				if ($use_cache && !WOME::$_page['read_cache'])
				{
					if (WOME::$cache_mode == 'full')
						WOME::initcache_save_file();
					if (WOME::$cache_mode == 'pieces' || WOME::$cache_mode == 'optimized')
						WOME::initcache_save_pieces();
				}
			}
		}
	}
	private static function		initcache_check_file(){
		//check if the cache file exists and is update

		//file : the cache directory + the page name + the closest group
		$group = null;
		if (WOME::$_page['properties']['user_cache'])
			$group = '_'.WOME::str_valid_dir(WOME::get_option('user.personal_group').WOME::$_user[get_option('user.id_index')]);
		else if (!empty(WOME::$_user['groups']))
			$group = '_'.WOME::str_valid_dir(WOME::$_user['groups'][0]);

		WOME::$cache_file = WOME::$_site['directory'].'cache/'.WOME::str_valid_dir(WOME::$_page['path']).$group;

		if (file_exists(WOME::$cache_file)
		&&	filemtime(WOME::$cache_file) > (time() - WOME::$_page['properties']['cache']))
			return (true);
		else
			return (false);
	}
	private static function		initcache_check_pieces(){
		//check if the index file and all the pieces
		//exists and are update

		//cache group
		$group = null;
		if (WOME::$_page['properties']['user_cache'])
			$group = '_'.WOME::str_valid_dir(WOME::get_option('user.personal_group').WOME::$_user[get_option('user.id_index')]);
		else if (!empty(WOME::$_user['groups']))
			$group = '_'.WOME::str_valid_dir(WOME::$_user['groups'][0]);

		WOME::$cache_dir = WOME::$_site['directory'].'cache/'.WOME::str_valid_dir(WOME::$_page['name']).'/';
		WOME::$cache_file = WOME::$cache_dir.'index'.$group;

		if (file_exists(WOME::$cache_file)														//if the index file exist
		&&	filemtime(WOME::$cache_file) > (time() - WOME::$_page['properties']['cache']))		//and the file cache is updated
		{
			//read the pieces names
			WOME::$cache_pieces = explode('\n', file_get_contents(WOME::$cache_file));

			//check the validity of each pieces
			//pieces mode : the pieces can't be outdated, they was all created with the index file
			//optimized mode : the pieces can be outdated, they could be not rewritten
			$piece_ok = true;

			$piece_count = count(WOME::$cache_pieces);
			for($i = 0; $i < $piece_count; $i++)
			{
				if (!file_exists(WOME::$cache_dir.WOME::$cache_pieces[$i])
				|| (WOME::$cache_mode == 'optimized' 
					&& filemtime(WOME::$cache_dir.WOME::$cache_pieces[$i]) < (time() - WOME::$_page['properties']['cache'])))
				{
					$piece_ok = false;
					break;
				}
			}
			return ($piece_ok);
		}
		return (false);
	}
	private static function		initcache_read_file(){
		//read the cache file
		$temp = file_get_contents(WOME::$cache_file);
		WOME::$_page['content'] .= $temp[0];
		WOME::$_page['js'] .= $temp[1];
	}
	private static function		initcache_read_pieces(){
		//read each piece of the index file

		$piece_count = count(WOME::$cache_pieces);
		for($i = 0; $i < $piece_count; $i++)
		{
			$temp = unserialize(file_get_contents(WOME::$cache_dir.WOME::$cache_pieces[$i]));
			WOME::$_page['content'] .= $temp[0];
			WOME::$_page['js'] .= $temp[1];
		}
	}
	private static function		initcache_save_file(){
		//save the cache file

		file_put_contents(WOME::$cache_file, serialize([
			WOME::$buffer['content'],
			WOME::$buffer['js'],
		]));
	}
	private static function		initcache_save_pieces(){
		//pieces have been updated one by one.
		//save the index file

		if (!file_exists(WOME::$cache_dir))
			mkdir(WOME::$cache_dir, 0777, true);
		file_put_contents(WOME::$cache_file, implode(WOME::$cache_pieces, '\n'));
	}
	private static function		initcache_save_buffer($restart = false){
		WOME::$buffer['content'] = ob_get_contents();
		WOME::$_page['content'] .= WOME::$buffer['content'];
		WOME::$_page['js'] .= WOME::$buffer['js'];

		WOME::$buffer = [
			'content' => null,
			'js' => null,
		];

		ob_end_clean();
		if ($restart)
			ob_start();
	}

	//	(RETURN)
	private static function		init_content(){
		//if there is a page
		if (WOME::$_page['path'] != WOME::get_option('pages.no_page'))
		{
			//echo the content
			echo(WOME::$_page['content']);
		}
		else
		{
			//echo the scripts and forms
			echo(json_encode(WOME::$return));
		}
	}


	//STRINGS
	public static function		str_valid_dir($dir){
		$replace = ['&' => '&&', '\\' => '&92', '/' => '&47', '?' => '&63', '%' => '&37', '*' => '&42', 
					':' => '&58', '|' => '&124', '"' => '&34', '<' => '&60', '>' => '&62'];
		if (is_string($dir))
			return (strtr($dir, $replace));
		else
			return (false);
	}
	public static function		associative_implode($array, $char1 = null, $char2 = false){
		$return = '';
		if ($char2 === false)
			$char2 = $char1;

		foreach($array as $key => $value)
			$return .= $key.$char1.$value.$char2;
		$return = substr($return, 0, -sizeof($char2));

		return ($return);
	}
	public static function		rand_str($size, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabCdefghijklmnopqrstuvwxyz123457890'){
		$key = '';
		$chars_size = strlen($chars);
		
		for ($i = 0; $i < $size; $i++){
			$key .= $chars{rand(0, $chars_size - 1)};
		}
		
		return ($key);
	}

	//SIMPLE TESTS
	public static function		get_value($function){
		if (is_callable($function))
			return ($function());						//if it is a function, execute it
		else												//else, return value
			return ($function);
	}
	public static function		get_ultimate_value($index, $value){
		if (is_integer($index))
			return (WOME::get_value($value));
		else
			return ($index);
	}
	public static function		get_ultimate_list($list){
		$return = [];

		if (is_array($list))
		{	
			foreach ($list as $index => $value)
			{
				$value = WOME::get_value($value);
				if (is_integer($index) && is_string($value))
					$return[] = $value;
				else if (is_string($index) && $value === true)
					$return[] = $index;
			}
			return ($return);
		}
		else
			return (false);
	}
	public static function		in_array($array, $index, $defaut = null){
		if (is_array($index))
		{
			foreach($index as $ind)
			{
				if (is_array($array) && isset($array[$ind]))
					$array = $array[$ind];
				else
					return($defaut);
			}
			return ($array);
		}
		else if (is_array($array) && isset($array[$index]))
			return $array[$index];
		else
			return $defaut;
	}
	public static function		in_ultimate_array($array, $index, $defaut = null){
		if (is_array($array) && (isset($array[$index]) || is_integer(in_array($index, $array))))
			return $array[$index];
		else
			return $defaut;
	}
	private static function		indexs_replace($array, $indexs){
		if (is_array($array) && is_array($indexs))
		{
			$size_array = count($array);

			for ($i = 0; $i < $size_array; $i++)
			{
				if (isset($indexs[$i]))
				{
					$array[$indexs[$i]] = $array[$i];
					unset($array[$i]);
				}
			}
		}
		return ($array);
	}
	private static function		match($reg, $str){
		$reg = preg_quote($reg, '/');
		$reg = '~'.str_replace('\*', '(.*)?', $reg).'~';

		$match = preg_match($reg, $str, $matches);
	
		if (count($matches) > 1)
		{
	    	array_shift($matches);
			return ($matches);
		}
		else if ($match == 1)
			return (true);
		else
			return (false);
	}

	//SCRIPTS
	public static function		script_exec($name, $datas = null){
		$properties = WOME::get_config(['SCRIPTS', $name]);

		if (is_array($properties))
		{
			//if it has not been included
			$path = WOME::$_site['directory'].'scripts/'.$properties['address'];
			if (file_exists($path) && !in_array($path, WOME::$included))
			{
				//include the script file
				include($path);
				WOME::$included[] = $path;
			}

			//execute
			if (is_callable($properties['function']))
				return($properties['function']($datas));
		}
	}

	//LANGUAGES
	public static function		import_language($name, $file = null){
		if ($file != null)
			$path = WOME::$_site['directory'].'languages/'.$file;
		else
			$path = WOME::$_site['directory'].'languages/'.$name.'.php';

		//if the language file exist, include it
		if (!in_array($path, WOME::$included))
		{
			if (file_exists($path))
			{
				//include the script file
				include($path);
				WOME::$included[] = $path;
				return (true);
			}
			else
				WOME::log(null, 'language file not found : '.$path);
		}
		return (false);
	}
	public static function		add_language($name, $datas){
		$datas = WOME::get_value($datas);

		//if the datas are correct
		if (is_string($name) && is_array($datas))
		{
			//if the language exist, complete it
			if (!isset(WOME::$languages[$name]))
				WOME::$languages[$name] = $datas;
			else
				WOME::$languages[$name] = array_replace_recursive(WOME::$languages[$name], $datas);
		}
	}
	public static function		set_language($new_language){
		//if the language must change
		if ($new_language != null && $new_language != WOME::$_page['language'])
		{
			//import the new language
			if (isset(WOME::$languages[$new_language]) || WOME::import_language($new_language))
			{
				//convert the path
				if (WOME::$_page['name'] != false)
					WOME::$_page['path'] = WOME::page_to_path(WOME::$_page['name'], WOME::$_page['path_datas'], $new_language);

				//change the language
				WOME::$_page['language'] = $new_language;
			}
		}
	}
	public static function		path_to_page($path, $language = null, &$path_datas = [], &$path_mode = 0){
		//custom language
		if ($language != null && !isset(WOME::$languages[$language]))
			WOME::import_language($from);
		else
			$language = WOME::$_page['language'];

		//find the page name with the from path
		$path_datas = [];
		$path_mode = 0;
		$page_names = WOME::in_array(WOME::$languages, [$language, 'PAGE_NAMES']);
		foreach ($page_names as $gbl_page => $lcl_pages)
		{
			if (!is_array($lcl_pages))
				$lcl_pages = [$lcl_pages];

			//test for each pattern
			foreach ($lcl_pages as $index => $lcl_page)
			{
				//if the path contain '*'
				if (strpos($lcl_page, '*') === false)
				{
					if ($lcl_page == $path && is_array(WOME::get_config(['PAGES', $gbl_page])))
						return ($gbl_page);
				}
				else
				{
					$path_datas = WOME::match($lcl_page, $path);
					if ($path_datas && is_array(WOME::get_config(['PAGES', $gbl_page])))
						return ($gbl_page);
					else
					{
						//else retry with a '/'
						$path_datas = WOME::match($lcl_page, $path.'/');
						if ($path_datas && is_array(WOME::get_config(['PAGES', $gbl_page])))
						{
							$path_mode = $index;
							return ($gbl_page);
						}
					}
				}
			}
		}
		return (false);
	}
	public static function		page_to_path($page, $path_datas = [], $language = null){
		//custom language
		if ($language == null || !isset(WOME::$languages[$language]) && !WOME::import_language($language))
			$language = WOME::$_page['language'];

		//defaut $path_datas value
		if (!is_array($path_datas))
			$path_datas = [$path_datas];

		//find the destination path
		$page_names = WOME::in_array(WOME::$languages, [$language, 'PAGE_NAMES']);
		if ($page != null 
		&& isset($page_names[$page])
		&& is_array(WOME::get_config(['PAGES', $page])))
		{
			$path = $page_names[$page];
			if (is_array($path))
				$count = count($path);
			else
			{
				$path = [$path];
				$count = 1;
			}

			for ($i = 0; $i < $count; $i++)
			{
				if (is_array($path_datas))
				{
					$count_star = count($path_datas);
					if (substr_count($path[$i], '*') == $count_star)
					{
						//replace each *
						for ($i_star = 0; $i_star < $count_star; $i_star++)
							$path[$i] = preg_replace('/\*/', $path_datas[$i_star], $path[$i], 1);
						return ($path[$i]);
					}
				}
			}
			return (false);
		}
		else
			return (false);
	}
	public static function		lang($word, $language = null){
		if ($language == null)
			$language = WOME::$_page['language'];

		if ($language != null)
		{
			if (is_array($word))
			{
				if (isset(WOME::$languages[$language])
				&&	isset(WOME::$languages[$language]['DATAS']))
					return(WOME::in_array(WOME::$languages[$language]['DATAS'], $word, false));
				else
					return (false);
			}
			else
				return(WOME::in_array(WOME::$languages, [$language, 'DATAS', $word], false));
		}
		else
			return (false);
	}

	//PAGES AND PATHS
	private static function		set_page($page = null, $path_datas = [], $visible = true, $language = null){
		//defaut page value
		if ($page == null)
			$page = WOME::$_page['name'];

		$path = WOME::page_to_path($page, $path_datas, $language);
		if ($path != false)
		{
			if ($visible === true)
				WOME::$_page['path'] = $path;
			WOME::$_page['name'] = $page;
			WOME::$_page['path_datas'] = $path_datas;
			WOME::$_page['properties'] = WOME::get_config(['PAGES', $page]);
		}
		return ($path);
	}
	private static function		set_path($path = null, &$path_datas = [], $visible = true, $language = null){
		//defaut path value
		if ($path == null)
			$path = WOME::$_page['path'];

		$page = WOME::path_to_page($path, $language, $path_datas, $path_mode);
		if ($page != false)
		{
			if ($visible === true)
				WOME::$_page['path'] = $path;
			WOME::$_page['name'] = $page;
			WOME::$_page['path_datas'] = $path_datas;
			WOME::$_page['path_mode'] = $path_mode;
			WOME::$_page['properties'] = WOME::get_config(['PAGES', $page]);
		}
		return ($page);
	}
	public static function		redirect_to_page($page, $path_datas = [], $language = null){
		WOME::$redirect = true;
		WOME::set_page($page, $path_datas, $language);
	}
	public static function		redirect($path){
		WOME::$redirect = true;
		WOME::set_path($path);
	}

	//RENDER
	public static function		cache($next_name = null, $next_group = null, $next_time = null){
		list($allowed, $name, $group, $time) = WOME::$cache_next_datas;

		if ($allowed)
		{
			//save the buffer
			WOME::initcache_save_buffer(true);

			//if the cache by pieces is enabled : save the piece
			if (!empty(WOME::$buffer)
			&&	$allowed == true
			&&	(WOME::$cache_mode == 'pieces' || WOME::$cache_mode == 'optimized'))
			{
				//check the folder
				if (!file_exists(WOME::$cache_dir))
					mkdir(WOME::$cache_dir, 0777, true);

				//group
				if ($group == WOME::get_option('user.personal_group'))
					$group = '_'.WOME::str_valid_dir($group.WOME::$_user[WOME::get_option('user.id_index')]);
				else if ($group != null)
					$group = '_'.WOME::str_valid_dir($group);

				//save the cache piece
				$file = WOME::str_valid_dir($name).$group;
				WOME::$cache_pieces[] = $file;
				file_put_contents(WOME::$cache_dir.$file, serialize([
					WOME::$buffer['content'],
					WOME::$buffer['js'],
				]));
			}
		}
		else
		{
			//clear
			ob_end_clean();
			WOME::$buffer = [
				'content' => null,
				'js' => null,
			];
			ob_start();
		}

		//allow or not the code execution :
		if ($next_group == null || WOME::user_in_group($next_group))		//if the group is allowed
		{
			//defaut time value for the piece
			if ($time == null)
				$time = WOME::$_page['properties']['cache'];

			$file = WOME::str_valid_dir($next_name);
			if (WOME::$cache_mode == 'optimized'							//if the optimized cache is enabled
			&&	file_exists(WOME::$cache_dir.$file)							//and the file exists
			&&	filemtime(WOME::$cache_dir.$file) > (time() - $time))		//and the file is update
			{
				//read the part cache
				$temp = unserialize(file_get_contents(WOME::$cache_dir.$file));
				WOME::$_page['content'] .= $temp[0];
				WOME::$_page['js'] .= $temp[1];
				WOME::$cache_pieces[] = $file;

				//don't allow execution
				$next_allowed = false;
			}
			else
				$next_allowed = true;
		}
		else
			$next_allowed = false;

		//allow or not the execution and save datas
		WOME::$cache_next_datas = [$next_allowed, $next_name, $next_group, $next_time = null];
		return ($next_allowed);
	}
	public static function		print_page(){
		//if page cache mode
		if (WOME::$cache_mode == 'page')
		{
			//save the previous buffer
			WOME::initcache_save_buffer(true);

			//check and read the cache
			WOME::$_page['read_cache'] = WOME::initcache_check_file();
			if(WOME::$_page['read_cache'])
				WOME::initcache_read_file();
		}

		if(!WOME::$_page['read_cache'])
		{
		//else : exec the page
			//include the page
			$function = function(){
				include(WOME::$_site['directory'].'pages/'.WOME::$_page['properties']['address']);
			};
			$function();
			
			if (WOME::$cache_mode == 'page')
			{
				//save the page buffer
				WOME::initcache_save_buffer(true);
				WOME::initcache_save_file();
			}
		}
	}
	public static function		add_js($js){
		//piece[1] : js
		WOME::$buffer['js'] .= $js;
	}
	public static function		get_js(){
		return ('
			WOME.init();
			WOME.page.name = "'.WOME::$_page['name'].'";
			WOME.page.path = "'.WOME::$_page['path'].'";
			'.WOME::$_page['js'].'
			'.WOME::$buffer['js'].'
		');
	}

	//USER
	public static function		user_in_group($group){
		if ($group == '*' || $group == WOME::get_option('user.personal_group'))
			return (true);
		else if (isset(WOME::$_user['groups']) && is_array(WOME::$_user['groups']))
			return (in_array($group, WOME::$_user['groups']));
		else
			return (false);
	}
	public static function		user_update(){
		WOME::$_user = [];
		WOME::init_user();
		return (WOME::$_user);
	}

	//SQL
	public static function		SQL_connect(){
		if (WOME::$db == null){
			try
			{
				WOME::$db = new PDO(
					'mysql:
					host='.WOME::get_option('db.host').';
					dbname='.WOME::get_option('db.name').'', 
					WOME::get_option('db.username'), 
					WOME::get_option('db.password')
					//[PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']
				);
				WOME::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			}
			catch(Exception $e)
			{
				WOME::error(500);
			}
		};
		return (WOME::$db);
	}
	public static function		SQL_request($request, $datas = []){
		$return = [];
		if (!is_array($datas))
			$datas = [$datas];

		//connect to the database
		$db = WOME::SQL_connect();

		//send request
		$req = $db->prepare($request);
		if (!$req->execute($datas))
			WOME::error(510);

		if (!isset(WOME::$_site['logs']['sql_request_number']))
			WOME::$_site['logs']['sql_request_number'] = 0;
		WOME::$_site['logs']['sql_request_number']++;

		//make return table
		if (substr($request, 0, 6) == 'SELECT')
		{
			while($data = $req->fetch()){
				$return[] = $data;
			}
			return $return;
		}
		else
			return (true);
	}

	//SETTINGS
	public static function		set_config($config){
		//set the config
		if (empty(WOME::$_config))
			WOME::$_config = $config;
		else
			WOME::$_config = array_replace_recursive(WOME::$_config, $config);

		//complete 'SITES' and 'SITE'
		if ((isset($config['SITES']) || isset($config['SITE'])) && isset(WOME::$_site['name']))
		{
			if (is_array(WOME::$_config['SITE']) && is_array(WOME::get_config(['SITES', WOME::$_site['name']])))
			{
				WOME::$_config['SITES'][WOME::$_site['name']] = array_replace_recursive(
					WOME::$_config['SITES'][WOME::$_site['name']], 
					WOME::$_config['SITE']
				);
				WOME::$_config['SITE'] = WOME::$_config['SITES'][WOME::$_site['name']];
			}
			else if (is_array(WOME::$_config['SITE']))
				WOME::$_config['SITES'][WOME::$_site['name']] = WOME::$_config['SITE'];
			else if (is_array(WOME::get_config(['SITES', WOME::$_site['name']])))
				WOME::$_config['SITE'] = WOME::$_config['SITES'][WOME::$_site['name']];
		}
		
		//convert indexs of 'PAGES'
		//OPTIMISATIONS HERE !!!!!
		$pages = WOME::get_config('PAGES');
		if (is_array($pages) && isset($pages[0]))
		{
			unset (WOME::$_config['PAGES'][0]);
			foreach(WOME::$_config['PAGES'] as $index=>$page)
				WOME::$_config['PAGES'][$index] = WOME::indexs_replace($page, $pages[0]);
		}

		//convert indexs of 'SCRIPTS'
		$scripts = WOME::get_config('SCRIPTS');
		if (is_array($scripts) && isset($scripts[0]))
		{
			unset (WOME::$_config['SCRIPTS'][0]);
			foreach(WOME::$_config['SCRIPTS'] as $index=>$script)
				WOME::$_config['SCRIPTS'][$index] = WOME::indexs_replace($script, $scripts[0]);
		}
	}
	public static function		get_config($name = null){
		$i = 0;
		$config = WOME::$_config;

		if (is_array($name))								//if the config name given is an array
		{													//for each name, check if he exist
			$size = count($name);
			for ($i = 0; $i < $size; $i++)
			{
				if (isset($config[$name[$i]]))
					$config = $config[$name[$i]];
				else
					return (null);
			}
		}
		else if ($name != null && !isset($config[$name]))	//else, check if the config name exist
			return (null);
		else if (isset($config[$name]))
			$config = $config[$name];

		return (WOME::get_value($config));					//return the value if it is a function
	}
	public static function		get_option($name = null){
		return (WOME::get_config(['OPTIONS', $name]));
	}

	//PLUGINS
	public static function		import_plugin($name){
		$path = WOME::$_site['directory'].'plugins/'.$name.'/'.$name.'.php';

		//if the plugin file exist, include it
		if (file_exists($path) && !in_array($path, WOME::$included))
		{
			//include the script file
			include($path);
			WOME::$included[] = $path;
		}
	}
	public static function		add_plugin($name, $datas){
		$datas = WOME::get_value($datas);

		//if the datas are valid
		if (is_string($name) && is_array($datas))
		{
			//if the plugin exist, complete it
			if (!isset(WOME::$plugins[$name]))
				WOME::$plugins[$name] = $datas;
			else
				WOME::$plugins[$name] = array_replace_recursive(WOME::$plugins[$name], $datas);
		}
	}
	public static function		plugin_event($event_name, $name = null){
		//if the event is for all the plugins
		if ($name == null)
		{
			//foreach plugin, check the functions and execute it
			foreach(WOME::$plugins as $plugin_name => $datas)
			{
				if (is_array(WOME::$plugins[$plugin_name])
					&& isset(WOME::$plugins[$plugin_name][$event_name])
					&& is_callable(WOME::$plugins[$plugin_name][$event_name]))
				{
					WOME::$plugins[$plugin_name][$event_name]();
				};
			};
		}
		//else for one plugin :
		else if (isset(WOME::$plugins[$name]))
		{
			//check the function and execute it
			if (is_array(WOME::$plugins[$name])
				&& isset(WOME::$plugins[$name][$event_name])
				&& is_callable(WOME::$plugins[$name][$event_name]))
			{
				WOME::$plugins[$name][$event_name]();
			};
		}
	}

	//LOG, INFORMATIONS AND HELP
	public static function		log($type = '', $name = ''){
		if (WOME::get_option('use_logs') !== false)
		{
			if (!isset(WOME::$_site['logs']['time_start']))
			{
				WOME::$_site['logs']['time_start'] = microtime(true);
				$time = 0;
			}
			else
				$time = microtime(true) - WOME::$_site['logs']['time_start'];
			WOME::$_site['logs'][] = [number_format((float)$time * 1000, 2, '.', ''), $type, $name];
		};

		//return the last index
		end(WOME::$_site['logs']);
		return (key(WOME::$_site['logs']));
	}
	private static function		set_log_scope(){
	
	}
	public static function		log_complete($key, $str){
		if (WOME::get_option('use_logs') !== false
		&& isset(WOME::$_site['logs'][$key]))
		{
			WOME::$_site['logs'][$key][2] .= $str;
		}
	}
	public static function		print_logs(){
		$logs = WOME::$_site['logs'];
		$count = count($logs) - 1;
		for($i = 0; $i < $count; $i++)
		{
			if (is_array($logs[$i]))
			{
				if (isset($logs[$i][1])
				&& $logs[$i][1] != '')
					echo('<B>'.$logs[$i][1].'</B></BR>');
				if (isset($logs[$i][2])
				&& $logs[$i][2] != '')
					echo(' '.$logs[$i][2].'</BR>');
			}
		}
	}
	public static function		error($number, $exit = false){
		$errors = [
			//100 : files or read errors
			100 => ['can\'t read the wome configuration file "config.php"', true],
			110 => ['can\'t read the website configuration file "config.php"', false],
			111 => ['can\'t read the website directory', true],

			//200 :  configuration errors
			200 => ['domain no found in the configuration', true],

			//500 : connection and database errors
			500 => ['unable to connect to database', true],
			510 => ['error with a sql request', false],
		];

		if (isset($errors[$number]))
		{
			$message = '<H1>An error has occurred.</H1>
						<H3>Please try to access the site later, the error may be temporary.</H3>
						<I>Note: Your computer works, you are connected to the Internet.<BR/>
						The error is caused by a configuration error of WOME, the engine of your website.<BR/>
						If you are the administrator, please check your configuration. </I><BR/>
						<BR/>
						<B>Error Number: WOME'.$number.' </B>('.$errors[$number][0].')';
			//exit wome
			if ($exit == true || $errors[$number][1] == true)
			{
				WOME::log('CRITICAL ERROR', 'WOME'.$number.' - '.$errors[$number][0]);
				exit($message);
			}
			else
				WOME::log('ERROR', 'WOME'.$number.' - '.$errors[$number][0]);
		};
	}
}

class	WOME_FORM
{
	//STATIC VARIABLES
	private static				$forms = [];

	//STATIC FUNCTIONS
	public static function		add_form($name, $fields = [], $labels = [], $php_function = null, $js_function = null){
		if (!isset(WOME_FORM::$forms[$name]))
			WOME_FORM::$forms[$name] = [];
		WOME_FORM::$forms[$name]['fields'] = $fields;
		WOME_FORM::$forms[$name]['labels'] = $labels;
		WOME_FORM::$forms[$name]['php_function'] = $php_function;
		WOME_FORM::$forms[$name]['js_function'] = $js_function;
	}

	//VARIABLES
	public						$name = null;

	//MAIN
	public function				__construct($name) {
		if (!isset(WOME_FORM::$forms[$name]))
		{
			WOME_FORM::$forms[$name]['sended'] = false;
			WOME_FORM::$forms[$name]['validated'] = false;

			//if the form exists
			$properties = WOME::get_config(['FORMS', $name]);
			if (is_array($properties))
			{
				//if the form file exists, include it
				$file = WOME::$_site['directory'].'forms/'.$properties[0];
				if (file_exists($file))
					include($file);
			}
		}

		//if the form has been added		(f**ck you english!)
		if (isset(WOME_FORM::$forms[$name]))
		{
			$this->name = $name;
		}
	}

	//PRINT FORMS
	public function				check($values, &$errors = []){
		//check the form has been send
		$fields = WOME_FORM::$forms[$this->name]['fields'];
		$errors = [];

		foreach($fields as $field_name => $properties)
		{
			if (!isset($values[$field_name]))
				$values[$field_name] = null;
			
			//check the field
			$error = $this->check_field($field_name, $values[$field_name], $values);
			if ($error !== true)
				$errors[$field_name] = $error;
		}

		//do function
		if (empty($errors)
		&&	is_callable(WOME_FORM::$forms[$this->name]['php_function']))
		{
			//do the function
			$function = WOME_FORM::$forms[$this->name]['php_function'];
			$function($values);
		}

		return (empty($errors));
	}
	public function				check_field($field_name, $value, $values = []){
		//get properties
		$properties = WOME::in_array(WOME_FORM::$forms, [$this->name, 'fields', $field_name], false);
		if ($properties === false)
			return (false);

		//for each property, check it
		$error = true;

		foreach($properties as $name => $property)
		{
			if (empty($value) && WOME::in_ultimate_array($properties, 'required', false))								//required
				$error = 'required';
			else if ($name === 'equal_to' && isset($values[$property]) && $value != $values[$property])						//equal to
				$error = 'equal_to';

			if (!empty($value) && !WOME::in_ultimate_array($properties, 'required', false))
			{
				if ($name === 'min_length' && strlen($value) < $property)													//min length
					$error = 'min_length';
				else if ($name === 'max_length' && strlen($value) > $property)												//max length
					$error = 'max_length';
				else if ($property === 'email' && !preg_match('/^[A-Z0-9._%+-]+@(?:[A-Z0-9-]+\.)+[A-Z]{2,4}$/i', $value))	//email
					$error = 'email';
				else if ($name === 'match' && !preg_match($property, $value))												//match
					$error = 'match';
				else if ($name === 'function' && is_callable($property))													//personal function
				{
					$return = $property($value, $values);
					if ($return && $return !== true)
						$error = $return;
				}
			}

			if ($error !== true)
				return (WOME::in_array(WOME_FORM::$forms, [$this->name, 'labels', $field_name, $error], $error));
		}
		return (true);
	}

	public function				get_tagname($properties = null){
		if (is_array($properties))
			$properties = WOME::associative_implode($properties, ' = "', '" ');
		return ('
			<form method = "POST" action = "'.WOME::$_page['path'].'" name = "'.$this->name.'" '.$properties.'>
				<input type = "hidden" name = "wome-name" value = "'.$this->name.'"/>
				<input type = "hidden" name = "wome-type" value = "form"/>
		');
	}
	public function				get_field($name, $properties = null){
		//check the field
		if (isset(WOME_FORM::$forms[$this->name]['fields'][$name]))
		{
			$field = WOME_FORM::$forms[$this->name]['fields'][$name];
			if (is_array($properties))
				$field = array_merge($field, $properties);

			//init vars
			$attr = '';
			$content = '';

			if (isset($field['type']))
			{
				if ($field['type'] == 'textarea'
				||	$field['type'] == 'select')
					$type = $field['type'];
				else
				{
					$type = 'input';
					$attr .= ' type = "'.$field['type'].'"';
				}
			}
			else
			{
				$type = 'input';
				$attr .= ' type = "text"';
			}

			//name
			if (isset($field['name']))
				$attr .= ' name = "'.$field['name'].'"';
			else
				$attr .= ' name = "'.$name.'"';

			//defaut
			if (isset($field['defaut']))
				$attr .= ' placeholder = "'.$field['defaut'].'"';

			//other attr
			foreach ($field as $property => $value)
			{
				//class
				if ($property === 'class' || $property === 'id')
					$attr .= ' '.$property.'="'.$value.'"';

				//value
				if ($property === 'value')
				{
					if ($type == 'input')
						$attr .= ' '.$property.'="'.$value.'"';
					else if ($type == 'textarea')
						$content .= $value;
				}

				//content
				if ($property === 'content')
				{
					if ($type == 'select')
					{
						if (is_array($value))
						{
							foreach ($value as $name => $label)
							{
								$content .= '<option value = "'.$name.'"';
								if ($name == $field['value'] || (!isset($field['value']) && $name == $field['defaut']))
									$content .= ' selected = "selected"';
								$content .= '>'.$label.'</option>';
							}
						}
					}
					else
						$content .= $value;
				}
			}

			//return
			if ($type == 'input')
				return ('<input '.$attr.'/>');
			else
				return ('<'.$type.' '.$attr.'>'.$content.'</'.$type.'>');
		}
	}
	public function				get_submit($properties = null){
		if (is_array($properties))
			$properties = WOME::associative_implode($properties, ' = "', '" ');
		return ('<input type = "submit" '.$properties.'/>');
	}
	public function				get_js(){
		//field properties
		$fields = WOME_FORM::$forms[$this->name]['fields'];
		$labels = WOME_FORM::$forms[$this->name]['labels'];
		$fields_properties = [];
		$fields_labels = [];

		$allowed_values = ['required', 'email', 'autoupload'];
		$allowed_properties = ['required', 'equal_to', 'min_length', 'max_length', 'email', 'match', 'value', 'defaut', 'autoupload'];
		$allowed_labels = ['required', 'equal_to', 'min_length', 'max_length', 'email', 'match'];

		foreach($fields as $field => $properties)
		{
			foreach($properties as $property => $value)
			{
				if (is_integer($property) && in_array($value, $allowed_values))
				{
					$fields_properties[$field][$value] = true;
					if (in_array($value, $allowed_labels))
						$fields_labels[$field][$value] = $labels[$field][$value];
				}
				else if (in_array($property, $allowed_properties))
				{
					$fields_properties[$field][$property] = $value;
					if (in_array($property, $allowed_labels))
						$fields_labels[$field][$property] = $labels[$field][$property];
				}

				//function exception
				if ($property === 'function')
					$fields_properties[$field]['function'] = true;
			}
		}

		return (
			'WOME.form.add("'.$this->name.'",
				'.json_encode($fields_properties).',
				'.json_encode($fields_labels).',
				function(){
					'.WOME_FORM::$forms[$this->name]['js_function'].'
				}
			);'
		);
	}
}

WOME::init();

?>
