<?php
/**
 * The iDoneThis to Dones Importer class.
 *
 * @package dones-idonethis-importer
 */

/**
 * The iDoneThis to Dones Importer.
 */
class Dones_IDoneThis_Import extends WP_Importer {

	/**
	 * CSV attachment ID.
	 *
	 * @var number
	 */
	var $id;

	/**
	 * Authors parsed from CSV import.
	 *
	 * @var array
	 */
	var $authors = array();

	/**
	 * Tasks from CSV import.
	 *
	 * @var array
	 */
	var $tasks = array();

	/**
	 * User-selected author mappings for tasks.
	 *
	 * @var array
	 */
	var $author_mapping = array();

	/**
	 * Registered callback function for the WordPress Importer.
	 *
	 * Manages the three separate stages of the CSV import process.
	 */
	function dispatch() {
		global $wpdb, $user_ID;
		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];

		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_die( __( 'Cheatin&#8217; uh?', 'dones-idonethis-importer' ) );
		}

		$this->header();

		switch ( $step ) {
			case 0:
				$this->greet();
				break;

			case 1:
				check_admin_referer( 'import-upload' );
				if ( $this->handle_upload() ) {
					$this->import_options();
				}
				break;

			case 2:
				check_admin_referer( 'import-dones-idonethis' );
				$this->id = (int) $_POST['import_id'];
				$file     = get_attached_file( $this->id );
				set_time_limit( 0 );
				$this->import( $file );
				break;
		}

		$this->footer();
	}

	/**
	 * Display import page title.
	 */
	function header() {
		echo '<div class="wrap">';
		echo '<h2>' . __( 'Import your tasks from iDoneThis to Dones', 'dones-idonethis-importer' ) . '</h2>';
	}

	/**
	 * Display import page footing.
	 */
	function footer() {
		echo '</div>';
	}

	/**
	 * Display introductory text and file upload form.
	 */
	function greet() {
		echo '<p>' . __( 'Export your tasks from iDoneThis and you can import the generated CSV here.', 'dones-idonethis-importer' ) . '</p>';

		wp_import_upload_form( 'admin.php?import=dones-idonethis&amp;step=1' );
	}

	/**
	 * Handles the CSV upload and initial parsing of the file to prepare for
	 * displaying author import options.
	 *
	 * @return bool False if error uploading or invalid file, true otherwise.
	 */
	function handle_upload() {
		$file = wp_import_handle_upload();

		if ( isset( $file['error'] ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'dones-idonethis-importer' ) . '</strong><br />';
			echo esc_html( $file['error'] ) . '</p>';
			return false;
		} elseif ( ! file_exists( $file['file'] ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'dones-idonethis-importer' ) . '</strong><br />';
			/* translators: Import file location */
			printf( __( 'The export file could not be found at <code>%s</code>. It is likely that this was caused by a permissions problem.', 'dones-idonethis-importer' ), esc_html( $file['file'] ) );
			echo '</p>';
			return false;
		}

		$this->id    = (int) $file['id'];
		$import_data = $this->parse( $file['file'] );
		if ( is_wp_error( $import_data ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'dones-idonethis-importer' ) . '</strong><br />';
			echo esc_html( $import_data->get_error_message() ) . '</p>';
			return false;
		}

		$this->get_authors_from_import( $import_data );

		return true;
	}

	/**
	 * Parse a CSV file.
	 *
	 * @param  string $file Path to CSV file for parsing.
	 * @return array        Information gathered from the CSV file.
	 */
	function parse( $file ) {
		$rows   = array_map( 'str_getcsv', file( $file ) );
		$header = array_shift( $rows );
		$parsed = array();
		foreach ( $rows as $row ) {
			$parsed[] = array_combine(
				$header,
				array_pad( $row, count( $header ), '' )
			);
		}

		return $parsed;
	}

	/**
	 * Retrieve authors from parsed CSV data.
	 *
	 * @param array $import_data Data returned by a CSV parser.
	 */
	function get_authors_from_import( $import_data ) {
		foreach ( $import_data as $task ) {
			$email = $task['user_email_address'];
			if ( ! in_array( $email, $this->authors ) ) {
				$this->authors[] = $email;
			}
		}
	}

	/**
	 * Display pre-import options, author importing/mapping and option to fetch
	 * attachments.
	 */
	function import_options() {
		$j = 0;
		?>
		<form
			action="<?php echo admin_url( 'admin.php?import=dones-idonethis&amp;step=2' ); ?>"
			method="post">
			<?php wp_nonce_field( 'import-dones-idonethis' ); ?>
			<input type="hidden" name="import_id" value="<?php echo $this->id; ?>" />

			<?php if ( ! empty( $this->authors ) ) : ?>
				<h3><?php _e( 'Assign Authors', 'dones-idonethis-importer' ); ?></h3>
				<p><?php _e( 'Assign the author of each imported item to an existing user of this site, or create a new user.', 'dones-idonethis-importer' ); ?></p>
				<p>
					<?php
					printf(
						/* translators: default role for imported new user */
						__( 'If a user is created, a new password will be randomly generated and the new user&#8217;s role will be set as %s.', 'dones-idonethis-importer' ),
						esc_html( get_option( 'default_role' ) )
					);
					?>
				</p>
				<ol id="authors">
			<?php foreach ( $this->authors as $author ) : ?>
					<li><?php $this->author_select( $j++, $author ); ?></li>
			<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<p class="submit">
				<input
					type="submit"
					class="button"
					value="<?php esc_attr_e( 'Submit', 'dones-idonethis-importer' ); ?>" />
			</p>
		</form>
		<?php
	}

	/**
	 * Display import options for an individual author. That is, either create
	 * a new user based on import info or map to an existing user.
	 *
	 * @param int   $n      Index for each author in the form.
	 * @param array $author Author information, e.g. login, display name, email.
	 */
	function author_select( $n, $author ) {
		_e( 'Import email:', 'dones-idonethis-importer' );

		echo ' <strong>' . esc_html( $author ) . '</strong><br />';
		echo '<div style="margin-left:18px">';

		_e( 'assign posts to an existing user:', 'dones-idonethis-importer' );

		$user = get_user_by( 'email', $author );

		$dropdown_args = array(
			'name'            => 'user_map[' . $n . ']',
			'multi'           => true,
			'show_option_all' => __( '- Select -', 'dones-idonethis-importer' ),
		);

		if ( false !== $user ) {
			$dropdown_args['selected'] = $user->ID;
		}

		wp_dropdown_users( $dropdown_args );

		echo '<br>';

		_e( 'or create new user with login name:', 'dones-idonethis-importer' );

		echo ' <input type="text" name="user_new[' . $n . ']" /><br />';
		echo '<input type="hidden" name="imported_authors[' . $n . ']" value="' . esc_attr( $author ) . '" />';
		echo '</div>';
	}

	/**
	 * The main controller for the actual import stage.
	 *
	 * @param string $file Path to the CSV file for importing.
	 */
	function import( $file ) {
		add_filter( 'http_request_timeout', array( &$this, 'bump_request_timeout' ) );

		$this->import_start( $file );

		$mapping_error = $this->get_author_mapping();
		if ( is_wp_error( $mapping_error ) ) {
			echo '<p>' . $mapping_error->get_error_message() . '</p>';
			$this->footer();
			exit;
		}

		wp_suspend_cache_invalidation( true );
		$this->process_tasks();
		wp_suspend_cache_invalidation( false );

		$this->import_end();
	}

	/**
	 * Parses the CSV file and prepares us for the task of processing parsed
	 * data.
	 *
	 * @param string $file Path to the CSV file for importing.
	 */
	function import_start( $file ) {
		if ( ! is_file( $file ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'dones-idonethis-importer' ) . '</strong><br />';
			echo __( 'The file does not exist, please try again.', 'dones-idonethis-importer' ) . '</p>';
			$this->footer();
			die();
		}

		$import_data = $this->parse( $file );

		if ( is_wp_error( $import_data ) ) {
			echo '<p><strong>' . __( 'Sorry, there has been an error.', 'dones-idonethis-importer' ) . '</strong><br />';
			echo esc_html( $import_data->get_error_message() ) . '</p>';
			$this->footer();
			die();
		}

		$this->get_authors_from_import( $import_data );
		$this->tasks = $import_data;

		wp_defer_term_counting( true );

		do_action( 'import_start' );
	}

	/**
	 * Performs post-import cleanup of files and the cache.
	 */
	function import_end() {
		wp_import_cleanup( $this->id );

		wp_cache_flush();
		wp_defer_term_counting( false );

		echo '<p>' . __( 'All done. Have fun!', 'dones-idonethis-importer' ) . '</p>';

		do_action( 'import_end' );
	}

	/**
	 * Map old author logins to local user IDs based on decisions made in
	 * import options form. Can map to an existing user or create a new user.
	 */
	function get_author_mapping() {
		if ( ! isset( $_POST['imported_authors'] ) ) {
			return new WP_error( 'import_invalid', 'Invalid imported authors from options step' );
		}

		// Validate non-empty
		foreach ( (array) $_POST['imported_authors'] as $i => $email ) {
			if ( empty( $_POST['user_map'][ $i ] ) && empty( $_POST['user_new'][ $i ] ) ) {
				return new WP_Error(
					'import_missing_mapping',
					wp_kses( sprintf(
						/* translators: Link to previous step for corrections */
						__( 'You did not make author selections for all email addresses. <a href="%s">Return to previous step</a>.', 'dones-idonethis-importer' ),
						"javascript:history.go(-1);"
					), array( 'a' => array( 'href' => array() ) ), array( 'javascript' ) )
				);
			}
		}

		foreach ( (array) $_POST['imported_authors'] as $i => $email ) {
			if ( ! empty( $_POST['user_map'][ $i ] ) ) {
				$user = get_userdata( intval( $_POST['user_map'][ $i ] ) );
				if ( isset( $user->ID ) ) {
					$user_id = $user->ID;
				}
			} elseif ( ! empty( $_POST['user_new'][ $i ] ) ) {
				$user_id = wp_create_user(
					$_POST['user_new'][ $i ],
					wp_generate_password(),
					$old_email
				);
			}

			if ( empty( $user_id ) || is_wp_error( $user_id ) ) {
				return new WP_Error( 'import_failed_mapping', sprintf(
					/* translators: Failed imported user email address */
					__( 'Failed to create new user for %s.', 'dones-idonethis-importer' ),
					esc_html( $email )
				) );
			}

			$this->author_mapping[ $email ] = $user_id;
		}
	}

	/**
	 * Added to http_request_timeout filter to force timeout at 60 seconds
	 * during import.
	 *
	 * @param  int $val Request timeout.
	 * @return int      Modified request timeout.
	 */
	function bump_request_timeout( $val ) {
		return 60;
	}

	/**
	 * Create new posts based on import information.
	 */
	function process_tasks() {
		foreach ( $this->tasks as $task ) {
			$user_id = $this->author_mapping[ $task['user_email_address'] ];

			$post = array(
				'post_type'   => 'done',
				'post_author' => $user_id,
				'post_title'  => $task['body'],
				'post_status' => 'done' === $task['status'] ? 'publish' : 'draft',
				'post_date'   => $task['occurred_on'] . ' 00:00:00',
			);

			wp_insert_post( $post );
		}

		unset( $this->tasks );
	}

}
