<?php
/**
 * Registers and dispatches all WordPress hooks for the plugin.
 *
 * @package Noor_TMS\Includes
 */

namespace Noor_TMS\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Class Loader
 *
 * Collects actions and filters, then registers them with WordPress in bulk
 * when run() is called. This decouples hook registration from hook execution
 * and makes the plugin straightforward to unit-test.
 */
class Loader {

	/** @var array<int, array<string, mixed>> */
	private array $actions = [];

	/** @var array<int, array<string, mixed>> */
	private array $filters = [];

	// -----------------------------------------------------------------------
	// Registration helpers
	// -----------------------------------------------------------------------

	/**
	 * Queue an action hook.
	 *
	 * @param string   $hook          The name of the WordPress action.
	 * @param object   $component     Object that owns the callback.
	 * @param string   $callback      Method name on $component.
	 * @param int      $priority      Hook priority (default 10).
	 * @param int      $accepted_args Number of arguments the callback accepts.
	 */
	public function add_action(
		string $hook,
		object $component,
		string $callback,
		int    $priority      = 10,
		int    $accepted_args = 1
	): void {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Queue a filter hook.
	 *
	 * @param string   $hook
	 * @param object   $component
	 * @param string   $callback
	 * @param int      $priority
	 * @param int      $accepted_args
	 */
	public function add_filter(
		string $hook,
		object $component,
		string $callback,
		int    $priority      = 10,
		int    $accepted_args = 1
	): void {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	// -----------------------------------------------------------------------
	// Dispatch
	// -----------------------------------------------------------------------

	/**
	 * Register all queued hooks with WordPress.
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				[ $hook['component'], $hook['callback'] ],
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				[ $hook['component'], $hook['callback'] ],
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
