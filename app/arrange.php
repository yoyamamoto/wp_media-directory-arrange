<?php
/**
 * Media Directory Arrange
 *
 * @package Media_Directory_Arrange
 * @author  Yo Yamamoto <cross_sphere@hotmail.com>
 */
class Arrange {

	private $placeholder;

	private $setting;

	/**
	 * construct
	 */
	public function __construct() {
		$this->placeholder = array(
			'file_type'						=> 'The file type',
			'file_ext'						=> 'The file extension',
			'post_id' 						=> 'The post ID',
			'author' 							=> 'The post author',
			'author_role'					=> 'The post author\'s role',
			'post_slug' 					=> 'The post\'s URL slug',
			'post_parent_slug' 		=> 'The parent URL slug',
			'post_type' 					=> '(post|page|attachment)',
			'post_type_with_comment'		=> '(post|page|attachment|comment)',
			'comment_label'				=> 'Insert \'comment\' dir label',
			'comment_id'					=> 'The post\'s comment ID',
			'year'								=> 'The post\'s year (YYYY)',
			'month'								=> 'The post\'s month (MM)',
			'day'									=> 'The post\'s day (DD)',
			'current_user'				=> 'The currently logged in user',
			'category'						=> 'The post\'s categories (see: Taxonomies)',
			'post_tag'						=> 'The post\'s tags (see: Taxonomies)',
		);

		// セッティング
		$this->setting = Setting::get_instance();
	}
	
	/**
	 * 現在のファイルのファイル名を取得
	 */
	public function get_current_file_name( $attachiment_id ){
		return basename( get_attached_file( $attachiment_id ) );
	}
	/**
	 * 現在のファイルの絶対パス
	 */
	public function get_current_file_path( $attachiment_id ){
		return get_attached_file( $attachiment_id );
	}

	/**
	 * 現在のファイルの相対パス
	 */
	public function get_current_relative_file_path( $attachiment_id ){
		$upload_path = $this->get_upload_path( $attachiment_id );
		return '/uploads' . str_replace( $upload_path['basedir'], '', $this->get_current_file_path( $attachiment_id ) );
	}

	/**
	 * 現在のディレクトリの相対パス
	 */
	public function get_current_relative_dir_path( $attachiment_id ){
		$upload_path = $this->get_upload_path( $attachiment_id );
		return '/uploads' . str_replace( $upload_path['basedir'], '', dirname( $this->get_current_file_path( $attachiment_id ) ) ) . '/';
	}

	/**
	 * 移動先の絶対パス
	 */
	public function get_new_file_path( $attachiment_id ){
		$path = $this->get_upload_path( $attachiment_id );
		$filename = basename( get_attached_file( $attachiment_id ) );
		return $path['path'] . '/' . $filename;
	}

	/**
	 * 移動先の相対パス
	 */
	public function get_new_relative_file_path( $attachiment_id ){
		$upload_path = $this->get_upload_path( $attachiment_id );
		return '/uploads' . str_replace( $upload_path['basedir'], '', $this->get_new_file_path( $attachiment_id ) );
	}

	/**
	 * 移動先ディレクトリの相対パス
	 */
	public function get_new_relative_dir_path( $attachiment_id ){
		$upload_path = $this->get_upload_path( $attachiment_id );
		return '/uploads' . str_replace( $upload_path['basedir'], '', $upload_path['path'] ) . '/';
	}

	/**
	 * アップロードパスの取得
	 */
	public function get_upload_path( $attachiment_id ){
		$upload_dir = wp_upload_dir();
		$custom_dir = $this->generate_path( $attachiment_id );
		$upload_dir['path'] = str_replace($upload_dir['subdir'], '', $upload_dir['path']); // remove default subdir (year/month)
		$upload_dir['url'] = str_replace($upload_dir['subdir'], '', $upload_dir['url']);
		$upload_dir['subdir'] = $custom_dir;
		$upload_dir['path'] .= $custom_dir;
		$upload_dir['url'] .= $custom_dir;
		return $upload_dir;
	}

	/**
	 * テンプレートパーマリンクから移動先パスを生成
	 */
	public function generate_path( $attachiment_id ){
		$option_data = $this->setting->get_option_data();
		$customdir = $option_data['mda_permalink'];
		// explode
		$arr_dir = preg_split( '/\//', ltrim($customdir, '/') );
		foreach ( $arr_dir as $key => $dir ){
			foreach( $this->placeholder as $holder => $description ){
				if( strpos( $dir, '%'.$holder.'%' ) === false ){
					continue;
				}
				$replace = call_user_func(
					array( $this, 'get_'.$holder ),
					$attachiment_id
				);
				if( $replace === '' ){
					unset( $arr_dir[$key] );
					continue;
				}
				$arr_dir[$key] = str_replace(
					'%'.$holder.'%',
					call_user_func(
						array( $this, 'get_'.$holder ),
						$attachiment_id
					),
					$arr_dir[$key]
				);
			}
		}
		// implode
		$customdir = '/' . join( '/', $arr_dir );
		return apply_filters( 'mda_generate_path', $customdir, $attachiment_id );
	}

	/**
	 * $this->placeholderに対応した置き換え関数たち
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

	private function get_post_type_with_comment( $attachment_id ){
		$comment_id = get_post_meta( $attachment_id, '_wp_attachment_relation_comment_id', true);
		if( ! empty( $comment_id ) ){
			return $this->get_comment_label();
		}else {
			return $this->get_post_type( $attachment_id );
		}
	}

	private function get_comment_label( $attachment_id = '' ){
		return 'comment';
	}

	private function get_comment_id( $attachment_id ){
		$comment_id = get_post_meta( $attachment_id, '_wp_attachment_relation_comment_id', true);
		return sanitize_title( $comment_id );
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

	/**
	 * 
	 */
	public function get_placeholder(){
		return $this->placeholder;
	}
} // end class
?>