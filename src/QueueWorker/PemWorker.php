<?php
namespace Futuredialog\PushWorker\QueueWorker;

use Pheanstalk\Exception;
use Futuredialog\PushWorker\QueueWorker\Exceptions\WorkerAbortExeption;
use Futuredialog\PushWorker\QueueWorker\Exceptions\WorkerRetryExeption;

class PemWorker extends Worker
{

	protected function validateData($data)
	{
		if (!isset($data['name']) || empty($data['name'])) {
			$this->abort('No Key Name provided');
		}

		if (!isset($data['token']) || empty($data['token'])) {
			$this->abort('No Key token provided');
		}

		return $data;
	}

	protected function processJob($job_func)
	{
		try {

			$data = $this->getData();
			
			call_user_func_array($job_func, [
				$data['name'],
				$data['token'],
			]);

		} catch (\Pheanstalk\Exception $e) {
			$this->notify('Job Failed', 'Caught '.get_class($e) ."\n".$e->getMessage(), 'danger');
			$this->logError($e);
			$this->buryJob();
		} catch (\Exception $e) {
			$this->notify('Job Failed', $e->getMessage(), 'danger');
			$this->logError($e);
			$this->buryJob();
		}
		
	}
	
}