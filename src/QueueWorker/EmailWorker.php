<?php

namespace Futuredialog\PushWorker\QueueWorker;

use Futuredialog\PushWorker\QueueWorker\Exceptions\WorkerAbortExeption;
use Futuredialog\PushWorker\QueueWorker\Exceptions\WorkerReplicatedExeption;
use Futuredialog\PushWorker\QueueWorker\Exceptions\WorkerRetryExeption;


class EmailWorker extends Worker
{
    public function processJob($job_func)
    {
        try {

            $this->checkJob();

            $data = $this->getData();
            call_user_func_array($job_func, [
                $data
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

    public function validateData($data)
    {
        if (!isset($data['recipients']) || !is_array($data['recipients'])) {
            $this->abort('Wrong recipients format');
        }
        if (!isset($data['company_id']) || !isset($data['from'])|| !isset($data['type'])  ) {
            $this->abort('Wrong job format');
        }
        return $data;
    }
}