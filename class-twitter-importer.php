<?php
/**
 * Twitter and X Importer.
 *
 * @package WordPress
 * @subpackage Importer
 */

/**
 * Twitter and X Importer.
 *
 * Will import tweets from a Twitter or X export file.
 */
class Twitter_Importer extends WP_Importer {

	public $posts = array();
	public $file;
	public $id;
	public $twitter_names = array();
	public $new_author_names = array();
	public $j = - 1;

	function header() {
		echo '<div class="wrap">';
		echo '<h2>' . esc_html__( 'Import Twitter or X', 'twitter-importer' ) . '</h2>';
	}

	function footer() {
		echo '</div>';
	}

	function greet() {
		$this->header();
		?>
		<div class="narrow">
			<p><?php _e( 'Howdy! We are about to begin importing all of your Movable Type or TypePad entries into WordPress. To begin, either choose a file to upload and click &#8220;Upload file and import&#8221;, or use FTP to upload your twitter export file as <code>twitter-export.txt</code> in your <code>/wp-content/</code> directory and then click &#8220;Import twitter-export.txt&#8221;.', 'twitter-importer' ); ?></p>

			<?php wp_import_upload_form( add_query_arg( 'step', 1 ) ); ?>
			<form method="post" action="<?php echo esc_attr( add_query_arg( 'step', 1 ) ); ?>" class="import-upload-form">
				<?php wp_nonce_field( 'import-upload' ); ?>
				<p>
					<input type="hidden" name="upload_type" value="ftp"/>
					<?php _e( 'Or use <code>twitter-export.zip</code> in your <code>/wp-content/</code> directory', 'twitter-importer' ); ?>
				</p>

				<?php submit_button( __( 'Import twitter-export.zip', 'twitter-importer' ) ); ?>
			</form>
			<p><?php _e( 'The importer is smart enough not to import duplicates, so you can run this multiple times without worry if&#8212;for whatever reason&#8212;it doesn&#8217;t finish. If you get an <strong>out of memory</strong> error try splitting up the import file into pieces.', 'twitter-importer' ); ?></p>
		</div>
		<?php
		$this->footer();
	}

	function users_form( $n ) {
		?><select name="userselect[<?php echo esc_attr( $n ); ?>]">
		<option value="#NONE#"><?php esc_html_e( '&mdash; Select &mdash;', 'twitter-importer' ) ?></option>
		<?php
		foreach ( get_users() as $user ) {
			echo '<option value="' . esc_attr( $user->user_login ) . '">' . esc_html( $user->user_login ) . '</option>';
		}
		?>
		</select>
		<?php
	}

	function fopen( $filename, $mode = 'r' ) {
		if ( is_callable( 'gzopen' ) ) {
			return gzopen( $filename, $mode );
		}

		return fopen( $filename, $mode );
	}

	function feof( $fp ) {
		if ( is_callable( 'gzopen' ) ) {
			return gzeof( $fp );
		}

		return feof( $fp );
	}

	function fgets( $fp, $len = 8192 ) {
		if ( is_callable( 'gzopen' ) ) {
			return gzgets( $fp, $len );
		}

		return fgets( $fp, $len );
	}

	function fclose( $fp ) {
		if ( is_callable( 'gzopen' ) ) {
			return gzclose( $fp );
		}

		return fclose( $fp );
	}

	//function to check the authorname and do the mapping
	function check_author( $author ) {
		//twitter_names is an array with the names in the twitter import file
		$pass = wp_generate_password();
		if ( ! ( in_array( $author, $this->twitter_names ) ) ) { //a new twitter author name is found
			++ $this->j;
			$this->twitter_names[ $this->j ] = $author; //add that new twitter author name to an array
			$user_id                   = username_exists( $this->new_author_names[ $this->j ] ); //check if the new author name defined by the user is a pre-existing wp user
			if ( ! $user_id ) { //banging my head against the desk now.
				if ( $this->new_author_names[ $this->j ] == 'left_blank' ) { //check if the user does not want to change the authorname
					$user_id                          = wp_create_user( $author, $pass );
					$this->new_author_names[ $this->j ] = $author; //now we have a name, in the place of left_blank.
				} else {
					$user_id = wp_create_user( $this->new_author_names[ $this->j ], $pass );
				}
			} else {
				return $user_id; // return pre-existing wp username if it exists
			}
		} else {
			$key     = array_search( $author, $this->twitter_names ); //find the array key for $author in the $twitter_names array
			$user_id = username_exists( $this->new_author_names[ $key ] ); //use that key to get the value of the author's name from $new_author_names
		}

		return $user_id;
	}

	function get_twitter_authors() {
		$temp    = array();
		$authors = array();

		$handle = $this->fopen( $this->file );
		if ( $handle == null ) {
			return false;
		}

		$in_comment = false;
		while ( $line = $this->fgets( $handle ) ) {
			$line = trim( $line );

			if ( 'COMMENT:' == $line ) {
				$in_comment = true;
			} else if ( '-----' == $line ) {
				$in_comment = false;
			}

			if ( $in_comment || 0 !== strpos( $line, "AUTHOR:" ) ) {
				continue;
			}

			$temp[] = trim( substr( $line, strlen( "AUTHOR:" ) ) );
		}

		//we need to find unique values of author names, while preserving the order, so this function emulates the unique_value(); php function, without the sorting.
		$authors[0] = array_shift( $temp );
		$y          = count( $temp ) + 1;
		for ( $x = 1; $x < $y; $x ++ ) {
			$next = array_shift( $temp );
			if ( ! ( in_array( $next, $authors ) ) ) {
				$authors[] = $next;
			}
		}

		$this->fclose( $handle );

		return $authors;
	}

	function get_authors_from_post() {
		$form_names   = array();
		$select_names = array();

		foreach ( $_POST['user'] as $line ) {
			$new_name = trim( stripslashes( $line ) );
			if ( $new_name == '' ) {
				$new_name = 'left_blank';
			} //passing author names from step 1 to step 2 is accomplished by using POST. left_blank denotes an empty entry in the form.
			$form_names[] = $new_name;
		} // $form_names is the array with the form entered names

		foreach ( $_POST['userselect'] as $key ) {
			$select_names[] = trim( stripslashes( $key ) );
		}

		$count = count( $form_names );
		for ( $i = 0; $i < $count; $i ++ ) {
			if ( $select_names[ $i ] != '#NONE#' ) { //if no name was selected from the select menu, use the name entered in the form
				$this->new_author_names[] = "$select_names[$i]";
			} else {
				$this->new_author_names[] = "$form_names[$i]";
			}
		}
	}

	function twitter_authors_form() {
		?>
		<div class="wrap">
		<h2><?php esc_html_e( 'Assign Authors', 'twitter-importer' ); ?></h2>
		<p><?php esc_html_e( 'To make it easier for you to edit and save the imported posts and drafts, you may want to change the name of the author of the posts. For example, you may want to import all the entries as admin&#8217;s entries.', 'twitter-importer' ); ?></p>
		<p><?php echo wp_kses_post( __( 'Below, you can see the names of the authors of the Movable Type posts in <em>italics</em>. For each of these names, you can either pick an author in your WordPress installation from the menu, or enter a name for the author in the textbox.', 'twitter-importer' ) ); ?></p>
		<p><?php esc_html_e( 'If a new user is created by WordPress, a password will be randomly generated. Manually change the user&#8217;s details if necessary.', 'twitter-importer' ); ?></p>
		<?php


		$authors = $this->get_twitter_authors();
		echo '<ol id="authors">';
		echo '<form action="?import=twitter&amp;step=2&amp;id=' . $this->id . '" method="post">';
		wp_nonce_field( 'import-twitter' );
		$j = - 1;
		foreach ( $authors as $author ) {
			++ $j;
			echo '<li><label>' . __( 'Current author:', 'twitter-importer' ) . ' <strong>' . $author . '</strong><br />' . sprintf( __( 'Create user %1$s or map to existing', 'twitter-importer' ), ' <input type="text" value="' . esc_attr( $author ) . '" name="' . 'user[]' . '" maxlength="30"> <br />' );
			$this->users_form( $j );
			echo '</label></li>';
		}

		echo '<p class="submit"><input type="submit" class="button" value="' . esc_attr__( 'Submit', 'twitter-importer' ) . '"></p>' . '<br />';
		echo '</form>';
		echo '</ol></div>';

	}

	function select_authors() {
		if ( isset( $_POST['upload_type'] ) && $_POST['upload_type'] === 'ftp' ) {
			$file['file'] = WP_CONTENT_DIR . '/twitter-export.txt';
			if ( ! file_exists( $file['file'] ) ) {
				$file['error'] = __( '<code>twitter-export.txt</code> does not exist', 'twitter-importer' );
			}
		} else {
			$file = wp_import_handle_upload();
		}
		if ( isset( $file['error'] ) ) {
			$this->header();
			echo '<p>' . esc_html__( 'Sorry, there has been an error', 'twitter-importer' ) . '.</p>';
			echo '<p><strong>' . esc_html( $file['error'] ) . '</strong></p>';
			$this->footer();

			return;
		}
		$this->file = $file['file'];
		$this->id   = (int) $file['id'];

		$this->twitter_authors_form();
	}

	function save_post( &$post, $comments, $pings ) {
		$post = get_object_vars( $post );
		$post = add_magic_quotes( $post );
		$post = (object) $post;

		echo '<li>';
		if ( $post_id = post_exists( $post->post_title, '', $post->post_date ) ) {
			printf( __( 'Post <em>%s</em> already exists.', 'twitter-importer' ), stripslashes( $post->post_title ) );
		} else {
			printf( __( 'Importing post <em>%s</em>&hellip;', 'twitter-importer' ), stripslashes( $post->post_title ) );

			if ( '' != trim( $post->extended ) ) {
				$post->post_content .= "\n<!--more-->\n$post->extended";
			}

			$post->post_author = $this->check_author( $post->post_author ); //just so that if a post already exists, new users are not created by check_author
			$post_id           = wp_insert_post( (array) $post );
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			// Add categories.
			if ( 0 != count( $post->categories ) ) {
				wp_create_categories( $post->categories, $post_id );
			}

			// Add tags or keywords
			if ( 1 < strlen( $post->post_keywords ) ) {
				// Keywords exist.
				printf( '<br />' . __( 'Adding tags <em>%s</em>...', 'twitter-importer' ), stripslashes( $post->post_keywords ) );
				wp_add_post_tags( $post_id, $post->post_keywords );
			}
		}

		$num_comments = 0;
		foreach ( $comments as $comment ) {
			$comment = get_object_vars( $comment );
			$comment = add_magic_quotes( $comment );

			if ( WP_MT_IMPORT_ALLOW_DUPE_COMMENTS || ! comment_exists( $comment['comment_author'], $comment['comment_date'] ) ) {
				$comment['comment_post_ID'] = $post_id;
				$comment                    = wp_filter_comment( $comment );
				wp_insert_comment( $comment );
				$num_comments ++;
			}
		}

		if ( $num_comments ) {
			printf( ' ' . _n( '(%s comment)', '(%s comments)', $num_comments, 'twitter-importer' ), $num_comments );
		}

		$num_pings = 0;
		foreach ( $pings as $ping ) {
			$ping = get_object_vars( $ping );
			$ping = add_magic_quotes( $ping );

			if ( WP_MT_IMPORT_ALLOW_DUPE_COMMENTS || ! comment_exists( $ping['comment_author'], $ping['comment_date'] ) ) {
				$ping['comment_content'] = "<strong>{$ping['title']}</strong>\n\n{$ping['comment_content']}";
				$ping['comment_post_ID'] = $post_id;
				$ping                    = wp_filter_comment( $ping );
				wp_insert_comment( $ping );
				$num_pings ++;
			}
		}

		if ( $num_pings ) {
			printf( ' ' . _n( '(%s ping)', '(%s pings)', $num_pings, 'twitter-importer' ), $num_pings );
		}

		echo "</li>";
		//ob_flush();flush();

		return true;
	}

	private function create_post() {
		$post = new StdClass();

		$post->post_content  = '';
		$post->extended      = '';
		$post->post_excerpt  = '';
		$post->post_keywords = '';
		$post->categories    = array();

		return $post;
	}

	private function create_comment() {
		$comment = new StdClass();

		$comment->comment_content      = '';
		$comment->comment_author       = '';
		$comment->comment_author_url   = '';
		$comment->comment_author_email = '';
		$comment->comment_author_IP    = '';
		$comment->comment_date         = null;
		$comment->comment_post_ID      = null;

		return $comment;
	}

	/**
	 * Process the posts.
	 *
	 * @return bool|WP_Error
	 */
	function process_posts() {
		global $wpdb;

		$handle = $this->fopen( $this->file );
		if ( $handle == null ) {
			return false;
		}

		$context  = '';
		$post     = $this->create_post();
		$comment  = $this->create_comment();
		$comments = array();
		$ping     = $this->create_comment();
		$pings    = array();

		echo "<div class='wrap'><ol>";

		// Disable some slowdown points, turn them back on later.
		wp_suspend_cache_invalidation();
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );

		// Turn off autocommit, for speed.
		if ( ! WP_MT_IMPORT_FORCE_AUTOCOMMIT ) {
			$wpdb->query( 'SET autocommit = 0' );
		}

		$count = 0;
		while ( $line = $this->fgets( $handle ) ) {

			// Commit once every 500 posts.
			$count++;
			if ( ! WP_MT_IMPORT_FORCE_AUTOCOMMIT && $count % 500 === 0 ) {
				$wpdb->query( 'COMMIT' );
			}

			$line = trim( $line );

			if ( '-----' == $line ) {
				// Finishing a multi-line field.
				if ( 'comment' == $context ) {
					$comments[] = $comment;
					$comment    = $this->create_comment();
				} else if ( 'ping' == $context ) {
					$pings[] = $ping;
					$ping    = $this->create_comment();
				}
				$context = '';
			} else if ( '--------' == $line ) {
				// Finishing a post.
				$context = '';
				$result  = $this->save_post( $post, $comments, $pings );
				if ( is_wp_error( $result ) ) {
					return $result;
				}

				$post     = $this->create_post();
				$comment  = $this->create_comment();
				$comments = array();
				$ping     = $this->create_comment();
				$pings    = array();
			} else if ( 'BODY:' == $line ) {
				$context = 'body';
			} else if ( 'EXTENDED BODY:' == $line ) {
				$context = 'extended';
			} else if ( 'EXCERPT:' == $line ) {
				$context = 'excerpt';
			} else if ( 'KEYWORDS:' == $line ) {
				$context = 'keywords';
			} else if ( 'COMMENT:' == $line ) {
				$context = 'comment';
			} else if ( 'PING:' == $line ) {
				$context = 'ping';
			} else if ( 0 === strpos( $line, 'AUTHOR:' ) ) {
				$author = trim( substr( $line, strlen( 'AUTHOR:' ) ) );
				if ( '' == $context ) {
					$post->post_author = $author;
				} else if ( 'comment' == $context ) {
					$comment->comment_author = $author;
				}
			} else if ( 0 === strpos( $line, 'TITLE:' ) ) {
				$title = trim( substr( $line, strlen( 'TITLE:' ) ) );
				if ( '' == $context ) {
					$post->post_title = $title;
				} else if ( 'ping' == $context ) {
					$ping->title = $title;
				}
			} else if ( 0 === strpos( $line, 'BASENAME:' ) ) {
				$slug = trim( substr( $line, strlen( 'BASENAME:' ) ) );
				if ( ! empty( $slug ) ) {
					$post->post_name = $slug;
				}
			} else if ( 0 === strpos( $line, 'STATUS:' ) ) {
				$status = trim( strtolower( substr( $line, strlen( 'STATUS:' ) ) ) );
				if ( empty( $status ) ) {
					$status = 'publish';
				}
				$post->post_status = $status;
			} else if ( 0 === strpos( $line, 'ALLOW COMMENTS:' ) ) {
				$allow = trim( substr( $line, strlen( 'ALLOW COMMENTS:' ) ) );
				if ( $allow == 1 ) {
					$post->comment_status = 'open';
				} else {
					$post->comment_status = 'closed';
				}
			} else if ( 0 === strpos( $line, 'ALLOW PINGS:' ) ) {
				$allow = trim( substr( $line, strlen( 'ALLOW PINGS:' ) ) );
				if ( $allow == 1 ) {
					$post->ping_status = 'open';
				} else {
					$post->ping_status = 'closed';
				}
			} else if ( 0 === strpos( $line, 'CATEGORY:' ) ) {
				$category = trim( substr( $line, strlen( 'CATEGORY:' ) ) );
				if ( '' != $category ) {
					$post->categories[] = $category;
				}
			} else if ( 0 === strpos( $line, 'PRIMARY CATEGORY:' ) ) {
				$category = trim( substr( $line, strlen( 'PRIMARY CATEGORY:' ) ) );
				if ( '' != $category ) {
					$post->categories[] = $category;
				}
			} else if ( 0 === strpos( $line, 'DATE:' ) ) {
				$date     = trim( substr( $line, strlen( 'DATE:' ) ) );
				$date     = strtotime( $date );
				$date     = date( 'Y-m-d H:i:s', $date );
				$date_gmt = get_gmt_from_date( $date );
				if ( '' == $context ) {
					$post->post_modified     = $date;
					$post->post_modified_gmt = $date_gmt;
					$post->post_date         = $date;
					$post->post_date_gmt     = $date_gmt;
				} else if ( 'comment' === $context ) {
					$comment->comment_date = $date;
				} else if ( 'ping' === $context ) {
					$ping->comment_date = $date;
				}
			} else if ( 0 === strpos( $line, 'EMAIL:' ) ) {
				$email = trim( substr( $line, strlen( 'EMAIL:' ) ) );
				if ( 'comment' == $context ) {
					$comment->comment_author_email = $email;
				} else {
					$ping->comment_author_email = '';
				}
			} else if ( 0 === strpos( $line, 'IP:' ) ) {
				$ip = trim( substr( $line, strlen( 'IP:' ) ) );
				if ( 'comment' == $context ) {
					$comment->comment_author_IP = $ip;
				} else {
					$ping->comment_author_IP = $ip;
				}
			} else if ( 0 === strpos( $line, 'URL:' ) ) {
				$url = trim( substr( $line, strlen( 'URL:' ) ) );
				if ( 'comment' == $context ) {
					$comment->comment_author_url = $url;
				} else {
					$ping->comment_author_url = $url;
				}
			} else if ( 0 === strpos( $line, 'BLOG NAME:' ) ) {
				$blog                 = trim( substr( $line, strlen( 'BLOG NAME:' ) ) );
				$ping->comment_author = $blog;
			} else {
				// Processing multi-line field, check context.

				if ( ! empty( $line ) ) {
					$line .= "\n";
				}

				if ( 'body' == $context ) {
					$post->post_content .= $line;
				} else if ( 'extended' == $context ) {
					$post->extended .= $line;
				} else if ( 'excerpt' == $context ) {
					$post->post_excerpt .= $line;
				} else if ( 'keywords' == $context ) {
					$post->post_keywords .= $line;
				} else if ( 'comment' == $context ) {
					$comment->comment_content .= $line;
				} else if ( 'ping' == $context ) {
					$ping->comment_content .= $line;
				}
			}
		}

		$this->fclose( $handle );

		echo '</ol>';

		// Commit the changes, turn autocommit back on.
		if ( ! WP_MT_IMPORT_FORCE_AUTOCOMMIT ) {
			$wpdb->query( 'COMMIT' );
			$wpdb->query( 'SET autocommit = 1' );
		}

		// Turn basic caching and counting back on, flush the cache. This will also cause a full count to be performed for terms and comments
		wp_suspend_cache_invalidation( false );
		wp_cache_flush();
		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );

		wp_import_cleanup( $this->id );
		do_action( 'import_done', 'twitter' );

		echo '<h3>' . sprintf( __( 'All done. <a href="%s">Have fun!</a>', 'twitter-importer' ), esc_url( get_option( 'home' ) ) ) . '</h3></div>';

		return true;
	}

	function import() {
		$this->id = (int) $_GET['id'];
		if ( $this->id === 0 ) {
			$this->file = WP_CONTENT_DIR . '/twitter-export.txt';
		} else {
			$this->file = get_attached_file( $this->id );
		}
		$this->get_authors_from_post();
		$result = $this->process_posts();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	function dispatch() {
		if ( empty ( $_GET['step'] ) ) {
			$step = 0;
		} else {
			$step = (int) $_GET['step'];
		}

		switch ( $step ) {
			case 0 :
				$this->greet();
				break;
			case 1 :
				check_admin_referer( 'import-upload' );
				$this->select_authors();
				break;
			case 2:
				check_admin_referer( 'import-twitter' );
				set_time_limit( 0 );
				$result = $this->import();
				if ( is_wp_error( $result ) ) {
					echo $result->get_error_message();
				}
				break;
		}
	}
}
