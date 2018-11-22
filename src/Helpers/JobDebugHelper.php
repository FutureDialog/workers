<?php
namespace Futuredialog\PushWorker\Helpers;

use function GuzzleHttp\default_ca_bundle;

class JobDebugHelper
{
	const NOTIFICATION_DEBUG_KEY = 'notificationDebug';
	
	private $job_platform = null;
	private $job_data = null;
	private $notification_debug = null;
	private $job_success_data = [];
	private $job_fail_data = [];
	
	function __construct($platform, $data, $success_data, $fail_data)
	{
		$this->job_platform = $platform;
		$this->job_data = $data;
		$this->notification_debug = (isset($this->job_data[JobDebugHelper::NOTIFICATION_DEBUG_KEY])) ? $this->job_data[JobDebugHelper::NOTIFICATION_DEBUG_KEY] : null;
		$this->job_success_data = $success_data;
		$this->job_fail_data = $fail_data;
	}

	public function isJobToDebug() 
	{
		return ($this->job_platform && $this->notification_debug);
	}
	
	public function getJobEnv()
	{
		return (isset($this->notification_debug['env'])) ? $this->notification_debug['env'] : null;
	}
	
	public function getSlackFields()
	{
		$env_fields = $this->getSlackEnvFields();
		
		$action_field = $this->getSlackJobAction();
		
		$info_fields = [];
		if(isset($this->notification_debug['info_fields'])) {
			$info_fields = $this->getSlackInfoFields($this->notification_debug['info_fields']);
		}
		
		$recipients_fields = $this->getSlackRecipientsInfo();
		return array_merge($env_fields, $action_field, $info_fields, $recipients_fields);
	}

	private function getSlackEnvFields()
	{
		return [
			[
				'title' => 'Platform',
				'value' => $this->job_platform,
				'short' => true
			],
			[
				'title' => 'Env',
				'value' => (isset($this->notification_debug['env'])) ? $this->notification_debug['env'] : 'unknown',
				'short' => true
			],
		];
	}

	private function getSlackJobAction()
	{
		if(!isset($this->job_data['payload']['theboard_action'])) {
			return [];
		}
		$action = '';
		switch ($this->job_data['payload']['theboard_action']) {
			case 'new_card':
				$action = 'New card added';
				break;
			case 'deleted_card':
				$action = 'Card deleted';
				break;
			default:
				$action = $this->job_data['payload']['theboard_action'];
				break;
		}
		return [
			[
				'title' => 'Notification action',
				'value' => $action,
				'short' => false
			]
		];
	}
	
	private function getSlackInfoFields(array $data)
	{
		$result = [];
		foreach ($data as $field => $value) {
			array_push($result, [
				'title' => $field,
				'value' => $value,
				'short' => false
			]);
		}
		return $result;
	}
	
	private function getSlackRecipientsInfo()
	{
		if(!isset($this->job_data['recipients'])) {
			return [];
		}
		
		$count_array = [
			'Total' =>count($this->job_data['recipients']),
			'Success' => count($this->job_success_data),
			'Fail' => count($this->job_fail_data),
		];
		$count_str = '';
		foreach ($count_array as $field => $data) {
			$count_str .= str_pad($field.': ` '.$data.' `', 20, ' ', STR_PAD_LEFT);
		}
		$count_field = [
			[
				'title' => 'Recipients count',
				'value' => $count_str,
				'short' => false
			]
		];

		$errors_field = [];
		switch ($this->job_platform) {
			case 'android':
				$errors_field = $this->getSlackRecipientsErrorsAndroid();
				break;
		}
		
		return array_merge($count_field, $errors_field);
	}
	
	private function getSlackRecipientsErrorsAndroid()
	{
		$result = [];
		$error_stats = [];
		
		foreach ($this->job_fail_data as $item) {
			if(!isset($error_stats[$item['error']])) {
				$error_stats[$item['error']] = [];
			}
			$app_version = (isset($item['app_version'])) ? $item['app_version'] : 'undefined';
			array_push($error_stats[$item['error']], $app_version);
		}
		
		foreach ($error_stats as &$item) {
			$item = array_count_values($item);
		}

		foreach ($error_stats as $error => $counts) {
			$counts_str = '';
			ksort($counts);
			foreach ($counts as $version => $count) {
				$counts_str .= str_pad($version.': ` '.$count.' `', 16, ' ', STR_PAD_LEFT);
			}
			$result[] = [
				'title' => 'Error: `'.$error.' ('.array_sum($counts).')`',
				'value' => $counts_str,
				'short' => false
			];
		}
		
		return $result;
	}
	
	
}