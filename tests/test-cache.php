<?php
/**
 * Runtime verification of the versioned cache helper and its invalidation.
 *
 * @package Wavira\Tests
 */

use Wavira\Core\Settings\Settings;
use Wavira\Core\Support\Cache;

/**
 * Class Test_Cache
 */
class Test_Cache extends Wavira_Test_Case {

	/**
	 * Values round-trip through the object cache.
	 *
	 * @return void
	 */
	public function test_set_get_and_remember() {
		$this->assertFalse( Cache::get( 'unit:missing' )['found'] );

		Cache::set( 'unit:value', array( 1, 2, 3 ), 60 );

		$hit = Cache::get( 'unit:value' );

		$this->assertTrue( $hit['found'] );
		$this->assertSame( array( 1, 2, 3 ), $hit['value'] );

		$calls = 0;
		$value = Cache::remember(
			'unit:computed',
			static function () use ( &$calls ) {
				$calls++;

				return 'computed';
			},
			60
		);

		$this->assertSame( 'computed', $value );
		$this->assertSame( 1, $calls );

		Cache::remember(
			'unit:computed',
			static function () use ( &$calls ) {
				$calls++;

				return 'recomputed';
			},
			60
		);

		$this->assertSame( 1, $calls, 'a warm cache never calls the callback' );
	}

	/**
	 * Flushing bumps the generation and invalidates every key.
	 *
	 * @return void
	 */
	public function test_flush_invalidates_everything() {
		$before = Cache::generation();

		Cache::set( 'unit:generation', 'alive', 60 );

		Cache::flush();

		$this->assertSame( $before + 1, Cache::generation() );
		$this->assertFalse( Cache::get( 'unit:generation' )['found'] );
	}

	/**
	 * Saving music content flushes computed caches.
	 *
	 * @return void
	 */
	public function test_saving_music_content_flushes_the_cache() {
		Cache::set( 'unit:content', 'cached', 60 );

		self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'publish',
				'post_title'  => 'Triggers a flush',
			)
		);

		$this->assertFalse( Cache::get( 'unit:content' )['found'] );
	}

	/**
	 * Unrelated content does not flush music caches.
	 *
	 * @return void
	 */
	public function test_unrelated_content_keeps_the_cache() {
		Cache::set( 'unit:blog', 'cached', 60 );

		self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'A blog post',
			)
		);

		$this->assertTrue( Cache::get( 'unit:blog' )['found'] );
	}

	/**
	 * Updating the settings option flushes computed caches.
	 *
	 * @return void
	 */
	public function test_settings_update_flushes_the_cache() {
		Cache::set( 'unit:settings', 'cached', 60 );

		Settings::update( array( 'related_limit' => 12 ) );

		$this->assertFalse( Cache::get( 'unit:settings' )['found'] );
	}
}
