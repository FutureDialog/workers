<?php
namespace Futuredialog\PushWorker\QueueWorker;
use Futuredialog\PushWorker\Loggers\HealthCheckFileLogger;
use Futuredialog\PushWorker\QueueWorker\Exceptions\WorkerAbortExeption;
use Futuredialog\PushWorker\QueueWorker\Exceptions\WorkerReplicatedExeption;
use Futuredialog\PushWorker\QueueWorker\Exceptions\WorkerRetryExeption;

use Futuredialog\PushWorker\Helpers\JobDebugHelper;

abstract class Worker implements WorkerInterface 
{
	
	const DEBUG_MODE_ON = 1;
    const DEBUG_MODE_OFF = 0;


	const SLEEP_CYCLE = 2;

	const RETRY_ATTEMPTS = 3;
	const RETRY_PRIORITY = 0;
	const RETRY_DELAY = 2;

	const REPLICATE_ATTEMPTS = 1;
	const REPLICATE_PRIORITY = 0;
	const REPLICATE_DELAY = 0;
	
	private $_settings = [];
	private $_client = null;
	
	protected $job_id = null;
	protected $job_body = null;
	protected $job_data = null;
	
	private $_onSuccessTube = null;
	private $_onFailTube = null;
	private $_onCompleteTube = null;

	private $_successData = [];
	private $_failData = [];

	function __construct($host, $tube, $port, $settings = [], $mode = self::DEBUG_MODE_OFF)
	{
		$this->_client = new BeanstalkdClient($host, $tube, $port);
		$this->_settings = $settings;
		$this->_mode = $mode;
	}


	private $_mode = self::DEBUG_MODE_OFF;

	/**
	 * @return int
	 */
	private function _getTime(){
		return time();
	}

	/**
	 * @return void
	 */
	private function _resetState()
	{
		$this->job_id = null;
		$this->job_data = null;
		
		$this->_onSuccessTube = null;
		$this->_onFailTube = null;
		$this->_onCompleteTube = null;

		$this->_successData = [];
		$this->_failData = [];
	}

	/**
	 * @return void
	 */
	private function _assignJob()
	{
		$this->job_id = $this->_client->getJobId();
		$this->job_body = $this->_client->getJobBody();
		$this->job_data = $this->_client->getJobData();
	}

	/**
	 * @param array $data
	 * @return array
	 */
	abstract protected function validateData($data);

	/**
	 * @return array
	 */
	protected function getData()
	{
		$data = $this->_client->getJobData();
		if($data === null) {
			$this->abort('Json not valid');
		}

		if(isset($data['onSuccess']) && is_string($data['onSuccess'])) {
			$this->_onSuccessTube = $data['onSuccess'];
		}
		if(isset($data['onFail']) && is_string($data['onFail'])) {
			$this->_onFailTube = $data['onFail'];
		}
		if(isset($data['onComplete']) && is_string($data['onComplete'])) {
			$this->_onCompleteTube = $data['onComplete'];
		}
		$this->job_data = $this->validateData($data);

		return $this->job_data;
	}

    /**
     * @param \Exception $e
     * @throws \Exception
     */
	public function logError(\Exception $e)
	{
		error_log(date('Y-m-d h:i:s').' Caught '.get_class($e).' '.$e->getMessage()."\n".$e->getTraceAsString());
		if($this->_mode === self::DEBUG_MODE_ON) {
			throw $e;
		}
	}

	/**
	 * @param string $message
	 * @param string|array $data
	 * @return void
	 */
	public function logData($message, $data)
	{
		if(is_array($data)) {
			$data = json_encode($data);
		}
		$log_message = date('Y-m-d h:i:s').' Tube '.$this->_client->getTube().' Job '.$this->job_id.' '.$message.': '.$data;
		$log_message = preg_replace( "/\r|\n/", '', $log_message);
		error_log($log_message);
	}

	/**
	 * @param string $message
	 * @param string $text
	 * @param string $color
	 */
	public function notify($message, $text, $color = 'warning', $show_stats = true)
	{

		$slack_channel = getenv('SLACK_CHANNEL');
		
		if(!$slack_channel) {
			return;
		}

		$job_platform = isset($this->_settings['platform']) ? $this->_settings['platform'] : null;

		$slack = new SlackNotificationClient($slack_channel, [
			'username' => (isset($this->_settings['slack_username'])) ? $this->_settings['slack_username'] : 'Worker',
		]);
		
		$helper = new JobDebugHelper($job_platform, $this->job_data, $this->_successData, $this->_failData);
		
		
		$attachment = [
			'mrkdwn_in' => ['text', 'pretext', 'fields'],
			'color' => $color,
			'title' => 'Message',
			'text' => $text,
			'fields' => [
				[
					'title' => 'Job ID',
					'value' => $this->job_id,
					'short' => true
				],
				[
					'title' => 'Job Tube',
					'value' => $this->_client->getTube(),
					'short' => true
				]
			],
			'ts' => time(),
		];
		
		if($show_stats && $helper->isJobToDebug()) {
			$attachment['fields'] = array_merge($attachment['fields'], $helper->getSlackFields());
		}

		$slack->addAttachement($attachment);
		$slack->send($message);
	}

	

	/**
	 * @return void
	 */
	protected function putSuccessData()
	{
		if($this->_onSuccessTube === null || empty($this->_successData)) return;
		$this->_client->putJob($this->_onSuccessTube,  [
			'job_id' => $this->job_id,
			'time' => $this->_getTime(),
			'count' => count($this->_successData),
			'data' => $this->_successData
		]);
	}

	/**
	 * @return void
	 */
	protected function putFailData()
	{
		if($this->_onFailTube === null || empty($this->_failData)) return;
		$this->_client->putJob($this->_onFailTube, [
			'job_id' => $this->job_id,
			'time' => $this->_getTime(),
			'count' => count($this->_failData),
			'data' => $this->_failData
		]);
	}

	/**
	 * @return void
	 */
	protected function putCompleteData()
	{
		if($this->_onCompleteTube === null || empty($this->_onCompleteTube)) return;
		$data = [];
		foreach ($this->_successData as $item) {
			$data[] = array_merge($item, ['status' => true]);
		}
		foreach ($this->_failData as $item) {
			$data[] = array_merge($item, ['status' => false]);
		}
		$this->_client->putJob($this->_onCompleteTube, [
			'job_id' => $this->job_id,
			'time' => $this->_getTime(),
			'success' => count($this->_successData),
			'failure' => count($this->_failData),
			'data' => array_merge($this->_successData, $this->_failData),
			'job_body' => $this->job_data
		]);
	}

	/**
	 * @return void
	 */
	protected function putJobData() 
	{
		$this->putSuccessData();
		$this->putFailData();
		$this->putCompleteData();
	}

	/**
	 * @param function $job_func
	 * @return void
	 */
	abstract protected function processJob($job_func);
	
	/*
	 * @return bool
	 * @throws Exception
	 */
	protected function checkJob()
	{
		$data = $this->job_data;
		
		if(isset($data['workerRetryCount']) && $data['workerRetryCount'] > Worker::RETRY_ATTEMPTS) {
			$this->abort('Retry limit exceeded');
			return;
		}

		if(isset($data['workerReplicatedCount']) && $data['workerReplicatedCount'] > Worker::REPLICATE_ATTEMPTS) {
			$this->abort('Job already was replicated');
			return;
		}
		
		return TRUE;
	}
	
	/*
	 * @return void
	 */
	protected function doneJob()
	{
		$this->_client->deleteJob();
		$this->putJobData();
		$this->_resetState();
	}

	/**
	 * @return void
	 */
	public function buryJob()
	{
		$this->_client->buryJob();
		$this->_resetState();
	}
	
	
	public function send_message($text)
	{
		$this->notify('Job Processing...', $text, 'danger', false);
	}

	/**
	 * @param array $data
	 */
	public function success($data, $worker_data = [])
	{
		if(!is_array($data)) {
			$data = ['token' => $data];
		}
		$data = array_merge($data, $worker_data, [
			'job_id' => $this->job_id,
			'status' => true
		]);
		array_push($this->_successData, $data);
		$this->logData('Recipent Success', $data);
	}

	/**
	 * @param array $data
	 */
	public function fail($data, $error, $worker_data = [])
	{
		if(!is_array($data)) {
			$data = ['token' => $data];
		}
		$data = array_merge($data, $worker_data, [
			'job_id' => $this->job_id,
			'status' => false, 
			'error' => $error
		]);
		array_push($this->_failData, $data);
		$this->logData('Recipent Fail', $data);
	}

	/**
	 * @param $job_func
	 * @return void
	 */
	public function run($job_func, $runOnce = FALSE)
	{
		if($runOnce) {
			$this->runOnce($job_func);
			return;
		}
		
		if(!is_callable($job_func)) {
			$this->abort('No run function provided');
		}
		$this->_client->watch();
		while(TRUE) {
            $this->healthCheckLog();
			$job = $this->_client->reserveJob(30);
			if($job === false) {
				sleep(Worker::SLEEP_CYCLE);
				continue;
			};
			$this->_assignJob();
			$this->processJob($job_func);
			
		}
	}

	/**
	 * @param $job_func
	 * @return void
	 */
	public function runOnce($job_func) 
	{
		if(!is_callable($job_func)) {
			$this->abort('No run function provided');
		}
		$this->_client->watch();
		while($job = $this->_client->reserveJob(10)) {
			$this->_assignJob();
			$this->processJob($job_func);
		}
	}

	

	/**
	 * @param string|array $reason
	 * @throws WorkerAbortExeption
	 */
	public function abort($reason)
	{
		$job_id = $this->job_id;
		
		throw new WorkerAbortExeption('Job '.$job_id.' Aborted. Reason: '.$reason);
	}

	/**
	 * @return void
	 * @throws WorkerRetryExeption
	 */
	public function retry()
	{
		$job_id = $this->job_id;
		$data = $this->job_data;

		$data['workerRetryCount'] = (isset($data['workerRetryCount'])) ? ++$data['workerRetryCount'] : 1;
		
		$new_job_id = $this->_client->retryJob($data, Worker::REPLICATE_PRIORITY, Worker::REPLICATE_DELAY);
		
		throw new WorkerRetryExeption('Retrying Job '.$job_id.'. New Job - '.$new_job_id);
	}

	/**
	 * @return void
	 * @throws WorkerReplicatedExeption
	 */
	public function replicate($new_data)
	{
		$job_id = $this->job_id;
		$data = $this->job_data;
		
		$data = array_merge($data, $new_data, [
			'workerReplicated' => true,
			'workerReplicatedCount' => (isset($data['workerReplicatedCount'])) ? ++$data['workerReplicatedCount'] : 1,
			'workerReplicatedOriginJobId' => $this->job_id
		]);

		$new_job_id = $this->_client->retryJob($data, Worker::RETRY_PRIORITY, Worker::RETRY_DELAY);
		
		throw new WorkerReplicatedExeption('Replicating Job '.$job_id.'. New Job - '.$new_job_id);
	}

	/**
	 * @return void
	 */
	public function complete()
	{
		$job_id = $this->job_id;
		$this->notify('Job Completed', 'Job '.$job_id.' completed', 'good');
		$this->doneJob();
	}

	private function healthCheckLog()
    {
        $logger = new HealthCheckFileLogger();
        $logger->healthCheckLog();
    }
	
}