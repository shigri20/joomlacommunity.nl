<?php
/**
* @package RSComments!
* @copyright (C) 2015 www.rsjoomla.com
* @license GPL, http://www.gnu.org/licenses/gpl-2.0.html
*/

defined('_JEXEC') or die('Restricted access');

require_once JPATH_SITE.'/components/com_rscomments/models/comments.php';
require_once JPATH_SITE.'/components/com_rscomments/helpers/emoticons.php';

abstract class RSCommentsHelper
{
	protected static $groups = null;
	protected static $users = null;
	
	// Get component configuration
	public static function getConfig($name = null) {
		static $config;
		
		if (!is_object($config)) {
			$db		= JFactory::getDbo();
			$query	= $db->getQuery(true);
			$config = new stdClass();
			
			$query->clear();
			$query->select($db->qn('params'));
			$query->from($db->qn('#__extensions'));
			$query->where($db->qn('type').' = '.$db->q('component'));
			$query->where($db->qn('element').' = '.$db->q('com_rscomments'));
			$db->setQuery($query);
			$params = $db->loadResult();
			
			// Convert the params to an object.
			if (is_string($params)) {
				$temp = new JRegistry;
				$temp->loadString($params);
				$config = $temp->toObject();
			}
		}
		
		if ($name != null) {
			if (isset($config->{$name})) return $config->{$name};
				else return false;
		} else {
			return $config;
		}
	}
	
	// Check for Joomla! Version
	public static function isJ3() {
		return version_compare(JVERSION, '3.0', '>=');
	}
	
	// Show date based on the specific date mask
	public static function showDate($date, $format = null) {
		$date_format = is_null($format) ? RSCommentsHelper::getConfig('date_format') : $format;
		return JHTML::date($date, $date_format);
	}
	
	// Remove {rscomments on/off} placeholder
	public static function clean(&$content) {
		$pattern = '/{rscomments\s+(on|off)}/is';

		if (isset($content->text)) 
			$content->text = preg_replace($pattern, '', $content->text);
		
		if (isset($content->introtext)) 
			$content->introtext = preg_replace($pattern, '', $content->introtext);
		
		if (isset($content->fulltext))
			$content->fulltext = preg_replace($pattern, '', $content->fulltext);
	}
	
	// Check if commenting is enabled
	public static function rscOn($content) {
		if(isset($content) && preg_match('/{rscomments\s+on}/is', $content)) 
			return true;
		
		return false;
	}
	
	// Check if commenting is disabled
	public static function rscOff($content) {
		if(isset($content) && preg_match('/{rscomments\s+off}/is', $content))
			return true;
		
		return false;
	}
	
	// Get the template
	public static function getTemplate() {
		return RSCommentsHelper::getConfig('template');
	}
	
	// Get comments number
	public static function getCommentsNumber($id, $article = false, $option = '') {
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		
		$query->clear()
			->select('COUNT('.$db->qn('IdComment').')')
			->from($db->qn('#__rscomments_comments'))
			->where($db->qn('id').' = '.(int) $id)
			->where($db->qn('published').' = 1');
		
		if ($article)
			$query->where($db->qn('option').' = '.$db->q('com_content'));
		else 
			$query->where($db->qn('option').' = '.$db->q($option));
		
		$db->setQuery($query);
		return (int) $db->loadResult();
	}
	
	// Get current page url
	public static function getUrl() {
		$uri	= (string) JURI::getInstance();
		$base	= JURI::base();
		$url	= str_replace($base,'',$uri);
		
		return $url ? base64_encode($url) : '';
	}
	
	// Load language file
	public static function loadLang($admin = false) {
		$lang = JFactory::getLanguage();
		$from = $admin ? JPATH_ADMINISTRATOR : JPATH_SITE;
		
		$lang->load('com_rscomments', $from, 'en-GB', true);
		$lang->load('com_rscomments', $from, $lang->getDefault(), true);
		$lang->load('com_rscomments', $from, null, true);
	}
	
	// Get user IP address
	public static function getIP($check_for_proxy = false) {
		$ip = $_SERVER['REMOTE_ADDR'];

		if ($check_for_proxy) {
			$headers = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'HTTP_VIA', 'HTTP_X_COMING_FROM', 'HTTP_COMING_FROM');
			foreach ($headers as $header)
				if (!empty($_SERVER[$header]))
					$ip = $_SERVER[$header];
		}

		return $ip;
	}
	
	// Routing function
	public static function route($url, $xhtml=true, $Itemid='') {
		if (strpos($url, 'Itemid=') === false) {
			if (!$Itemid) {
				$Itemid = JFactory::getApplication()->input->getInt('Itemid');
				if ($Itemid)
					$Itemid = 'Itemid='.$Itemid;
			} elseif ((int) ($Itemid)) {
				$Itemid = 'Itemid='.(int) $Itemid;
			}

			if ($Itemid)
				$url .= (strpos($url, '?') === false) ? '?'.$Itemid : '&'.$Itemid;
		}

		return JRoute::_($url, $xhtml);
	}
	
	// Convert new lines to <br /> tag
	public static function newlinetobr($text) {
		$text = str_replace("\r",'',$text);
		$text = str_replace("\n",'<br/>',$text);
		
		return $text;
	}
	
	// Clean comment
	public static function cleanComment($comment) {
		$patterns		= array();
		$replacements	= array();
		
		$patterns[] = '/\[b\](.*?)\[\/b\]/i';
		$replacements[] = '\\1';

		$patterns[] = '/\[i\](.*?)\[\/i\]/i';
		$replacements[] = '\\1';

		$patterns[] = '/\[u\](.*?)\[\/u\]/i';
		$replacements[] = '\\1';

		$patterns[] = '/\[s\](.*?)\[\/s\]/i';
		$replacements[] = '\\1';

		$patterns[] = '/\[url\]([ a-zA-Z0-9\:\/\-\?\&\.\=\_\~\#\']*)\[\/url\]/i';
		$replacements[] = '\\1';

		$patterns[] = '#\[img\](http:\/\/)?([^\s\<\>\(\)\"\']*?)\[\/img\]#i';
		$replacements[] = '\\2';

		$patterns[] = '#\[code\](.*?)\[\/code\]#ism';
		$replacements[] = '\\1';
		
		$patterns[] = '/\[youtube\](.+?)\[\/youtube\]/';
		$replacements[] = '\\1';
		
		$patterns[] = '/\[vimeo\](.+?)\[\/vimeo\]/';
		$replacements[] = '\\1';
		
		$comment = preg_replace($patterns, $replacements, $comment);
		
		// QUOTE
		$quotePattern = '#\[quote\s?name=\"([^\"\'\<\>\(\)]+)+\"\](<br\s?\/?\>)*(.*?)(<br\s?\/?\>)*\[\/quote\]#i';
		$quoteReplace = '\\3';
		while(preg_match($quotePattern, $comment)) {
			$comment = preg_replace($quotePattern, $quoteReplace, $comment);
		}
		$quotePattern = '#\[quote[^\]]*?\](<br\s?\/?\>)*([^\[]+)(<br\s?\/?\>)*\[\/quote\]#i';
		$quoteReplace = '\\2';
		while(preg_match($quotePattern, $comment)) {
			$comment = preg_replace($quotePattern, $quoteReplace, $comment);
		}

		$comment = preg_replace('#\[\/?(b|i|u|s|url|img|list|quote|code)\]#', '', $comment);
		return $comment;
	}
	
	// Parse comment
	public static function parseComment($comment, $permissions) {
		$config = RSCommentsHelper::getConfig();
		
		$patterns		= array();
		$replacements	= array();
		
		// B
		$patterns[] = '/\[b\](.*?)\[\/b\]/i';
		$replacements[] = (isset($permissions['bb_bold']) && $permissions['bb_bold']) ? '<b>\\1</b>' : '\\1';

		// I
		$patterns[] = '/\[i\](.*?)\[\/i\]/i';
		$replacements[] = (isset($permissions['bb_italic']) && $permissions['bb_italic']) ? '<i>\\1</i>' : '\\1';

		// U
		$patterns[] = '/\[u\](.*?)\[\/u\]/i';
		$replacements[] = (isset($permissions['bb_underline']) && $permissions['bb_underline']) ? '<u>\\1</u>' : '\\1';

		// S
		$patterns[] = '/\[s\](.*?)\[\/s\]/i';
		$replacements[] = (isset($permissions['bb_stroke']) && $permissions['bb_stroke']) ? '<strike>\\1</strike>' : '\\1';

		// URL
		$nofollow = $config->no_follow ? 'rel="nofollow"' : '';
		$patterns[] = '/(\[url=)(.+)(\])(.+)(\[\/url\])/i';
		$replacements[] = (isset($permissions['bb_url']) && $permissions['bb_url']) ? '<a href="\\2" '.$nofollow.'>\\4</a>' : '\\2';
		
		$patterns[] = '/\[url\]([ a-zA-Z0-9\:\/\-\?\&\.\=\_\~\#\']*)\[\/url\]/i';
		$replacements[] = (isset($permissions['bb_url']) && $permissions['bb_url']) ? '<a href="\\1" '.$nofollow.'>\\1</a>' : '\\1';
		
		// IMG
		$patterns[] = '#\[img\](http:\/\/)?([^\s\<\>\(\)\"\']*?)\[\/img\]#i';
		$replacements[] = (isset($permissions['bb_image']) && $permissions['bb_image']) ? '<img src="http://\\2" alt="" border="0" />' : '\\2';
		$patterns[] = '#\[img\](.*?)([^\s<>()\"\']*?)(.*?)\[\/img\]#i';
		$replacements[] = '[img:error]';

		// CODE
		$patterns[] = '#\[code\](.*?)\[\/code\]#ism';
		$replacements[] = (isset($permissions['bb_code']) && $permissions['bb_code']) ? '<span class="rsc_code">'.JText::_('COM_RSCOMMENTS_CODE').' : <pre>\\1</pre></span>' : '\\1';

		// Youtube
		$patterns[] = '#\[youtube\]http\://www\.youtube\.com/watch\?v\=(.+?)\[\/youtube\]#';
		$replacements[] = (isset($permissions['bb_videos']) && $permissions['bb_videos']) ? '<iframe width="560" height="315" src="http://www.youtube.com/embed/\\1" frameborder="0" allowfullscreen></iframe>' : 'http://www.youtube.com/v/\\1';
		
		// Vimeo
		$patterns[] = '#\[vimeo\]https://vimeo.com/([A-Za-z0-9-_]+)\[\/vimeo\]#';
		$replacements[] = (isset($permissions['bb_videos']) && $permissions['bb_videos']) ? '<iframe src="https://player.vimeo.com/video/\\1?byline=0&portrait=0" width="500" height="281" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>' : '<a href="https://vimeo.com/\\1" target="_blank">\\1</a>';
		
		
		$comment = preg_replace($patterns, $replacements, htmlentities($comment,ENT_COMPAT,'UTF-8'));
		$comment = str_replace('&quot;', '"', $comment);
		$comment = RSCommentsHelper::newlinetobr($comment);
		
		// QUOTE
		$quotePattern = '#\[quote\s?name=\"([^\"\'\<\>\(\)]+)+\"\](<br\s?\/?\>)*(.*?)(<br\s?\/?\>)*\[\/quote\]#i';
		$quoteReplace = '<span class="rsc_quote"> <strong>\\1</strong> '.JText::_('COM_RSCOMMENTS_USER_SAID').': <blockquote>\\3</blockquote> </span>';
		while(preg_match($quotePattern, $comment)) {
			$comment = preg_replace($quotePattern, $quoteReplace, $comment);
		}
		$quotePattern = '#\[quote[^\]]*?\](<br\s?\/?\>)*([^\[]+)(<br\s?\/?\>)*\[\/quote\]#i';
		$quoteReplace = '<span class="rsc_quote">'.JText::_('COM_RSCOMMENTS_QUOTE_SINGLE').' : <blockquote>\\2</blockquote> </span>';
		while(preg_match($quotePattern, $comment)) {
			$comment = preg_replace($quotePattern, $quoteReplace, $comment);
		}

		// LIST
		$matches = array();
		$matchCount = preg_match_all('#\[list\](<br\s?\/?\>)*(.*?)(<br\s?\/?\>)*\[\/list\]#is', $comment, $matches);
		for ($i = 0; $i < $matchCount; $i++) {
			$textBefore = preg_quote($matches[2][$i]);
			$matches[2][$i] = trim($matches[2][$i]);
			$textAfter = preg_replace('#(<br\s?\/?\>)*\[\*\](<br\s?\/?\>)*#is', "</li>\n<li>", $matches[2][$i]);
			$textAfter = preg_replace("#^</?li>#", "", $textAfter);
			$textAfter = str_replace("\n</li>", "</li>", $textAfter."</li>");
			$comment = preg_replace('#\[list\](<br\s?\/?\>)*' . $textBefore . '(<br\s?\/?\>)*\[/list\]#is', "\n<ul>$textAfter\n</ul>\n", $comment);
		}
		$matches = array();
		$matchCount = preg_match_all('#\[list=(a|A|i|I|1)\](<br\s?\/?\>)*(.*?)(<br\s?\/?\>)*\[\/list\]#is', $comment, $matches);
		for ($i = 0; $i < $matchCount; $i++) {
			$textBefore = preg_quote($matches[3][$i]);
			$matches[3][$i] = trim($matches[3][$i]);
			$textAfter = preg_replace('#(<br\s?\/?\>)*\[\*\](<br\s?\/?\>)*#is', "</li>\n<li>", $matches[3][$i]);
			$textAfter = preg_replace("#^</?li>#", '', $textAfter);
			$textAfter = str_replace("\n</li>", "</li>", $textAfter."</li>");
			$comment = preg_replace('#\[list=(a|A|i|I|1)\](<br\s?\/?\>)*' . $textBefore . '(<br\s?\/?\>)*\[/list\]#is', "\n<ol type=\\1>$textAfter\n</ol>\n", $comment);
		}	

		$comment = preg_replace('#\[\/?(b|i|u|s|url|img|list|quote|code)\]#', '', $comment);
		if($config->enable_smiles == 1) $comment = RSCommentsEmoticons::cleanText($comment);
		$comment = RSCommentsHelper::breakwords($comment);
		
		return $comment;
	}
	
	// Break long words
	public static function breakwords($comment) {
		$length = RSCommentsHelper::getConfig('word_length');
		$length = empty($length) ? 15 : $length;
		$marker = ' ';

		$text = $comment;
		$text = preg_replace('#<img[^\>]+/>#isU', '', $text);
		$text = preg_replace('#<a.*?>(.*?)</a>#isU', '', $text);
		$text = preg_replace('#<object.*?>(.*?)</object>#isU', '', $text);
		$text = preg_replace('#<code.*?>(.*?)</code>#isU', '', $text);
		$text = preg_replace('#<embed.*?>(.*?)</embed>#isU', '', $text);
		$text = preg_replace('#(^|\s|\>|\()((http://|https://|news://|ftp://|www.)\w+[^\s\[\]\<\>\"\'\)]+)#i', '', $text);
		
		$matches = array();
		$matchCount = preg_match_all('#([^\s<>\'\"/\.\x133\x151\\-\?&%=\n\r\%]{'.$length.'})#iu', $text, $matches);
		
		for ($i = 0; $i < $matchCount; $i++) {
			$comment = preg_replace("#(".preg_quote($matches[1][$i], '#').")#iu", "\\1".$marker, $comment);
		}
		$comment = preg_replace('#('.preg_quote($marker, '#').'\s)#iu', " ", $comment);
		unset($matches);

		return $comment;
	}
	
	// Get user profile
	public static function getUserSocialLink($user_id) {
		$config		= RSCommentsHelper::getConfig();
		$link		= $config->user_social_link;
		$url		= '';
		
		if (!$link || $user_id == 0) return '';
		switch ($link) {
			// Community Builder
			case 'comprofiler':
			// get link for CB
				$url = JURI::root().'index.php?option=com_comprofiler&task=userprofile&user='.$user_id;
			break;

			 // JomSocial
			case 'community':
				require_once JPATH_BASE.'/components/com_community/libraries/core.php';
				$url = CRoute::_('index.php?option=com_community&view=profile&userid='.$user_id);
			break;
		}
		
		return $url;
	}
	
	// Get user comments
	public static function getCBUserComments($userid, $start, $items_no) {
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		$return = array();
		
		$query->clear()
			->select($db->qn('IdComment'))->select($db->qn('comment'))->select($db->qn('url'))
			->from($db->qn('#__rscomments_comments'))
			->where($db->qn('uid').' = '.(int) $userid)
			->where($db->qn('published').' = 1')
			->order($db->qn('IdComment').' DESC');
		
		$db->setQuery($query, $start, $items_no);
		if ($comments = $db->loadObjectList()) {
			foreach($comments as $comment) {
				$newcomment = new stdClass();
				$newcomment->url 		= $comment->url ? JURI::root().base64_decode($comment->url) : '';
				$newcomment->comment 	= RSCommentsHelper::cleanComment($comment->comment);
				$return[]	= $newcomment;
			}
		}

		return $return;
	}
	
	// Get users total number of comments
	public static function getTotalCBUserComments($uid) {
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		
		$query->clear()
			->select('COUNT('.$db->qn('IdComment').')')
			->from($db->qn('#__rscomments_comments'))
			->where($db->qn('uid').' = '.(int) $uid)
			->where($db->qn('published').' = 1');
		
		$db->setQuery($query);
		return (int) $db->loadResult();
	}
	
	// Set the image path
	public static function ImagePath($file) {
		$template	= RSCommentsHelper::getTemplate();
		$imagePath	= JURI::root().'components/com_rscomments/assets/images/'.$file;

		if(file_exists(JPATH_SITE.'/components/com_rscomments/designs/'.$template.'/'.'images/'.$file))
			$imagePath = JURI::root().'components/com_rscomments/designs/'.$template.'/images/'.$file;

		return $imagePath;
	}
	
	// Show icons
	public static function showIcons($permissions) {
		$config	= RSCommentsHelper::getConfig();
		$icons	= array();
		
		if ($config->enable_bbcode == 1 && (isset($permissions['bbcode']) && $permissions['bbcode'])) {
			$bbcode = RSCommentsHelper::createBBs($permissions);
			$icons  = array_merge($bbcode, $icons); 
		}
		
		if ($config->enable_smiles == 1) {
			$smileys = array('<a href="javascript:void(0);" onclick="rsc_show_emoticons(this);" class="rsc_emoti_on btn btn-small '.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_EMOTICONS')).'"><i class="rsc_emoti_on fa fa-smile-o"></i></a>');
			$icons = array_merge($icons, $smileys);
			
		}
		
		return $icons;
	}
	
	// Create BBCodes
	public static function createBBs($permissions) {
		$bbcode = array();
		
		if (isset($permissions['bb_bold']) && $permissions['bb_bold']) {
			$bbcode[] = '<a href="javascript:void(0);" onclick="rsc_addTags(\'[b]\',\'[/b]\',\'rsc_comment\');" class="btn btn-small '.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_BOLD')).'"><i class="fa fa-bold"></i></a>';
		}
		
		if (isset($permissions['bb_italic']) && $permissions['bb_italic']) {
			$bbcode[] = '<a href="javascript:void(0);" onclick="rsc_addTags(\'[i]\',\'[/i]\',\'rsc_comment\');" class="btn btn-small '.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_ITALIC')).'"><i class="fa fa-italic"></i></a>';
		}
		
		if (isset($permissions['bb_underline']) && $permissions['bb_underline']) {
			$bbcode[] = '<a href="javascript:void(0);" onclick="rsc_addTags(\'[u]\',\'[/u]\',\'rsc_comment\');" class="btn btn-small '.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_UNDERLINE')).'"><i class="fa fa-underline"></i></a>';
		}
		
		if (isset($permissions['bb_stroke']) && $permissions['bb_stroke']) {
			$bbcode[] = '<a href="javascript:void(0);" onclick="rsc_addTags(\'[s]\',\'[/s]\',\'rsc_comment\');" class="btn btn-small '.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_STROKE')).'"><i class="fa fa-strikethrough"></i></a>';
		}
		
		if (isset($permissions['bb_quote']) && $permissions['bb_quote']) {
			$bbcode[] = '<a href="javascript:void(0);" onclick="rsc_addTags(\'[quote]\',\'[/quote]\',\'rsc_comment\');" class="btn btn-small '.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_QUOTE')).'"><i class="fa fa-quote-right"></i></a>';
		}
		
		if (isset($permissions['bb_lists']) && $permissions['bb_lists']) {
			$bbcode[] = '<a href="javascript:void(0);" onclick="rsc_createList(\'[LIST=1]\',\'[/LIST]\',\'rsc_comment\');" class="btn btn-small '.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_ORDERED')).'"><i class="fa fa-list-ul"></i></a>';
			$bbcode[] = '<a href="javascript:void(0);" onclick="rsc_createList(\'[LIST]\',\'[/LIST]\',\'rsc_comment\');" class="btn btn-small '.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_UNORDERED')).'"><i class="fa fa-list-ol"></i></a>';
		}
		
		if (isset($permissions['bb_image']) && $permissions['bb_image']) {
			$bbcode[] = '<a href="javascript:void(0);" onclick="rsc_createImage(\'rsc_comment\',\''.JText::_('COM_RSCOMMENTS_ADD_IMAGE',true).'\');" class="btn btn-small '.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_ADD_IMAGE')).'"><i class="fa fa-picture-o"></i></a>';
		}
		
		if (isset($permissions['bb_url']) && $permissions['bb_url']) {
			$bbcode[] = '<a href="javascript:void(0);" onclick="rsc_createUrl(\'rsc_comment\',\''.JText::_('COM_RSCOMMENTS_ADD_URL',true).'\');" class="btn btn-small '.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_ADD_URL')).'"><i class="fa fa-link"></i></a>';
		}
		
		if (isset($permissions['bb_code']) && $permissions['bb_code']) {
			$bbcode[] = '<a href="javascript:void(0);" onclick="rsc_addTags(\'[code]\',\'[/code]\',\'rsc_comment\');" class="btn btn-small '.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_ADD_CODE')).'"><i class="fa fa-code"></i></a>';
		}
		
		if (isset($permissions['bb_videos']) && $permissions['bb_videos']) {
			$bbcode[] = '<a href="javascript:void(0);" onclick="rsc_addTags(\'[youtube]\',\'[/youtube]\',\'rsc_comment\');" class="btn btn-small '.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_ADD_YOUTUBE')).'"><i class="fa fa-youtube"></i></a>';
			$bbcode[] = '<a href="javascript:void(0);" onclick="rsc_addTags(\'[vimeo]\',\'[/vimeo]\',\'rsc_comment\');" class="btn btn-small '.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_ADD_VIMEO')).'"><i class="fa fa-vimeo"></i></a>';
		}
		
		return $bbcode;
	}
	
	// Get avatar image
	public static function getAvatar($user_id, $useremail = null, $module = null, $class = null) {
		$db			= JFactory::getDbo();
		$query		= $db->getQuery(true);
		$avatar 	= RSCommentsHelper::getConfig('avatar');
		$size	 	= RSCommentsHelper::getConfig('avatar_size');
		$size		= $size ? $size : 60;
		$theclass	= $module ? 'rsc_module_avatar' : 'rsc_avatar';
		$html		= '';
		
		if (!is_null($class)) {
			$theclass = $class;
		}
		
		if (!$avatar) 
			return $html;
		
		switch ($avatar) {
			// Gravatar
			case 'gravatar':
				$user = JFactory::getUser($user_id);
				$email = ($user_id == 0 && !is_null($useremail)) ? md5(strtolower(trim($useremail))) : md5(strtolower(trim($user->get('email'))));
				$html .= '<img src="https://www.gravatar.com/avatar/'.$email.'?d='.urlencode(JURI::root().'components/com_rscomments/assets/images/user.png').'&s='.$size.'" alt="Gravatar" class="'.$theclass.'" />';
			break;
			
			// Community Builder
			case 'comprofiler':
				$query->clear()
					->select($db->qn('avatar'))
					->from($db->qn('#__comprofiler'))
					->where($db->qn('user_id').' = '.(int) $user_id);
				
				$db->setQuery($query);
				if ($avatar = $db->loadResult())
					$html .= '<img width="'.$size.'" src="'.JURI::root().'images/comprofiler/'.$avatar.'" alt="Community Builder Avatar" class="'.$theclass.'" />';
				else
					$html .= '<img width="'.$size.'" src="'.JURI::root().'components/com_comprofiler/plugin/templates/default/images/avatar/tnnophoto_n.png" alt="Community Builder Avatar" class="'.$theclass.'" />';
			break;
			
			 // JomSocial
			case 'community':
				require_once JPATH_BASE.'/components/com_community/libraries/core.php';
				$user =& CFactory::getUser($user_id);
				$html .= '<img width="'.$size.'" src="'.$user->getThumbAvatar().'" alt="JomSocial Avatar" class="'.$theclass.'" />';
			break;
			
			//Kunena
			case 'kunena':
				$query->clear()
					->select($db->qn('avatar'))
					->from($db->qn('#__kunena_users'))
					->where($db->qn('userid').' = '.(int) $user_id);
				
				$db->setQuery($query);
				$avatar = $db->loadResult();
				
				if (!$avatar)
					$avatar = 's_nophoto.jpg';
				
				$html .= '<img width="'.$size.'" src="'.JURI::root().'media/kunena/avatars/'.$avatar.'" alt="Kunena Avatar" class="'.$theclass.'" />';
			break;
			
			//Fireboard
			case 'fireboard':
				$query->clear()
					->select($db->qn('avatar'))
					->from($db->qn('#__fb_users'))
					->where($db->qn('userid').' = '.(int) $user_id);
				
				$db->setQuery($query);
				$avatar = $db->loadResult();
				
				if (!$avatar)
					$avatar = 's_nophoto.jpg';
				
				$html .= '<img width="'.$size.'" src="'.JURI::root().'images/fbfiles/avatars/'.$avatar.'" alt="Fireboard Avatar" class="'.$theclass.'" />';
			break;
			
			//EasyBlog
			case 'easyblog':
				$query->clear()
					->select($db->qn('avatar'))
					->from($db->qn('#__easyblog_users'))
					->where($db->qn('id').' = '.(int) $user_id);
				
				$db->setQuery($query);
				$avatar = $db->loadResult();
				
				$query->clear()
					->select($db->qn('params'))
					->from($db->qn('#__easyblog_configs'))
					->where($db->qn('name').' = '.$db->q('config'));
				
				$db->setQuery($query);
				$eparams = $db->loadResult();
				
				$params = new JRegistry();
				$params->loadString($eparams);
				$path = $params->get('main_avatarpath','images/easyblog_avatar/');
				
				if (empty($avatar) || $avatar == 'default.png')
					$html .= '<img width="'.$size.'" src="'.JURI::root().'components/com_easyblog/assets/images/default.png" alt="EasyBlog Avatar" class="'.$theclass.'" />';
				else
					$html .= '<img width="'.$size.'" src="'.JURI::root().$path.$avatar.'" alt="EasyBlog Avatar" class="'.$theclass.'" />';
			break;

			// EasyDiscuss
			case 'easydiscuss':
				$query->clear()
					->select($db->qn('avatar'))
					->from($db->qn('#__discuss_users'))
					->where($db->qn('id').' = '.(int) $user_id);
				$db->setQuery($query);
				$avatar = $db->loadResult();
				$query->clear()
					->select($db->qn('params'))
					->from($db->qn('#__discuss_configs'))
					->where($db->qn('name').' = '.$db->q('config'));
				$db->setQuery($query);
				$eparams = $db->loadResult();
				$params = new JRegistry();
				$params->loadString($eparams);
				$path = $params->get('main_avatarpath','images/discuss_avatar/');
				if (empty($avatar) || $avatar == 'default.png')
					$html .= '<img width="'.$size.'" src="'.JURI::root().'components/com_easydiscuss/assets/images/default.png" alt="EasyDiscuss Avatar" class="'.$theclass.'" />';
				else
					$html .= '<img width="'.$size.'" src="'.JURI::root().$path.$avatar.'" alt="EasyDiscuss Avatar" class="'.$theclass.'" />';
			break;
		}
		
		return $html;
	}
	
	// Get comment name
	public static function name($comment, $permissions) {
		$config	= RSCommentsHelper::getConfig();
		
		if ($comment->uid == 0) {
			$name		= (isset($permissions['show_emails']) && $permissions['show_emails']) ? '<a href="mailto:'.$comment->email.'">'.$comment->name.'</a>' : $comment->name;
			$cleanname	= $comment->name;
		} else {
			$user = JFactory::getUser($comment->uid);
			
			switch($config->authorname) {
				case 'username':
					$name		= (isset($permissions['show_emails']) && $permissions['show_emails']) ? '<a href="mailto:'.$user->get('email').'">'.$user->get('username').'</a>' : $user->get('username');
					$cleanname	= $user->get('username');
				break;
				
				case 'name':
					$name		= (isset($permissions['show_emails']) && $permissions['show_emails']) ? '<a href="mailto:'.$user->get('email').'">'.$user->get('name').'</a>' : $user->get('name');
					$cleanname	= $user->get('name');
				break;
				
				case 'cb':
					$db		= JFactory::getDbo();
					$query	= $db->getQuery(true);
					
					$query->clear()
						->select($db->qn('firstname'))->select($db->qn('lastname'))
						->from($db->qn('#__comprofiler'))
						->where($db->qn('user_id').' = '.(int) $user->get('id'));
					$db->setQuery($query);
					$cb = $db->loadObject();
					
					$cbname = !empty($cb) ? $cb->firstname.' '.$cb->lastname : $user->get('name');
					$name		= (isset($permissions['show_emails']) && $permissions['show_emails']) ? '<a href="mailto:'.$user->get('email').'">'.$cbname.'</a>' : $cbname;
					$cleanname	= $cbname;
				break;
			}
		}
		
		return array('name' => $name, 'cleanname' => $cleanname);
	}
	
	// Get thread status
	public static function getThreadStatus($id, $option) {
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		$hash	= md5($option.$id);
		
		static $cache = array();
		
		if (!isset($cache[$hash])) {
			$query->clear()
				->select('COUNT('.$db->qn('IdThread').')')
				->from($db->qn('#__rscomments_threads'))
				->where($db->qn('id').' = '.(int) $id)
				->where($db->qn('option').' = '.$db->q($option));
			
			$db->setQuery($query);
			$cache[$hash] = $db->loadResult();
		}

		return $cache[$hash] > 0 ? true : false;
	}
	
	// Get user permissions
	public static function getPermissions() {
		$db			 = JFactory::getDbo();
		$query		 = $db->getQuery(true);
		$user		 = JFactory::getUser();
		$groups		 = JAccess::getGroupsByUser($user->id);
		$permissions = self::getDefaultPermissions();
		$tmp		 = array();
		
		static $perm = array();
		
		if (empty($perm)) {
			// Remove the public permission
			if (!$user->guest) {
				foreach ($groups as $i => $group)
					if ($group == 1) unset($groups[$i]);
			}
			
			if (!empty($groups)) {
				foreach ($groups as $group) {
					$query->clear()
						->select($db->qn('permissions'))
						->from($db->qn('#__rscomments_groups'))
						->where($db->qn('gid').' = '.(int) $group);
					
					$db->setQuery($query);
					$permission = $db->loadResult();
					if (!empty($permission)) 
						$tmp[$group] = unserialize($permission);
				}
			}
			
			if (!empty($tmp)) {
				foreach ($tmp as $group) {
					foreach ($group as $key => $value) {
						if ($value) $permissions[$key] = $value;
							else $permissions[$key] = 0;
					}
				}
			}
			
			$perm = $permissions;
		}
		
		return $perm;
	}
	
	// Get default permissions
	public static function getDefaultPermissions() {
		return array(
			'new_comments' => 1, 'edit_own_comment' => 0, 'delete_own_comment' => 0, 'edit_comments' => 0, 'delete_comments' => 0, 'bbcode' => 1, 
			'vote_comments' => 1, 'auto_subscribe_thread' => 0, 'close_thread' => 0, 'enable_reply' => 1, 'publish_comments' => 0, 'autopublish' => 0, 
			'show_emails' => 0, 'view_ip' => 0, 'captcha' => 1, 'censored' => 1, 'flood_control' => 1, 'check_names' => 1, 
			'bb_bold' => 1, 'bb_italic' => 1, 'bb_underline' => 1, 'bb_stroke' => 1, 'bb_quote' => 1, 'bb_lists' => 0, 
			'bb_image' => 0, 'bb_url' => 0, 'bb_code' => 0, 'bb_videos' => 0
		);
	}
	
	// Check for forbidden names
	public static function forbiddenNames($name) {
		$config = RSCommentsHelper::getConfig();
		$return = false;
		$names	= strtolower(trim($config->forbiden_names));
		
		if(!empty($names)) {	
			$name = strtolower($name);
			$names = explode("\n",$names);
			foreach($names as $val)
				if(trim($val) == $name) 
					$return = true;
		}
		return $return;
	}
	
	// Check if the current user is the author
	public static function isAuthor($id) {
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		$user	= JFactory::getUser();
		
		if ($user->guest) {
			$query->select($db->qn('ip'))
				  ->from($db->qn('#__rscomments_comments'))
				  ->where($db->qn('IdComment').' = '.(int) $id);
			$db->setQuery($query);
			$ip = $db->loadResult();
			
			return ($ip == $_SERVER['REMOTE_ADDR']);
		} else {
			$query->select($db->qn('uid'))
				  ->from($db->qn('#__rscomments_comments'))
				  ->where($db->qn('IdComment').' = '.(int) $id);
			$db->setQuery($query);
			$uid = (int) $db->loadResult();
			
			return ($uid == $user->get('id'));
		}
	}
	
	// Check URL
	public static function checkURL($url) {
		// SCHEME
		$urlregex = "#^(https?|ftp)\:\/\/";

		// USER AND PASS (optional)
		$urlregex .= "([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?";

		// HOSTNAME OR IP
		$urlregex .= "[a-z0-9+\$_-]+(\.[a-z0-9+\$_-]+)*"; // http://x = allowed (ex. http://localhost, http://routerlogin)
		//$urlregex .= "[a-z0-9+\$_-]+(\.[a-z0-9+\$_-]+)+"; // http://x.x = minimum
		//$urlregex .= "([a-z0-9+\$_-]+\.)*[a-z0-9+\$_-]{2,3}"; // http://x.xx(x) = minimum
		//use only one of the above

		// PORT (optional)
		$urlregex .= "(\:[0-9]{2,5})?";
		// PATH (optional)
		$urlregex .= "(\/([a-z0-9+\$_-]\.?)+)*\/?";
		// GET Query (optional)
		$urlregex .= "(\?[a-z+&\$_.-][a-z0-9;:@/&%=+\$_.-]*)?";
		// ANCHOR (optional)
		$urlregex .= "(\#[a-z_.-][a-z0-9+\$_.-]*)?\$#is";

		// check
		if (preg_match($urlregex, $url)) 
			return true;
		else return false;
	}
	
	// Censor bad words
	public static function censor($text) {
		$config		= RSCommentsHelper::getConfig();
		$replace	= trim($config->censored_words);
		$with		= empty($config->replace_censored) ? '***' : $config->replace_censored;
		
		if (!empty($replace)) {
			$replace = explode(',',$replace);
			foreach($replace as $value) {
				if(empty($value)) continue;
				$text = preg_replace('#'.$value.'#is', $with, $text);
			}
		}
		return $text;
	}
	
	// Check for flood control
	public static function flood($ip) {
		$config = RSCommentsHelper::getConfig();
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		
		$query->clear()
			->select('COUNT('.$db->qn('IdComment').')')
			->from($db->qn('#__rscomments_comments'))
			->where($db->qn('ip').' = '.$db->q($ip))
			->where($db->q(JFactory::getDate()->toSql()).' < DATE_ADD('.$db->qn('date').', INTERVAL '.(int) $config->flood_interval.' SECOND)');
		
		$db->setQuery($query);
		$result = $db->loadResult();
		if ($result == 0) 
			return true;
		
		return false;
	}
	
	// Is the current user an admin
	public static function admin() {
		$user	= JFactory::getUser();
		$admins = self::getAdminUsers();
		
		if (in_array($user->get('id'), $admins))
			return true;
		
		return false;
	}
	
	public static function getAdminGroups() {
		if (!is_array(self::$groups)) {
			$db 	= JFactory::getDbo();
			$query 	= $db->getQuery(true);
			$query->select($db->qn('id'))
				  ->from($db->qn('#__usergroups'));
			$db->setQuery($query);
			$groups = $db->loadColumn();
			
			self::$groups = array();
			foreach ($groups as $group_id) {
				if (JAccess::checkGroup($group_id, 'core.login.admin'))
					self::$groups[] = $group_id;
				elseif (JAccess::checkGroup($group_id, 'core.admin'))
					self::$groups[] = $group_id;
			}
			
			self::$groups = array_unique(self::$groups);
		}
		
		return self::$groups;
	}
	
	public static function getAdminUsers() {
		if (!is_array(self::$users)) {
			self::$users = array();
			
			if ($groups	= self::getAdminGroups()) {
				$db 	= JFactory::getDbo();
				$query 	= $db->getQuery(true);
				$query->select($db->qn('u.id'))
					  ->from($db->qn('#__user_usergroup_map','m'))
					  ->join('right', $db->qn('#__users','u').' ON ('.$db->qn('u.id').' = '.$db->qn('m.user_id').')')
					  ->where($db->qn('m.group_id').' IN ('.implode(',', $groups).')')
					  ->group($db->qn('u.id'));
				$db->setQuery($query);
				self::$users = $db->loadColumn();
			}
		}
		
		return self::$users;
	}
	
	// Check if the user is subscribed to this thread
	public static function isSubscribed($id, $option) {
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		$user	= JFactory::getUser();
		
		if ($user->get('id') == 0) 
			return false;
		
		$query->clear()
			->select('COUNT('.$db->qn('IdSubscription').')')
			->from($db->qn('#__rscomments_subscriptions'))
			->where($db->qn('email').' = '.$db->q($user->get('email')))
			->where($db->qn('option').' = '.$db->q($option))
			->where($db->qn('id').' = '.$db->q($id));
		
		$db->setQuery($query,0,1);
		$count = $db->loadResult();
		
		return $count > 0 ? true : false;
	}
	
	// Display the comment form
	public static function displayForm($option, $id, $override = false) {
		$permissions	= self::getPermissions();
		$config			= RSCommentsHelper::getConfig();
		$uri			= JURI::getInstance();
		$doc			= JFactory::getDocument();
		
		// Check to see if thread is closed
		$status = self::getThreadStatus($id,$option);
		if ($status) {
			return;
		}
		
		// If the form is displayed in the articles layout, check for category restriction
		if($option == 'com_content') {
			$db			= JFactory::getDbo();
			$query		= $db->getQuery(true);
			$categories = RSCommentsHelper::getConfig('categories');
			
			if ($categories && !$override) {
				$query->clear()
					->select($db->qn('catid'))
					->from($db->qn('#__content'))
					->where($db->qn('id').' = '.(int) $id);
				
				$db->setQuery($query);
				$cid = (int) $db->loadResult();

				if(in_array($cid, $categories)) 
					return;
			}
		}
		
		// Check for new comment permission
		if(empty($permissions['new_comments'])) {
			$comments_denied = RSCommentsHelper::getMessage('comments_denied');
			return empty($comments_denied) ? '' : '<hr/>'.$comments_denied;
		}
		
		if ($config->form_accordion) {
			JText::script('COM_RSCOMMENTS_HIDE_FORM');
			JText::script('COM_RSCOMMENTS_SHOW_FORM');
		}
		
		require_once JPATH_SITE.'/components/com_rscomments/helpers/tooltip.php';
		
		$class = self::isJ3() ? 'JViewLegacy' : 'JView';
		if ($class == 'JView') {
			jimport('joomla.application.component.view');
		}
		
		$view = new $class(array(
			'name' => 'rscomments',
			'layout' => 'form',
			'base_path' => JPATH_SITE.'/components/com_rscomments'
		));
		
		$view->addTemplatePath(JPATH_THEMES . '/' . JFactory::getApplication()->getTemplate() . '/html/com_rscomments/' . $view->getName());
		
		$view->option		= $option;
		$view->id			= $id;
		$view->override		= $override;
		$view->config		= $config;
		$view->user			= JFactory::getUser();
		$view->permissions	= $permissions;
		$view->disable		= $view->user->get('id') != 0 ? 'disabled="disabled"' : '';
		$view->root			= $uri->toString(array('scheme','host'));
		
		return $view->loadTemplate();
	}
	
	// Deprecated function. Use: RSCommentsHelper::displayForm
	public static function showForm($option,$id,$override=false) {
		return self::displayForm($option, $id, $override);
	}
	
	// Show RSComments! layout
	public static function showRSComments($option, $id, $template = null, $container = null, $override = false) {
		// Check the category of the article
		if ($option == 'com_content') {
			$db			= JFactory::getDbo();
			$query		= $db->getQuery(true);
			$categories = RSCommentsHelper::getConfig('categories');
			
			if ($categories && !$override) {
				$query->clear()
					->select($db->qn('catid'))
					->from($db->qn('#__content'))
					->where($db->qn('id').' = '.(int) $id);
				
				$db->setQuery($query);
				$cid = (int) $db->loadResult();

				if (in_array($cid, $categories)) {
					return;
				}
			}
		}
		
		// Load the js and css code
		RSCommentsHelper::loadScripts();

		// Load language file
		JFactory::getLanguage()->load('com_rscomments');
		RSCommentsHelper::loadLang();

		$template = is_null($template) ? RSCommentsHelper::getTemplate() : $template;
		$position = RSCommentsHelper::getConfig('comment_form_position');
		
		$return  = '<div class="rscomments">';
		
		if ($position) {
			$return .= '<div id="rscomments-comment-form">'."\n".RSCommentsHelper::displayForm($option, $id, $override)."\n".'</div>';
		}
		
		$return .= RSCommentsHelper::showComments($option, $id, $template, $container, $override)."\n";
		
		if (!$position) {
			$return .= '<div id="rscomments-comment-form">'."\n".RSCommentsHelper::displayForm($option, $id, $override)."\n".'</div>';
		}

		$return .= RSCommentsHelper::initScripts($option, $id)."\n";
		$return .= '</div>';
		
		return $return;
	}
	
	public static function initScripts($option, $id) {
		require_once JPATH_SITE.'/components/com_rscomments/helpers/tooltip.php';
		
		$class = self::isJ3() ? 'JViewLegacy' : 'JView';
		if ($class == 'JView') {
			jimport('joomla.application.component.view');
		}
		
		$view = new $class(array(
			'name' => 'rscomments',
			'layout' => 'default',
			'base_path' => JPATH_SITE.'/components/com_rscomments'
		));
		
		$view->addTemplatePath(JPATH_THEMES . '/' . JFactory::getApplication()->getTemplate() . '/html/com_rscomments/' . $view->getName());
		
		$view->option		= $option;
		$view->id			= $id;
		$view->config		= RSCommentsHelper::getConfig();
		$view->user			= JFactory::getUser();
		
		return $view->loadTemplate('init');
	}
	
	public static function showComments($option, $id, $template, $container, $override, $tpl = null, $IdComment = null) {
		$permissions	= self::getPermissions();
		$config			= RSCommentsHelper::getConfig();
		$uri			= JURI::getInstance();
		$doc			= JFactory::getDocument();
		
		RSCommentsHelper::loadLang();
		
		// If the form is displayed in the articles layout, check for category restriction
		if($option == 'com_content') {
			$db			= JFactory::getDbo();
			$query		= $db->getQuery(true);
			$categories = RSCommentsHelper::getConfig('categories');
			
			if ($categories && !$override) {
				$query->clear()
					->select($db->qn('catid'))
					->from($db->qn('#__content'))
					->where($db->qn('id').' = '.(int) $id);
				
				$db->setQuery($query);
				$cid = (int) $db->loadResult();

				if(in_array($cid, $categories)) 
					return;
			}
		}
		
		require_once JPATH_SITE.'/components/com_rscomments/helpers/tooltip.php';
		
		$class = self::isJ3() ? 'JViewLegacy' : 'JView';
		if ($class == 'JView') {
			jimport('joomla.application.component.view');
		}
		
		$view = new $class(array(
			'name' => 'rscomments',
			'layout' => 'default',
			'base_path' => JPATH_SITE.'/components/com_rscomments'
		));
		
		$view->addTemplatePath(JPATH_THEMES . '/' . JFactory::getApplication()->getTemplate() . '/html/com_rscomments/' . $view->getName());
		
		$view->option		= $option;
		$view->id			= $id;
		$view->override		= $override;
		$view->config		= $config;
		$view->user			= JFactory::getUser();
		$view->permissions	= $permissions;
		$view->template		= $template;
		$view->root			= $uri->toString(array('scheme','host'));
		
		$commentsClass 	= new RSCommentsModelComments($id, $option, $config->nr_comments, $template, $override, $IdComment);
		$pagination  	= $commentsClass->getPagination();
		$comments 	 	= $commentsClass->getComments();
		$total	 	 	= $commentsClass->getTotal();
		
		$view->comments		= $comments;
		$view->pagination	= $pagination;
		$view->total		= $total;
		
		if (!self::isJ3()) {
			//set the template and add the stylesheet
			$doc->addStyleSheet(JURI::root(true).'/components/com_rscomments/designs/'.$template.'/'.$template.'.css');
		}
		
		return $view->loadTemplate($tpl);
	}
	
	// Method to show a specific comment 
	// This is only used on Joomla 2.5
	public static function showComment($comment,$template,$ThreadClosed) {
		$uri 			= JURI::getInstance();
		$root			= $uri->toString(array('scheme','host'));
		$db 			= JFactory::getDbo();
		$query			= $db->getQuery(true);
		$user 			= JFactory::getUser();
		$config			= RSCommentsHelper::getConfig();
		$permissions	= self::getPermissions();
		$layout 		= file_get_contents(JPATH_SITE.'/components/com_rscomments/designs/'.$template.'/'.$template.'.html');
		$newu 			= JFactory::getUser($comment->uid);
		$avatar 		= RSCommentsHelper::getAvatar($newu->id,$comment->email);
		$classsuffix	= !empty($avatar) ? '_on' : '_off';
		$usersocialpage = RSCommentsHelper::getUserSocialLink($newu->id,$newu->name);
		$commentName	= RSCommentsHelper::name($comment, $permissions);
		
		RSCommentsHelper::loadLang();
		
		$pos = (int) self::getPositiveVotes($comment->IdComment);
		$neg = (int) self::getNegativeVotes($comment->IdComment);
		
		if (!empty($avatar) && !empty($usersocialpage)) {
			$avatar = '<a href="'.$usersocialpage.'">'.$avatar.'</a>';
		}
		
		//set the website 
		$website = ($config->enable_website_field == 1 && !empty($comment->website)) ? '<a class="rsc_website" href="'.$comment->website.'" '.($config->nofollow_rel == 1 ? 'rel="nofollow"' : '').' target="_blank" title="'.JText::_('COM_RSCOMMENTS_WEBSITE').'">'.JText::_('COM_RSCOMMENTS_WEBSITE').'</a>' : '';
		
		//set the subject
		$subject = ($config->enable_title_field == 1) ? '<span id="rscsubject'.$comment->IdComment.'">'.$comment->subject.'</span>' : '';
		
		//set the date
		$date = RSCommentsHelper::showDate($comment->date);
		$date = '<time itemprop="commentTime" datetime="'.RSCommentsHelper::showDate($comment->date,'Y-m-d H:i:s').'">'.$date.'</time>';
		
		//parse the comment
		$comment->comment = RSCommentsHelper::parseComment($comment->comment,$permissions);
		
		//set the comment
		$commenttext = '';
		
		$negativeComment = isset($config->negative_count) && $config->negative_count && $neg >= $config->negative_count;
		
		if ($negativeComment) {
			$commenttext .= '<span id="chidden'.$comment->IdComment.'" class="rsc_comment_box">'.JText::_('COM_RSCOMMENTS_COMMENT_HIDDEN').' <a href="javascript:void(0);" onclick="rsc_view(\''.$comment->IdComment.'\')">'.JText::_('COM_RSCOMMENTS_COMMENT_HIDDEN_LINK').'</a></span>';
		}
		
		$extraOptions = $negativeComment ? 'style="display: none; opacity: 0.7;"' : '';
		$commenttext .=  '<span id="c'.$comment->IdComment.'" class="rsc_comment_box" '.$extraOptions.' itemprop="commentText">'.$comment->comment.'</span>';
		
		
		$commenttext .= '<div class="rsc_buttons_container">';
		
		if ($comment->published) {
			// show reply button (default value = 1 )
			if (!empty($permissions['enable_reply']) && !$ThreadClosed)
				$commenttext .= '<span class="rsc_reply" onclick="rsc_reply(\''.$comment->IdComment.'\')"><a href="javascript:void(0);" title="'.JText::_('COM_RSCOMMENTS_REPLY').'">'.JText::_('COM_RSCOMMENTS_REPLY').'</a></span>';

			if(isset($permissions['new_comments']) && $permissions['new_comments'] && !$ThreadClosed)
				$commenttext .=  '<span class="rsc_rq" onclick="rsc_quote(\''.$commentName['cleanname'].'\',\''.$comment->IdComment.'\');" ><a href="javascript:void(0);" >'.JText::_('COM_RSCOMMENTS_COMMENT_QUOTE').'</a></span>';
		}
		
		$commenttext .= '</div> <!-- .rsc_buttons_container --> <span class="rsc_clear">&nbsp;</span>';
		
		if (isset($config->enable_modified) && $config->enable_modified) {
			if (!empty($comment->modified) && $comment->modified != $db->getNullDate()) {
				$commenttext .= '<div class="rsc_modified">';
				$commenttext .= JText::sprintf('COM_RSCOMMENTS_LAST_MODIFIED_ON',RSCommentsHelper::showDate($comment->modified));
				$modified_by = $comment->modified_by ? JFactory::getUser($comment->modified_by)->get('name') : JText::_('COM_RSCOMMENTS_GUEST');
				$commenttext .= JText::sprintf('COM_RSCOMMENTS_LAST_MODIFIED_BY',$modified_by);
				$commenttext .= '</div>';
			}
		}
		
		$commenttext .= '<div id="rscomments-reply-'.$comment->IdComment.'" class="rsc_comment_box_form"></div>';
		
		$replace = array(
				'{authorname}',
				'{AuthorName}',
				'{comment}',
				'{Comment}',
				'{avatar}',
				'{Avatar}',
				'{AuthorWebsite}',
				'{authorwebsite}',
				'{subject}',
				'{Subject}',
				'{date}',
				'{Date}',
				'{usersocialpage}',
				'{UserSocialPage}',
				'{ClassSuffix}',
				'{classsuffix}'
				);
		$with 	 = array(
				'<span id="rscname'.$comment->IdComment.'" itemprop="creator" itemscope itemtype="http://schema.org/Person"><span itemprop="name">'.$commentName['name'].'</span></span>',
				'<span id="rscname'.$comment->IdComment.'" itemprop="creator" itemscope itemtype="http://schema.org/Person"><span itemprop="name">'.$commentName['name'].'</span></span>',
				$commenttext,
				$commenttext,
				$avatar,
				$avatar,
				$website,
				$website,
				$subject,
				$subject,
				$date,
				$date,
				$usersocialpage,
				$usersocialpage,
				$classsuffix,
				$classsuffix
				);

		//set the ip button
		if (isset($permissions['view_ip']) && $permissions['view_ip']) {
			$replace[] = '{authorip}';
			$replace[] = '{AuthorIP}';
			
			$with[] = '<a href="http://www.db.ripe.net/whois?searchtext='.$comment->ip.'" target="_blank" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_IP_ADDRESS'),$comment->ip).'" class="'.RSTooltip::tooltipClass().'"><i class="fa fa-home"></i></a>'; 
			$with[] = '<a href="http://www.db.ripe.net/whois?searchtext='.$comment->ip.'" target="_blank" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_IP_ADDRESS'),$comment->ip).'" class="'.RSTooltip::tooltipClass().'"><i class="fa fa-home"></i></a>'; 
		} else { $replace[] = '{authorip}'; $with[] = ''; $replace[] = '{AuthorIP}'; $with[] = '';}
		
		// Own comment?
		$ip 		= $_SERVER['REMOTE_ADDR'];
		$ownComment = false;
		if ($user->guest) {
			$ownComment = $comment->ip == $ip;
		} else {
			$ownComment = $comment->uid == $user->id;
		}
		
		//set the edit button
		
		if (((isset($permissions['edit_own_comment']) && $permissions['edit_own_comment']) && $ownComment && !$ThreadClosed) || (isset($permissions['edit_comments']) && $permissions['edit_comments'] && !$ThreadClosed)) {
			$replace[] = '{editcomment}';
			$replace[] = '{EditComment}';
			
			$with[] = '<a class="'.RSTooltip::tooltipClass().'" href="javascript:void(0);" onclick="rsc_edit(\''.$comment->IdComment.'\');" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_EDIT_COMMENT')).'"><i class="fa fa-pencil"></i></a>'; 
			$with[] = '<a class="'.RSTooltip::tooltipClass().'" href="javascript:void(0);" onclick="rsc_edit(\''.$comment->IdComment.'\');" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_EDIT_COMMENT')).'"><i class="fa fa-pencil"></i></a>'; 
		} else { $replace[] = '{editcomment}'; $with[] = ''; $replace[] = '{EditComment}'; $with[] = '';}
		
		//set the delete button
		if (((isset($permissions['delete_own_comment']) && $permissions['delete_own_comment']) && $ownComment) || (isset($permissions['delete_comments']) && $permissions['delete_comments'])) {
			$replace[] = '{deletecomment}'; 
			$replace[] = '{DeleteComment}'; 

			$with[] = '<a class="'.RSTooltip::tooltipClass().'" href="javascript:void(0);" onclick="rsc_delete_fn(\''.JText::_('COM_RSCOMMENTS_DELETE_COMMENT_CONFIRM',true).'\',\''.$comment->IdComment.'\');return false;" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_DELETE_COMMENT')).'"><i class="fa fa-trash"></i></a>'; 
			$with[] = '<a class="'.RSTooltip::tooltipClass().'" href="javascript:void(0);" onclick="rsc_delete_fn(\''.JText::_('COM_RSCOMMENTS_DELETE_COMMENT_CONFIRM',true).'\',\''.$comment->IdComment.'\');return false;" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_DELETE_COMMENT')).'"><i class="fa fa-trash"></i></a>';
		} else { $replace[] = '{deletecomment}'; $with[] = '';$replace[] = '{DeleteComment}'; $with[] = ''; }
		
		//set the publish/unpublish button
		if (isset($permissions['publish_comments']) && $permissions['publish_comments']) {
			$publish = ($comment->published == 1) ? 'fa fa-minus-circle' : 'fa fa-check'; 
			$function = ($comment->published == 1) ? 'rsc_unpublish(\''.$comment->IdComment.'\')' : 'rsc_publish(\''.$comment->IdComment.'\')'; 
			$message = ($comment->published == 1) ? JText::_('COM_RSCOMMENTS_UNPUBLISH') : JText::_('COM_RSCOMMENTS_PUBLISH');
			$replace[] = '{publishcomment}'; 
			$replace[] = '{PublishComment}'; 
			$with[] = '<span id="rsc_publish'.$comment->IdComment.'"><a class="'.RSTooltip::tooltipClass().'" href="javascript:void(0);" onclick="'.$function.'" title="'.RSTooltip::tooltipText($message).'"><i class="'.$publish.'"></i></a></span>'; 
			$with[] = '<span id="rsc_publish'.$comment->IdComment.'"><a class="'.RSTooltip::tooltipClass().'" href="javascript:void(0);" onclick="'.$function.'" title="'.RSTooltip::tooltipText($message).'"><i class="'.$publish.'"></i></a></span>'; 
		} else { $replace[] = '{publishcomment}'; $with[] = '';$replace[] = '{PublishComment}'; $with[] = ''; }
		
		//set the vote buttons
		$enablevoting 	= ($config->enable_votes == 1) ? true : false; 
		
		if($enablevoting) {
			$positive = '<a class="'.RSTooltip::tooltipClass().'" href="javascript:void(0);" onclick="rsc_pos(\''.$comment->IdComment.'\');" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_GOOD_COMMENT')).'"><i class="fa fa-thumbs-up"></i></a> ';
			$negative = '<a class="'.RSTooltip::tooltipClass().'" href="javascript:void(0);" onclick="rsc_neg(\''.$comment->IdComment.'\');" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_BAD_COMMENT')).'"><i class="fa fa-thumbs-down"></i></a>';
			
			if(isset($permissions['vote_comments']) && $permissions['vote_comments']) {
				$replace[] = '{vote}';
				$replace[] = '{Vote}';
				$voted = RSCommentsHelper::voted($comment->IdComment);
				
				if(empty($voted)){
					$with[] = '<span id="rsc_voting'.$comment->IdComment.'">'.$positive.$negative.'</span>';
					$with[] = '<span id="rsc_voting'.$comment->IdComment.'">'.$positive.$negative.'</span>';
				} else {
					$with[] = ($pos - $neg) > 0 ? '<i class="fa fa-thumbs-up"></i> <span class="rsc_green">'.($pos - $neg).'</span>' : '<i class="fa fa-thumbs-down"></i> <span class="rsc_red">'.($pos - $neg).'</span>'; 
					$with[] = ($pos - $neg) > 0 ? '<i class="fa fa-thumbs-up"></i> <span class="rsc_green">'.($pos - $neg).'</span>' : '<i class="fa fa-thumbs-down"></i> <span class="rsc_red">'.($pos - $neg).'</span>'; 
				}
			} else {
				$replace[] = '{vote}'; 
				$replace[] = '{Vote}'; 
				$with[] = ($pos - $neg) > 0 ? '<i class="fa fa-thumbs-up"></i> <span class="rsc_green">'.($pos - $neg).'</span>' : '<i class="fa fa-thumbs-down"></i> <span class="rsc_red">'.($pos - $neg).'</span>'; 
				$with[] = ($pos - $neg) > 0 ? '<i class="fa fa-thumbs-up"></i> <span class="rsc_green">'.($pos - $neg).'</span>' : '<i class="fa fa-thumbs-down"></i> <span class="rsc_red">'.($pos - $neg).'</span>'; 
			}
		} else {
			$replace[] = '{vote}';
			$replace[] = '{Vote}';
			$with[] = ''; 
			$with[] = ''; 
		}
		
		$replace[] = '{attachement}';
		$replace[] = '{Attachment}';
		$replace[] = '{attachment}';

		if (!empty($comment->file)) {
			$with[] = '<a href="'.RSCommentsHelper::route('index.php?option=com_rscomments&task=download&id='.$comment->IdComment,false).'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_ATTACHMENT')).'" class="'.RSTooltip::tooltipClass().'"><i class="fa fa-file"></i> '.$comment->file.'</a>';
			$with[] = '<a href="'.RSCommentsHelper::route('index.php?option=com_rscomments&task=download&id='.$comment->IdComment,false).'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_ATTACHMENT')).'" class="'.RSTooltip::tooltipClass().'"><i class="fa fa-file"></i> '.$comment->file.'</a>';
			$with[] = '<a href="'.RSCommentsHelper::route('index.php?option=com_rscomments&task=download&id='.$comment->IdComment,false).'" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_ATTACHMENT')).'" class="'.RSTooltip::tooltipClass().'"><i class="fa fa-file"></i> '.$comment->file.'</a>';
		} else {
			$with[] = ''; $with[] = ''; $with[] = ''; 
		}
		
		if ($config->enable_reports) {
			$report	 = '';
			
			$report .= '<a class="'.RSTooltip::tooltipClass().'" onclick="rscomments_show_report('.(int) $comment->IdComment.');" href="javascript:void(0)" title="'.RSTooltip::tooltipText(JText::_('COM_RSCOMMENTS_REPORT_COMMENT')).'">';
			$report .= '<i class="fa fa-flag"></i>';
			$report .= '</a>';
		
			$replace[]  = '{report}';
			$with[] = $report;
		}
		
		$replace[] = '{location}';
		
		if ($config->enable_location && !empty($comment->location)) {
			$locationlink = $comment->coordinates ? 'https://www.google.com/maps/place/'.$comment->coordinates : 'javascript: void(0)';
			$with[] = '<a href="'.$locationlink.'" target="_blank" class="'.RSTooltip::tooltipClass().'" title="'.RSTooltip::tooltipText($comment->location).'"><i class="rscomm-meta-icon fa fa-map-marker"></i></a>';
		} else {
			$with[] = '';
		}
		
		return str_replace($replace, $with, $layout);
	}
	
	// Check if the current user has voted
	public static function voted($id) {
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		$user	= JFactory::getUser();
		
		if ($user->get('guest')) {
			$query->clear()
				->select($db->qn('IdVote'))
				->from($db->qn('#__rscomments_votes'))
				->where($db->qn('IdComment').' = '.(int) $id)
				->where($db->qn('ip').' = '.$db->q(RSCommentsHelper::getIp(true)));
		} else {
			$query->clear()
				->select($db->qn('IdVote'))
				->from($db->qn('#__rscomments_votes'))
				->where($db->qn('IdComment').' = '.(int) $id)
				->where('('.$db->qn('ip').' = '.$db->q(RSCommentsHelper::getIp(true)).' OR '.$db->qn('uid').' = '.(int) $user->get('id').')');
		}
		
		$db->setQuery($query);
		return $db->loadResult();
	}
	
	// Get messages
	public static function getMessage($type) {
		$tag 	= JFactory::getLanguage()->getTag();
		$db		= JFactory::getDBO();
		$query	= $db->getQuery(true);
		
		$query->clear()
			->select($db->qn('content'))
			->from($db->qn('#__rscomments_messages'))
			->where($db->qn('tag').' = '.$db->q($tag))
			->where($db->qn('type').' = '.$db->q($type));
		
		$db->setQuery($query);
		$content = $db->loadResult();
		
		if (empty($content)) {
			$query->clear()
				->select($db->qn('content'))
				->from($db->qn('#__rscomments_messages'))
				->where($db->qn('tag').' = '.$db->q('en-GB'))
				->where($db->qn('type').' = '.$db->q($type));
			
			$db->setQuery($query);
			$content = $db->loadResult();
		}
		
		return $content;
	}
	
	// Read a file chunk
	public static function readfile_chunked($filename, $retbytes = true) {
		$chunksize = 1*(1024*1024); // how many bytes per chunk
		$buffer = '';
		$cnt =0;
		$handle = fopen($filename, 'rb');
		if ($handle === false) {
			return false;
		}
		while (!feof($handle)) {
			$buffer = fread($handle, $chunksize);
			echo $buffer;
			if ($retbytes) {
				$cnt += strlen($buffer);
			}
		}
	   $status = fclose($handle);
	   if ($retbytes && $status) {
			return $cnt; // return num. bytes delivered like readfile() does.
		}
		return $status;
	}
	
	// Get positive votes
	public static function getPositiveVotes($id) {
		$db		= JFactory::getDBO();
		$query	= $db->getQuery(true);
		
		static $posvotes = array();
		
		if (empty($posvotes)) {
			$query->clear()
				->select('COUNT('.$db->qn('IdVote').') AS pos')->select($db->qn('IdComment'))
				->from($db->qn('#__rscomments_votes'))
				->where($db->qn('value').' = '.$db->q('positive'))
				->group($db->qn('IdComment'));
			$db->setQuery($query);
			if ($results = $db->loadObjectList()) {
				foreach ($results as $result) {
					$posvotes[$result->IdComment] = $result->pos;
				}
			}
		}
		
		return isset($posvotes[$id]) ? $posvotes[$id] : 0;
	}
	
	// Get negative votes
	public static function getNegativeVotes($id) {
		$db		= JFactory::getDBO();
		$query	= $db->getQuery(true);
		
		static $negvotes = array();
		
		if (empty($negvotes)) {
			$query->clear()
				->select('COUNT('.$db->qn('IdVote').') AS neg')->select($db->qn('IdComment'))
				->from($db->qn('#__rscomments_votes'))
				->where($db->qn('value').' = '.$db->q('negative'))
				->group($db->qn('IdComment'));
			$db->setQuery($query);
			if ($results = $db->loadObjectList()) {
				foreach ($results as $result) {
					$negvotes[$result->IdComment] = $result->neg;
				}
			}
		}
		
		return isset($negvotes[$id]) ? $negvotes[$id] : 0;
	}
	
	// Load necessary scripts
	public static function loadScripts() {
		static $loaded;
		
		if (!$loaded) {
			$config = RSCommentsHelper::getConfig();
			$permissions = RSCommentsHelper::getPermissions();
			
			$doc = JFactory::getDocument();
			$doc->addScriptDeclaration("var rsc_root = '".addslashes(JURI::root())."';");
			$doc->addScriptDeclaration("var rsc_tooltip = '".(RSCommentsHelper::isJ3() ? 'hasTooltip' : 'hasTip')."';");
			
			// Load jQuery
			RSCommentsHelper::loadjQuery();
			
			// Load Bootstrap
			RSCommentsHelper::loadBootstrap();
			
			// Load font Awesome
			if ($config->fontawesome == 1) {
				$doc->addStyleSheet(JURI::root(true).'/components/com_rscomments/assets/css/font-awesome.min.css');
			}
			
			if ($config->enable_location) {
				$doc->addScript('https://maps.google.com/maps/api/js');
				$doc->addScript(JURI::root(true).'/components/com_rscomments/assets/js/jquery.map.js');
			}
			
			$doc->addScript(JURI::root(true).'/components/com_rscomments/assets/js/rscomments.js');
			$doc->addStyleSheet(JURI::root(true).'/components/com_rscomments/assets/css/style.css');
			
			if (isset($permissions['captcha']) && $permissions['captcha']) {
				if ($config->captcha == 2) {
					$doc->addScript('https://www.google.com/recaptcha/api.js?render=explicit&amp;hl='.JFactory::getLanguage()->getTag());
					$doc->addScriptDeclaration("
						RSCommentsReCAPTCHAv2.loaders.push(function(){
							grecaptcha.render('rsc-g-recaptcha', {
								'sitekey': '".htmlentities($config->recaptcha_new_site_key, ENT_QUOTES, 'UTF-8')."',
								'theme': '".htmlentities($config->recaptcha_new_theme, ENT_QUOTES, 'UTF-8')."',
								'type': '".htmlentities($config->recaptcha_new_type, ENT_QUOTES, 'UTF-8')."'
							});
						});
					");
				}
			}
			
			$loaded = true;
		}
	}
	
	// Load jQuery
	public static function loadjQuery($noconflict = true) {
		if (RSCommentsHelper::getConfig('frontend_jquery')) {
			if (RSCommentsHelper::isJ3()) {
				JHtml::_('jquery.framework', $noconflict);
			} else {
				$doc = JFactory::getDocument();
				$doc->addScript(JURI::root(true).'/components/com_rscomments/assets/js/jquery-1.11.1.min.js');
				
				if ($noconflict) {
					$doc->addScript(JURI::root(true).'/components/com_rscomments/assets/js/jquery.noConflict.js');
				}
			}
		}
	}
	
	// Load Bootstrap
	public static function loadBootstrap($force = false) {
		if (RSCommentsHelper::getConfig('load_bootstrap') || $force) {
			if (RSCommentsHelper::isJ3()) {
				JHtml::_('bootstrap.framework');
				JHtmlBootstrap::loadCss(true);
			} else {
				$document = JFactory::getDocument();
				$document->addScript(JURI::root(true).'/components/com_rscomments/assets/js/bootstrap.min.js');
				$document->addStyleSheet(JURI::root(true).'/components/com_rscomments/assets/css/bootstrap.min.css');
				$document->addStyleSheet(JURI::root(true).'/components/com_rscomments/assets/css/bootstrap-responsive.min.css');
			}
		}
	}
	
	// Clear the cache
	public static function clearCache() {
		$cache = JFactory::getCache('com_content');
		$cache->clean();
		$cache = JFactory::getCache('page');
		$cache->clean();
	}
	
	// Display a human readeable date format
	public static function humanReadableDate($date) {
		$date1 = JFactory::getDate($date)->toUnix();
		$date2 = JFactory::getDate()->toUnix();
			
		$diff_secs = abs($date1 - $date2);
		$base_year = min( JFactory::getDate($date1)->format('Y'), JFactory::getDate($date2)->format('Y') );
		
		if ($diff_secs == 0) {
			return JText::_('COM_RSCOMMENTS_JUST_NOW');
		}
		
		$diff = gmmktime(0, 0, $diff_secs, 1, 1, $base_year);
		
		$data = (object)array(
			'years' => JFactory::getDate($diff)->format('Y') - $base_year,
			'months_total' => ( JFactory::getDate($diff)->format('Y') - $base_year ) * 12 + JFactory::getDate($diff)->format('n') - 1,
			'months' => JFactory::getDate($diff)->format('n') - 1,
			'days_total' => floor( $diff_secs / (3600 * 24) ),
			'days' => JFactory::getDate($diff)->format('j') - 1,
			'hours_total' => floor($diff_secs / 3600),
			'hours' => JFactory::getDate($diff)->format('G'),
			'minutes_total' => floor($diff_secs / 60),
			'minutes' => (int)JFactory::getDate($diff)->format('i'),
			'seconds_total' => $diff_secs,
			'seconds' => (int)JFactory::getDate($diff)->format('s'),
		);
		
		// Initialize the result array.
		$result = array();
			
		// Set the precision.
		$precision = 2;
			
		// Current precision.
		$current_precision = 0;
			
		$units = array('years', 'months', 'days', 'hours', 'minutes', 'seconds');
		
		foreach ($units as $unit) {
			if ( ($data->$unit || $current_precision) && $current_precision < $precision ) {
				if ($data->$unit) {
					$result[] = JText::plural( 'COM_RSCOMMENTS_NUMBER_OF_' . strtoupper($unit), $data->$unit );
				}
					
				$current_precision++;
			}
		}
		
		return implode(' ', $result);
	}
	
	// Remove comments
	public static function remove($id) {
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		$dfolder= JPATH_SITE.'/components/com_rscomments/assets/files/';
		
		// Get comment replies
		$query->clear()
			->select($db->qn('IdComment'))
			->from($db->qn('#__rscomments_comments'))
			->where($db->qn('IdParent').' = '.(int) $id);
		
		$db->setQuery($query);
		if ($children = $db->loadObjectList()) {
			foreach($children as $child) {
				RSCommentsHelper::remove($child->IdComment);
			}
		}
		
		$query->clear()
			->select($db->qn('file'))
			->from($db->qn('#__rscomments_comments'))
			->where($db->qn('IdComment').' = '.(int) $id);
		
		$db->setQuery($query);
		$file = $db->loadResult();

		if (!empty($file) && file_exists($dfolder.$file)) {
			JFile::delete($dfolder.$file);
		}
		
		$query->clear()
			->delete()
			->from($db->qn('#__rscomments_votes'))
			->where($db->qn('IdComment').' = '.(int) $id);
		
		$db->setQuery($query);
		$db->execute();
		
		$query->clear()
			->delete()
			->from($db->qn('#__rscomments_comments'))
			->where($db->qn('IdComment').' = '.(int) $id);
		
		$db->setQuery($query);
		$db->execute();
	}
	
	// Check if a comment is valid
	public static function valid($id) {
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		
		$query->select('COUNT('.$db->qn('IdComment').')')
			->from($db->qn('#__rscomments_comments'))
			->where($db->qn('IdComment').' = '.$db->q($id));
		$db->setQuery($query);
		return (bool) $db->loadResult();
	}
}
