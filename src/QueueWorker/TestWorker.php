<?php
namespace Futuredialog\PushWorker\QueueWorker;

use Pheanstalk\Exception;
use QueueWorker\Exceptions\WorkerExeption;
use QueueWorker\Exceptions\WorkerAbortExeption;
use QueueWorker\Exceptions\WorkerReplicatedExeption;
use QueueWorker\Exceptions\WorkerRetryExeption;

class TestWorker extends Worker
{
	protected function validateData($data)
	{
		
	}

	protected function processJob($job_func)
	{

	}
	
	
	
}