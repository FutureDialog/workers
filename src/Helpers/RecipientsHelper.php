<?php
namespace Futuredialog\PushWorker\Helpers;

class RecipientsHelper 
{
	public static function getRecipientsTokens($recipients)
	{
		$tokens = [];
		foreach ($recipients as $key => $recipient) {
			if(is_array($recipient) && !isset($recipient['token'])) {
				continue;
			}
			$tokens[$key] = (is_array($recipient)) ? $recipient['token'] : $recipient;
		}
		return $tokens;
	}
	
	public static function checkWnsRecipientToken($device_token)
	{
		if(strpos($device_token, 'http://') === FALSE && strpos($device_token, 'https://') === FALSE) {
			return FALSE;
		}
		return $device_token;
	}
}