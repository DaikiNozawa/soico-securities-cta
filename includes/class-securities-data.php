<?php
/**
 * 証券会社データ管理クラス
 *
 * @package Soico_Securities_CTA
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 証券会社データの取得・管理を行うクラス
 */
class Soico_CTA_Securities_Data {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
    /**
     * キャッシュキー
     */
    const CACHE_KEY = 'soico_cta_securities_cache';
    
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
        // データ更新時にキャッシュクリア
        add_action( 'update_option_soico_cta_securities_data', array( $this, 'clear_cache' ) );
    }
    
    /**
     * 全証券会社データ取得
     *
     * @param bool $use_cache キャッシュを使用するか
     * @return array
     */
    public function get_all_securities( $use_cache = true ) {
        $this->debug_log( 'get_all_securities called', array( 'use_cache' => $use_cache ) );

        // キャッシュチェック
        if ( $use_cache ) {
            $cached = get_transient( self::CACHE_KEY );
            if ( false !== $cached ) {
                $this->debug_log( 'Returning cached data', array( 'count' => count( $cached ) ) );
                return $cached;
            }
        }

        // データ取得
        $securities = get_option( 'soico_cta_securities_data', array() );
        $this->debug_log( 'Raw data from option', array(
            'count' => count( $securities ),
            'slugs' => array_keys( $securities ),
        ) );

        // ThirstyAffiliateリンクを解決
        $securities = $this->resolve_thirsty_links( $securities );

        // 優先順位でソート
        uasort( $securities, function( $a, $b ) {
            return ( $a['priority'] ?? 99 ) - ( $b['priority'] ?? 99 );
        } );

        // キャッシュ保存
        set_transient( self::CACHE_KEY, $securities, self::CACHE_EXPIRATION );
        $this->debug_log( 'Data cached', array( 'count' => count( $securities ) ) );

        return $securities;
    }
    
    /**
     * 有効な証券会社のみ取得
     *
     * @param int $limit 取得件数（0=無制限）
     * @return array
     */
    public function get_enabled_securities( $limit = 0 ) {
        $securities = $this->get_all_securities();
        
        // 有効なもののみフィルタ
        $enabled = array_filter( $securities, function( $item ) {
            return ! empty( $item['enabled'] );
        } );
        
        // 件数制限
        if ( $limit > 0 ) {
            $enabled = array_slice( $enabled, 0, $limit, true );
        }
        
        return $enabled;
    }
    
    /**
     * 単一の証券会社データ取得
     *
     * @param string $slug 証券会社スラッグ
     * @return array|null
     */
    public function get_security( $slug ) {
        $securities = $this->get_all_securities();
        return isset( $securities[ $slug ] ) ? $securities[ $slug ] : null;
    }
    
    /**
     * 優先順位1位の証券会社取得
     *
     * @return array|null
     */
    public function get_top_security() {
        $enabled = $this->get_enabled_securities( 1 );
        return ! empty( $enabled ) ? reset( $enabled ) : null;
    }
    
    /**
     * デバッグログ出力
     *
     * @param string $message
     * @param array $context
     */
    private function debug_log( $message, $context = array() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_message = '[SOICO CTA Data] ' . $message;
            if ( ! empty( $context ) ) {
                $log_message .= ' | ' . wp_json_encode( $context, JSON_UNESCAPED_UNICODE );
            }
            error_log( $log_message );
        }
    }

    /**
     * ThirstyAffiliateリンクを解決
     *
     * @param array $securities
     * @return array
     */
    private function resolve_thirsty_links( $securities ) {
        $thirsty = Soico_CTA_Thirsty_Integration::get_instance();

        $this->debug_log( 'resolve_thirsty_links called', array(
            'securities_count' => count( $securities ),
            'thirsty_active' => $thirsty->is_thirsty_active(),
        ) );

        foreach ( $securities as $slug => &$data ) {
            $this->debug_log( 'Processing security', array(
                'slug' => $slug,
                'thirsty_link' => $data['thirsty_link'] ?? 'not set',
                'direct_url' => $data['direct_url'] ?? 'not set',
                'enabled' => $data['enabled'] ?? false,
            ) );

            if ( ! empty( $data['thirsty_link'] ) ) {
                $url = $thirsty->get_affiliate_url( $data['thirsty_link'] );
                $this->debug_log( 'ThirstyAffiliate URL result', array(
                    'slug' => $slug,
                    'thirsty_link_id' => $data['thirsty_link'],
                    'resolved_url' => $url ? $url : 'false/null',
                ) );
                if ( $url ) {
                    $data['affiliate_url'] = $url;
                }
            }

            // フォールバック: 直接URLがある場合
            if ( empty( $data['affiliate_url'] ) && ! empty( $data['direct_url'] ) ) {
                $data['affiliate_url'] = $data['direct_url'];
                $this->debug_log( 'Using direct_url as fallback', array(
                    'slug' => $slug,
                    'direct_url' => $data['direct_url'],
                ) );
            }

            $this->debug_log( 'Final affiliate_url', array(
                'slug' => $slug,
                'affiliate_url' => $data['affiliate_url'] ?? 'not set',
            ) );
        }

        return $securities;
    }
    
    /**
     * 証券会社データ保存
     *
     * @param array $data
     * @return bool
     */
    public function save_securities( $data ) {
        $this->debug_log( 'save_securities called', array( 'data_count' => count( $data ) ) );

        // バリデーション
        $sanitized = $this->sanitize_securities_data( $data );

        $this->debug_log( 'Sanitized data', array(
            'slugs' => array_keys( $sanitized ),
            'count' => count( $sanitized ),
        ) );

        // 保存
        // Note: update_option() はデータに変更がない場合も false を返すため、
        // 実際にエラーかどうかを確認するために get_option で比較する
        $current = get_option( 'soico_cta_securities_data', array() );
        $result = update_option( 'soico_cta_securities_data', $sanitized );

        // データが同じ場合は成功とみなす
        if ( ! $result && $current === $sanitized ) {
            $this->debug_log( 'Data unchanged, treating as success' );
            $result = true;
        }

        $this->debug_log( 'Save result', array( 'result' => $result ) );

        // キャッシュクリア
        $this->clear_cache();

        return $result;
    }
    
    /**
     * 証券会社データのサニタイズ
     *
     * @param array $data
     * @return array
     */
    private function sanitize_securities_data( $data ) {
        $sanitized = array();

        foreach ( $data as $slug => $item ) {
            $slug = sanitize_key( $slug );

            // featuresを適切に配列に変換
            $features = $item['features'] ?? array();
            if ( is_string( $features ) ) {
                // 改行区切りの文字列を配列に変換
                $features = array_filter( array_map( 'trim', explode( "\n", $features ) ) );
            }
            $features = array_map( 'sanitize_text_field', (array) $features );

            $sanitized[ $slug ] = array(
                'name'         => sanitize_text_field( $item['name'] ?? '' ),
                'slug'         => $slug,
                'priority'     => absint( $item['priority'] ?? 99 ),
                'enabled'      => ! empty( $item['enabled'] ),
                'thirsty_link' => absint( $item['thirsty_link'] ?? 0 ),
                'direct_url'   => esc_url_raw( $item['direct_url'] ?? '' ),
                'features'     => $features,
                'commission'   => sanitize_text_field( $item['commission'] ?? '' ),
                'badge'        => sanitize_text_field( $item['badge'] ?? '' ),
                'badge_color'  => sanitize_hex_color( $item['badge_color'] ?? '' ),
                'button_text'  => sanitize_text_field( $item['button_text'] ?? '' ),
                'button_color' => sanitize_hex_color( $item['button_color'] ?? '#FF6B35' ),
            );
        }

        return $sanitized;
    }
    
    /**
     * 新しい証券会社を追加
     *
     * @param array $data
     * @return bool
     */
    public function add_security( $data ) {
        $securities = get_option( 'soico_cta_securities_data', array() );
        
        $slug = sanitize_key( $data['slug'] ?? '' );
        if ( empty( $slug ) ) {
            return false;
        }
        
        // 既存チェック
        if ( isset( $securities[ $slug ] ) ) {
            return false;
        }
        
        // 優先順位を最後に設定
        $max_priority = 0;
        foreach ( $securities as $item ) {
            $max_priority = max( $max_priority, $item['priority'] ?? 0 );
        }
        $data['priority'] = $max_priority + 1;
        
        $securities[ $slug ] = $data;
        
        return $this->save_securities( $securities );
    }
    
    /**
     * 証券会社を削除
     *
     * @param string $slug
     * @return bool
     */
    public function delete_security( $slug ) {
        $securities = get_option( 'soico_cta_securities_data', array() );
        
        if ( ! isset( $securities[ $slug ] ) ) {
            return false;
        }
        
        unset( $securities[ $slug ] );
        
        return $this->save_securities( $securities );
    }
    
    /**
     * キャッシュクリア
     */
    public function clear_cache() {
        delete_transient( self::CACHE_KEY );
        // ThirstyAffiliateのキャッシュもクリア
        $thirsty = Soico_CTA_Thirsty_Integration::get_instance();
        $thirsty->clear_cache();
        $this->debug_log( 'All caches cleared' );
    }
    
    /**
     * デザイン設定取得
     *
     * @return array
     */
    public function get_design_settings() {
        $defaults = array(
            'primary_color'   => '#FF6B35',
            'secondary_color' => '#1E88E5',
            'border_radius'   => 8,
        );
        
        $settings = get_option( 'soico_cta_design_settings', array() );
        
        return wp_parse_args( $settings, $defaults );
    }
    
    /**
     * トラッキング設定取得
     *
     * @return array
     */
    public function get_tracking_settings() {
        $defaults = array(
            'gtm_enabled'     => true,
            'event_category'  => 'CTA Click',
            'event_action'    => 'securities_affiliate',
        );
        
        $settings = get_option( 'soico_cta_tracking_settings', array() );
        
        return wp_parse_args( $settings, $defaults );
    }
    
    /**
     * トラッキング用データ属性を生成
     *
     * @param string $company_slug
     * @param string $cta_type
     * @return string
     */
    public function get_tracking_attributes( $company_slug, $cta_type = 'button' ) {
        $tracking = $this->get_tracking_settings();
        
        if ( empty( $tracking['gtm_enabled'] ) ) {
            return '';
        }
        
        $attrs = array(
            'data-gtm-category' => esc_attr( $tracking['event_category'] ),
            'data-gtm-action'   => esc_attr( $tracking['event_action'] ),
            'data-gtm-label'    => esc_attr( $company_slug ),
            'data-cta-type'     => esc_attr( $cta_type ),
        );
        
        $output = '';
        foreach ( $attrs as $key => $value ) {
            $output .= sprintf( ' %s="%s"', $key, $value );
        }
        
        return $output;
    }
    
    /**
     * ブロックエディタ用の証券会社セレクトオプション
     *
     * @return array
     */
    public function get_securities_select_options() {
        $securities = $this->get_enabled_securities();
        $options = array();
        
        foreach ( $securities as $slug => $data ) {
            $options[] = array(
                'value' => $slug,
                'label' => $data['name'],
            );
        }
        
        return $options;
    }
}
