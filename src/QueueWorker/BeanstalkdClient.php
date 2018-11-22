<?php
namespace Futuredialog\PushWorker\QueueWorker;

use Pheanstalk\Pheanstalk;

class BeanstalkdClient
{

	private $_job = null;
	private $_tube = null;
	private $_queue = null;

	function __construct($host, $tube, $port = 11300)
	{
		$this->_tube = $tube;
		$this->_queue = new Pheanstalk($host, $port);
	}

	/**
	 * @param \Pheanstalk\Job $job
	 */
	private function _assignJob(\Pheanstalk\Job $job)
	{
		$this->_job = $job;
	}

	/**
	 * @param array $data
	 * @param int $priority
	 * @param int $delay (in seconds)
	 */
	public function putJob($tube, $data, $priority = 1024, $delay = 0)
	{
		$this->_queue->putInTube($tube, json_encode($data), $priority, $delay);
	}

	/**
	 * @param array $data
	 * @param int $priority
	 * @param int $delay
	 */
	public function retryJob($data, $priority = 1024, $delay = 0)
	{
		return $this->_queue->putInTube($this->_tube, json_encode($data), $priority, $delay);
	}

	/**
	 * @return void
	 */
	public function watch()
	{
		$this->_queue->watchOnly($this->_tube);
	}

	/**
	 * @return bool|object|\Pheanstalk\Job
	 */
	public function reserveJob($timeout = null)
	{
		$job = $this->_queue->reserve($timeout);
		if($job) {
			$this->_assignJob($job);	
		}
		return $job;
	}

	/**
	 * @param \Pheanstalk\Job $job
	 */
	public function buryJob()
	{
		if($this->_job === null) return;
		$this->_queue->bury($this->_job);
		$this->_job = null;
	}

	/**
	 * @param \Pheanstalk\Job $job
	 */
	public function deleteJob()
	{
		if($this->_job === null) return;
		$this->_queue->delete($this->_job);
		$this->_job = null;
	}

	/**
	 * @param \Pheanstalk\Job $job
	 * @return int
	 */
	public function getJobId()
	{
		return ($this->_job) ? $this->_job->getId() : null;
	}

	/**
	 * @param \Pheanstalk\Job $job
	 * @return string
	 */
	public function getJobBody()
	{
		return $this->_job->getData();
	}

	/**
	 * @param \Pheanstalk\Job $job
	 * @return null|array
	 */
	public function getJobData()
	{
		return json_decode($this->_job->getData(), TRUE);
	}

	/**
	 * @param \Pheanstalk\Job $job
	 * @return null|array
	 */
	public function getTube()
	{
		return $this->_tube;
	}

}