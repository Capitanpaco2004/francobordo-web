<?php
class Validate {
	
	/**
	 * Check for e-mail validity
	 *
	 * @param string $email e-mail address to validate
	 * @return boolean Validity is ok or not
	 */
	public static function isEmail($email)
	{
		return !empty($email) && preg_match('/^[a-z\p{L}0-9!#$%&\'*+\/=?^`{}|~_-]+[.a-z\p{L}0-9!#$%&\'*+\/=?^`{}|~_-]*@[a-z\p{L}0-9]+(?:[.]?[_a-z\p{L}0-9-])*\.[a-z\p{L}0-9]+$/ui', $email);
	}
	
	/**
	 * Check url validity (disallowed empty string)
	 *
	 * @param string $url Url to validate
	 * @return boolean Validity is ok or not
	 */
	public static function isUrl($url)
	{
		if(preg_match('/^[~:#,$%&_=\(\)\.\? \+\-@\/a-zA-Z0-9]+$/', $url)){
			if ((strpos($url, 'http')) === false)
				$url = 'http://'.$url;
			return  is_array(@get_headers($url));
		}			
		return false;
	}
}
