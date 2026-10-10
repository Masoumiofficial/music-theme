<?php
/**
 * Runtime verification of the plugin's public function API.
 *
 * The theme is allowed to call these functions and nothing else
 * (`tools/check-boundaries.mjs` rule R3), so their contract — exists, returns the
 * documented type, degrades safely — is part of the product, not an internal
 * detail.
 *
 * @package Wavira\Tests
 */

/**
 * Class Test_Public_Api
 */
class Test_Public_Api extends Wavira_Test_Case {

	/**
	 * The documented surface exists.
	 *
	 * @return void
	 */
	public function test_public_functions_are_loaded() {
		$this->assertTrue( function_exists( 'wavira_core_is_active' ) );
		$this->assertTrue( function_exists( 'wavira_core_get_setting' ) );
		$this->assertTrue( function_exists( 'wavira_core_related_posts' ) );
	}

	/**
	 * The plugin reports itself as active once it booted.
	 *
	 * @return void
	 */
	public function test_is_active_follows_the_booted_action() {
		$this->assertGreaterThan( 0, did_action( 'wavira_core_booted' ) );
		$this->assertTrue( wavira_core_is_active() );
	}

	/**
	 * Settings are read with the schema defaults, and unknown keys fall back.
	 *
	 * @return void
	 */
	public function test_get_setting_uses_schema_defaults() {
		$this->assertSame( 8, wavira_core_get_setting( 'related_limit' ) );
		$this->assertSame( 'sentinel', wavira_core_get_setting( 'not_a_setting', 'sentinel' ) );
	}

	/**
	 * Related items always come back as an array, even without relations.
	 *
	 * @return void
	 */
	public function test_related_posts_returns_an_array() {
		$track_id = self::factory()->post->create(
			array(
				'post_type'   => 'wavira_track',
				'post_status' => 'publish',
				'post_title'  => 'Lonely track',
			)
		);

		$this->assertSame( array(), wavira_core_related_posts( $track_id ) );
		$this->assertSame( array(), wavira_core_related_posts( 999999 ) );
	}
}
