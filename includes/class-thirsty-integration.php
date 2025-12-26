<?php
/**
 * ThirstyAffiliate連携クラス
 *
 * @package Soico_Securities_CTA
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ThirstyAffiliatesプラグインとの連携を行うクラス
 */
class Soico_CTA_Thirsty_Integration {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
    /**
     * ThirstyAffiliatesの投稿タイプ
     */
    const POST_TYPE = 'thirstylink';
    
    /**
     * キャッシュキー
     */
    const CACHE_KEY = 'soico_cta_thirsty_links_cache';
    
    /**
     * キャッシュ有効期限（秒）
     */
    const CACHE_EXPIRATION = 3600;
    
    /**
     * インスタンス取得
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * フック初期化
     */
    private function init_hooks() {
        // AJAX ハンドラー
        add_action( 'wp_ajax_soico_cta_get_thirsty_links', array( $this, 'ajax_get_links' ) );
        add_action( 'wp_ajax_soico_cta_search_thirsty_links', array( $this, 'ajax_search_links' ) );
        
        // ThirstyAffiliateリンク更新時にキャッシュクリア
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'clear_cache' ) );
        add_action( 'delete_post', array( $this, 'maybe_clear_cache' ) );
    }
    
    /**
     * ThirstyAffiliatesが有効かチェック
     *
     * @return bool
     */
    public function is_thirsty_active() {
        return class_exists( 'ThirstyAffiliates' ) || post_type_exists( self::POST_TYPE );
    }
    
    /**
     * アフィリエイトURLを取得
     *
     * @param int $link_id ThirstyAffiliateリンクID
     * @return string|false
     */
    public function get_affiliate_url( $link_id ) {
        if ( empty( $link_id ) || ! $this->is_thirsty_active() ) {
            return false;
        }
        
        $link_id = absint( $link_id );
        
        // キャッシュから取得
        $cached_links = $this->get_cached_links();
        if ( isset( $cached_links[ $link_id ] ) ) {
            return $cached_links[ $link_id ]['url'];
        }
        
        // 直接取得
        $post = get_post( $link_id );
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return false;
        }
        
        // クローキングURL優先
        $cloaked_url = home_url( '/recommends/' . $post->post_name . '/' );
        
        // クローキングURLが使えない場合は直接URL
        $destination = get_post_meta( $link_id, '_ta_destination_url', true );
        
        return $cloaked_url ? $cloaked_url : $destination;
    }
    
    /**
     * リンク情報を取得
     *
     * @param int $link_id
     * @return array|false
     */
    public function get_link_info( $link_id ) {
        if ( ! $this->is_thirsty_active() ) {
            return false;
        }
        
        $link_id = absint( $link_id );
        $post = get_post( $link_id );
        
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return false;
        }
        
        $destination = get_post_meta( $link_id, '_ta_destination_url', true );
        $cloaked_url = home_url( '/recommends/' . $post->post_name . '/' );
        
        return array(
            'id'              => $link_id,
            'name'            => $post->post_title,
            'slug'            => $post->post_name,
            'destination_url' => $destination,
            'cloaked_url'     => $cloaked_url,
            'url'             => $cloaked_url ? $cloaked_url : $destination,
        );
    }
    
    /**
     * 全リンク取得
     *
     * @param bool $use_cache
     * @return array
     */
    public function get_all_links( $use_cache = true ) {
        if ( ! $this->is_thirsty_active() ) {
            return array();
        }
        
        // キャッシュチェック
        if ( $use_cache ) {
            $cached = get_transient( self::CACHE_KEY );
            if ( false !== $cached ) {
                return $cached;
            }
        }
        
        $links = array();
        
        $query = new WP_Query( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $link_id = get_the_ID();
                $links[ $link_id ] = $this->get_link_info( $link_id );
            }
            wp_reset_postdata();
        }
        
        // キャッシュ保存
        set_transient( self::CACHE_KEY, $links, self::CACHE_EXPIRATION );
        
        return $links;
    }
    
    /**
     * キャッシュ済みリンク取得
     *
     * @return array
     */
    private function get_cached_links() {
        $cached = get_transient( self::CACHE_KEY );
        return is_array( $cached ) ? $cached : array();
    }
    
    /**
     * リンク検索
     *
     * @param string $search
     * @return array
     */
    public function search_links( $search ) {
        if ( ! $this->is_thirsty_active() ) {
            return array();
        }
        
        $links = array();
        
        $query = new WP_Query( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            's'              => $search,
            'orderby'        => 'relevance',
        ) );
        
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $link_id = get_the_ID();
                $links[] = $this->get_link_info( $link_id );
            }
            wp_reset_postdata();
        }
        
        return $links;
    }
    
    /**
     * カテゴリ別リンク取得
     *
     * @param string $category_slug
     * @return array
     */
    public function get_links_by_category( $category_slug ) {
        if ( ! $this->is_thirsty_active() ) {
            return array();
        }
        
        $links = array();
        
        $query = new WP_Query( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'tax_query'      => array(
                array(
                    'taxonomy' => 'thirstylink-category',
                    'field'    => 'slug',
                    'terms'    => $category_slug,
                ),
            ),
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $link_id = get_the_ID();
                $links[] = $this->get_link_info( $link_id );
            }
            wp_reset_postdata();
        }
        
        return $links;
    }
    
    /**
     * AJAX: リンク一覧取得
     */
    public function ajax_get_links() {
        // 権限チェック
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません' ) );
        }
        
        // nonceチェック
        check_ajax_referer( 'soico_cta_nonce', 'nonce' );
        
        $links = $this->get_all_links();
        
        wp_send_json_success( array( 'links' => array_values( $links ) ) );
    }
    
    /**
     * AJAX: リンク検索
     */
    public function ajax_search_links() {
        // 権限チェック
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません' ) );
        }
        
        // nonceチェック
        check_ajax_referer( 'soico_cta_nonce', 'nonce' );
        
        $search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        
        if ( empty( $search ) ) {
            $links = $this->get_all_links();
            wp_send_json_success( array( 'links' => array_values( $links ) ) );
        }
        
        $links = $this->search_links( $search );
        
        wp_send_json_success( array( 'links' => $links ) );
    }
    
    /**
     * キャッシュクリア
     */
    public function clear_cache() {
        delete_transient( self::CACHE_KEY );
    }
    
    /**
     * 条件付きキャッシュクリア
     *
     * @param int $post_id
     */
    public function maybe_clear_cache( $post_id ) {
        if ( get_post_type( $post_id ) === self::POST_TYPE ) {
            $this->clear_cache();
        }
    }
    
    /**
     * セレクトボックス用オプション生成
     *
     * @return array
     */
    public function get_select_options() {
        $links = $this->get_all_links();
        $options = array(
            array(
                'value' => '',
                'label' => '-- 選択してください --',
            ),
        );
        
        foreach ( $links as $link ) {
            $options[] = array(
                'value' => $link['id'],
                'label' => $link['name'],
            );
        }
        
        return $options;
    }
    
    /**
     * ThirstyAffiliate未インストール時のメッセージ
     *
     * @return string
     */
    public function get_not_installed_message() {
        if ( $this->is_thirsty_active() ) {
            return '';
        }
        
        return sprintf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            __( 'ThirstyAffiliatesプラグインがインストールされていません。アフィリエイトリンクの自動取得機能を使用するには、ThirstyAffiliatesをインストール・有効化してください。', 'soico-securities-cta' )
        );
    }
}
