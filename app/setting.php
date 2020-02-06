<?php
/**
 * Setting
 *
 * @package Media_Directory_Arrange
 * @author  Yo Yamamoto <cross_sphere@hotmail.com>
 */
class Setting {
	
	/**
	 * インスタンス生成
	 */
	protected static $instance = null;
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	private $page;

	private $option_name;

	private $register_setting_group;

	private $capability;

	/**
	 * construct
	 */
	public function __construct() {
		// $page
		$this->page = 'media-directory-arrange-option';

		// DB wp_options table 'option_name'
		$this->option_name = 'mda_attachiment_permalink';

		// register_setting group name
		$this->register_setting_group = 'mda-setting-group';
		
		// Allow people to change what capability is required to use this plugin
		$this->capability = apply_filters('media_directory_arrange_cap', 'manage_options');
		
		register_activation_hook( __FILE__, array( __class__, 'install_options' ) );
		
		register_uninstall_hook( __FILE__, array( __class__, 'delete_options' ) );
		
		// メニューの追加
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// 設定項目の登録
		add_action( 'admin_init', array( $this, 'add_admin_init' ) );
	}

	public static function install_options(){
	}

	public static function delete_options(){
		delete_option( $this->option_name );
	}

	/**
	 * 設定画面の登録
	 */
	public function add_admin_menu() {
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
	public function add_admin_init(){
		$option_data = $this->get_option_data();

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
					$arrange = new Arrange();
					$upload_path = $arrange->get_upload_path( $attachment_id );
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
	public function mda_option_interface() {
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
								<?php
									$arrange = new Arrange();
									foreach( $arrange->get_placeholder() as $key => $description ):
								?>
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

	public function get_option_name(){
		return $this->option_name;
	}

	public function get_option_data(){
		return get_option( $this->option_name );;
	}



} // end class
?>