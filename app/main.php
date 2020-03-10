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
			<noscript><p><em>Javascriptを有効化してください。</em></p></noscript>
			<p>メディアファイルの保存先を一括整理、移動する</p>
			<h3>移動先テンプレート</h3>
			<code>
				<?php
					$option_data = $this->setting->get_option_data();
					echo $option_data['mda_permalink'];
				?>
			</code>
			<p><a href='<?php echo admin_url('options-general.php?page=media-directory-arrange-option'); ?>'>設定ページ</a>から移動先テンプレートを変更できます。</p>

			<h3>選択したファイルのみ</h3>
			<form method="post" action="">
				<?php if( ! empty( $ids ) ): ?>
					<p>
						<input type="submit" class="button-primary hide-if-no-js" name="media-directory-arrange" id="media-directory-arrange" value="選択したメディアファイルを移動する" />
					</p>
					<h4>選択したファイル</h4>
					<ul>
						<?php
							$arrange = new Arrange();
							foreach( $id_list as $attachment_id ):
						?>
							<li>
								<?php echo $arrange->get_current_file_name( $attachment_id ); ?>
								(&nbsp;<a href="<?php echo admin_url( 'post.php?post=' . $attachment_id . '&action=edit' );?>" target="_blank">ID:<?php echo $attachment_id; ?></a>&nbsp;)
								
								<?php
									$current = $arrange->get_current_relative_dir_path( $attachment_id );
									$new = $arrange->get_new_relative_dir_path( $attachment_id );
									if( $current !== $new ):
								?>
									<code><?php echo $current; ?></code>
									=&gt;
									<code><?php echo $new; ?></code>
								<?php else: ?>
									<code><?php echo $current; ?></code> 移動先が同一です。
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<p><a href='<?php echo admin_url('upload.php'); ?>'>メディアページ</a>から個別に選択してファイルを移動させることができます。</p>
				<?php wp_nonce_field('media-directory-arrange') ?>
				<input type="hidden" name="type" value="select">
			</form>
			
			<h3>添付先の投稿タイプから選ぶ</h3>
			<form method="post" action="">
				<ul>
				<?php
					$post_types = get_post_types(array(
						'public' => true,
						'show_ui' => true
					));
					unset($post_types['attachment']);
					foreach( $post_types as $post_type ):
				?>
					<li><label for='mda_<?php echo $post_type; ?>'><input type="checkbox" name="mda_post_type[]" value='<?php echo $post_type; ?>' id='mda_<?php echo $post_type; ?>'><?php echo $post_type; ?></label></li>
				<?php
					endforeach;
				?>
				</ul>
				<p>
					<input type="submit" class="button-primary hide-if-no-js" name="media-directory-arrange" id="media-directory-arrange" value="添付先の投稿タイプから選ぶ" />
				</p>
				<?php wp_nonce_field('media-directory-arrange') ?>
				<input type="hidden" name="type" value="post_type">
			</form>

			<h3>全てファイル</h3>
			<form method="post" action="">
				<p>
					<input type="submit" class="button-primary hide-if-no-js" name="media-directory-arrange" id="media-directory-arrange" value="全てのメディアファイルを移動する" />
				</p>
				<?php wp_nonce_field('media-directory-arrange') ?>
				<input type="hidden" name="type" value="all">
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
		$type = filter_input( INPUT_POST, 'type' );
		if( $type === 'select' ) {
			// ファイルが見つかりません & リダイレクトを入れる
			if( empty( $_REQUEST['ids'] ) ){
				return;
			}
			$images = array_map( 'intval', explode( ',', trim( $_REQUEST['ids'], ',' ) ) );

		}else if( $type === 'post_type' ){
			/* same code
			$attachments = get_posts(array(
				'post_type' => 'attachment',
				'posts_per_page' => 2,
				'post_status' => 'any',
				'post_parent' => null
			));
			d( $attachments );
			*/
			$attachments = array_column(
				get_children(array(
					'post_parent' => null,
					'post_type'   => 'attachment', 
					'numberposts' => 50,
					'post_status' => 'any'
				), 'ARRAY_A'),
				'post_parent',
				'ID'
			);
			$post_types = filter_input( INPUT_POST, 'mda_post_type' , FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$images = array();
			foreach( $attachments as $id => $post_parent ){
				$parent_post_type = get_post_type( $post_parent );
				if( in_array( $parent_post_type, $post_types ) ){
					$images[] = $id;
				}
			}
		}else if( $type === 'all' ){
			$attachments = get_children(array(
				'post_parent' => null,
				'post_type'   => 'attachment', 
				'numberposts' => -1,
				'post_status' => 'any'
			), 'ARRAY_A');
			$images = array_column($attachments, 'ID');
		}
		
		$ids = implode( ',', $images );
		
		echo '<p>メディアファイルの保存されているディレクトリ（URL）を移動します。</p>';

		$count = count( $images );
		$text_goback = ( ! empty( $_GET['goback'] ) )
						? sprintf('<a href="%s">一つ前のページへ戻ります</a>.', 'javascript:history.go(-1)')
						: '';

		$text_failures = sprintf(
			'完了しました。 %1$s個のメディアファイルが移動され、 %2$s秒かかりました。%3$s個のメディアファイルの移動に失敗しました。<a href="%4$s">こちらのリンクより再度実行してください。</a> %5$s',
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

			// Called after each arrange. Updates debug information and the progress bar.
			function MdaUpdateStatus(id, success, data) {
				$("#mda-bar").progressbar("value", (mda_count / mda_total) * 100);
				$("#mda-bar-percent").html(Math.round((mda_count / mda_total) * 1000) / 10 + "%");
				mda_count = mda_count + 1;

				console.log(data.debug);
				console.log(data.debug02);

				if ( success ) {
					mda_successes = mda_successes + 1;
					$("#mda-debug-successcount").html(mda_successes);
					if( data.current_dir === data.new_dir ){
						$("#mda-debuglist").append('<li>' + data.file_name +  ' (ID : ' + id + ').&nbsp;&nbsp;&nbsp; ' + data.msg + ' <code>' + data.new_dir + "</code></li>");
					}else{
						$("#mda-debuglist").append('<li>' + data.file_name +  ' (ID : ' + id + ').&nbsp;&nbsp;&nbsp;<code>' + data.current_dir + '</code> =&gt; <code>' + data.new_dir + "</code></li>");
					}
				}
				else {
					mda_errors = mda_errors + 1;
					mda_failedlist = mda_failedlist + ',' + id;
					$("#mda-debug-failurecount").html(mda_errors);
					$("#mda-debuglist").append('<li>' + data.file_name +  ' (ID : ' + id + ').&nbsp;&nbsp;&nbsp; ' + data.msg + '<code>' + data.current_dir + '</code> =&gt; <code>' + data.new_dir + "</code></li>");
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

			function MdaAjaxCallback( id, response ){
				
				MdaUpdateStatus( id, response.success, response.data );
				if ( mda_images.length && mda_continue ){
					MdaMoveMediaFile( mda_images.shift() );
				} else {
					MdaFinishUp();
				}
			}

			// Regenerate a specified image via AJAX
			function MdaMoveMediaFile( id ) {
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
						MdaAjaxCallback( id, response );
					},
					error: function( response ) {
						MdaAjaxCallback( id, response );
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
	 * @access public
	 */
	public function ajax_process() {
		// No timeout limit
		set_time_limit(0);
		
		// Don't break the JSON result
		error_reporting(0);

		$id = (int) filter_input( INPUT_POST, 'id' );

		$arrange = new Arrange();

		try {
			// セキュリティチェック
			if ( ! wp_verify_nonce( filter_input( INPUT_POST, 'nonce'), 'media-directory-arrange_js' ) ) {
				throw new Exception( 'Security error.' );
			}

			// 権限チェック
			if ( ! current_user_can( $this->capability ) ) {
				throw new Exception( 'Your user account does not have permission.' );
			}

			// メディアファイルが不正チェック
			$image = get_post( $id );
			if( is_null( $image ) ){
				throw new Exception(sprintf( 'Failed: %d is an invalid attachment ID.', $id ) );
			}

			$current_file_path = $arrange->get_current_file_path( $image->ID );
			$current_file_name = basename( $current_file_path );

			// メディアファイルではありません
			if ( false === $current_file_path || strlen( $current_file_path ) == 0 ) {
				throw new Exception(sprintf( 'Failed: %d is not attachments.', $id ) );
			}

			// ファイルが見つかりません
			if ( ! file_exists( $current_file_path ) || realpath( $current_file_path ) === false) {
				throw new Exception(
					sprintf(
						'The originally uploaded image file cannot be found at &quot;%s&quot;.',
						esc_html( ( string ) $current_file_path )
					)
				);
			}

			/*
			 * 既存メディアファイルの移動関連
			 */
			$new_file_path = $arrange->get_new_file_path( $image->ID );
			$upload_path = $arrange->get_upload_path( $image->ID );
			
			// 移動先ディレクトリ
			$new_dir = $upload_path['path'];
			// サムネイルが保存されている旧ディレクトリを取得
			$current_dir = dirname( $current_file_path );
			$new_relative_dir = '/uploads' . str_replace( $upload_path['basedir'], '', $new_dir );
			$current_relative_dir = '/uploads' . str_replace( $upload_path['basedir'], '', $current_dir );
			
			// 移動先が同一です。
			if( $current_file_path === $new_file_path ){
				$response = array(
					'msg' => '移動先が同一です。',
					'file_name' => $current_file_name,
					'current_dir' => $current_relative_dir,
					'new_dir' => $new_relative_dir,
					'debug' => '',
					'debug02' => ''
				);
				header( 'Content-Type: application/json' );
				wp_send_json_success( $response );
			}

			// ディレクトリを確認・作成
			if( ! is_dir( $new_dir ) ){
				if( ! mkdir( $new_dir, 0755, true ) ){
					throw new Exception( sprintf( 'Failed: %d doesn\'t make new directory.', $id ) );
				}
			}
			
			// ディレクトリの書き込み確認
			if( ! is_writable( $new_dir ) ) {
				throw new Exception( sprintf( 'Failed: %s is not writable.', $new_dir ) );
			}
			
			// 移動先にファイルが存在している。
			if( file_exists( $new_file_path ) ){
				throw new Exception( sprintf( 'Failed: %d. Already file exist.', $id ) );
			}

			/*
			// ファイルの移動
			if( rename( $current_file_path, $new_file_path ) === false ){
				throw new Exception( sprintf( 'Failed: %d dosen\'t rename.', $id ) );
			}

			// メタ情報を更新
			if( update_attached_file( $image->ID, $new_path ) === false ){
				rename( $new_file_path, $current_file_path );
				throw new Exception( sprintf( 'Failed: %d. update_attached_file is failed. メタ情報の更新に失敗しました。', $id ) );
			}
			*/

			/*
			 * サムネイルの移動関連
			 */
			// サムネイルのファイル名を取得
			$thumb_files = $this->get_metadata_thumbs_file( $image->ID );
			$directory_files = $this->get_thumbs_file_list( $current_file_path );
			$msg = '';
			// 全てのサムネイルを新しいディレクトリへ移動
			foreach( $thumb_files as $thumb ){
				$new_thumb_path = $new_dir . '/' . $thumb;
				$current_thumb_path = realpath( $current_dir . '/' . $thumb );
				// 既存ファイルが存在しません
				if( $current_thumb_path === false ){
					continue;
				}
				/*
				// ファイルの移動
				if( rename( $current_thumb_path, $new_thumb_path ) === false ){
					$msg .= 'Failed:' . $id . '(' . $current_thumb_path . ')\'s thumbs dosen\'t rename. ';
				}
				*/
			}

			if( !empty( $msg ) ){
				throw new Exception( $msg );
			}

			$response = array(
				'msg' => '',
				'file_name' => $current_file_name,
				'current_dir' => $current_relative_dir,
				'new_dir' => $new_relative_dir,
				'debug' => $directory_files,
				'debug02' => $thumb_files
			);
			header( 'Content-Type: application/json' );
			wp_send_json_success( $response );
		}
		catch (Exception $e){
			$response = array(
				'msg' => $e->getMessage() . "\n",
				'file_name' => $current_file_name,
				'current_dir' => $current_relative_dir,
				'new_dir' => $new_relative_dir,
				'debug' => '',
				'debug02' => ''
			);
			header('Content-Type: application/json');
			wp_send_json_error( $response );
		}
	}
	
	public function get_metadata_thumbs_file( $attahiment_id ){
		$post_meta_data = get_post_meta( $attahiment_id );
		$unserialize_post_wp_attachment_metadata = maybe_unserialize( $post_meta_data['_wp_attachment_metadata'][0] );
		$metadata_thumbs = $unserialize_post_wp_attachment_metadata['sizes'];
		$files = array();
		foreach( $metadata_thumbs as $metadata ){
			$files[] = $metadata['file'];
		}
		return $files;
	}

	public function get_thumbs_file_list( $image_fullpath ){
		/**
		 * サムネの取得
		 */
		$file_info = pathinfo( $image_fullpath );
		$file_info['filename'] .= '-';
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
		return $files;
	}


} // end class
?>