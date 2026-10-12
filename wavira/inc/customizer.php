<?php
/**
 * The theme options panel (Customizer).
 *
 * A theme that ships an options panel has to answer three questions, and the
 * answers are the design of this file:
 *
 * 1. **Where does it live?** In the Customizer, not in a hand-built settings
 *    page. It is the screen WordPress already opens for this job, it carries the
 *    capability model, the nonces, the preview and the "unsaved changes" guard,
 *    and a reviewer of the product does not have to audit a second permission
 *    system that only exists here.
 * 2. **What does it contain?** Exactly the fields in
 *    `wavira_options_schema()` — no second list to drift out of sync, and no
 *    control whose value nothing reads.
 * 3. **What does it *not* contain?** Page-builder switches, SEO toggles, a
 *    "disable Gutenberg" checkbox, demo-content switches for someone else's
 *    plugin. Every one of those is a defect in a commercial theme: it fights
 *    another plugin, it breaks when that plugin changes, or it hides the
 *    standard WordPress behaviour a customer learned.
 *
 * @package Wavira\Theme
 * @since   0.12.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the panel, its sections and every setting in the schema.
 *
 * @param WP_Customize_Manager $wp_customize Customizer instance.
 * @return void
 */
function wavira_customize_register( $wp_customize ): void {
	$wp_customize->add_panel(
		'wavira',
		array(
			'title'       => __( 'Wavira settings', 'wavira' ),
			'description' => wavira_customize_panel_description(),
			'priority'    => 20,
			'capability'  => 'edit_theme_options',
		)
	);

	foreach ( wavira_customize_sections() as $slug => $section ) {
		$wp_customize->add_section(
			'wavira_' . $slug,
			array(
				'title'       => $section['title'],
				'description' => isset( $section['description'] ) ? $section['description'] : '',
				'panel'       => 'wavira',
				'priority'    => $section['priority'],
				'capability'  => 'edit_theme_options',
			)
		);
	}

	foreach ( wavira_options_schema() as $key => $field ) {
		wavira_customize_field( $wp_customize, (string) $key, $field );
	}

	// The two core uploads belong next to the theme's own identity settings.
	$logo = $wp_customize->get_control( 'custom_logo' );

	if ( $logo ) {
		$logo->section  = 'wavira_identity';
		$logo->priority = 5;
	}

	$icon = $wp_customize->get_control( 'site_icon' );

	if ( $icon ) {
		$icon->section     = 'wavira_identity';
		$icon->priority    = 6;
		$icon->description = __( 'The browser tab icon (favicon) and the app icon on phones.', 'wavira' );
	}
}
add_action( 'customize_register', 'wavira_customize_register' );

/**
 * The sections of the Wavira panel, in the order a site owner meets them.
 *
 * @return array<string, array<string, mixed>> Section title, priority and description.
 */
function wavira_customize_sections(): array {
	$sections = array(
		'site'       => array(
			'title'       => __( 'Language and Persian setup', 'wavira' ),
			'description' => wavira_customize_site_description(),
			'priority'    => 5,
		),
		'identity'   => array(
			'title'    => __( 'Identity and logo', 'wavira' ),
			'priority' => 10,
		),
		'header'     => array(
			'title'       => __( 'Header', 'wavira' ),
			'description' => __( 'The header is a template part: reorder it and add blocks in the Site Editor. These switches control what the theme prints around your own blocks.', 'wavira' ),
			'priority'    => 20,
		),
		'appearance' => array(
			'title'       => __( 'Appearance', 'wavira' ),
			'description' => __( 'Colours and sizes are emitted as CSS variables, so every block follows them — including the blocks you add later.', 'wavira' ),
			'priority'    => 30,
		),
		'type'       => array(
			'title'    => __( 'Fonts and typography', 'wavira' ),
			'priority' => 40,
		),
		'social'     => array(
			'title'       => __( 'Social networks', 'wavira' ),
			'description' => __( 'A filled field prints its icon in the footer. An empty field prints nothing at all — no dead links.', 'wavira' ),
			'priority'    => 50,
		),
		'texts'      => array(
			'title'    => __( 'Texts and footer', 'wavira' ),
			'priority' => 60,
		),
		'tools'      => array(
			'title'       => __( 'Tools', 'wavira' ),
			'description' => __( 'Additional CSS is loaded after the theme stylesheet, exactly like the Customizer’s own field, and it is printed without a build step.', 'wavira' ),
			'priority'    => 70,
		),
	);

	/**
	 * Filters the Customizer sections of the Wavira panel.
	 *
	 * @since 0.12.0
	 * @param array<string, array<string, mixed>> $sections Sections keyed by slug.
	 */
	return (array) apply_filters( 'wavira_customize_sections', $sections );
}

/**
 * The panel description: what this panel is and where the rest of the product is.
 *
 * @return string Safe HTML.
 */
function wavira_customize_panel_description(): string {
	$description = '<p>' . esc_html__( 'Everything the theme itself needs: identity, header, colours, fonts, social links and footer text. Music content, the player engine and the migration tool belong to the Wavira Core plugin and keep working if you switch themes.', 'wavira' ) . '</p>';

	return $description;
}

/**
 * The description of the "Language and Persian setup" section.
 *
 * This section has no settings on purpose: switching the *site* language is a
 * WordPress action, not a theme setting, so it is offered as a nonced link that
 * does the three things a Persian music site needs at once. Both links state
 * whether the core plugin is present, instead of offering a button that would
 * fail on click.
 *
 * @return string Safe HTML.
 */
function wavira_customize_site_description(): string {
	$html = '<p>' . esc_html__( 'The theme and the plugin ship their own Persian translations, so the music interface is Persian on any locale. A site whose language is not Persian yet also needs WordPress itself in Persian — this link sets the site language, the timezone and the Persian date format in one step.', 'wavira' ) . '</p>';

	$html .= '<p><a class="button" href="' . esc_url( wavira_persian_setup_url() ) . '">' . esc_html__( 'Make the site Persian', 'wavira' ) . '</a></p>';

	$active = function_exists( 'wavira_core_is_active' ) && wavira_core_is_active();

	if ( $active ) {
		$html .= '<p>' . esc_html__( 'Demo content (a Persian artist, an album, tracks, a video and a menu):', 'wavira' ) . ' '
			. '<a href="' . esc_url( admin_url( 'tools.php?page=wavira-demo' ) ) . '">' . esc_html__( 'Tools → Wavira demo content', 'wavira' ) . '</a>.</p>';
	} else {
		$html .= '<p>' . esc_html__( 'Wavira Core is not active: install and activate it to get the music content types, the player and the demo content. The theme renders what exists and stays functional either way.', 'wavira' ) . ' '
			. '<a href="' . esc_url( admin_url( 'plugin-install.php?s=wavira&tab=search&type=term' ) ) . '">' . esc_html__( 'Find the plugin', 'wavira' ) . '</a>.</p>';
	}

	return $html;
}

/**
 * Register one setting and its control.
 *
 * @param WP_Customize_Manager $wp_customize Customizer instance.
 * @param string               $key          Option name.
 * @param array<string, mixed> $field        Field definition from the schema.
 * @return void
 */
function wavira_customize_field( $wp_customize, string $key, array $field ): void {
	$id      = 'wavira_' . $key;
	$section = isset( $field['section'] ) ? (string) $field['section'] : 'appearance';
	$control = isset( $field['control'] ) ? (string) $field['control'] : 'text';
	$descr   = wavira_customize_descriptions();
	$args    = array(
		'label'       => isset( $field['label'] ) ? (string) $field['label'] : $key,
		'description' => isset( $descr[ $key ] ) ? $descr[ $key ] : '',
		'section'     => 'wavira_' . $section,
		'settings'    => $id,
		'priority'    => wavira_customize_priority( $key ),
	);
	$input   = array();

	foreach ( array( 'min', 'max', 'step' ) as $attribute ) {
		if ( isset( $field[ $attribute ] ) ) {
			$input[ $attribute ] = $field[ $attribute ];
		}
	}

	if ( array() !== $input ) {
		$args['input_attrs'] = $input;
	}

	$wp_customize->add_setting(
		$id,
		array(
			'default'           => array_key_exists( 'default', $field ) ? $field['default'] : '',
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => isset( $field['transport'] ) ? (string) $field['transport'] : 'refresh',
			'sanitize_callback' => 'wavira_sanitize_option',
		)
	);

	switch ( $control ) {
		case 'media':
			$wp_customize->add_control(
				new WP_Customize_Media_Control(
					$wp_customize,
					$id,
					$args + array( 'mime_type' => 'image' )
				)
			);

			return;

		case 'color':
			$wp_customize->add_control(
				new WP_Customize_Color_Control( $wp_customize, $id, $args )
			);

			return;

		case 'code':
			$wp_customize->add_control(
				new WP_Customize_Code_Editor_Control(
					$wp_customize,
					$id,
					$args + array(
						'editor_settings' => array(
							'codemirror' => array( 'mode' => 'css' ),
						),
					)
				)
			);

			return;

		case 'select':
			$args['type']    = 'select';
			$args['choices'] = isset( $field['choices'] ) ? $field['choices'] : array();

			break;

		case 'checkbox':
		case 'textarea':
		case 'number':
		case 'url':
		default:
			$args['type'] = in_array( $control, array( 'checkbox', 'textarea', 'number', 'url' ), true ) ? $control : 'text';

			break;
	}

	$wp_customize->add_control( $id, $args );
}

/**
 * The inline help of each field, keyed by option name.
 *
 * Kept out of the schema on purpose: the schema is data (what a value *is*), the
 * help text is prose about consequences, and the two change for different
 * reasons.
 *
 * @return array<string, string> Description per option name.
 */
function wavira_customize_descriptions(): array {
	$descriptions = array(
		'logo_width'        => __( 'The maximum width of the logo. The logo keeps its own proportions on smaller screens.', 'wavira' ),
		'dark_logo'         => __( 'Optional. Shown instead of the logo above when dark mode is active — including on the automatic setting, which follows the device.', 'wavira' ),
		'show_site_title'   => __( 'Hide it when the logo already contains the site name; search engines still read the title.', 'wavira' ),
		'show_tagline'      => __( 'The tagline is set in Settings → General.', 'wavira' ),
		'sticky_header'     => __( 'Keeps the header at the top while the page scrolls.', 'wavira' ),
		'header_search'     => __( 'Adds the WordPress search block to the header.', 'wavira' ),
		'top_bar'           => __( 'A slim bar above the header, for a tour announcement or a release note.', 'wavira' ),
		'top_bar_text'      => __( 'Shown in the announcement bar. Leave empty to keep the bar for the link only.', 'wavira' ),
		'top_bar_url'       => __( 'Optional. Where the announcement bar links to.', 'wavira' ),
		'player_bar'        => __( 'Off removes the player bar and its reserved space from every template. The per-page players (tracklists, the player block) are not affected.', 'wavira' ),
		'colour_mode'       => __( 'Applies to a first visit. A visitor who uses the toggle keeps their own choice.', 'wavira' ),
		'accent'            => __( 'Used for buttons, links and highlights. Each mode has one colour field, so dark mode is not forgotten: check the contrast against both backgrounds.', 'wavira' ),
		'container_width'   => __( 'The width of the site layout on wide screens.', 'wavira' ),
		'radius'            => __( 'Used by covers, cards, buttons and inputs.', 'wavira' ),
		'card_shadow'       => __( 'Off gives a flat, editorial look.', 'wavira' ),
		'card_min_width'    => __( 'Cards wrap into as many columns as fit. A larger number means fewer, bigger cards.', 'wavira' ),
		'show_theme_toggle' => __( 'Hides the button only; the visitor’s device preference keeps working.', 'wavira' ),
		'font_family'       => __( 'Vazirmatn is bundled in the theme: no request leaves the site, and Persian letterforms are complete. A custom stack must not point at a remote font file.', 'wavira' ),
		'font_stack'        => __( 'A CSS font stack, for example: "IRANSans", Tahoma, sans-serif. Used only when “Custom stack” is selected.', 'wavira' ),
		'font_base_size'    => __( 'Every block size in the theme is relative to this, so the whole page scales together.', 'wavira' ),
		'heading_weight'    => __( 'Vazirmatn is a variable font, so every weight renders without a second file.', 'wavira' ),
		'font_preload'      => __( 'Starts the font download with the document, which removes the swap on the first screen. Turn it off if you replace the font and keep this setting.', 'wavira' ),
		'social_size'       => __( 'Icon size in the footer row.', 'wavira' ),
		'social_new_tab'    => __( 'Each new tab gets rel="noopener" — the browser cannot hand the tab to the linked page.', 'wavira' ),
		'footer_note'       => __( 'A line of your own in the footer, for a licence note or a request. The design credit stays: attribution is part of the licence.', 'wavira' ),
		'back_to_top'       => __( 'A small button that returns to the top of a long page. It is skipped for visitors who prefer reduced motion.', 'wavira' ),
		'custom_css'        => __( 'CSS of your own, printed after the theme stylesheet. HTML tags and comments are stripped; a closing style tag would end the block.', 'wavira' ),
	);

	/**
	 * Filters the inline help of the theme options.
	 *
	 * @since 0.12.0
	 * @param array<string, string> $descriptions Description per option name.
	 */
	return (array) apply_filters( 'wavira_customize_descriptions', $descriptions );
}

/**
 * The order of the fields inside a section: the schema's own order.
 *
 * Returned as a stable, small number so two fields never share a priority and
 * the panel does not reorder itself between requests.
 *
 * @param string $key Option name.
 * @return int Priority.
 */
function wavira_customize_priority( string $key ): int {
	$keys  = array_keys( wavira_options_schema() );
	$index = array_search( $key, $keys, true );

	return false === $index ? 100 : ( (int) $index + 1 ) * 10;
}

/**
 * The settings the preview updates without a reload, and how.
 *
 * Derived from the schema's `live` entries rather than written again: every
 * field whose transport is `postMessage` is in this list, and no other field is.
 * A setting that arrives here without a `live` entry would be a setting whose
 * transport promised something the preview cannot do.
 *
 * `mode` is `var` (a CSS custom property), `bool-class` (a body class while the
 * switch is off), `root-font` (`html{font-size}`), `weight` (the heading-weight
 * rule) or `css` (the free-CSS block). A `var` entry carries the property name,
 * an optional unit, and — for switches — the value that means "off".
 *
 * @return array<string, array<string, mixed>> Live entries keyed by option name.
 */
function wavira_customize_live_map(): array {
	$map = array();

	foreach ( wavira_options_schema() as $key => $field ) {
		if ( 'postMessage' !== ( isset( $field['transport'] ) ? (string) $field['transport'] : 'refresh' ) ) {
			continue;
		}

		$live = isset( $field['live'] ) && is_array( $field['live'] ) ? $field['live'] : array();

		if ( ! isset( $live['mode'] ) ) {
			$live['mode'] = isset( $live['class'] ) ? 'bool-class' : 'var';
		}

		$map[ (string) $key ] = array_merge(
			$live,
			array(
				'key'     => (string) $key,
				'type'    => isset( $field['type'] ) ? (string) $field['type'] : 'text',
				'default' => array_key_exists( 'default', $field ) ? $field['default'] : '',
			)
		);
	}

	/**
	 * Filters the live-preview map.
	 *
	 * @since 0.12.0
	 * @param array<string, array<string, mixed>> $map Entries keyed by option name.
	 */
	return (array) apply_filters( 'wavira_customize_live_map', $map );
}

/**
 * Load the preview script inside the Customizer's preview frame.
 *
 * Only there, and only with `customize-preview` as its dependency: the control
 * pane talks to the preview over `postMessage`, and both sides are core's. The
 * script is built like every other asset (`tools/build.mjs`) so what ships is
 * the concatenated file the build verified.
 *
 * @return void
 */
function wavira_customize_preview_script(): void {
	$version = wavira_asset_version( 'js/customizer.js' );

	wp_enqueue_script(
		'wavira-customize-preview',
		WAVIRA_THEME_URI . 'assets/dist/customizer.js',
		array( 'customize-preview' ),
		$version,
		true
	);

	wp_add_inline_script(
		'wavira-customize-preview',
		'window.waviraCustomizeLive = ' . wp_json_encode( array_values( wavira_customize_live_map() ) ) . ';',
		'before'
	);
}
add_action( 'customize_preview_init', 'wavira_customize_preview_script' );
