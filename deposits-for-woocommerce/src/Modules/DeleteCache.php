<?php
namespace Deposits_WooCommerce\Modules;

class DeleteCache extends \WC_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'cix_delete_deposit_cache';
	/**
	 * @var mixed
	 */
	protected $noices;
	/**
	 * @var mixed
	 */
	protected $db;
	public function __construct() {

		parent::__construct();
	}
	/**
	 * Is job running?
	 *
	 * @return boolean
	 */
	public function is_running() {
		return $this->is_queue_empty();
	}

	/**
	 * Task callback.
	 *
	 * @param mixed $batch Batch of data.
	 * @return bool
	 */
	protected function task( $batch ) {

		foreach ( $batch as $key ) {
			delete_transient( $key );
		}

		return false; // Return false to stop the background process
	}

	/**
	 * This runs once the job has completed all items on the queue.
	 *
	 * @return void
	 */
	protected function complete() {
		parent::complete();
	}
}
