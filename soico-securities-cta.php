<?php
/**
 * Plugin Name: SOICO Securities CTA
 * Plugin URI: https://www.soico.jp/
 * Description: 証券アフィリエイト用Gutenbergブロック（結論ボックス、インラインCTA、比較表など）- ThirstyAffiliate連携対応
 * Version: 1.1.0
 * Author: SOICO Inc.
 * Author URI: https://www.soico.jp/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: soico-securities-cta
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// プラグイン定数
define( 'SOICO_CTA_VERSION', '1.1.0' );
define( 'SOICO_CTA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SOICO_CTA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SOICO_CTA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * メインプラグインクラス
 */
final class Soico_Securities_CTA {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
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
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * 依存ファイル読み込み
     */
    private function load_dependencies() {
        require_once SOICO_CTA_PLUGIN_DIR . 'includes/class-securities-data.php';
        require_once SOICO_CTA_PLUGIN_DIR . 'includes/class-thirsty-integration.php';
        require_once SOICO_CTA_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once SOICO_CTA_PLUGIN_DIR . 'includes/class-block-register.php';
    }
    
    /**
     * フック初期化
     */
    private function init_hooks() {
        // プラグイン有効化・無効化
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // 初期化
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        // ブロックカテゴリ追加
        add_filter( 'block_categories_all', array( $this, 'add_block_category' ), 10, 2 );

        // フロントエンドアセット
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        // 管理画面リンク
        add_filter( 'plugin_action_links_' . SOICO_CTA_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );

        // テスト用ショートコード
        add_shortcode( 'soico_cta_test', array( $this, 'render_test_shortcode' ) );
    }
    
    /**
     * プラグイン初期化
     */
    public function init() {
        // 各コンポーネント初期化
        Soico_CTA_Securities_Data::get_instance();
        Soico_CTA_Thirsty_Integration::get_instance();
        Soico_CTA_Admin_Settings::get_instance();
        Soico_CTA_Block_Register::get_instance();
    }
    
    /**
     * テキストドメイン読み込み
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'soico-securities-cta',
            false,
            dirname( SOICO_CTA_PLUGIN_BASENAME ) . '/languages'
        );
    }
    
    /**
     * ブロックカテゴリ追加
     * WordPress 5.8+: block_categories_all フィルタ（第2引数は WP_Block_Editor_Context）
     *
     * @param array $categories 既存のカテゴリ配列
     * @param mixed $context WP_Block_Editor_Context または WP_Post（後方互換）
     * @return array
     */
    public function add_block_category( $categories, $context = null ) {
        // 新しいカテゴリを追加
        $custom_categories = array(
            array(
                'slug'  => 'soico-securities-cta',
                'title' => __( '証券CTA', 'soico-securities-cta' ),
                'icon'  => 'money-alt',
            ),
            array(
                'slug'  => 'soico-cardloan-cta',
                'title' => __( 'カードローンCTA', 'soico-securities-cta' ),
                'icon'  => 'money-alt',
            ),
        );

        // デバッグログ
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[SOICO CTA] add_block_category called' );
            error_log( '[SOICO CTA] Existing categories: ' . count( $categories ) );
            error_log( '[SOICO CTA] Adding custom categories: ' . count( $custom_categories ) );
        }

        // カテゴリを先頭に追加
        $result = array_merge( $custom_categories, $categories );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[SOICO CTA] Total categories after merge: ' . count( $result ) );
            $slugs = array_map( function( $cat ) { return $cat['slug']; }, array_slice( $result, 0, 10 ) );
            error_log( '[SOICO CTA] First 10 category slugs: ' . implode( ', ', $slugs ) );
        }

        return $result;
    }
    
    /**
     * フロントエンドアセット読み込み
     */
    public function enqueue_frontend_assets() {
        // 証券CTAブロック
        $has_securities_block = has_block( 'soico-cta/conclusion-box' ) ||
             has_block( 'soico-cta/inline-cta' ) ||
             has_block( 'soico-cta/single-button' ) ||
             has_block( 'soico-cta/comparison-table' ) ||
             has_block( 'soico-cta/subtle-banner' );

        // カードローンCTAブロック
        $has_cardloan_block = has_block( 'soico-cta/cardloan-conclusion-box' ) ||
             has_block( 'soico-cta/cardloan-inline-cta' ) ||
             has_block( 'soico-cta/cardloan-single-button' ) ||
             has_block( 'soico-cta/cardloan-comparison-table' ) ||
             has_block( 'soico-cta/cardloan-subtle-banner' );

        // いずれかのCTAブロックが存在する場合のみ読み込み
        if ( $has_securities_block || $has_cardloan_block ) {

            wp_enqueue_style(
                'soico-cta-frontend',
                SOICO_CTA_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                SOICO_CTA_VERSION
            );

            if ( file_exists( SOICO_CTA_PLUGIN_DIR . 'assets/js/frontend.js' ) ) {
                wp_enqueue_script(
                    'soico-cta-frontend',
                    SOICO_CTA_PLUGIN_URL . 'assets/js/frontend.js',
                    array(),
                    SOICO_CTA_VERSION,
                    true
                );
            }

            // CSS変数を出力
            $this->output_css_variables( $has_securities_block, $has_cardloan_block );
        }
    }
    
    /**
     * CSS変数出力
     */
    private function output_css_variables( $has_securities = true, $has_cardloan = false ) {
        $css = '';

        // 証券用CSS変数
        if ( $has_securities ) {
            $design = get_option( 'soico_cta_design_settings', array() );

            $primary_color = isset( $design['primary_color'] ) ? $design['primary_color'] : '#FF6B35';
            $secondary_color = isset( $design['secondary_color'] ) ? $design['secondary_color'] : '#1E88E5';
            $border_radius = isset( $design['border_radius'] ) ? intval( $design['border_radius'] ) : 8;

            $css .= sprintf(
                ':root {
                    --soico-cta-primary: %s;
                    --soico-cta-secondary: %s;
                    --soico-cta-border-radius: %dpx;
                    --soico-cta-gradient-start: #E8F4FC;
                    --soico-cta-gradient-end: #F0F8FF;
                }',
                esc_attr( $primary_color ),
                esc_attr( $secondary_color ),
                $border_radius
            );
        }

        // カードローン用CSS変数
        if ( $has_cardloan ) {
            $cardloan_design = get_option( 'soico_cta_cardloan_design_settings', array() );

            $cardloan_primary = isset( $cardloan_design['primary_color'] ) ? $cardloan_design['primary_color'] : '#00A95F';
            $cardloan_secondary = isset( $cardloan_design['secondary_color'] ) ? $cardloan_design['secondary_color'] : '#2E7D32';
            $cardloan_radius = isset( $cardloan_design['border_radius'] ) ? intval( $cardloan_design['border_radius'] ) : 8;

            $css .= sprintf(
                ':root {
                    --soico-cta-cardloan-primary: %s;
                    --soico-cta-cardloan-secondary: %s;
                    --soico-cta-cardloan-border-radius: %dpx;
                    --soico-cta-cardloan-gradient-start: #E8F8F0;
                    --soico-cta-cardloan-gradient-end: #F0FFF8;
                }',
                esc_attr( $cardloan_primary ),
                esc_attr( $cardloan_secondary ),
                $cardloan_radius
            );
        }

        if ( ! empty( $css ) ) {
            wp_add_inline_style( 'soico-cta-frontend', $css );
        }
    }
    
    /**
     * 設定リンク追加
     */
    public function add_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=soico-cta-settings' ),
            __( '設定', 'soico-securities-cta' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }
    
    /**
     * プラグイン有効化
     */
    public function activate() {
        // デフォルト証券会社データ
        $default_securities = array(
            'sbi' => array(
                'name'           => 'SBI証券',
                'slug'           => 'sbi',
                'priority'       => 1,
                'enabled'        => true,
                'thirsty_link'   => '',
                'features'       => array(
                    '口座開設数1,400万突破、ネット証券No.1の実績',
                    '国内株式の売買手数料が0円（ゼロ革命）',
                    'クレカ積立でVポイントが貯まる',
                ),
                'commission'     => '0円',
                'badge'          => 'おすすめ',
                'badge_color'    => '#E53935',
                'button_text'    => 'SBI証券で口座開設（無料）',
                'button_color'   => '#FF6B35',
            ),
            'monex' => array(
                'name'           => 'マネックス証券',
                'slug'           => 'monex',
                'priority'       => 2,
                'enabled'        => true,
                'thirsty_link'   => '',
                'features'       => array(
                    '米国株に強い / 為替手数料0円キャンペーン',
                    'dカード積立1.1%還元',
                ),
                'commission'     => '0円',
                'badge'          => '',
                'badge_color'    => '',
                'button_text'    => '詳細を見る',
                'button_color'   => '#666666',
            ),
            'rakuten' => array(
                'name'           => '楽天証券',
                'slug'           => 'rakuten',
                'priority'       => 3,
                'enabled'        => true,
                'thirsty_link'   => '',
                'features'       => array(
                    '楽天ポイント投資 / 楽天経済圏と連携',
                ),
                'commission'     => '0円',
                'badge'          => '',
                'badge_color'    => '',
                'button_text'    => '詳細を見る',
                'button_color'   => '#666666',
            ),
            'okasan' => array(
                'name'           => '岡三証券',
                'slug'           => 'okasan',
                'priority'       => 4,
                'enabled'        => true,
                'thirsty_link'   => '',
                'features'       => array(
                    '定額プランで200万円まで手数料0円',
                ),
                'commission'     => '0円',
                'badge'          => '',
                'badge_color'    => '',
                'button_text'    => '詳細を見る',
                'button_color'   => '#666666',
            ),
        );
        
        // デフォルトデザイン設定
        $default_design = array(
            'primary_color'   => '#FF6B35',
            'secondary_color' => '#1E88E5',
            'border_radius'   => 8,
        );

        // デフォルトトラッキング設定
        $default_tracking = array(
            'gtm_enabled'     => true,
            'event_category'  => 'CTA Click',
            'event_action'    => 'securities_affiliate',
        );

        // ==========================================================================
        // カードローンデフォルトデータ
        // ==========================================================================
        $default_cardloans = array(
            'aiful' => array(
                'name'          => 'アイフル',
                'slug'          => 'aiful',
                'priority'      => 1,
                'enabled'       => true,
                'thirsty_link'  => '',
                'direct_url'    => '',
                'features'      => array(
                    '最短25分で融資可能',
                    'WEB完結で来店不要',
                    '初めての方は30日間利息0円',
                ),
                'interest_rate' => '3.0%〜18.0%',
                'limit_amount'  => '800万円',
                'review_time'   => '最短25分',
                'badge'         => '人気No.1',
                'badge_color'   => '#E53935',
                'button_text'   => '今すぐ申し込む',
                'button_color'  => '#00A95F',
            ),
            'promise' => array(
                'name'          => 'プロミス',
                'slug'          => 'promise',
                'priority'      => 2,
                'enabled'       => true,
                'thirsty_link'  => '',
                'direct_url'    => '',
                'features'      => array(
                    '最短3分で融資可能',
                    '初回30日間無利息',
                ),
                'interest_rate' => '4.5%〜17.8%',
                'limit_amount'  => '500万円',
                'review_time'   => '最短3分',
                'badge'         => '',
                'badge_color'   => '',
                'button_text'   => '詳細を見る',
                'button_color'  => '#00A95F',
            ),
            'acom' => array(
                'name'          => 'アコム',
                'slug'          => 'acom',
                'priority'      => 3,
                'enabled'       => true,
                'thirsty_link'  => '',
                'direct_url'    => '',
                'features'      => array(
                    '最短20分で融資可能',
                    '初回30日間金利0円',
                ),
                'interest_rate' => '3.0%〜18.0%',
                'limit_amount'  => '800万円',
                'review_time'   => '最短20分',
                'badge'         => '',
                'badge_color'   => '',
                'button_text'   => '詳細を見る',
                'button_color'  => '#00A95F',
            ),
            'lake' => array(
                'name'          => 'レイクALSA',
                'slug'          => 'lake',
                'priority'      => 4,
                'enabled'       => true,
                'thirsty_link'  => '',
                'direct_url'    => '',
                'features'      => array(
                    '選べる無利息サービス',
                    'WEB完結対応',
                ),
                'interest_rate' => '4.5%〜18.0%',
                'limit_amount'  => '500万円',
                'review_time'   => '最短25分',
                'badge'         => '',
                'badge_color'   => '',
                'button_text'   => '詳細を見る',
                'button_color'  => '#00A95F',
            ),
            'mobit' => array(
                'name'          => 'SMBCモビット',
                'slug'          => 'mobit',
                'priority'      => 5,
                'enabled'       => true,
                'thirsty_link'  => '',
                'direct_url'    => '',
                'features'      => array(
                    'WEB完結で電話連絡なし',
                    'Tポイントが貯まる',
                ),
                'interest_rate' => '3.0%〜18.0%',
                'limit_amount'  => '800万円',
                'review_time'   => '最短30分',
                'badge'         => '',
                'badge_color'   => '',
                'button_text'   => '詳細を見る',
                'button_color'  => '#00A95F',
            ),
        );

        // カードローンデフォルトデザイン設定
        $default_cardloan_design = array(
            'primary_color'   => '#00A95F',
            'secondary_color' => '#2E7D32',
            'border_radius'   => 8,
        );

        // カードローンデフォルトトラッキング設定
        $default_cardloan_tracking = array(
            'gtm_enabled'     => true,
            'event_category'  => 'CTA Click',
            'event_action'    => 'cardloan_affiliate',
        );

        // ==========================================================================
        // オプション保存（既存がない場合のみ）
        // ==========================================================================

        // 証券
        if ( ! get_option( 'soico_cta_securities_data' ) ) {
            add_option( 'soico_cta_securities_data', $default_securities );
        }
        if ( ! get_option( 'soico_cta_design_settings' ) ) {
            add_option( 'soico_cta_design_settings', $default_design );
        }
        if ( ! get_option( 'soico_cta_tracking_settings' ) ) {
            add_option( 'soico_cta_tracking_settings', $default_tracking );
        }

        // カードローン
        if ( ! get_option( 'soico_cta_cardloan_data' ) ) {
            add_option( 'soico_cta_cardloan_data', $default_cardloans );
        }
        if ( ! get_option( 'soico_cta_cardloan_design_settings' ) ) {
            add_option( 'soico_cta_cardloan_design_settings', $default_cardloan_design );
        }
        if ( ! get_option( 'soico_cta_cardloan_tracking_settings' ) ) {
            add_option( 'soico_cta_cardloan_tracking_settings', $default_cardloan_tracking );
        }

        // キャッシュクリア
        delete_transient( 'soico_cta_securities_cache' );
        delete_transient( 'soico_cta_cardloan_cache' );

        // リライトルール更新フラグ
        set_transient( 'soico_cta_flush_rewrite', true, 60 );
    }
    
    /**
     * プラグイン無効化
     */
    public function deactivate() {
        // キャッシュクリア
        delete_transient( 'soico_cta_securities_cache' );
        delete_transient( 'soico_cta_cardloan_cache' );
        delete_transient( 'soico_cta_thirsty_links_cache' );
    }

    /**
     * テスト用ショートコード
     *
     * @param array $atts ショートコード属性
     * @return string
     */
    public function render_test_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'type'    => 'conclusion-box',
            'company' => 'sbi',
            'limit'   => 3,
        ), $atts, 'soico_cta_test' );

        // CSSを読み込み
        wp_enqueue_style(
            'soico-cta-frontend',
            SOICO_CTA_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            SOICO_CTA_VERSION
        );

        $block_register = Soico_CTA_Block_Register::get_instance();

        $output = '';

        switch ( $atts['type'] ) {
            case 'conclusion-box':
                $output = $block_register->render_conclusion_box( array(
                    'company'      => sanitize_key( $atts['company'] ),
                    'showFeatures' => true,
                    'customTitle'  => '',
                ) );
                break;

            case 'inline-cta':
                $output = $block_register->render_inline_cta( array(
                    'company' => sanitize_key( $atts['company'] ),
                    'style'   => 'default',
                ) );
                break;

            case 'single-button':
                $output = $block_register->render_single_button( array(
                    'company'    => sanitize_key( $atts['company'] ),
                    'buttonText' => '',
                    'showPR'     => true,
                ) );
                break;

            case 'comparison-table':
                $output = $block_register->render_comparison_table( array(
                    'limit'          => absint( $atts['limit'] ),
                    'showCommission' => true,
                ) );
                break;

            case 'subtle-banner':
                $output = $block_register->render_subtle_banner( array(
                    'company' => sanitize_key( $atts['company'] ),
                    'message' => '',
                ) );
                break;

            // カードローンブロック
            case 'cardloan-conclusion-box':
                $output = $block_register->render_cardloan_conclusion_box( array(
                    'company'      => sanitize_key( $atts['company'] ),
                    'showFeatures' => true,
                    'customTitle'  => '',
                ) );
                break;

            case 'cardloan-inline-cta':
                $output = $block_register->render_cardloan_inline_cta( array(
                    'company' => sanitize_key( $atts['company'] ),
                    'style'   => 'default',
                ) );
                break;

            case 'cardloan-single-button':
                $output = $block_register->render_cardloan_single_button( array(
                    'company'    => sanitize_key( $atts['company'] ),
                    'buttonText' => '',
                    'showPR'     => true,
                ) );
                break;

            case 'cardloan-comparison-table':
                $output = $block_register->render_cardloan_comparison_table( array(
                    'limit'            => absint( $atts['limit'] ),
                    'showInterestRate' => true,
                    'showLimitAmount'  => true,
                    'showReviewTime'   => true,
                ) );
                break;

            case 'cardloan-subtle-banner':
                $output = $block_register->render_cardloan_subtle_banner( array(
                    'company' => sanitize_key( $atts['company'] ),
                    'message' => '',
                ) );
                break;

            default:
                $output = '<!-- [SOICO CTA Test] Unknown type: ' . esc_attr( $atts['type'] ) . ' -->';
        }

        // デバッグ情報を追加（WP_DEBUG時のみ）
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $debug_info = sprintf(
                '<!-- [SOICO CTA Test Shortcode] type=%s, company=%s, output_length=%d -->',
                esc_attr( $atts['type'] ),
                esc_attr( $atts['company'] ),
                strlen( $output )
            );
            $output = $debug_info . $output;
        }

        return $output;
    }
}

/**
 * プラグイン初期化
 */
function soico_securities_cta() {
    return Soico_Securities_CTA::get_instance();
}

// プラグイン起動
soico_securities_cta();
