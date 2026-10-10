<?php
/**
 * The theme settings screen in the dashboard: Appearance → Wavira settings.
 *
 * 0.12.0 put the panel in the Customizer, and the live preview is still there —
 * a control moves and the page changes while it moves. What that placement does
 * not survive is a **block theme**: WordPress hides Appearance → Customize for
 * block themes (`wp-admin/menu.php` adds the entry only when something has
 * registered `customize_register`), and even where the link exists, a panel that
 * lives inside a screen a customer does not think of as "the theme settings" is
 * a panel most customers never open. Wavira is a block theme, so "I installed it
 * and saw nothing new in the dashboard" is the report this file answers.
 *
 * There are now two front doors and one schema, not two implementations:
 *
 * - **this screen** — a `form-table` page under Appearance, with a tab per
 *   section, saving through `wavira_sanitize_value()` into the same theme mods;
 * - **the Customizer** (`inc/customizer.php`) — the same schema, with preview.
 *
 * `wavira_options_schema()` is still the only list of settings. Nothing in this
 * file re-declares a field, a default or a sanitizer, which is what keeps the
 * two doors from drifting apart: the test suite walks the schema, this screen
 * and the Customizer and compares all three.
 *
 * The **demo import** tab is the second half of the same report. The demo lives
 * in the Wavira Core plugin (`Tools → Wavira demo content`); a theme that
 * imported it itself would be calling another plugin's internals
 * (docs/ARCHITECTURE.md §2). What the theme can do is put the door in front of
 * the customer and say honestly whether the plugin is installed yet.
 *
 * @package Wavira\Theme
 * @since   0.13.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * The screen slug.
 *
 * @return string Slug.
 */
function wavira_admin_panel_slug(): string {
	return 'wavira-settings';
}

/**
 * A URL on the settings screen, optionally on one tab.
 *
 * @param string $tab Tab slug. Empty for the first tab.
 * @return string Admin URL.
 */
function wavira_admin_panel_url( string $tab = '' ): string {
	$url = admin_url( 'themes.php?page=' . wavira_admin_panel_slug() );

	return '' === $tab ? $url : add_query_arg( 'tab', $tab, $url );
}

/**
 * Register the screen under Appearance.
 *
 * `add_theme_page()` is deliberately not a top-level menu: this is a theme
 * setting, and WordPress already made room for it next to Themes and the Site
 * Editor. The capability is the same one the Customizer uses, so the two doors
 * admit exactly the same people.
 *
 * @return void
 */
function wavira_admin_panel_register(): void {
	add_theme_page(
		__( 'Wavira settings', 'wavira' ),
		__( 'Wavira settings', 'wavira' ),
		'edit_theme_options',
		wavira_admin_panel_slug(),
		'wavira_admin_panel_render'
	);
}
add_action( 'admin_menu', 'wavira_admin_panel_register' );

/**
 * The tabs of the screen: the schema's sections, plus the demo import.
 *
 * The sections are the Customizer's own list, in the same order, so a customer
 * who opens either door recognises the furniture. The demo tab is last because
 * it is the only tab that is not a setting.
 *
 * @return array<string, string> Tab slug => tab title.
 */
function wavira_admin_panel_tabs(): array {
	$sections = wavira_customize_sections();

	uasort(
		$sections,
		static function ( $a, $b ) {
			return (int) $a['priority'] <=> (int) $b['priority'];
		}
	);

	$tabs = array();

	foreach ( $sections as $slug => $section ) {
		$tabs[ (string) $slug ] = (string) $section['title'];
	}

	$tabs['demo'] = __( 'Demo import', 'wavira' );

	/**
	 * Filters the tabs of the settings screen.
	 *
	 * @since 0.13.0
	 * @param array<string, string> $tabs Tab slug => title.
	 */
	return (array) apply_filters( 'wavira_admin_panel_tabs', $tabs );
}

/**
 * The tab the request is asking for, or the first tab.
 *
 * Reading a tab slug is not a state change, so there is no nonce here; an
 * unknown value falls back to the first tab rather than printing an empty page.
 *
 * @return string Tab slug.
 */
function wavira_admin_panel_current_tab(): string {
	$tabs = wavira_admin_panel_tabs();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading our own read-only tab slug.
	$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';

	if ( isset( $tabs[ $requested ] ) ) {
		return $requested;
	}

	$keys = array_keys( $tabs );

	return (string) $keys[0];
}

/**
 * Whether a tab has any setting to show.
 *
 * @param string $tab Tab slug.
 * @return bool
 */
function wavira_admin_panel_tab_has_fields( string $tab ): bool {
	foreach ( wavira_options_schema() as $field ) {
		if ( wavira_admin_panel_has_field( $tab, $field ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether a field belongs to a tab.
 *
 * @param string               $tab   Tab slug.
 * @param array<string, mixed> $field Field definition.
 * @return bool
 */
function wavira_admin_panel_has_field( string $tab, array $field ): bool {
	return isset( $field['section'] ) && (string) $field['section'] === $tab;
}

/**
 * Print the screen.
 *
 * @return void
 */
function wavira_admin_panel_render(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$tabs   = wavira_admin_panel_tabs();
	$tab    = wavira_admin_panel_current_tab();
	$custom = add_query_arg( 'autofocus[panel]', 'wavira', admin_url( 'customize.php' ) );

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Wavira settings', 'wavira' ); ?></h1>

		<p class="description">
			<?php esc_html_e( 'Everything the theme itself needs: identity, header, colours, fonts, social links, footer text — and the demo content. Changes here are saved immediately; the live preview is in the Customizer.', 'wavira' ); ?>
		</p>

		<p>
			<a class="button" href="<?php echo esc_url( $custom ); ?>">
				<?php esc_html_e( 'Live preview in the Customizer', 'wavira' ); ?>
			</a>
		</p>

		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $title ) : ?>
				<a
					class="nav-tab<?php echo $slug === $tab ? ' nav-tab-active' : ''; ?>"
					href="<?php echo esc_url( wavira_admin_panel_url( (string) $slug ) ); ?>"
				>
					<?php echo esc_html( $title ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<?php if ( 'demo' === $tab ) : ?>
			<?php wavira_admin_panel_demo_tab(); ?>
		<?php elseif ( ! wavira_admin_panel_tab_has_fields( $tab ) ) : ?>
			<?php
			// A section with no settings of its own ("Language and Persian
			// setup"): its description is the whole tab, and a form with an
			// empty table and a Save button would be furniture for nothing.
			wavira_admin_panel_section_intro( $tab );
			?>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wavira_save_settings" />
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
				<?php wp_nonce_field( 'wavira_save_settings' ); ?>

				<?php wavira_admin_panel_section_intro( $tab ); ?>

				<table class="form-table" role="presentation">
					<tbody>
					<?php wavira_admin_panel_fields( $tab ); ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Save changes', 'wavira' ) ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * The section's own description, printed above its fields.
 *
 * The "Language and Persian setup" section has no settings at all: the site
 * language is a WordPress setting, not a theme option, so its description — the
 * nonced action that makes the site Persian, and the state of the plugin — is
 * the whole tab.
 *
 * @param string $tab Tab slug.
 * @return void
 */
function wavira_admin_panel_section_intro( string $tab ): void {
	$sections = wavira_customize_sections();

	if ( isset( $sections[ $tab ]['description'] ) && '' !== (string) $sections[ $tab ]['description'] ) {
		echo wp_kses_post( (string) $sections[ $tab ]['description'] );
	}

	if ( 'identity' === $tab ) {
		?>
		<p class="description">
			<?php esc_html_e( 'The site logo and the site icon are WordPress’ own settings, so they keep working if you switch themes. They are one click away:', 'wavira' ); ?>
			<a href="<?php echo esc_url( add_query_arg( 'autofocus[control]', 'custom_logo', admin_url( 'customize.php' ) ) ); ?>"><?php esc_html_e( 'logo', 'wavira' ); ?></a>
			·
			<a href="<?php echo esc_url( add_query_arg( 'autofocus[control]', 'site_icon', admin_url( 'customize.php' ) ) ); ?>"><?php esc_html_e( 'site icon', 'wavira' ); ?></a>
		</p>
		<?php
	}
}

/**
 * Every field of one tab.
 *
 * @param string $tab Tab slug.
 * @return void
 */
function wavira_admin_panel_fields( string $tab ): void {
	foreach ( wavira_options_schema() as $key => $field ) {
		if ( ! wavira_admin_panel_has_field( $tab, $field ) ) {
			continue;
		}

		wavira_admin_panel_field( (string) $key, $field );
	}
}

/**
 * One row of the form table.
 *
 * The `control` value in the schema decides what is printed — the same value the
 * Customizer switches on, so a field that renders as a media picker in one door
 * cannot render as a free-text box in the other.
 *
 * @param string               $key   Option name (without the `wavira_` prefix).
 * @param array<string, mixed> $field Field definition.
 * @return void
 */
function wavira_admin_panel_field( string $key, array $field ): void {
	$control = isset( $field['control'] ) ? (string) $field['control'] : 'text';
	$label   = isset( $field['label'] ) ? (string) $field['label'] : $key;
	$name    = 'wavira_' . $key;
	$id      = 'wavira-field-' . $key;
	$value   = wavira_option( $key );
	$help    = wavira_customize_descriptions();
	$text    = isset( $help[ $key ] ) ? (string) $help[ $key ] : '';

	?>
	<tr>
		<th scope="row">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		</th>
		<td>
			<?php
			switch ( $control ) {
				case 'checkbox':
					?>
					<label for="<?php echo esc_attr( $id ); ?>">
						<input
							type="checkbox"
							id="<?php echo esc_attr( $id ); ?>"
							name="<?php echo esc_attr( $name ); ?>"
							value="1"
							<?php checked( (bool) $value ); ?>
						/>
						<?php esc_html_e( 'Enabled', 'wavira' ); ?>
					</label>
					<?php
					break;

				case 'number':
					?>
					<input
						type="number"
						class="small-text"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( (string) $value ); ?>"
						<?php
						foreach ( array( 'min', 'max', 'step' ) as $attribute ) {
							if ( isset( $field[ $attribute ] ) ) {
								echo esc_attr( $attribute ) . '="' . esc_attr( (string) $field[ $attribute ] ) . '" ';
							}
						}
						?>
					/>
					<?php
					break;

				case 'select':
					$choices = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : array();
					?>
					<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
						<?php foreach ( $choices as $choice => $choice_label ) : ?>
							<option value="<?php echo esc_attr( (string) $choice ); ?>" <?php selected( (string) $choice, (string) $value ); ?>>
								<?php echo esc_html( (string) $choice_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php
					break;

				case 'color':
					?>
					<input
						type="text"
						class="regular-text code"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( (string) $value ); ?>"
						placeholder="#000000"
						data-wavira-colour-text
					/>
					<input
						type="color"
						value="<?php echo esc_attr( '' === (string) $value ? '#000000' : (string) $value ); ?>"
						data-wavira-colour-picker
						data-target="<?php echo esc_attr( $id ); ?>"
						aria-label="<?php esc_attr_e( 'Colour picker', 'wavira' ); ?>"
					/>
					<button type="button" class="button-link" data-wavira-colour-clear data-target="<?php echo esc_attr( $id ); ?>">
						<?php esc_html_e( 'Clear (use the theme colour)', 'wavira' ); ?>
					</button>
					<?php
					break;

				case 'media':
					$attachment = absint( $value );
					?>
					<input
						type="hidden"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( (string) $attachment ); ?>"
						data-wavira-media-input
					/>
					<div data-wavira-media>
						<div data-wavira-media-preview>
							<?php
							if ( $attachment > 0 ) {
								// Core built this markup from the attachment id; it is safe by construction.
								echo wp_get_attachment_image( $attachment, 'medium' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</div>
						<p>
							<button type="button" class="button" data-wavira-media-select data-target="<?php echo esc_attr( $id ); ?>">
								<?php esc_html_e( 'Choose image', 'wavira' ); ?>
							</button>
							<button type="button" class="button-link" data-wavira-media-remove data-target="<?php echo esc_attr( $id ); ?>">
								<?php esc_html_e( 'Remove', 'wavira' ); ?>
							</button>
						</p>
					</div>
					<?php
					break;

				case 'textarea':
					?>
					<textarea
						rows="3"
						class="large-text"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
					><?php echo esc_textarea( (string) $value ); ?></textarea>
					<?php
					break;

				case 'code':
					?>
					<textarea
						rows="8"
						class="large-text code"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						spellcheck="false"
					><?php echo esc_textarea( (string) $value ); ?></textarea>
					<?php
					break;

				case 'url':
					?>
					<input
						type="url"
						class="regular-text code"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( (string) $value ); ?>"
						placeholder="https://"
					/>
					<?php
					break;

				default:
					?>
					<input
						type="text"
						class="regular-text"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( (string) $value ); ?>"
					/>
					<?php
					break;
			}
			?>

			<?php if ( '' !== $text ) : ?>
				<p class="description"><?php echo esc_html( $text ); ?></p>
			<?php endif; ?>

			<?php if ( 'media' === $control ) : ?>
				<p class="description">
					<?php esc_html_e( 'Optional. A logo for dark mode; leave it empty to keep using the logo above in both modes.', 'wavira' ); ?>
				</p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/**
 * The demo-import tab.
 *
 * Two states, stated instead of implied: with the plugin, one button to the
 * screen that does the work; without it, what to install and where the download
 * is. A button that fails on click would be worse than no button.
 *
 * @return void
 */
function wavira_admin_panel_demo_tab(): void {
	$active = function_exists( 'wavira_core_is_active' ) && wavira_core_is_active();

	?>
	<h2><?php esc_html_e( 'Demo content', 'wavira' ); ?></h2>

	<p>
		<?php esc_html_e( 'The demo is a small, complete Persian music site: one artist, an album with four tracks (one of them a remix), a single and a music video — with lyrics, genres, durations and Jalali dates. It is the same content this theme was designed on, so the site looks finished before you add your own music.', 'wavira' ); ?>
	</p>

	<p>
		<strong><?php esc_html_e( 'No audio or video files are imported.', 'wavira' ); ?></strong>
		<?php esc_html_e( 'A demo pointing at files that do not exist would break the player on the first click. Add your own media and the player picks it up.', 'wavira' ); ?>
	</p>

	<?php if ( $active ) : ?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'tools.php?page=wavira-demo' ) ); ?>">
				<?php esc_html_e( 'Open Tools → Wavira demo content', 'wavira' ); ?>
			</a>
		</p>
		<p class="description">
			<?php esc_html_e( 'The import and the removal are on that screen, both behind the same capability check and nonce. Removing the demo deletes only the posts it created, never your own content.', 'wavira' ); ?>
		</p>
	<?php else : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php esc_html_e( 'Wavira Core is not active, so there is nothing on this site to import into yet. The plugin provides the music content types, the player and the demo importer; the theme renders what exists and keeps working without it.', 'wavira' ); ?>
			</p>
		</div>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=upload' ) ); ?>">
				<?php esc_html_e( 'Install the plugin (Upload Plugin)', 'wavira' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( 'https://github.com/Masoumiofficial/music-theme/releases' ); ?>">
				<?php esc_html_e( 'Download wavira-core', 'wavira' ); ?>
			</a>
		</p>
		<p class="description">
			<?php esc_html_e( 'After installing and activating it, come back to this tab: the import button appears here and on Tools → Wavira demo content.', 'wavira' ); ?>
		</p>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Persian setup', 'wavira' ); ?></h2>

	<p>
		<?php esc_html_e( 'The theme and the plugin are Persian on any locale. The WordPress dashboard is not, until the site language changes — one nonced action sets the site language, the timezone, the week start and the Persian date format, and reports what it could not do.', 'wavira' ); ?>
	</p>

	<p>
		<a class="button" href="<?php echo esc_url( wavira_persian_setup_url() ); ?>">
			<?php esc_html_e( 'Make the site Persian', 'wavira' ); ?>
		</a>
	</p>
	<?php
}

/**
 * The values a submitted tab holds, after sanitizing.
 *
 * Split from the save itself so the rule that matters most can be tested
 * without a request and without a redirect: **only the fields of the posted tab
 * are read**. A request that names a tab this screen does not have returns
 * nothing at all, and a request for one tab cannot touch another tab's
 * settings — which is the whole point of tabs in a form that posts back.
 *
 * Absence means `false` for a switch (an unchecked box is not posted) and the
 * empty string for everything else, which every sanitizer already handles as
 * "no value given".
 *
 * @param string               $tab  Tab slug.
 * @param array<string, mixed> $post Unslashed request data.
 * @return array<string, mixed> Sanitized value per option name.
 */
function wavira_admin_panel_values( string $tab, array $post ): array {
	$tabs = wavira_admin_panel_tabs();

	if ( ! isset( $tabs[ $tab ] ) ) {
		return array();
	}

	$values = array();

	foreach ( wavira_options_schema() as $key => $field ) {
		if ( ! wavira_admin_panel_has_field( $tab, $field ) ) {
			continue;
		}

		$name = 'wavira_' . $key;
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';
		$raw  = array_key_exists( $name, $post ) ? $post[ $name ] : ( 'bool' === $type ? false : '' );

		if ( is_array( $raw ) ) {
			$raw = '';
		}

		$values[ (string) $key ] = wavira_sanitize_value( $field, $raw );
	}

	return $values;
}

/**
 * Store the values of one tab, and report how many theme mods changed.
 *
 * A value that equals its declared default is removed instead of stored, the
 * same rule the rest of the theme follows: an untouched setting has no row, and
 * `wavira_option_css()` keeps returning an empty string on a default site.
 *
 * @param string               $tab  Tab slug.
 * @param array<string, mixed> $post Unslashed request data.
 * @return int Number of theme mods written or removed.
 */
function wavira_admin_panel_apply( string $tab, array $post ): int {
	$changed = 0;

	foreach ( wavira_admin_panel_values( $tab, $post ) as $key => $value ) {
		$default = wavira_option_default( $key );
		$name    = 'wavira_' . $key;

		if ( $value === $default ) {
			if ( false !== get_theme_mod( $name, false ) ) {
				remove_theme_mod( $name );
				++$changed;
			}

			continue;
		}

		set_theme_mod( $name, $value );
		++$changed;
	}

	return $changed;
}

/**
 * Save the submitted tab.
 *
 * @return void
 */
function wavira_admin_panel_save(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to change the theme settings.', 'wavira' ), 403 );
	}

	check_admin_referer( 'wavira_save_settings' );

	// The posted tab has to be a tab of this screen: a request that names an
	// unknown tab saves nothing, instead of trusting the caller's field list.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer() above.
	$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['tab'] ) ) : '';

	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified above; every value is sanitized by wavira_sanitize_value() through wavira_admin_panel_values().
	wavira_admin_panel_apply( $tab, (array) wp_unslash( $_POST ) );

	wp_safe_redirect( add_query_arg( 'wavira-saved', '1', wavira_admin_panel_url( $tab ) ) );
	exit;
}
add_action( 'admin_post_wavira_save_settings', 'wavira_admin_panel_save' );

/**
 * Say what happened after a save.
 *
 * The redirect-after-save pattern means the browser cannot re-post the form by
 * refreshing, and the message is read from a flag on the URL instead of state
 * that has to be cleaned up.
 *
 * @return void
 */
function wavira_admin_panel_notice(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'appearance_page_' . wavira_admin_panel_slug() !== $screen->id ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading our own read-only redirect flag.
	if ( ! isset( $_GET['wavira-saved'] ) ) {
		return;
	}

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wavira' ) . '</p></div>';
}
add_action( 'admin_notices', 'wavira_admin_panel_notice' );

/**
 * The styles and the one script the screen needs.
 *
 * The script is loaded on this screen only — an admin bundle on every screen
 * would be a cost every other page pays for this one. The media library is
 * enqueued through core's own helper so the picker is the familiar one, and the
 * colour field is an `<input type="color">`: the theme does not load jQuery for
 * a swatch (ADR 0006).
 *
 * @param string $hook_suffix Current screen's hook suffix.
 * @return void
 */
function wavira_admin_panel_assets( string $hook_suffix ): void {
	if ( 'appearance_page_' . wavira_admin_panel_slug() !== $hook_suffix ) {
		return;
	}

	$script = 'assets/dist/admin.js';

	if ( ! file_exists( WAVIRA_THEME_DIR . $script ) ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_script(
		'wavira-admin',
		WAVIRA_THEME_URI . $script,
		array(),
		wavira_asset_version( $script ),
		true
	);
	wp_add_inline_script(
		'wavira-admin',
		'window.waviraAdminText = ' . wp_json_encode(
			array(
				'choose' => __( 'Choose image', 'wavira' ),
				'use'    => __( 'Use this image', 'wavira' ),
			)
		) . ';',
		'before'
	);
}
add_action( 'admin_enqueue_scripts', 'wavira_admin_panel_assets' );

/**
 * Leave a note for the person who just activated the theme.
 *
 * Activation is the one moment a customer is looking for what changed, and the
 * appearance menu is not where a person who has just switched themes looks
 * first: the dashboard is. One notice, once per user, on the dashboard only.
 *
 * @return void
 */
function wavira_admin_panel_activated(): void {
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	set_transient( 'wavira_activated_' . get_current_user_id(), 'new', WEEK_IN_SECONDS );
}
add_action( 'after_switch_theme', 'wavira_admin_panel_activated' );

/**
 * The activation note, on the dashboard.
 *
 * @return void
 */
function wavira_admin_panel_activated_notice(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'dashboard' !== $screen->id ) {
		return;
	}

	if ( 'new' !== get_transient( 'wavira_activated_' . get_current_user_id() ) ) {
		return;
	}

	$demo = function_exists( 'wavira_core_is_active' ) && wavira_core_is_active()
		? admin_url( 'tools.php?page=wavira-demo' )
		: wavira_admin_panel_url( 'demo' );

	?>
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Wavira is active.', 'wavira' ); ?></strong>
			<?php esc_html_e( 'The theme settings are under Appearance → Wavira settings, the live preview is in the Customizer, and the demo content is one click away.', 'wavira' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( wavira_admin_panel_url() ); ?>">
				<?php esc_html_e( 'Wavira settings', 'wavira' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>">
				<?php esc_html_e( 'Customize', 'wavira' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( $demo ); ?>">
				<?php esc_html_e( 'Demo import', 'wavira' ); ?>
			</a>
			<a class="button-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wavira_activated_dismiss' ), 'wavira_activated_dismiss' ) ); ?>">
				<?php esc_html_e( 'Dismiss', 'wavira' ); ?>
			</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'wavira_admin_panel_activated_notice' );

/**
 * Handle the dismissal of the activation note.
 *
 * @return void
 */
function wavira_admin_panel_activated_dismiss(): void {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to change this.', 'wavira' ), 403 );
	}

	check_admin_referer( 'wavira_activated_dismiss' );

	delete_transient( 'wavira_activated_' . get_current_user_id() );

	$referer = wp_get_referer();

	wp_safe_redirect( $referer ? $referer : admin_url() );
	exit;
}
add_action( 'admin_post_wavira_activated_dismiss', 'wavira_admin_panel_activated_dismiss' );
