<?php

namespace Futuredialog\PushWorker\QueueWorker;

use Futuredialog\PushWorker\QueueWorker\Exceptions\WorkerAbortExeption;
use Futuredialog\PushWorker\QueueWorker\Exceptions\WorkerReplicatedExeption;
use Futuredialog\PushWorker\QueueWorker\Exceptions\WorkerRetryExeption;

class PushWorker extends Worker
{

	protected function validateData($data)
	{
		if (!isset($data['apiKey'])) {
			$this->abort('No Api Key Provided');
		}

		if (!isset($data['recipients']) || !is_array($data['recipients'])) {
			$this->abort('Wrong recipients format');
		}
		
		if (!isset($data['notification']) || !is_array($data['notification']) || empty($data['notification'])) {
			$data['notification'] = null;
		}

		$data['payload'] = (isset($data['payload'])) ? $data['payload'] : [];
		
		return $data;
	}

	protected function processJob($job_func)
	{
		try {
			$this->checkJob();
			
			$data = $this->getData();
			
			$log_data = $data;
			$log_data['apiKey'] = substr(((is_array($log_data['apiKey'])) ? current($log_data['apiKey']) : $log_data['apiKey']), -10);
			$log_data['recipients'] = count($log_data['recipients']);
			$this->logData('Started', $log_data);

			call_user_func_array($job_func, [
				$data['apiKey'],
				$data['recipients'],
				$data['notification'],
				$data['payload']
			]);

		} catch (\Pheanstalk\Exception $e) {
			$this->notify('Job Failed', 'Caught '.get_class($e) ."\n".$e->getMessage(), 'danger');
			$this->logError($e);
			$this->buryJob();
		} catch(WorkerReplicatedExeption $e) {
			$this->notify('Job Completed. Replicating...', $e->getMessage(), 'good');
			$this->logError($e);
			$this->doneJob();
		} catch(WorkerRetryExeption $e) {
			$this->notify('Retrying Job', $e->getMessage(), 'warning');
			$this->logError($e);
			$this->doneJob();
		} catch(WorkerAbortExeption $e) {
			$this->notify('Job Aborted', $e->getMessage(), 'warning');
			$this->logError($e);
			$this->doneJob();
		} catch (\Exception $e) {
			$this->notify('Job Failed', $e->getMessage(), 'danger');
			$this->logError($e);
			$this->buryJob();
		}
	}
	
}