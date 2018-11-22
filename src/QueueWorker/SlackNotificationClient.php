<?php
namespace Futuredialog\PushWorker\QueueWorker;

use \Maknz\Slack\Client;

class SlackNotificationClient
{
	private $_client = null;
	private $_message = [];
	private $_attachments = [];
	
	function __construct($channel, $settings)
	{
		$this->_channel = $channel;
		$this->_message = [
			'username' => $settings['username']
		];
	}
	
	public function addAttachement(array $array)
	{
		if(empty($array)) return;
		array_push($this->_attachments, $array);
	}
	
	public function send($text = null)
	{
		$this->_message = [
			'text' => $text,
			'attachments' => $this->_attachments
		];
		$json = json_encode($this->_message, JSON_UNESCAPED_UNICODE);
		$slack_call = curl_init($this->_channel);
		curl_setopt($slack_call, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($slack_call, CURLOPT_POSTFIELDS, $json);
		curl_setopt($slack_call, CURLOPT_CRLF, true);
		curl_setopt($slack_call, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($slack_call, CURLOPT_HTTPHEADER, array(
				"Content-Type: application/json",
				"Content-Length: " . strlen($json))
		);
		$result = curl_exec($slack_call);
		curl_close($slack_call);
	}

}