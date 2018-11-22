<?php
namespace Futuredialog\PushWorker\QueueWorker;


interface WorkerInterface {

	function success($data, $worker_data);
	function fail($data, $error, $worker_data);
	function run($func, $runOnce);
	function runOnce($func);
	function retry();
	function abort($reason);
	function complete();
	
}