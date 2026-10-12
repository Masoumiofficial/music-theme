<?php
/**
 * Contract for self-registering modules.
 *
 * Every module of the plugin (content types, player, REST controllers, settings,
 * admin screens, …) implements this interface, so that new modules can be added
 * without touching the bootstrap class (open/closed principle).
 *
 * @package Wavira\Core\Contracts
 */

namespace Wavira\Core\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Interface Registrable
 */
interface Registrable {

	/**
	 * Register the module's hooks. Called exactly once per request.
	 *
	 * @return void
	 */
	public function register(): void;
}
