<?php
/**
 * Setting
 *
 * @package Media_Directory_Arrange
 * @author  Yo Yamamoto <cross_sphere@hotmail.com>
 */
class Setting {
	
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
	
	// DB wp_options table labelname 'option_name' 
	private $option_name;

	private $setting_group_name;

	private $capability;

	private $page;

	private $placeholder;
	/**
	 * construct
	 */
	private function __construct() {
		// $page
		$this->page = 'media-directory-arrange-option';

		// DB wp_options table 'option_name'
		$this->option_name = 'mda_attachiment_permalink';

		// register_setting group name
		$this->register_setting_group = 'mda-setting-group';

		// template placeholder
		$this->placeholder = array(
			'file_type'						=> 'The file type',
			'file_ext'						=> 'The file extension',
			'post_id' 						=> 'The post ID',
			'author' 							=> 'The post author',
			'author_role'					=> 'The post author\'s role',
			//'post_category' 		=> 'The post\'s first category',
			//'post_categories'		=> 'All the post\'s categories',
			//'post_status' 			=> 'The post status (publish|draft|private|static|object|attachment|inherit|future)',
			'post_slug' 					=> 'The post\'s URL slug',
			'post_parent_slug' 		=> 'The parent URL slug',
			'post_type' 					=> '(post|page|attachment)',
			'year'								=> 'The post\'s year (YYYY)',
			'month'								=> 'The post\'s month (MM)',
			'day'									=> 'The post\'s day (DD)',
			'current_user'				=> 'The currently logged in user',
			'category'						=> 'The post\'s categories (see: Taxonomies)',
			'post_tag'						=> 'The post\'s tags (see: Taxonomies)',
		);

		// メニューの追加
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// 設定項目の登録
		add_action( 'admin_init', array( $this, 'add_admin_init' ) );

		// Allow people to change what capability is required to use this plugin
		$this->capability = apply_filters('media_directory_arrange_cap', 'manage_options');

		register_activation_hook( __FILE__, array( __class__, 'install_options' ) );
		
		register_uninstall_hook( __FILE__, array( __class__, 'delete_options' ) );
	}

	static function install_options(){
	}

	static function delete_options(){
		delete_option( $this->option_name );
	}

	/**
	 * 設定画面の登録
	 */
	function add_admin_menu() {
		add_options_page(
			'メディア自動整理の設定',
			'メディア自動整理の設定画面',
			$this->capability,
			$this->page,
			array( $this, 'mda_option_interface' )
		);
		return;
	}

	/**
	 * 設定の保存許可
	 */
	function add_admin_init(){
		
		$option_data = get_option( $this->option_name );

		d($option_data);

		// オプションフィールドの登録
		register_setting(
			$this->register_setting_group,
			$this->option_name
		);

		// セッティングセクションの定義
		add_settings_section(
			'mda_setting_section', // Section ID
			'設定', // Title
			function(){ return; }, // Callback
			$this->page // Page
		);

		// セッティングセクションへ追加するフィールドの定義
		$fields_ID_01 = 'mda_permalink';
		add_settings_field(
			$fields_ID_01, // ID
			'保存先パスのテンプレート', // Title 
			function( $arg ) use ( $fields_ID_01, $option_data ){
				?>
					<input type="text" data-target="<?php echo $this->option_name; ?>" id="<?php echo $this->option_name; ?>[<?php echo $fields_ID_01; ?>]" name="<?php echo $this->option_name; ?>[<?php echo $fields_ID_01; ?>]" value="<?php echo ( isset( $option_data[$fields_ID_01] ) ? esc_attr( $option_data[$fields_ID_01] ) : '' ) ?>" style="width:100%; max-width:500px;" />
				<?php
					$wud = wp_upload_dir();
					$upload_dir = $wud['basedir'];
				?>
				<p><code><?php echo $upload_dir; ?></code></p>
				<?php
			}, // Callback
			$this->page, // Page
			'mda_setting_section' // Section ID
		);

		// セッティングセクションへ追加するフィールドの定義
		$fields_ID_02 = 'mda_example_attachment_ID';
		add_settings_field(
			$fields_ID_02, // ID
			'移動先フォルダ テスト用 アタッチメントID', // Title 
			function( $arg ) use ( $fields_ID_02, $option_data ){
				?>
       	<input type="text" id="<?php echo $this->option_name; ?>[<?php echo $fields_ID_02; ?>]" name="<?php echo $this->option_name; ?>[<?php echo $fields_ID_02; ?>]" value="<?php echo ( isset( $option_data[$fields_ID_02] ) ? esc_attr( $option_data[$fields_ID_02] ) : '' ) ?>" />
				<?php
					$attachment_id = $option_data[$fields_ID_02];
					$upload_path = $this->get_upload_path( $attachment_id );
					echo '<p>移動先フォルダ：<code>' . $upload_path['path'] . '/</code></p>';
			}, // Callback
			$this->page, // Page
			'mda_setting_section' // Section ID
		);

	}
	
	/**
	 * 設定画面のインタフェース
	 * 
	 * @access public
	 * @since 1.0
	 */
	function mda_option_interface() {
		if( ! function_exists('current_user_can') || ! current_user_can('manage_options') ){
			die( __('Cheatin&#8217; uh?') );
		}
	?>
		<script type='text/javascript'>
			jQuery(document).ready(function(){
				var path = jQuery("input[data-target='<?php echo $this->option_name; ?>']");
				jQuery("div.updated").delay(2000).slideUp("slow");
				jQuery(".mda_permalink_template").click(function(){
					var sep = (path.val().charAt(path.val().length-1) !== "/") ? "/" : "";
					path.val(path.val()+sep+jQuery(this).html());
				});
			});
		</script>

		<div class="wrap">
			<h2>メディア自動整理 設定画面</h2>
			<form method="post" action="options.php">
				<?php
					settings_fields( $this->register_setting_group );
					do_settings_sections( $this->page );
				?>
				<table class="form-table">
				<tr>
						<th class="field_info" valign="top">パーマリンク構造を選択：</th>
						<td valign="top">
							<ul>
								<?php foreach( $this->placeholder as $key => $description ): ?>
								<li>
									<button type="button" class="button button-secondary mda_permalink_template" aria-label="<?php echo esc_html( $description ); ?>" aria-pressed="false" style="vertical-align:middle;">%<?php echo esc_html( $key ); ?>%</button>
									=&gt;<code><?php echo esc_html( $description ); ?></code>
								</li>
								<?php endforeach; ?>
							</ul>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function get_upload_path( $attachiment_id ){
		$upload_dir = wp_upload_dir();
		$custom_dir = $this->generate_path( $attachiment_id );
		$upload_dir['path'] 	 = str_replace($upload_dir['subdir'], '', $upload_dir['path']); //remove default subdir (year/month)
		$upload_dir['url']	 = str_replace($upload_dir['subdir'], '', $upload_dir['url']);
		$upload_dir['subdir']  = $custom_dir;
		$upload_dir['path'] 	.= $custom_dir;
		$upload_dir['url'] 	.= $custom_dir;
		return $upload_dir;
	}


	/**
	 * テンプレートパーマリンクから移動先パスを生成
	 */
	function generate_path( $attachiment_id ){
		$option_data = get_option( $this->option_name );
		$customdir = $option_data['mda_permalink'];
		foreach( $this->placeholder as $holder => $description ){
			$customdir = str_replace(
				'%'.$holder.'%',
				call_user_func(
					array( $this, 'get_'.$holder ),
					$attachiment_id
				),
				$customdir
			);
		}
		while(strpos($customdir, '//') !== false){
			$customdir = str_replace('//', '/', $customdir); //avoid duplicate slashes.
		}
		return apply_filters( 'mda_generate_path', $customdir, $attachiment_id );
	}



	/**
	 * str_replace template for $this->placeholder
	 */
	private function get_file_type( $attachment_id ){
		$path = get_attached_file( $attachment_id );
		$wp_filetype = wp_check_filetype( $path );
		return ( ! empty( $wp_filetype['type'] ) ) ? $wp_filetype['type'] : '' ;
	}

	private function get_file_ext( $attachment_id ){
		$path = get_attached_file( $attachment_id );
		$wp_filetype = wp_check_filetype( $path );
		return ( ! empty( $wp_filetype['ext'] ) ) ? $wp_filetype['ext'] : '' ;
	}
	
	private function get_post_id( $attachment_id ){
		return sanitize_title( get_post_field( 'post_parent', $attachment_id ) );
	}

	private function get_author( $attachment_id ){
		$user = get_userdata( get_post_field( 'post_author', $attachment_id ) );
		return sanitize_title( $user->user_nicename );
	}

	private function get_author_role( $attachment_id ){
		$post = get_post( $attachment_id );
		$user = get_userdata( $post->post_author );
		return $user->roles[0];
	}

	private function get_post_slug( $attachment_id ){
		$slug = get_post_field( 'post_name', $this->get_post_id( $attachment_id ) );
		return sanitize_title( $slug );
	}

	private function get_post_parent_slug( $attachment_id ){
		$post_parent_id = get_post_field( 'post_parent',  $this->get_post_id( $attachment_id ) );
		$slug = get_post_field( 'post_name',  $post_parent_id );
		return sanitize_title( $slug );
	}

	private function get_post_type( $attachment_id ){
		$post_type = get_post_field( 'post_type', $this->get_post_id( $attachment_id ) );
		return sanitize_title( $post_type );
	}

	private function get_year( $attachment_id ){
		$year = get_the_date( 'Y', $this->get_post_id( $attachment_id ) );
		return sanitize_title( $year );
	}

	private function get_month( $attachment_id ){
		$month = get_the_date( 'm', $this->get_post_id( $attachment_id ) );
		return sanitize_title( $month );
	}

	private function get_day( $attachment_id ){
		$day = get_the_date( 'd', $this->get_post_id( $attachment_id ) );
		return sanitize_title( $day );
	}

	private function get_current_user( $attachiment_id ){
		if( ! is_user_logged_in() ) return;
		$current_user = wp_get_current_user();
		$user_slug = $current_user->user_nicename;
		return sanitize_title( $user_slug );
	}

	private function get_category( $attachment_id ){
		$categories = get_the_terms( $this->get_post_id( $attachment_id ), 'category' );
		return sanitize_title( $categories[0]->taxonomy . '_' . $categories[0]->slug );
	}

	private function get_post_tag( $attachment_id ){
		$post_tags = get_the_terms( $this->get_post_id( $attachment_id ), 'post_tag' );
		return sanitize_title( $post_tags[0]->taxonomy . '_' . $post_tags[0]->slug );
	}
	/*
		if(get_option('uploads_use_yearmonth_folders') && stripos($options['template'], '/%year%/%monthnum%') !== 0){
			$options['template'] = '/%year%/%monthnum%'.$options['template'];
		}	
	$ancestors = get_post_ancestors( $attachment_id );
	d( $ancestors );
	*/





} // end class
?>