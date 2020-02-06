<?php
/**
 * Media Directory Arrange
 *
 * @package Media_Directory_Arrange
 * @author  Yo Yamamoto <cross_sphere@hotmail.com>
 */
class Media_Directory_Arrange {

	// このクラスのインスタンス
	protected static $instance = null;

	/**
	 * インスタンス生成
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		} // end if
		return self::$instance;
	} // end get_instance

	/**
	 * Register ID of management page
	 * 
	 * @var
	 * @since 1.0
	 */
	private $menu_id;

	/**
	 * User capability
	 * 
	 * @access public
	 * @since 1.0
	 */
	private $capability;

	private $setting;

	/**
	 * コンストラクタ
	 */
	public function __construct() {

		// Allow people to change what capability is required to use this plugin
		$this->capability = apply_filters('media_directory_arrange_cap', 'manage_options');

		// メニューの追加
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Javascriptの追加
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueues' ) );
	
		// Ajax プロセス
		add_action( 'wp_ajax_movemediafileaction', array( $this, 'ajax_process' ) );

		// バルクアクション
		add_action( 'bulk_actions-upload', array( $this, 'mda_bulk_actions' ) );

		add_filter( 'handle_bulk_actions-upload', array( $this, 'mda_bulk_action_handler'), 10, 3 );

		// セッティング
		$this->setting = Setting::get_instance();
	}

	/*--------------------------------------------*
	 * コアファンクション
	 *---------------------------------------------*/

	/**
	 * Enqueue Javascript and CSS
	 * 
	 * @param string $hook_suffix
	 * @access public
	 * @since 1.0
	 */
	public function admin_enqueues( $hook_suffix ){
		if( $hook_suffix != $this->menu_id ){
			return;
		}
		wp_enqueue_script('jquery-ui-progressbar', MDA_URL . 'asset/js/jquery-ui/jquery.ui.progressbar.min.1.7.2.js', array('jquery-ui-core'), '1.7.2' );
		wp_enqueue_style('jquery-ui-regenthumbs', MDA_URL . 'asset/js/jquery-ui/redmond/jquery-ui-1.7.2.custom.css', array(), '1.7.2' );
		//wp_enqueue_style('plugin-custom-style', plugins_url('style.css', __FILE__), array(), '2.0.1');
		if ( 'upload.php' === $hook_suffix ) {
		}
	}

	/**
	 * メディア一覧 バルクアクション
	 * @access public
	 */
	function mda_bulk_actions( $bulk ){
		$bulk['media_directory_arrange'] = 'メディア自動整理';
		return $bulk;
	}

	/**
	 * メディア一覧 バルク コールバック
	 * @access public
	 */
	public function mda_bulk_action_handler( $redirect_to, $doaction, $id_list ) {
		if ( $doaction !== 'media_directory_arrange' ) {
			return $redirect_to;
		}
		check_admin_referer( 'bulk-media' );
		$ids = implode( ',', array_map( 'intval', $id_list ) );
		$redirect_to = add_query_arg(
			'_wpnonce',
			wp_create_nonce( 'media-directory-arrange' ),
			admin_url( 'tools.php?page=media-directory-arrange&goback=1&ids=' . $ids )
		);
		return $redirect_to;
	}


	/**
	 * 独自ページの登録
	 * 
	 * @access public
	 * @since 1.0
	 */
	public function add_admin_menu() {
		$this->menu_id = add_management_page(
			'メディア自動整理',
			'メディア自動整理',
			$this->capability,
			'media-directory-arrange',
			array(&$this, 'mda_interface')
		);
	}

	/**
	 * 独自ページのインタフェース
	 * 
	 * @access public
	 * @since 1.0
	 */
	public function mda_interface() {
		global $wpdb;
		?>
			<div id="message" class="updated fade" style="display:none"></div>
			<div class="wrap regenthumbs">
				<h2>メディア自動整理</h2>
				<?php
					if(
						! empty( $_POST['media-directory-arrange'] ) ||
						filter_input( INPUT_GET, 'media-directory-arrange-action' ) === '1' 
					){
						$this->process_template_arrangedirectory();
					}else if( ! empty( $_GET['ids'] ) ){
						$this->process_template_allmediafile( filter_input( INPUT_GET, 'ids' ) );
					}else {
						$this->process_template_allmediafile();
					}
				?>
			</div>
		<?php
	}

	/**
	 * テンプレート：ノーマル
	 */
	private function process_template_allmediafile( $ids = '' ){
		$id_list = explode(',', $ids);
		?>
			<form method="post" action="">
				<?php wp_nonce_field('media-directory-arrange') ?>
				<noscript><p><em>Javascriptを有効化してください。</em></p></noscript>
				<p>メディアファイルの保存先を一括整理、移動する</p>
				<h3>移動先テンプレート</h3>
				<code>
					<?php
						$option_data = $this->setting->get_option_data();
						echo $option_data['mda_permalink'];
					?>
				</code>

				<h3>選択したファイルのみ</h3>
				<?php if( ! empty( $ids ) ): ?>
					<p>
						<input type="submit" class="button-primary hide-if-no-js" name="media-directory-arrange" id="media-directory-arrange" value="選択したメディアファイルを移動する" />
					</p>
					<h4>選択したファイル</h4>
					<ul>
						<?php
							$arrange = new Arrange();
							foreach( $id_list as $attachment_id ):
								$upload_path = $arrange->get_upload_path( $attachment_id );
						?>
							<li>
								ID：<?php echo $attachment_id; ?> =&gt; <code><?php echo $upload_path['path'] . '/'; ?></code></p>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<p><a href='<?php echo admin_url('upload.php'); ?>'>メディアページ</a>から個別に選択してファイルを移動させることができます。</p>
			
				<h3>全てファイル</h3>
				<p>
					<input type="submit" class="button-primary hide-if-no-js" name="media-directory-arrange" id="media-directory-arrange" value="全てのメディアファイルを移動する" />
				</p>
			</form>
		<?php
	}

	/**
	 * テンプレート：ファイル移動中
	 */
	private function process_template_arrangedirectory(){
		// Capability check
		if ( ! current_user_can( $this->capability ) ){
			wp_die( __('Cheatin&#8217; uh?') );
		}

		// Form nonce check
		check_admin_referer( 'media-directory-arrange' );

		// Create the list of image IDs
		if ( ! empty( $_REQUEST['ids'] ) ) {
			$images = array_map( 'intval', explode( ',', trim( $_REQUEST['ids'], ',' ) ) );
			$ids = implode( ',', $images );
		} else {
			// Directly querying the database is normally frowned upon, but all
			// of the API functions will return the full post objects which will
			// suck up lots of memory. This is best, just not as future proof.
			if (!$images = $wpdb->get_results("SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' ORDER BY ID DESC")) {
				echo '	<p>' . sprintf(__("Unable to find any images. Are you sure <a href='%s'>some exist</a>?", 'media-directory-arrange'), admin_url('upload.php?post_mime_type=image')) . "</p></div>";
				return;
			}
			// Generate the list of IDs
			$ids = array();
			foreach ($images as $image) {
				$ids[] = $image->ID;
			}
			$ids = implode(',', $ids);
		}

		echo '<p>メディアファイルの保存されているディレクトリ（URL）を移動します。</p>';

		$count = count( $images );
		$text_goback = ( ! empty( $_GET['goback'] ) )
						? sprintf('<a href="%s">一つ前のページへ戻ります</a>.', 'javascript:history.go(-1)')
						: '';

		$text_failures = sprintf(
			'完了しました。 %1$s個のメディアファイルが移動され、 %2$s秒かかりました。%3$s個のメディアファイルの移動に失敗しました。<a href="%4$s">こちらのリンクより再度実行してください。</a>. %5$s',
			"' + mda_successes + '",
			"' + mda_totaltime + '",
			"' + mda_errors + '",
			esc_url(
				wp_nonce_url(
					admin_url('tools.php?page=media-directory-arrange&goback=1' ),
					'media-directory-arrange'
				) . '&ids='
			) . "' + mda_failedlist + '",
			$text_goback
		);
		$text_nofailures = sprintf(
			'全て完了しました。 %1$s個のメディアファイルが移動され、%2$s秒かかりました。 %3$s',
			"' + mda_successes + '",
			"' + mda_totaltime + '",
			$text_goback
		);
	?>

	<noscript><p><em>JavascriptをONにしてください。</em></p></noscript>

	<div id="mda-bar" style="position:relative;height:25px;">
		<div id="mda-bar-percent" style="position:absolute;left:50%;top:50%;width:300px;margin-left:-150px;height:25px;margin-top:-9px;font-weight:bold;text-align:center;"></div>
	</div>

	<p><input type="button" class="button hide-if-no-js" name="mda-stop" id="mda-stop" value="プロセスを中断する" /></p>

	<h3 class="title">進行状況</h3>

	<p>
		<?php printf( '全体: %s', $count ); ?><br />
		<?php printf( '成功: %s', '<span id="mda-debug-successcount">0</span>' ); ?><br />
		<?php printf( '失敗: %s', '<span id="mda-debug-failurecount">0</span>' ); ?>
	</p>

	<ol id="mda-debuglist">
		<li style="display:none"></li>
	</ol>
	<?php
	  var_dump( $ids );
	?>
	<script type="text/javascript">
		jQuery(document).ready(function($){
			var i;
			var mda_images = [<?php echo $ids; ?>];
			var mda_total = mda_images.length;
			var mda_count = 1;
			var mda_percent = 0;
			var mda_successes = 0;
			var mda_errors = 0;
			var mda_failedlist = '';
			var mda_resulttext = '';
			var mda_timestart = new Date().getTime();
			var mda_timeend = 0;
			var mda_totaltime = 0;
			var mda_continue = true;

			// Create the progress bar
			$("#mda-bar").progressbar();
			$("#mda-bar-percent").html("0%");

			// Stop button
			$("#mda-stop").click(function() {
				mda_continue = false;
				$('#mda-stop').val("停止中");
			});

			// Clear out the empty list element that's there for HTML validation purposes
			$("#mda-debuglist li").remove();

			// Called after each resize. Updates debug information and the progress bar.
			function MdaUpdateStatus(id, success, msg) {
				$("#mda-bar").progressbar("value", (mda_count / mda_total) * 100);
				$("#mda-bar-percent").html(Math.round((mda_count / mda_total) * 1000) / 10 + "%");
				mda_count = mda_count + 1;

				if ( success ) {
					mda_successes = mda_successes + 1;
					$("#mda-debug-successcount").html(mda_successes);
					$("#mda-debuglist").append("<li>" + msg + "</li>");
				}
				else {
					mda_errors = mda_errors + 1;
					mda_failedlist = mda_failedlist + ',' + id;
					$("#mda-debug-failurecount").html(mda_errors);
					$("#mda-debuglist").append("<li>" + msg + "</li>");
				}
			}

			// Called when all images have been processed. Shows the results and cleans up.
			function MdaFinishUp() {
				mda_timeend = new Date().getTime();
				mda_totaltime = Math.round((mda_timeend - mda_timestart) / 1000);

				$('#mda-stop').hide();

				if (mda_errors > 0) {
					mda_resulttext = '<?php echo $text_failures; ?>';
				} else {
					mda_resulttext = '<?php echo $text_nofailures; ?>';
				}

				$("#message").html("<p><strong>" + mda_resulttext + "</strong></p>");
				$("#message").show();
			}

			// Regenerate a specified image via AJAX
			function MdaMoveMediaFile(id) {
				$.ajax({
					type: 'POST',
					cache: false,
					url: ajaxurl,
					data: {
						action: "movemediafileaction",
						id: id,
						nonce: '<?php echo wp_create_nonce( 'media-directory-arrange_js' ); ?>'
					},
					success: function( response ) {
						if( response === null ) {
							response = {};
							response.success = false;
							response.data.msg = 'Unknown error occured.';
						}
						MdaUpdateStatus( id, response.success, response.data.msg );
						if ( mda_images.length && mda_continue ){
							MdaMoveMediaFile( mda_images.shift() );
						} else {
							MdaFinishUp();
						}
					},
					error: function( response ) {
						MdaUpdateStatus( id, false, response );
						if ( mda_images.length && mda_continue ) {
							MdaMoveMediaFile( mda_images.shift() );
						} else {
							MdaFinishUp();
						}
					}
				});
			}
			
			MdaMoveMediaFile(mda_images.shift());
		});
	</script>
	<?php
	}


	/**
	 * Process a single image ID (this is an AJAX handler)
	 * 
	 * @access public
	 * @since 1.0
	 */
	public function ajax_process() {
		// No timeout limit
		set_time_limit(0);
		// Don't break the JSON result
		error_reporting(0);

		$id = (int) filter_input( INPUT_POST, 'id' );
		try {
			// NONCEチェック
			if ( ! wp_verify_nonce( filter_input( INPUT_POST, 'nonce'), 'media-directory-arrange_js' ) ) {
				throw new Exception( 'Security error.' );
			}

			// 権限がありません
			if ( ! current_user_can( $this->capability ) ) {
				throw new Exception( 'Your user account does not have permission.' );
			}

			// メディアファイルが不正です
			$image = get_post( $id );
			if( is_null( $image ) ){
				throw new Exception(sprintf( 'Failed: %d is an invalid attachment ID.', $id ) );
			}

			$image_fullpath = get_attached_file( $image->ID );

			// メディアファイルではありません
			if ( false === $image_fullpath || strlen( $image_fullpath ) == 0 ) {
				throw new Exception(sprintf( 'Failed: %d is not attachments.', $id ) );
			}

			// ファイルが見つかりません
			if ( ! file_exists( $image_fullpath ) || realpath( $image_fullpath ) === false) {
				throw new Exception(
					sprintf(
						'The originally uploaded image file cannot be found at &quot;%s&quot;.',
						esc_html( ( string ) $image_fullpath )
					)
				);
			}
			
			$debug_1 = $image_fullpath;
			$debug_2 = '';
			$debug_3 = '';
			$debug_4 = '';
			
			// Results
			$thumb_deleted = array();
			$thumb_error = array();
			$thumb_regenerate = array();
			
			/**
			 * サムネの削除用ハック
			 */
			$file_info = pathinfo( $image_fullpath );
			$file_info['filename'] .= '-';

			/**
			 * サムネの確認
			 */
			$files = array();
			$path = opendir( $file_info['dirname'] );
			if( false !== $path ) {
				while( false !== ( $thumb = readdir( $path ) ) ){
					if( ! ( strrpos( $thumb, $file_info['filename'] ) === false ) ){
						$files[] = $thumb;
					}
				}
				closedir( $path );
				sort( $files );
			}
			/*
			foreach ($files as $thumb) {
				$thumb_fullpath = $file_info['dirname'] . DIRECTORY_SEPARATOR . $thumb;
				$thumb_info = pathinfo($thumb_fullpath);
				$valid_thumb = explode($file_info['filename'], $thumb_info['filename']);
				if ($valid_thumb[0] == "") {
					$dimension_thumb = explode('x', $valid_thumb[1]);
					if (count($dimension_thumb) == 2) {
						if (is_numeric($dimension_thumb[0]) && is_numeric($dimension_thumb[1])) {
							unlink($thumb_fullpath);
							if (!file_exists($thumb_fullpath)) {
								$thumb_deleted[] = sprintf("%sx%s", $dimension_thumb[0], $dimension_thumb[1]);
							} else {
								$thumb_error[] = sprintf("%sx%s", $dimension_thumb[0], $dimension_thumb[1]);
							}
						}
					}
				}
			}
			*/

			/**
			 * サムネイルの再生成
			 */
			/*
			$metadata = wp_generate_attachment_metadata($image->ID, $image_fullpath);
			if (is_wp_error($metadata)) {
				throw new Exception($metadata->get_error_message());
      }
			if (empty($metadata)) {
				throw new Exception(__('Unknown failure reason.', 'force-regenerate-thumbnails'));
      }
			wp_update_attachment_metadata($image->ID, $metadata);
			*/

			/**
			 * サムネ再生成の確認 (deleted, errors, success)
			 */
			$files = array();
			$path = opendir($file_info['dirname']);
			if( false !== $path ) {
				while( false !== ( $thumb = readdir( $path ) ) ){
					if( ! ( strrpos( $thumb, $file_info['filename'] ) === false ) ){
						$files[] = $thumb;
					}
				}
				closedir( $path );
				sort( $files );
			}
			foreach( $files as $thumb ){
				$thumb_fullpath = $file_info['dirname'] . DIRECTORY_SEPARATOR . $thumb;
				$thumb_info = pathinfo( $thumb_fullpath );
				$valid_thumb = explode( $file_info['filename'], $thumb_info['filename'] );
				if ( $valid_thumb[0] == "" ) {
					$dimension_thumb = explode( 'x', $valid_thumb[1] );
					if ( count( $dimension_thumb ) == 2 ) {
						if ( is_numeric( $dimension_thumb[0] ) && is_numeric( $dimension_thumb[1] ) ) {
							$thumb_regenerate[] = sprintf( "%sx%s", $dimension_thumb[0], $dimension_thumb[1] );
						}
					}
				}
			}


			// Remove success if has in error list
			foreach( $thumb_regenerate as $key => $regenerate ){
				if( in_array( $regenerate, $thumb_error ) ){
					//unset( $thumb_regenerate[$key] );
				}
			}

			// Remove deleted if has in success list
			foreach( $thumb_deleted as $key => $deleted ){
				if( in_array( $deleted, $thumb_regenerate ) ){
					unset( $thumb_deleted[$key] );
				}
			}





			/**
			 * Display results
			 */
			$upload_dir = wp_upload_dir();
			$message  = sprintf('<b>&quot;%s&quot; (ID %s)</b>', esc_html( get_the_title( $id ) ), $image->ID);
			$message .= "<br /><br />";
			$message .= sprintf("<code>BaseDir: %s</code><br />", $upload_dir['basedir']);
			$message .= sprintf("<code>BaseUrl: %s</code><br />", $upload_dir['baseurl']);
			$message .= sprintf("<code>Image: %s</code><br />", $debug_1);
			if ($debug_2 != '')
				$message .= sprintf("<code>Image Debug 2: %s</code><br />", $debug_2);
			if ($debug_3 != '')
				$message .= sprintf("<code>Image Debug 3: %s</code><br />", $debug_3);
			if ($debug_4 != '')
				$message .= sprintf("<code>Image Debug 4: %s</code><br />", $debug_4);

			if (count($thumb_deleted) > 0) {
				$message .= sprintf('<br />Deleted: %s', implode(', ', $thumb_deleted));	
			}
			if (count($thumb_error) > 0) {
				$message .= sprintf('<br /><b><span style="color: #DD3D36;">Deleted error: %s</span></b>', implode(', ', $thumb_error));
				$message .= sprintf('<br /><span style="color: #DD3D36;">Please, check the folder permission (chmod 777): %s</span>', $upload_dir['basedir']);
			}
			if (count($thumb_regenerate) > 0) {
				$message .= sprintf('<br />Regenerate: %s</span>', implode(', ', $thumb_regenerate));
				if (count($thumb_error) <= 0) {
					$message .=	sprintf('<br />Successfully regenerated in %s seconds', timer_stop());
				}
			}



			$output = array(
				'msg' => $message
			);

			header( 'Content-Type: application/json' );
			wp_send_json_success( $output );
		}
		catch (PDOException $e){
			header('Content-Type: application/json');
			wp_send_json_error( array( 'msg' => 'ERROR: ' . $e->getMessage() . "\n" ) );
		}
		
		/**
		 * Update META POST
		 * Thanks (@norecipes)
		 *
		 * @since 2.0.2
		 */
		//update_attached_file( $image->ID, $image_fullpath );
	}

} // end class
?>