<?php
/**
 * Theme options: one schema, one getter, one CSS output.
 *
 * Everything a site owner can set lives in `wavira_options_schema()`. The
 * Customizer panel (`inc/customizer.php`) builds its controls from that array,
 * the sanitizer dispatches on the type it declares, and the front end reads it
 * through `wavira_option()`. One source of truth means a setting cannot exist in
 * the panel but not in the output, or the other way round — the failure mode of
 * every hand-written options framework.
 *
 * Where the values end up (ADR 0014 keeps this consistent):
 * - colours, radii, sizes and font stacks are emitted as **CSS custom
 *   properties**, so a block that uses a preset follows the setting without a
 *   second stylesheet and without `!important`;
 * - a switch that CSS would have to fight the editor over (heading weight,
 *   sticky header) is a **body class**, which wins by specificity;
 * - the logo, the social row, the top bar and the back-to-top button are
 *   rendered by patterns, because they are markup, not style.
 *
 * Nothing here is a `!important`, nothing is loaded from a remote origin, and
 * `wavira_option_css()` returns an empty string when every setting is at its
 * default — a default site ships no extra bytes (docs/adr/0020).
 *
 * @package Wavira\Theme
 * @since   0.12.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * The option schema: every setting, its default and how it is rendered.
 *
 * Types are exhaustive on purpose — `int`, `bool`, `color`, `url`, `text`,
 * `multiline`, `choice`, `stack`, `attachment`, `css` — and each one has exactly
 * one sanitizer in `wavira_sanitize_value()`. A type that is missing there is a
 * bug the test suite catches, not a value that reaches the database unvalidated.
 *
 * @return array<string, array<string, mixed>> Field keyed by option name.
 */
function wavira_options_schema(): array {
	$schema = array(
		// ---------------------------------------------------------------- identity.
		'logo_width'        => array(
			'default'   => 150,
			'type'      => 'int',
			'min'       => 40,
			'max'       => 480,
			'step'      => 2,
			'control'   => 'number',
			'section'   => 'identity',
			'label'     => __( 'Logo width (px)', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array(
				'css'  => '--wavira-logo-width',
				'unit' => 'px',
			),
		),
		'dark_logo'         => array(
			'default' => 0,
			'type'    => 'attachment',
			'control' => 'media',
			'section' => 'identity',
			'label'   => __( 'Logo for dark mode', 'wavira' ),
		),
		'show_site_title'   => array(
			'default'   => true,
			'type'      => 'bool',
			'control'   => 'checkbox',
			'section'   => 'identity',
			'label'     => __( 'Show the site title', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array(
				'mode'  => 'bool-class',
				'class' => 'wavira-no-title',
			),
		),
		'show_tagline'      => array(
			'default'   => true,
			'type'      => 'bool',
			'control'   => 'checkbox',
			'section'   => 'identity',
			'label'     => __( 'Show the tagline', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array(
				'mode'  => 'bool-class',
				'class' => 'wavira-no-tagline',
			),
		),

		// ------------------------------------------------------------------ header.
		'sticky_header'     => array(
			'default'   => true,
			'type'      => 'bool',
			'control'   => 'checkbox',
			'section'   => 'header',
			'label'     => __( 'Sticky header', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array(
				'mode'  => 'bool-class',
				'class' => 'wavira-not-sticky',
			),
		),
		'header_search'     => array(
			'default' => true,
			'type'    => 'bool',
			'control' => 'checkbox',
			'section' => 'header',
			'label'   => __( 'Search button in the header', 'wavira' ),
		),
		'top_bar'           => array(
			'default' => false,
			'type'    => 'bool',
			'control' => 'checkbox',
			'section' => 'header',
			'label'   => __( 'Announcement bar above the header', 'wavira' ),
		),
		'top_bar_text'      => array(
			'default' => '',
			'type'    => 'text',
			'control' => 'text',
			'section' => 'header',
			'label'   => __( 'Announcement text', 'wavira' ),
		),
		'top_bar_url'       => array(
			'default' => '',
			'type'    => 'url',
			'control' => 'url',
			'section' => 'header',
			'label'   => __( 'Announcement link', 'wavira' ),
		),
		'player_bar'        => array(
			'default' => true,
			'type'    => 'bool',
			'control' => 'checkbox',
			'section' => 'header',
			'label'   => __( 'Player bar on every page', 'wavira' ),
		),

		// -------------------------------------------------------------- appearance.
		'colour_mode'       => array(
			'default' => 'auto',
			'type'    => 'choice',
			'control' => 'select',
			'section' => 'appearance',
			'label'   => __( 'Colour mode for a first visit', 'wavira' ),
			'choices' => array(
				'auto'  => __( 'Auto (follow the device)', 'wavira' ),
				'light' => __( 'Light', 'wavira' ),
				'dark'  => __( 'Dark', 'wavira' ),
			),
		),
		'accent'            => array(
			'default'   => '',
			'type'      => 'color',
			'control'   => 'color',
			'section'   => 'appearance',
			'label'     => __( 'Accent colour', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array( 'css' => '--wp--preset--color--primary' ),
		),
		'container_width'   => array(
			'default'   => 1200,
			'type'      => 'int',
			'min'       => 720,
			'max'       => 1600,
			'step'      => 10,
			'control'   => 'number',
			'section'   => 'appearance',
			'label'     => __( 'Content width (px)', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array(
				'css'  => '--wavira-container',
				'unit' => 'px',
			),
		),
		'radius'            => array(
			'default'   => 12,
			'type'      => 'int',
			'min'       => 0,
			'max'       => 32,
			'step'      => 1,
			'control'   => 'number',
			'section'   => 'appearance',
			'label'     => __( 'Corner radius (px)', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array(
				'css'  => '--wp--custom--radius--md',
				'unit' => 'px',
			),
		),
		'card_shadow'       => array(
			'default'   => true,
			'type'      => 'bool',
			'control'   => 'checkbox',
			'section'   => 'appearance',
			'label'     => __( 'Shadow on cards and covers', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array(
				'css' => '--wavira-cover-shadow',
				'off' => 'none',
			),
		),
		'card_min_width'    => array(
			'default'   => 14,
			'type'      => 'int',
			'min'       => 10,
			'max'       => 26,
			'step'      => 1,
			'control'   => 'number',
			'section'   => 'appearance',
			'label'     => __( 'Smallest card width (rem)', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array(
				'css'  => '--wavira-grid-min',
				'unit' => 'rem',
			),
		),
		'show_theme_toggle' => array(
			'default'   => true,
			'type'      => 'bool',
			'control'   => 'checkbox',
			'section'   => 'appearance',
			'label'     => __( 'Light/dark toggle button', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array(
				'mode'  => 'bool-class',
				'class' => 'wavira-no-toggle',
			),
		),
		'glass'             => array(
			'default'   => true,
			'type'      => 'bool',
			'control'   => 'checkbox',
			'section'   => 'appearance',
			'label'     => __( 'Frosted-glass panels', 'wavira' ),
			'transport' => 'postMessage',
			// The class is the switch: tokens.css redefines the four material
			// tokens under `body.wavira-no-glass`, so turning the effect off is
			// one selector in one place rather than a rule per component.
			'live'      => array(
				'mode'  => 'bool-class',
				'class' => 'wavira-no-glass',
			),
		),

		// ---------------------------------------------------------------- typography.
		'font_family'       => array(
			'default' => 'vazirmatn',
			'type'    => 'choice',
			'control' => 'select',
			'section' => 'type',
			'label'   => __( 'Font', 'wavira' ),
			'choices' => array(
				'vazirmatn' => __( 'Vazirmatn (bundled, recommended for Persian)', 'wavira' ),
				'system'    => __( 'System fonts (no font file)', 'wavira' ),
				'custom'    => __( 'Custom stack', 'wavira' ),
			),
		),
		'font_stack'        => array(
			'default' => '',
			'type'    => 'stack',
			'control' => 'text',
			'section' => 'type',
			'label'   => __( 'Custom font stack', 'wavira' ),
		),
		'font_base_size'    => array(
			'default'   => 16,
			'type'      => 'int',
			'min'       => 14,
			'max'       => 22,
			'step'      => 1,
			'control'   => 'number',
			'section'   => 'type',
			'label'     => __( 'Base text size (px)', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array(
				'mode' => 'root-font',
				'unit' => 'px',
			),
		),
		'heading_weight'    => array(
			'default'   => '700',
			'type'      => 'choice',
			'control'   => 'select',
			'section'   => 'type',
			'label'     => __( 'Heading weight', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array( 'mode' => 'weight' ),
			'choices'   => array(
				'600' => __( 'Semi bold (600)', 'wavira' ),
				'700' => __( 'Bold (700)', 'wavira' ),
				'800' => __( 'Extra bold (800)', 'wavira' ),
			),
		),
		'font_preload'      => array(
			'default' => true,
			'type'    => 'bool',
			'control' => 'checkbox',
			'section' => 'type',
			'label'   => __( 'Preload the bundled font', 'wavira' ),
		),

		// ------------------------------------------------------------------- social.
		'social_instagram'  => array(
			'default' => '',
			'type'    => 'url',
			'control' => 'url',
			'section' => 'social',
			'label'   => __( 'Instagram', 'wavira' ),
		),
		'social_telegram'   => array(
			'default' => '',
			'type'    => 'url',
			'control' => 'url',
			'section' => 'social',
			'label'   => __( 'Telegram', 'wavira' ),
		),
		'social_youtube'    => array(
			'default' => '',
			'type'    => 'url',
			'control' => 'url',
			'section' => 'social',
			'label'   => __( 'YouTube', 'wavira' ),
		),
		'social_twitter'    => array(
			'default' => '',
			'type'    => 'url',
			'control' => 'url',
			'section' => 'social',
			'label'   => __( 'X (Twitter)', 'wavira' ),
		),
		'social_facebook'   => array(
			'default' => '',
			'type'    => 'url',
			'control' => 'url',
			'section' => 'social',
			'label'   => __( 'Facebook', 'wavira' ),
		),
		'social_size'       => array(
			'default'   => 22,
			'type'      => 'int',
			'min'       => 16,
			'max'       => 36,
			'step'      => 1,
			'control'   => 'number',
			'section'   => 'social',
			'label'     => __( 'Icon size (px)', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array(
				'css'  => '--wavira-social-size',
				'unit' => 'px',
			),
		),
		'social_new_tab'    => array(
			'default' => true,
			'type'    => 'bool',
			'control' => 'checkbox',
			'section' => 'social',
			'label'   => __( 'Open social links in a new tab', 'wavira' ),
		),

		// -------------------------------------------------------------------- texts.
		'footer_note'       => array(
			'default' => '',
			'type'    => 'multiline',
			'control' => 'textarea',
			'section' => 'texts',
			'label'   => __( 'Footer note', 'wavira' ),
		),
		'back_to_top'       => array(
			'default' => true,
			'type'    => 'bool',
			'control' => 'checkbox',
			'section' => 'texts',
			'label'   => __( 'Back-to-top button', 'wavira' ),
		),

		// -------------------------------------------------------------------- tools.
		'custom_css'        => array(
			'default'   => '',
			'type'      => 'css',
			'control'   => 'code',
			'section'   => 'tools',
			'label'     => __( 'Additional CSS', 'wavira' ),
			'transport' => 'postMessage',
			'live'      => array( 'mode' => 'css' ),
		),
	);

	/**
	 * Filters the theme option schema.
	 *
	 * A child theme adds a field here and it appears in the panel, is sanitized
	 * by its declared type and is available through `wavira_option()` — without
	 * touching one line of the parent theme.
	 *
	 * @since 0.12.0
	 * @param array<string, array<string, mixed>> $schema Option schema.
	 */
	return (array) apply_filters( 'wavira_options_schema', $schema );
}

/**
 * One sanitized option value.
 *
 * Unknown keys never invent a value: the caller gets its own fallback, which is
 * `null` unless it asked for something else.
 *
 * @param string $key      Option name (without the `wavira_` prefix).
 * @param mixed  $fallback Value for an unknown key.
 * @return mixed Option value.
 */
function wavira_option( $key, $fallback = null ) {
	$schema = wavira_options_schema();

	if ( ! isset( $schema[ $key ] ) ) {
		return $fallback;
	}

	$default = array_key_exists( 'default', $schema[ $key ] ) ? $schema[ $key ]['default'] : $fallback;
	$value   = get_theme_mod( 'wavira_' . $key, $default );

	/**
	 * Filters a single theme option.
	 *
	 * @since 0.12.0
	 * @param mixed  $value Option value.
	 * @param string $key   Option name.
	 */
	return apply_filters( 'wavira_option', $value, $key );
}

/**
 * The declared default of an option, or a caller-supplied fallback.
 *
 * @param string $key      Option name.
 * @param mixed  $fallback Value for an unknown key.
 * @return mixed Default value.
 */
function wavira_option_default( $key, $fallback = null ) {
	$schema = wavira_options_schema();

	return isset( $schema[ $key ]['default'] ) ? $schema[ $key ]['default'] : $fallback;
}

/**
 * Sanitize one value against one field definition.
 *
 * This is the single gate between a request and the database: every type in the
 * schema is handled here, and an unknown type falls back to the declared default
 * rather than storing what arrived.
 *
 * @param array<string, mixed> $field Field definition.
 * @param mixed                $value Raw value.
 * @return mixed Sanitized value.
 */
function wavira_sanitize_value( array $field, $value ) {
	$default = array_key_exists( 'default', $field ) ? $field['default'] : '';
	$type    = isset( $field['type'] ) ? (string) $field['type'] : '';

	switch ( $type ) {
		case 'int':
			if ( ! is_numeric( $value ) ) {
				return $default;
			}

			$number = (int) $value;

			if ( isset( $field['min'] ) ) {
				$number = max( (int) $field['min'], $number );
			}

			if ( isset( $field['max'] ) ) {
				$number = min( (int) $field['max'], $number );
			}

			return $number;

		case 'bool':
			return in_array( $value, array( true, 1, '1', 'on', 'true', 'yes' ), true );

		case 'color':
			$color = sanitize_hex_color( (string) $value );

			// An empty string means "the theme's own colour"; a typo is not a reset.
			if ( '' === trim( (string) $value ) ) {
				return '';
			}

			return $color ? $color : $default;

		case 'url':
			return esc_url_raw( trim( (string) $value ) );

		case 'text':
			return sanitize_text_field( (string) $value );

		case 'multiline':
			return sanitize_textarea_field( (string) $value );

		case 'choice':
			$choices = isset( $field['choices'] ) && is_array( $field['choices'] ) ? array_keys( $field['choices'] ) : array();

			return in_array( (string) $value, $choices, true ) ? (string) $value : $default;

		case 'attachment':
			return absint( $value );

		case 'stack':
			return wavira_sanitize_font_stack( $value );

		case 'css':
			return wavira_sanitize_custom_css( $value );

		default:
			return $default;
	}
}

/**
 * Sanitize a font stack.
 *
 * A stack ends up inside the inline `<style>` element, so it may not carry the
 * characters that end a declaration or a rule. Quotes and commas stay: they are
 * what a stack is made of.
 *
 * @param mixed $value Raw stack.
 * @return string Stack, or an empty string when nothing usable is left.
 */
function wavira_sanitize_font_stack( $value ) {
	$stack = sanitize_text_field( (string) $value );
	$stack = str_replace( array( ';', '{', '}', '<', '>', '\\', '/', '!', '@', 'expr', 'url(' ), '', $stack );
	$stack = trim( (string) preg_replace( '/\s+/', ' ', $stack ) );

	return $stack;
}

/**
 * Sanitize the free CSS field.
 *
 * The user has `edit_theme_options` — the same capability as the Customizer's own
 * Additional CSS, which is already unfiltered CSS by design. What this does is
 * keep the value from ending the element it is printed in, and from starting a
 * tag at all: the closing/opening `<style` sequences, a PHP opener and an HTML
 * comment are removed, and so is every remaining `<` — a character no stylesheet
 * needs, and whose absence is trivial to verify. It is auditable, it does not
 * pretend to be a CSS parser, and the field's description in the panel says so.
 *
 * @param mixed $value Raw CSS.
 * @return string CSS safe to print inside `<style>`.
 */
function wavira_sanitize_custom_css( $value ) {
	$css = (string) $value;
	$css = wp_check_invalid_utf8( $css );
	$css = str_ireplace( array( '</style', '<style', '<?', '<!--', '-->' ), '', $css );
	$css = str_replace( '<', '', $css );

	return trim( $css );
}

/**
 * The Customizer sanitize callback: one function for every setting.
 *
 * Core passes the setting object as the second argument, so the key — and with
 * it the field definition — is always available; a call without a setting (a
 * test, a filter) keeps the value as it arrived.
 *
 * @param mixed                $value   Raw value.
 * @param WP_Customize_Setting $setting Setting being saved.
 * @return mixed Sanitized value.
 */
function wavira_sanitize_option( $value, $setting = null ) {
	if ( ! $setting instanceof WP_Customize_Setting ) {
		return $value;
	}

	$key    = 'wavira_' === substr( (string) $setting->id, 0, 7 ) ? substr( (string) $setting->id, 7 ) : (string) $setting->id;
	$schema = wavira_options_schema();

	if ( ! isset( $schema[ $key ] ) ) {
		return $value;
	}

	return wavira_sanitize_value( $schema[ $key ], $value );
}

/**
 * The font stacks the theme ships, keyed by option value.
 *
 * @return array<string, string> CSS font stacks.
 */
function wavira_font_stacks(): array {
	$stacks = array(
		'vazirmatn' => '"Vazirmatn", "Segoe UI", system-ui, -apple-system, sans-serif',
		'system'    => 'system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif',
	);

	$custom = (string) wavira_option( 'font_stack' );

	if ( '' !== $custom ) {
		$stacks['custom'] = $custom;
	}

	/**
	 * Filters the font stacks offered to the theme.
	 *
	 * @since 0.12.0
	 * @param array<string, string> $stacks Stacks keyed by option value.
	 */
	return (array) apply_filters( 'wavira_font_stacks', $stacks );
}

/**
 * The font stack currently in effect.
 *
 * @return string CSS font stack.
 */
function wavira_font_stack() {
	$stacks = wavira_font_stacks();
	$family = (string) wavira_option( 'font_family' );

	if ( isset( $stacks[ $family ] ) ) {
		return $stacks[ $family ];
	}

	// `custom` was chosen without a stack: the bundled font is the honest answer.
	return (string) $stacks['vazirmatn'];
}

/**
 * Whether the bundled Vazirmatn file should be preloaded.
 *
 * A preload for a font nothing uses is a wasted request, so the answer is no
 * unless the bundled stack is the one in effect and the switch is on.
 *
 * @return bool
 */
function wavira_preloads_font(): bool {
	return 'vazirmatn' === (string) wavira_option( 'font_family' ) && (bool) wavira_option( 'font_preload' );
}

/**
 * The social networks the theme renders, in display order.
 *
 * @return array<string, array<string, string>> Keyed by option suffix.
 */
function wavira_social_networks(): array {
	$networks = array(
		'instagram' => array(
			'label' => __( 'Instagram', 'wavira' ),
			'icon'  => 'instagram',
		),
		'telegram'  => array(
			'label' => __( 'Telegram', 'wavira' ),
			'icon'  => 'telegram',
		),
		'youtube'   => array(
			'label' => __( 'YouTube', 'wavira' ),
			'icon'  => 'youtube',
		),
		'twitter'   => array(
			'label' => __( 'X (Twitter)', 'wavira' ),
			'icon'  => 'x',
		),
		'facebook'  => array(
			'label' => __( 'Facebook', 'wavira' ),
			'icon'  => 'facebook',
		),
	);

	/**
	 * Filters the social networks the theme renders.
	 *
	 * @since 0.12.0
	 * @param array<string, array<string, string>> $networks Networks keyed by option suffix.
	 */
	return (array) apply_filters( 'wavira_social_networks', $networks );
}

/**
 * The social links that have a URL, in display order.
 *
 * An unset field is simply absent — the row never renders an empty anchor, and a
 * site with no social links gets no markup at all.
 *
 * @return array<int, array<string, string>> Rows of `label`, `url`, `icon`.
 */
function wavira_social_links(): array {
	$links = array();

	foreach ( wavira_social_networks() as $key => $network ) {
		$url = (string) wavira_option( 'social_' . $key );

		if ( '' === $url ) {
			continue;
		}

		$links[] = array(
			'label' => (string) $network['label'],
			'icon'  => (string) $network['icon'],
			'url'   => $url,
		);
	}

	return $links;
}

/**
 * The stylesheet text a setting change produces, or an empty string.
 *
 * Only values that differ from the declared default produce a declaration: a
 * site that changed nothing prints nothing, and the built stylesheet stays the
 * single source of the design.
 *
 * @return string CSS, without a `<style>` wrapper.
 */
function wavira_option_css(): string {
	$css = array();

	// The variable declarations come from the schema's own `live` entries — the
	// same list the Customizer preview reads (ADR 0020). A variable name written
	// in two places is a variable that will eventually disagree with itself.
	foreach ( wavira_options_schema() as $key => $field ) {
		if ( ! isset( $field['live']['css'] ) ) {
			continue;
		}

		$property = (string) $field['live']['css'];
		$value    = wavira_option( $key );

		if ( 'bool' === $field['type'] ) {
			if ( ! $value ) {
				$css[] = $property . ':' . ( isset( $field['live']['off'] ) ? (string) $field['live']['off'] : 'none' );
			}

			continue;
		}

		$value   = (string) $value;
		$default = (string) wavira_option_default( $key );

		// A default value needs no declaration: the built stylesheet already
		// carries the design. This is what makes a default site free.
		if ( '' === $value || $default === $value ) {
			continue;
		}

		$css[] = $property . ':' . $value . ( isset( $field['live']['unit'] ) ? (string) $field['live']['unit'] : '' );
	}

	if ( 'vazirmatn' !== (string) wavira_option( 'font_family' ) ) {
		$css[] = '--wp--preset--font-family--body:' . wavira_font_stack();
		$css[] = '--wp--preset--font-family--display:' . wavira_font_stack();
	}

	$rules = array();

	if ( array() !== $css ) {
		$rules[] = ':root{' . implode( ';', $css ) . '}';
	}

	if ( (int) wavira_option( 'font_base_size' ) !== (int) wavira_option_default( 'font_base_size' ) ) {
		$rules[] = 'html{font-size:' . (int) wavira_option( 'font_base_size' ) . 'px}';
	}

	$weight = (string) wavira_option( 'heading_weight' );

	if ( (string) wavira_option_default( 'heading_weight' ) !== $weight ) {
		$selectors = array();

		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', '.wp-block-heading' ) as $selector ) {
			$selectors[] = 'body.wavira-heading-' . $weight . ' ' . $selector;
		}

		$rules[] = implode( ',', $selectors ) . '{font-weight:' . $weight . '}';
	}

	$css   = implode( "\n", $rules );
	$extra = wavira_sanitize_custom_css( (string) wavira_option( 'custom_css' ) );

	if ( '' !== $extra ) {
		$css .= ( '' === $css ? '' : "\n" ) . $extra;
	}

	return $css;
}

/**
 * Print the option stylesheet in the head.
 *
 * Deliberately one path instead of `wp_add_inline_style()`: the settings must
 * also apply when the built stylesheet is missing (an un-built checkout, a
 * static export), and a second code path is a second thing to test. The tag is
 * printed after the theme's own stylesheet, so equal-specificity rules here win
 * by order — no `!important` anywhere (ADR 0009).
 *
 * @return void
 */
function wavira_print_option_css(): void {
	$css = wavira_option_css();

	if ( '' === $css ) {
		return;
	}

	printf(
		"<style id=\"wavira-options-css\">\n%s\n</style>\n",
		$css // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from sanitized option values (see wavira_sanitize_value()).
	);
}
add_action( 'wp_head', 'wavira_print_option_css', 20 );

/**
 * Add the body classes the settings need.
 *
 * A class is the honest way to switch something the block editor also styles:
 * `body.wavira-heading-800 h2` beats a core `h2` rule on specificity, in both
 * directions, with no `!important` and no dependence on stylesheet order.
 *
 * @param string[] $classes Body classes.
 * @return string[] Body classes.
 */
function wavira_option_body_classes( $classes ) {
	if ( ! wavira_option( 'sticky_header' ) ) {
		$classes[] = 'wavira-not-sticky';
	}

	if ( ! wavira_option( 'show_site_title' ) ) {
		$classes[] = 'wavira-no-title';
	}

	if ( ! wavira_option( 'show_tagline' ) ) {
		$classes[] = 'wavira-no-tagline';
	}

	if ( ! wavira_option( 'glass' ) ) {
		$classes[] = 'wavira-no-glass';
	}

	if ( ! wavira_option( 'show_theme_toggle' ) ) {
		$classes[] = 'wavira-no-toggle';
	}

	$weight = (string) wavira_option( 'heading_weight' );

	if ( (string) wavira_option_default( 'heading_weight' ) !== $weight ) {
		$classes[] = 'wavira-heading-' . $weight;
	}

	return $classes;
}
add_filter( 'body_class', 'wavira_option_body_classes' );

/**
 * Render the dark-mode logo beside the light one.
 *
 * Core's `wp:site-logo` block renders `custom_logo`; the documented filter for
 * that markup is used rather than re-rendering the block, so everything core
 * does — alt text, srcset, the link to home — stays core's job. The two images
 * are swapped in CSS, which is also why `auto` mode works: the same
 * `prefers-color-scheme` query that remaps the palette shows the second logo.
 *
 * @param string $html Core's logo markup.
 * @return string Logo markup, with the dark variant appended when one is set.
 */
function wavira_custom_logo( $html ) {
	$dark_id = (int) wavira_option( 'dark_logo' );

	if ( '' === trim( (string) $html ) || $dark_id <= 0 ) {
		return $html;
	}

	$alt = (string) get_post_meta( $dark_id, '_wp_attachment_image_alt', true );

	if ( '' === $alt ) {
		$alt = (string) get_bloginfo( 'name' );
	}

	$dark = wp_get_attachment_image(
		$dark_id,
		'full',
		false,
		array(
			'class' => 'custom-logo wavira-logo--dark',
			'alt'   => $alt,
		)
	);

	// `wp_get_attachment_image()` returns false when the file has no image data;
	// an empty string would then be concatenated as if it were markup.
	if ( ! $dark ) {
		return $html;
	}

	return '<span class="wavira-logo">'
		. str_replace( 'class="custom-logo"', 'class="custom-logo wavira-logo--light"', $html )
		. $dark
		. '</span>';
}
add_filter( 'get_custom_logo', 'wavira_custom_logo' );

/**
 * Whether the front page has its banner heading.
 *
 * The visible banner carries the site title as level one. The shared header
 * keeps its title at level zero on every template.
 *
 * @return bool True on the front page.
 */
function wavira_front_page_title_is_the_heading() {
	return is_front_page();
}

/**
 * Drop an old hidden fallback heading when the banner provides one.
 *
 * `front-page.html` used to carry a visually hidden site title as a fallback. The
 * visible banner now supplies the page's `<h1>` regardless of the header's
 * display option, so retaining the old block would announce the site name
 * twice. Removing it beats hiding it with CSS — a hidden heading is still a
 * heading to everything that reads the document.
 *
 * @since 0.15.0
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string The block, or an empty string when the banner covers it.
 */
function wavira_drop_duplicate_front_page_title( $content, $block ) {
	if ( 'core/site-title' !== ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) ) {
		return $content;
	}

	$classes = isset( $block['attrs']['className'] ) ? explode( ' ', (string) $block['attrs']['className'] ) : array();

	if ( ! in_array( 'wavira-visually-hidden', $classes, true ) ) {
		return $content;
	}

	return wavira_front_page_title_is_the_heading() ? '' : $content;
}
add_filter( 'render_block', 'wavira_drop_duplicate_front_page_title', 10, 2 );

/**
 * Name the two navigations, so each landmark is distinguishable.
 *
 * A page carries two `core/navigation` blocks — the header's and the footer's —
 * and core names a navigation only when it has been given a menu (`ref`) or a
 * label. Two unnamed `<nav>` landmarks are one axe `landmark-unique` finding and
 * an unusable landmark list for anyone tabbing through it. The class is how the
 * theme tells them apart: core's navigation block has no attribute that says
 * which one it is, and the two live in different template parts, which is not
 * something a block filter can see.
 *
 * @since 0.15.0
 * @param array $parsed_block Block being rendered.
 * @return array The block, with a Persian label when the theme recognises it.
 */
function wavira_label_navigation_landmarks( $parsed_block ) {
	if ( 'core/navigation' !== ( isset( $parsed_block['blockName'] ) ? $parsed_block['blockName'] : '' ) ) {
		return $parsed_block;
	}

	$class = isset( $parsed_block['attrs']['className'] ) ? (string) $parsed_block['attrs']['className'] : '';
	$label = '';

	if ( false !== strpos( $class, 'wavira-nav--primary' ) ) {
		$label = __( 'Primary menu', 'wavira' );
	} elseif ( false !== strpos( $class, 'wavira-nav--footer' ) ) {
		$label = __( 'Footer menu', 'wavira' );
	}

	// A label the site owner chose wins; this is only for the unnamed case.
	if ( $label && empty( $parsed_block['attrs']['ariaLabel'] ) ) {
		$parsed_block['attrs']['ariaLabel'] = $label;
	}

	return $parsed_block;
}
add_filter( 'render_block_data', 'wavira_label_navigation_landmarks' );

/**
 * Leave the player bar out of the page when the site owner turned it off.
 *
 * Filtering the rendered template part removes the markup instead of hiding it:
 * an `<audio>` element that cannot be played is a request and a tab stop nobody
 * asked for. The block is identified by the same slug the templates use.
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string Rendered block, or an empty string.
 */
function wavira_filter_player_bar( $content, $block ) {
	if ( ! wavira_option( 'player_bar' ) && isset( $block['blockName'], $block['attrs']['slug'] ) && 'core/template-part' === $block['blockName'] && 'player-bar' === $block['attrs']['slug'] ) {
		return '';
	}

	return $content;
}
add_filter( 'render_block', 'wavira_filter_player_bar', 10, 2 );
