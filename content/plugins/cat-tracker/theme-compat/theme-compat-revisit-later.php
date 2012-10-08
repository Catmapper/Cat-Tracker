	/**
	 * Possibly intercept the template being loaded
	 * props bbPress for this function
	 *
	 * @since 1.0
	 * @param string $template
	 * @return string the path to the template file that is being used
	*/
	public function template_include( $template ) {

		if ( is_singular( 'map' ) )
			return $this->locate_template( array( 'single-map.php', 'single.php', 'index.php' ) );

		

		return $template;

	}

	/**
	 * Retrieve the name of the highest priority template file that exists.
	 * props bbPress for this function
	 *
	 * @since 1.0
	 * @param string|array $template_names iemplate file(s) to search for, in order.
	 * @param bool $load if true the template file will be loaded if it is found.
	 * @param bool $require_once whether to require_once or require. Default true. Has no effect if $load is false.
	 * @return string the template filename if one is located.
	*/
	public function locate_template( $template_names, $load = false, $require_once = true ) {

		// No file found yet
		$located = false;

		// Try to find a template file
		foreach ( (array) $template_names as $template_name ) {

			// Continue if template is empty
			if ( empty( $template_name ) )
				continue;

			// Trim off any slashes from the template name
			$template_name = ltrim( $template_name, '/' );

			// Check child theme first
			if ( file_exists( trailingslashit( STYLESHEETPATH ) . $template_name ) ) {
				$located = trailingslashit( STYLESHEETPATH ) . $template_name;
				break;

			// Check parent theme next
			} elseif ( file_exists( trailingslashit( TEMPLATEPATH ) . $template_name ) ) {
				$located = trailingslashit( TEMPLATEPATH ) . $template_name;
				break;

			// Check theme compatibility last
			} elseif ( file_exists( trailingslashit( $this->theme_path ) . $template_name ) ) {
				$located = trailingslashit( $this->theme_path ) . $template_name;
				break;
			}
		}

		if ( ( true == $load ) && !empty( $located ) )
			load_template( $located, $require_once );

		return $located;
	}
