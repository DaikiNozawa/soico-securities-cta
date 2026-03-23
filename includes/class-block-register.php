<?php
/**
 * Gutenbergブロック登録クラス
 *
 * @package Soico_Securities_CTA
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gutenbergブロックの登録を行うクラス
 */
class Soico_CTA_Block_Register {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
    /**
     * 登録するブロック一覧（証券）
     */
    private $blocks = array(
        'conclusion-box',
        'inline-cta',
        'single-button',
        'comparison-table',
        'subtle-banner',
    );

    /**
     * 登録するブロック一覧（カードローン）
     */
    private $cardloan_blocks = array(
        'cardloan-conclusion-box',
        'cardloan-inline-cta',
        'cardloan-single-button',
        'cardloan-comparison-table',
        'cardloan-subtle-banner',
    );

    /**
     * 登録するブロック一覧（仮想通貨）
     */
    private $crypto_blocks = array(
        'crypto-conclusion-box',
        'crypto-inline-cta',
        'crypto-single-button',
        'crypto-comparison-table',
        'crypto-subtle-banner',
    );
    
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
        // Note: このクラスは init フック内でインスタンス化されるため、
        // add_action('init', ...) では間に合わない。
        // did_action('init') をチェックして、既に init が実行済みなら直接呼び出す。
        if ( did_action( 'init' ) ) {
            $this->register_blocks();
        } else {
            add_action( 'init', array( $this, 'register_blocks' ) );
        }

        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );

        // フロントエンドでのブロック解析をデバッグ
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! is_admin() ) {
            add_filter( 'the_content', array( $this, 'debug_content_blocks' ), 5 );
        }

        // 管理画面でのデバッグ用メタボックス
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            add_action( 'add_meta_boxes', array( $this, 'add_debug_meta_box' ) );
        }
    }

    /**
     * コンテンツ内のブロックをデバッグ出力
     */
    public function debug_content_blocks( $content ) {
        // SOICO CTAブロックを検索
        if ( preg_match_all( '/<!-- wp:soico-cta\/([a-z-]+)/', $content, $matches ) ) {
            $this->debug_log( 'Found SOICO CTA blocks in content', array(
                'blocks' => $matches[1],
                'count' => count( $matches[1] ),
            ) );
        } else {
            $this->debug_log( 'No SOICO CTA blocks found in content' );
        }

        return $content;
    }
    
    /**
     * ブロック登録
     *
     * Note: ブロックのメタデータ（title, icon, attributes等）はJavaScript側で定義。
     * PHP側ではrender_callbackのみを登録し、サーバーサイドレンダリングを担当。
     * block.jsonはWordPressのブロックディレクトリ等の参照用に残すが、登録には使用しない。
     */
    public function register_blocks() {
        $this->debug_log( 'register_blocks called' );

        // 証券ブロック登録
        foreach ( $this->blocks as $block ) {
            $this->register_block_php( $block );
        }

        // カードローンブロック登録
        foreach ( $this->cardloan_blocks as $block ) {
            $this->register_block_php( $block );
        }

        // 仮想通貨ブロック登録
        foreach ( $this->crypto_blocks as $block ) {
            $this->register_block_php( $block );
        }

        // 登録確認
        $registry = WP_Block_Type_Registry::get_instance();
        $registered = array();
        $all_blocks = array_merge( $this->blocks, $this->cardloan_blocks, $this->crypto_blocks );
        foreach ( $all_blocks as $block ) {
            $block_name = 'soico-cta/' . $block;
            $block_type = $registry->get_registered( $block_name );
            if ( $block_type ) {
                $registered[] = $block_name;
                $has_render = is_callable( $block_type->render_callback );
                $this->debug_log( 'Block registered', array(
                    'name' => $block_name,
                    'has_render_callback' => $has_render,
                ) );
            } else {
                $this->debug_log( 'Block NOT registered', array( 'name' => $block_name ) );
            }
        }
        $this->debug_log( 'Block registration complete', array( 'registered_count' => count( $registered ) ) );
    }
    
    /**
     * PHPでブロック登録
     */
    private function register_block_php( $block ) {
        $block_settings = $this->get_block_settings( $block );

        if ( $block_settings ) {
            $block_name = 'soico-cta/' . $block;
            $result = register_block_type( $block_name, $block_settings );

            if ( is_wp_error( $result ) ) {
                $this->debug_log( 'Block registration FAILED', array(
                    'name' => $block_name,
                    'error' => $result->get_error_message(),
                ) );
            } elseif ( $result === false ) {
                $this->debug_log( 'Block registration returned false', array(
                    'name' => $block_name,
                ) );
            } else {
                $this->debug_log( 'Block registration SUCCESS', array(
                    'name' => $block_name,
                    'result_type' => get_class( $result ),
                ) );
            }
        } else {
            $this->debug_log( 'Block settings not found', array( 'block' => $block ) );
        }
    }
    
    /**
     * ブロック設定取得
     */
    private function get_block_settings( $block ) {
        // Note: editor_script は設定しない
        // JavaScriptでunregister→registerを行い、edit関数を提供する
        // スクリプトは enqueue_block_editor_assets で別途読み込む
        $settings = array(
            'api_version'    => 3,
            'style'          => 'soico-cta-frontend',
            'supports'       => array(
                'html' => false,
            ),
        );

        switch ( $block ) {
            case 'conclusion-box':
                $settings['title'] = __( '結論ボックス', 'soico-securities-cta' );
                $settings['icon'] = 'megaphone';
                $settings['category'] = 'soico-securities-cta';
                $settings['description'] = __( '証券会社をおすすめする結論ボックス', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'company' => array(
                        'type'    => 'string',
                        'default' => 'sbi',
                    ),
                    'showFeatures' => array(
                        'type'    => 'boolean',
                        'default' => true,
                    ),
                    'customTitle' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_conclusion_box' );
                break;
                
            case 'inline-cta':
                $settings['title'] = __( 'インラインCTA', 'soico-securities-cta' );
                $settings['icon'] = 'migrate';
                $settings['category'] = 'soico-securities-cta';
                $settings['description'] = __( '記事中に挿入する控えめなインラインCTA', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'company' => array(
                        'type'    => 'string',
                        'default' => 'sbi',
                    ),
                    'style' => array(
                        'type'    => 'string',
                        'default' => 'default',
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_inline_cta' );
                break;
                
            case 'single-button':
                $settings['title'] = __( 'CTAボタン', 'soico-securities-cta' );
                $settings['icon'] = 'button';
                $settings['category'] = 'soico-securities-cta';
                $settings['description'] = __( 'シンプルなCTAボタン。PR表記付き', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'company' => array(
                        'type'    => 'string',
                        'default' => 'sbi',
                    ),
                    'buttonText' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                    'showPR' => array(
                        'type'    => 'boolean',
                        'default' => true,
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_single_button' );
                break;
                
            case 'comparison-table':
                $settings['title'] = __( '比較表', 'soico-securities-cta' );
                $settings['icon'] = 'editor-table';
                $settings['category'] = 'soico-securities-cta';
                $settings['description'] = __( '複数の証券会社を比較する表', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'companies' => array(
                        'type'    => 'array',
                        'default' => array( 'sbi', 'monex', 'rakuten' ),
                    ),
                    'limit' => array(
                        'type'    => 'number',
                        'default' => 3,
                    ),
                    'showCommission' => array(
                        'type'    => 'boolean',
                        'default' => true,
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_comparison_table' );
                break;
                
            case 'subtle-banner':
                $settings['title'] = __( '控えめバナー', 'soico-securities-cta' );
                $settings['icon'] = 'info-outline';
                $settings['category'] = 'soico-securities-cta';
                $settings['description'] = __( '控えめなテキストリンクバナー', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'company' => array(
                        'type'    => 'string',
                        'default' => 'sbi',
                    ),
                    'message' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_subtle_banner' );
                break;

            // ==========================================================================
            // カードローンブロック
            // ==========================================================================

            case 'cardloan-conclusion-box':
                $settings['title'] = __( 'カードローン結論ボックス', 'soico-securities-cta' );
                $settings['icon'] = 'money-alt';
                $settings['category'] = 'soico-cardloan-cta';
                $settings['description'] = __( 'カードローンをおすすめする結論ボックス', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'company' => array(
                        'type'    => 'string',
                        'default' => 'aiful',
                    ),
                    'showFeatures' => array(
                        'type'    => 'boolean',
                        'default' => true,
                    ),
                    'customTitle' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                    'customFeatures' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                    'buttonNote' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_cardloan_conclusion_box' );
                break;

            case 'cardloan-inline-cta':
                $settings['title'] = __( 'カードローンインラインCTA', 'soico-securities-cta' );
                $settings['icon'] = 'money-alt';
                $settings['category'] = 'soico-cardloan-cta';
                $settings['description'] = __( '記事中に挿入する控えめなカードローンCTA', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'company' => array(
                        'type'    => 'string',
                        'default' => 'aiful',
                    ),
                    'style' => array(
                        'type'    => 'string',
                        'default' => 'default',
                    ),
                    'featureText' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                    'buttonText' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_cardloan_inline_cta' );
                break;

            case 'cardloan-single-button':
                $settings['title'] = __( 'カードローンCTAボタン', 'soico-securities-cta' );
                $settings['icon'] = 'money-alt';
                $settings['category'] = 'soico-cardloan-cta';
                $settings['description'] = __( 'シンプルなカードローンCTAボタン', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'company' => array(
                        'type'    => 'string',
                        'default' => 'aiful',
                    ),
                    'buttonText' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                    'showPR' => array(
                        'type'    => 'boolean',
                        'default' => true,
                    ),
                    'buttonNote' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_cardloan_single_button' );
                break;

            case 'cardloan-comparison-table':
                $settings['title'] = __( 'カードローン比較表', 'soico-securities-cta' );
                $settings['icon'] = 'money-alt';
                $settings['category'] = 'soico-cardloan-cta';
                $settings['description'] = __( '複数のカードローンを比較する表', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'companies' => array(
                        'type'    => 'array',
                        'default' => array( 'aiful', 'promise', 'acom' ),
                    ),
                    'limit' => array(
                        'type'    => 'number',
                        'default' => 3,
                    ),
                    'showInterestRate' => array(
                        'type'    => 'boolean',
                        'default' => true,
                    ),
                    'showLimitAmount' => array(
                        'type'    => 'boolean',
                        'default' => true,
                    ),
                    'showReviewTime' => array(
                        'type'    => 'boolean',
                        'default' => true,
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_cardloan_comparison_table' );
                break;

            case 'cardloan-subtle-banner':
                $settings['title'] = __( 'カードローン控えめバナー', 'soico-securities-cta' );
                $settings['icon'] = 'money-alt';
                $settings['category'] = 'soico-cardloan-cta';
                $settings['description'] = __( '控えめなカードローンテキストリンクバナー', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'company' => array(
                        'type'    => 'string',
                        'default' => 'aiful',
                    ),
                    'message' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_cardloan_subtle_banner' );
                break;

            // ==========================================================================
            // 仮想通貨ブロック
            // ==========================================================================

            case 'crypto-conclusion-box':
                $settings['title'] = __( '仮想通貨結論ボックス', 'soico-securities-cta' );
                $settings['icon'] = 'bitcoin';
                $settings['category'] = 'soico-crypto-cta';
                $settings['description'] = __( '仮想通貨取引所をおすすめする結論ボックス', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'exchange' => array(
                        'type'    => 'string',
                        'default' => 'gmo_coin',
                    ),
                    'showFeatures' => array(
                        'type'    => 'boolean',
                        'default' => true,
                    ),
                    'customTitle' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                    'customFeatures' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_crypto_conclusion_box' );
                break;

            case 'crypto-inline-cta':
                $settings['title'] = __( '仮想通貨インラインCTA', 'soico-securities-cta' );
                $settings['icon'] = 'bitcoin';
                $settings['category'] = 'soico-crypto-cta';
                $settings['description'] = __( '記事中に挿入する控えめな仮想通貨取引所CTA', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'exchange' => array(
                        'type'    => 'string',
                        'default' => 'gmo_coin',
                    ),
                    'style' => array(
                        'type'    => 'string',
                        'default' => 'default',
                    ),
                    'featureText' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_crypto_inline_cta' );
                break;

            case 'crypto-single-button':
                $settings['title'] = __( '仮想通貨CTAボタン', 'soico-securities-cta' );
                $settings['icon'] = 'bitcoin';
                $settings['category'] = 'soico-crypto-cta';
                $settings['description'] = __( 'シンプルな仮想通貨取引所CTAボタン', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'exchange' => array(
                        'type'    => 'string',
                        'default' => 'gmo_coin',
                    ),
                    'buttonText' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                    'showPR' => array(
                        'type'    => 'boolean',
                        'default' => true,
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_crypto_single_button' );
                break;

            case 'crypto-comparison-table':
                $settings['title'] = __( '仮想通貨比較表', 'soico-securities-cta' );
                $settings['icon'] = 'bitcoin';
                $settings['category'] = 'soico-crypto-cta';
                $settings['description'] = __( '複数の仮想通貨取引所を比較する表', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'exchanges' => array(
                        'type'    => 'array',
                        'default' => array( 'gmo_coin', 'coincheck', 'sbi_vc' ),
                    ),
                    'limit' => array(
                        'type'    => 'number',
                        'default' => 3,
                    ),
                    'showFees' => array(
                        'type'    => 'boolean',
                        'default' => true,
                    ),
                    'showCoins' => array(
                        'type'    => 'boolean',
                        'default' => true,
                    ),
                    'showFeatures' => array(
                        'type'    => 'boolean',
                        'default' => true,
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_crypto_comparison_table' );
                break;

            case 'crypto-subtle-banner':
                $settings['title'] = __( '仮想通貨控えめバナー', 'soico-securities-cta' );
                $settings['icon'] = 'bitcoin';
                $settings['category'] = 'soico-crypto-cta';
                $settings['description'] = __( '控えめな仮想通貨取引所テキストリンクバナー', 'soico-securities-cta' );
                $settings['attributes'] = array(
                    'exchange' => array(
                        'type'    => 'string',
                        'default' => 'gmo_coin',
                    ),
                    'message' => array(
                        'type'    => 'string',
                        'default' => '',
                    ),
                );
                $settings['render_callback'] = array( $this, 'render_crypto_subtle_banner' );
                break;

            default:
                return null;
        }
        
        return $settings;
    }
    
    /**
     * エディタアセット読み込み
     */
    public function enqueue_editor_assets() {
        $securities_data = Soico_CTA_Securities_Data::get_instance();
        $thirsty = Soico_CTA_Thirsty_Integration::get_instance();

        // エディタスクリプト
        wp_enqueue_script(
            'soico-cta-editor',
            SOICO_CTA_PLUGIN_URL . 'assets/js/editor.js',
            array(
                'wp-blocks',
                'wp-element',
                'wp-editor',
                'wp-components',
                'wp-i18n',
                'wp-block-editor',
                'wp-hooks', // addFilter を使用するため必要
            ),
            SOICO_CTA_VERSION,
            true
        );

        // エディタスタイル
        wp_enqueue_style(
            'soico-cta-editor-style',
            SOICO_CTA_PLUGIN_URL . 'assets/css/editor.css',
            array( 'wp-edit-blocks' ),
            SOICO_CTA_VERSION
        );

        // JavaScriptに渡すデータ
        wp_localize_script( 'soico-cta-editor', 'soicoCTAData', array(
            // 証券データ
            'securities'            => $securities_data->get_enabled_securities(),
            'selectOptions'         => $securities_data->get_securities_select_options(),
            'designSettings'        => $securities_data->get_design_settings(),
            // カードローンデータ
            'cardloans'             => $securities_data->get_enabled_cardloans(),
            'cardloanSelectOptions' => $securities_data->get_cardloan_select_options(),
            'cardloanDesignSettings'=> $securities_data->get_cardloan_design_settings(),
            // 仮想通貨データ
            'cryptos'               => $securities_data->get_enabled_cryptos(),
            'cryptoSelectOptions'   => $securities_data->get_crypto_select_options(),
            'cryptoDesignSettings'  => $securities_data->get_crypto_design_settings(),
            // 共通
            'thirstyActive'         => $thirsty->is_thirsty_active(),
            'nonce'                 => wp_create_nonce( 'soico_cta_nonce' ),
            'i18n'                  => array(
                // 証券
                'blockTitle'            => __( '証券CTA', 'soico-securities-cta' ),
                'conclusionBox'         => __( '結論ボックス', 'soico-securities-cta' ),
                'inlineCTA'             => __( 'インラインCTA', 'soico-securities-cta' ),
                'singleButton'          => __( 'CTAボタン', 'soico-securities-cta' ),
                'comparisonTable'       => __( '比較表', 'soico-securities-cta' ),
                'subtleBanner'          => __( '控えめバナー', 'soico-securities-cta' ),
                'selectCompany'         => __( '証券会社を選択', 'soico-securities-cta' ),
                'showFeatures'          => __( '特徴を表示', 'soico-securities-cta' ),
                'customTitle'           => __( 'カスタムタイトル', 'soico-securities-cta' ),
                'buttonText'            => __( 'ボタンテキスト', 'soico-securities-cta' ),
                'showPR'                => __( 'PR表記を表示', 'soico-securities-cta' ),
                'limit'                 => __( '表示件数', 'soico-securities-cta' ),
                'showCommission'        => __( '手数料を表示', 'soico-securities-cta' ),
                'message'               => __( 'メッセージ', 'soico-securities-cta' ),
                // カードローン
                'cardloanBlockTitle'    => __( 'カードローンCTA', 'soico-securities-cta' ),
                'cardloanConclusionBox' => __( 'カードローン結論ボックス', 'soico-securities-cta' ),
                'cardloanInlineCTA'     => __( 'カードローンインラインCTA', 'soico-securities-cta' ),
                'cardloanSingleButton'  => __( 'カードローンCTAボタン', 'soico-securities-cta' ),
                'cardloanComparisonTable' => __( 'カードローン比較表', 'soico-securities-cta' ),
                'cardloanSubtleBanner'  => __( 'カードローン控えめバナー', 'soico-securities-cta' ),
                'selectCardloan'        => __( 'カードローンを選択', 'soico-securities-cta' ),
                'showInterestRate'      => __( '金利を表示', 'soico-securities-cta' ),
                'showLimitAmount'       => __( '限度額を表示', 'soico-securities-cta' ),
                'showReviewTime'        => __( '審査時間を表示', 'soico-securities-cta' ),
            ),
        ) );
    }
    
    /**
     * デバッグログ出力
     */
    private function debug_log( $message, $context = array() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_message = '[SOICO CTA Block] ' . $message;
            if ( ! empty( $context ) ) {
                $log_message .= ' | ' . wp_json_encode( $context, JSON_UNESCAPED_UNICODE );
            }
            error_log( $log_message );
        }
    }

    /**
     * デバッグコメント生成（HTMLに埋め込み）
     */
    private function debug_comment( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            return '<!-- [SOICO CTA Debug] ' . esc_html( $message ) . ' -->';
        }
        return '';
    }

    /**
     * デバッグ用メタボックス追加
     */
    public function add_debug_meta_box() {
        $post_types = array( 'post', 'page' );
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'soico_cta_debug',
                '🔧 SOICO CTA デバッグ情報',
                array( $this, 'render_debug_meta_box' ),
                $post_type,
                'normal',
                'low'
            );
        }
    }

    /**
     * デバッグ用メタボックス描画
     */
    public function render_debug_meta_box( $post ) {
        $content = $post->post_content;

        // SOICO CTAブロックを検索
        preg_match_all( '/<!-- wp:soico-cta\/([a-z-]+)(\s+(\{.*?\}))?\s*(\/)?-->/', $content, $matches, PREG_SET_ORDER );

        echo '<div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';

        if ( empty( $matches ) ) {
            echo '<p style="color: #666;">⚠️ この投稿にはSOICO CTAブロックが含まれていません。</p>';
            echo '<p style="font-size: 12px; color: #999;">ブロックエディタで証券CTAブロックを挿入し、保存してください。</p>';
        } else {
            echo '<p style="color: green; margin-bottom: 10px;">✅ ' . count( $matches ) . '個のSOICO CTAブロックが見つかりました</p>';
            echo '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
            echo '<thead><tr style="background: #eee;"><th style="padding: 8px; text-align: left;">ブロック</th><th style="padding: 8px; text-align: left;">属性</th></tr></thead>';
            echo '<tbody>';
            foreach ( $matches as $match ) {
                $block_type = $match[1];
                $attrs_json = isset( $match[3] ) ? $match[3] : '{}';
                $is_self_closing = isset( $match[4] ) && $match[4] === '/';

                echo '<tr style="border-bottom: 1px solid #eee;">';
                echo '<td style="padding: 8px;"><code>soico-cta/' . esc_html( $block_type ) . '</code></td>';
                echo '<td style="padding: 8px;"><code style="font-size: 11px; word-break: break-all;">' . esc_html( $attrs_json ) . '</code></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        // 生のブロックコメントを表示（折りたたみ）
        echo '<details style="margin-top: 15px;">';
        echo '<summary style="cursor: pointer; color: #0073aa;">生のブロックコメントを表示</summary>';
        echo '<pre style="background: #fff; padding: 10px; margin-top: 10px; font-size: 11px; overflow: auto; max-height: 200px; border: 1px solid #ddd;">';

        // コンテンツからブロックコメント行のみを抽出
        preg_match_all( '/<!-- wp:[^>]+-->/', $content, $all_blocks );
        if ( ! empty( $all_blocks[0] ) ) {
            foreach ( $all_blocks[0] as $block_comment ) {
                if ( strpos( $block_comment, 'soico-cta' ) !== false ) {
                    echo '<span style="color: #0073aa; font-weight: bold;">' . esc_html( $block_comment ) . '</span>' . "\n";
                } else {
                    echo esc_html( $block_comment ) . "\n";
                }
            }
        } else {
            echo '（ブロックコメントなし - クラシックエディタまたはHTMLモード使用中）';
        }

        echo '</pre>';
        echo '</details>';

        echo '</div>';
    }

    /**
     * 結論ボックス描画
     */
    public function render_conclusion_box( $attributes ) {
        // 最初に必ずログを出力（問題特定用）
        error_log( '[SOICO CTA Block] render_conclusion_box CALLED - attributes: ' . wp_json_encode( $attributes ) );
        $this->debug_log( 'render_conclusion_box called', $attributes );

        $securities_data = Soico_CTA_Securities_Data::get_instance();
        $company_slug = $attributes['company'] ?? 'sbi';
        $security = $securities_data->get_security( $company_slug );

        $this->debug_log( 'Security data', array(
            'company_slug' => $company_slug,
            'security_found' => ! empty( $security ),
            'has_affiliate_url' => ! empty( $security['affiliate_url'] ?? '' ),
            'thirsty_link' => $security['thirsty_link'] ?? 'not set',
            'direct_url' => $security['direct_url'] ?? 'not set',
            'affiliate_url' => $security['affiliate_url'] ?? 'not set',
        ) );

        if ( ! $security ) {
            return $this->debug_comment( 'Security not found: ' . $company_slug );
        }

        if ( empty( $security['affiliate_url'] ) ) {
            return $this->debug_comment( 'No affiliate_url for: ' . $company_slug . ' (thirsty_link=' . ($security['thirsty_link'] ?? 'empty') . ', direct_url=' . ($security['direct_url'] ?? 'empty') . ')' );
        }
        
        $show_features = $attributes['showFeatures'] ?? true;
        $custom_title = $attributes['customTitle'] ?? '';
        $custom_features = $attributes['customFeatures'] ?? '';

        $title = $custom_title ? $custom_title : sprintf(
            __( '証券口座を開設するなら<span style="color: #E53935;">%s</span>がおすすめ', 'soico-securities-cta' ),
            esc_html( $security['name'] )
        );

        // カスタム特徴がある場合は使用、なければ証券会社データから取得
        $features = array();
        if ( ! empty( $custom_features ) ) {
            $features = array_filter( array_map( 'trim', explode( "\n", $custom_features ) ) );
        } elseif ( ! empty( $security['features'] ) ) {
            $features = (array) $security['features'];
        }

        $tracking_attrs = $securities_data->get_tracking_attributes( $company_slug, 'conclusion_box' );

        ob_start();
        ?>
        <div class="soico-cta-conclusion-box">
            <div class="soico-cta-conclusion-header">
                <span class="soico-cta-conclusion-label"><?php esc_html_e( '結論', 'soico-securities-cta' ); ?></span>
                <p class="soico-cta-conclusion-title"><?php echo wp_kses_post( $title ); ?></p>
            </div>

            <?php if ( $show_features && ! empty( $features ) ) : ?>
                <ul class="soico-cta-conclusion-features">
                    <?php foreach ( $features as $feature ) : ?>
                        <li><?php echo esc_html( $feature ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <div class="soico-cta-conclusion-action">
                <a href="<?php echo esc_url( $security['affiliate_url'] ); ?>" 
                   class="soico-cta-button soico-cta-button-primary"
                   style="background-color: <?php echo esc_attr( $security['button_color'] ?? '#FF6B35' ); ?>"
                   target="_blank" rel="noopener noreferrer sponsored"
                   <?php echo $tracking_attrs; ?>>
                    <?php echo esc_html( $security['button_text'] ?? $security['name'] . 'で口座開設（無料）' ); ?>
                </a>
                <p class="soico-cta-conclusion-note">
                    <?php esc_html_e( '※最短5分で申込完了 ※口座開設・維持費無料', 'soico-securities-cta' ); ?>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * インラインCTA描画
     */
    public function render_inline_cta( $attributes ) {
        $this->debug_log( 'render_inline_cta called', $attributes );

        $securities_data = Soico_CTA_Securities_Data::get_instance();
        $company_slug = $attributes['company'] ?? 'sbi';
        $security = $securities_data->get_security( $company_slug );

        $this->debug_log( 'inline_cta security data', array(
            'company_slug' => $company_slug,
            'security_found' => ! empty( $security ),
            'affiliate_url' => $security['affiliate_url'] ?? 'not set',
        ) );

        if ( ! $security ) {
            return $this->debug_comment( 'Security not found: ' . $company_slug );
        }

        if ( empty( $security['affiliate_url'] ) ) {
            return $this->debug_comment( 'No affiliate_url for inline_cta: ' . $company_slug );
        }
        
        $style = $attributes['style'] ?? 'default';
        $tracking_attrs = $securities_data->get_tracking_attributes( $company_slug, 'inline_cta' );

        // カスタム特徴テキストがある場合は使用、なければ証券会社データから取得
        $feature_text = ! empty( $attributes['featureText'] )
            ? $attributes['featureText']
            : ( ! empty( $security['features'] ) ? $security['features'][0] : '' );
        
        ob_start();
        ?>
        <div class="soico-cta-inline soico-cta-inline-<?php echo esc_attr( $style ); ?>">
            <div class="soico-cta-inline-content">
                <strong class="soico-cta-inline-name"><?php echo esc_html( $security['name'] ); ?></strong>
                <?php if ( $feature_text ) : ?>
                    <span class="soico-cta-inline-feature"><?php echo esc_html( $feature_text ); ?></span>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url( $security['affiliate_url'] ); ?>" 
               class="soico-cta-inline-button"
               style="background-color: <?php echo esc_attr( $security['button_color'] ?? '#FF6B35' ); ?>"
               target="_blank" rel="noopener noreferrer sponsored"
               <?php echo $tracking_attrs; ?>>
                <?php esc_html_e( '詳細を見る →', 'soico-securities-cta' ); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 単体ボタン描画
     */
    public function render_single_button( $attributes ) {
        $this->debug_log( 'render_single_button called', $attributes );

        $securities_data = Soico_CTA_Securities_Data::get_instance();
        $company_slug = $attributes['company'] ?? 'sbi';
        $security = $securities_data->get_security( $company_slug );

        $this->debug_log( 'single_button security data', array(
            'company_slug' => $company_slug,
            'security_found' => ! empty( $security ),
            'affiliate_url' => $security['affiliate_url'] ?? 'not set',
        ) );

        if ( ! $security ) {
            return $this->debug_comment( 'Security not found: ' . $company_slug );
        }

        if ( empty( $security['affiliate_url'] ) ) {
            return $this->debug_comment( 'No affiliate_url for single_button: ' . $company_slug );
        }
        
        // 空文字もフォールバック対象とする（?? は null のみ判定のため）
        $button_text = ! empty( $attributes['buttonText'] )
            ? $attributes['buttonText']
            : ( ! empty( $security['button_text'] )
                ? $security['button_text']
                : $security['name'] . 'の公式サイトを見る' );
        $show_pr = $attributes['showPR'] ?? true;
        $tracking_attrs = $securities_data->get_tracking_attributes( $company_slug, 'single_button' );
        
        ob_start();
        ?>
        <div class="soico-cta-single-button-wrapper">
            <a href="<?php echo esc_url( $security['affiliate_url'] ); ?>" 
               class="soico-cta-button soico-cta-button-primary"
               style="background-color: <?php echo esc_attr( $security['button_color'] ?? '#FF6B35' ); ?>"
               target="_blank" rel="noopener noreferrer sponsored"
               <?php echo $tracking_attrs; ?>>
                <?php echo esc_html( $button_text ); ?>
            </a>
            <?php if ( $show_pr ) : ?>
                <p class="soico-cta-pr-label">PR</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * 比較表描画
     */
    public function render_comparison_table( $attributes ) {
        $this->debug_log( 'render_comparison_table called', $attributes );

        $securities_data = Soico_CTA_Securities_Data::get_instance();
        $limit = $attributes['limit'] ?? 3;
        $show_commission = $attributes['showCommission'] ?? true;

        // デザイン設定から商材別注釈を取得
        $design_settings = get_option( 'soico_cta_design_settings', array() );
        $table_notes_by_company = isset( $design_settings['table_notes_by_company'] ) ? (array) $design_settings['table_notes_by_company'] : array();
        $table_notes_size = absint( $design_settings['table_notes_size'] ?? 11 );
        if ( $table_notes_size < 8 || $table_notes_size > 14 ) {
            $table_notes_size = 11;
        }

        // 全証券会社データを取得（商材名表示用）
        $all_securities = $securities_data->get_all_securities();

        $securities = $securities_data->get_enabled_securities( $limit );

        $this->debug_log( 'comparison_table data', array(
            'limit' => $limit,
            'securities_count' => count( $securities ),
            'securities_slugs' => array_keys( $securities ),
        ) );

        if ( empty( $securities ) ) {
            return $this->debug_comment( 'No enabled securities found for comparison_table' );
        }
        
        $rank = 1;
        ob_start();
        ?>
        <div class="soico-cta-comparison-wrapper">
            <table class="soico-cta-comparison-table">
                <thead>
                    <tr>
                        <th class="soico-cta-col-rank"><?php esc_html_e( '順位', 'soico-securities-cta' ); ?></th>
                        <th class="soico-cta-col-name"><?php esc_html_e( '証券会社', 'soico-securities-cta' ); ?></th>
                        <th class="soico-cta-col-features"><?php esc_html_e( '特徴', 'soico-securities-cta' ); ?></th>
                        <?php if ( $show_commission ) : ?>
                            <th class="soico-cta-col-commission"><?php esc_html_e( '手数料', 'soico-securities-cta' ); ?></th>
                        <?php endif; ?>
                        <th class="soico-cta-col-action"><?php esc_html_e( '口座開設', 'soico-securities-cta' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $securities as $slug => $security ) :
                        $tracking_attrs = $securities_data->get_tracking_attributes( $slug, 'comparison_table' );
                        // ランクに応じたクラスを設定（4位以降はデフォルト）
                        if ( $rank === 1 ) {
                            $rank_class = 'soico-cta-rank-gold';
                        } elseif ( $rank === 2 ) {
                            $rank_class = 'soico-cta-rank-silver';
                        } elseif ( $rank === 3 ) {
                            $rank_class = 'soico-cta-rank-bronze';
                        } else {
                            $rank_class = 'soico-cta-rank-default';
                        }
                    ?>
                        <tr class="<?php echo $rank === 1 ? 'soico-cta-row-highlight' : ''; ?>">
                            <td class="soico-cta-col-rank">
                                <span class="soico-cta-rank <?php echo esc_attr( $rank_class ); ?>"><?php echo esc_html( $rank ); ?></span>
                            </td>
                            <td class="soico-cta-col-name">
                                <?php if ( ! empty( $security['affiliate_url'] ) ) : ?>
                                    <a href="<?php echo esc_url( $security['affiliate_url'] ); ?>"
                                       class="soico-cta-name-link"
                                       target="_blank" rel="noopener noreferrer sponsored"
                                       <?php echo $tracking_attrs; ?>>
                                        <strong><?php echo esc_html( $security['name'] ); ?></strong>
                                    </a>
                                <?php else : ?>
                                    <strong><?php echo esc_html( $security['name'] ); ?></strong>
                                <?php endif; ?>
                                <?php if ( ! empty( $security['badge'] ) ) : ?>
                                    <span class="soico-cta-badge" style="background-color: <?php echo esc_attr( $security['badge_color'] ?? '#E53935' ); ?>">
                                        <?php echo esc_html( $security['badge'] ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="soico-cta-col-features">
                                <ul class="soico-cta-features-list">
                                    <?php foreach ( array_slice( (array) $security['features'], 0, 2 ) as $feature ) : ?>
                                        <li><?php echo esc_html( $feature ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <?php if ( $show_commission ) : ?>
                                <td class="soico-cta-col-commission">
                                    <span class="soico-cta-commission"><?php echo esc_html( $security['commission'] ?? '-' ); ?></span>
                                </td>
                            <?php endif; ?>
                            <td class="soico-cta-col-action">
                                <?php if ( ! empty( $security['affiliate_url'] ) ) : ?>
                                    <a href="<?php echo esc_url( $security['affiliate_url'] ); ?>" 
                                       class="soico-cta-table-button"
                                       style="background-color: <?php echo esc_attr( $security['button_color'] ?? '#666' ); ?>"
                                       target="_blank" rel="noopener noreferrer sponsored"
                                       <?php echo $tracking_attrs; ?>>
                                        <?php echo $rank === 1 ? esc_html__( '口座開設', 'soico-securities-cta' ) : esc_html__( '詳細を見る', 'soico-securities-cta' ); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php 
                        $rank++;
                    endforeach; ?>
                </tbody>
            </table>
            <p class="soico-cta-table-note">PR | <?php printf( esc_html__( '情報は%s時点', 'soico-securities-cta' ), date_i18n( 'Y年n月' ) ); ?></p>
            <?php if ( ! empty( $table_notes_by_company ) ) : ?>
            <div class="soico-cta-table-notes-by-company" style="font-size: <?php echo esc_attr( $table_notes_size ); ?>px;">
                <?php foreach ( $table_notes_by_company as $company_slug => $notes ) :
                    if ( empty( $notes ) ) continue;
                    $company_name = isset( $all_securities[ $company_slug ]['name'] ) ? $all_securities[ $company_slug ]['name'] : $company_slug;
                ?>
                <div class="soico-cta-company-notes" style="margin-bottom: 8px;">
                    <strong><?php echo esc_html( $company_name ); ?>注釈</strong>
                    <ul style="margin: 4px 0 0 1.2em; padding: 0; list-style: disc;">
                        <?php foreach ( $notes as $note ) : ?>
                        <li><?php echo wp_kses_post( $note ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 控えめバナー描画
     */
    public function render_subtle_banner( $attributes ) {
        $this->debug_log( 'render_subtle_banner called', $attributes );

        $securities_data = Soico_CTA_Securities_Data::get_instance();
        $company_slug = $attributes['company'] ?? 'sbi';
        $security = $securities_data->get_security( $company_slug );

        $this->debug_log( 'subtle_banner security data', array(
            'company_slug' => $company_slug,
            'security_found' => ! empty( $security ),
            'affiliate_url' => $security['affiliate_url'] ?? 'not set',
        ) );

        if ( ! $security ) {
            return $this->debug_comment( 'Security not found: ' . $company_slug );
        }

        if ( empty( $security['affiliate_url'] ) ) {
            return $this->debug_comment( 'No affiliate_url for subtle_banner: ' . $company_slug );
        }
        
        // 空文字もフォールバック対象とする（?? は null のみ判定のため）
        $custom_message = ! empty( $attributes['message'] ) ? $attributes['message'] : '';
        $tracking_attrs = $securities_data->get_tracking_attributes( $company_slug, 'subtle_banner' );

        // リンク生成
        $link_html = '<a href="' . esc_url( $security['affiliate_url'] ) . '" target="_blank" rel="noopener noreferrer sponsored"' . $tracking_attrs . '>' . esc_html( $security['name'] ) . '</a>';

        if ( $custom_message ) {
            // カスタムメッセージがある場合
            if ( strpos( $custom_message, $security['name'] ) !== false ) {
                // メッセージ内に証券会社名があればリンクに置換
                $message_html = str_replace( $security['name'], $link_html, $custom_message );
            } else {
                // メッセージ内に証券会社名がなければ末尾にリンクを追加
                $message_html = $custom_message . ' → ' . $link_html;
            }
        } else {
            // デフォルトメッセージ
            $message_html = sprintf(
                __( '💡 証券口座をお探しなら → %s（国内株手数料0円）', 'soico-securities-cta' ),
                $link_html
            );
        }

        ob_start();
        ?>
        <div class="soico-cta-subtle-banner">
            <span class="soico-cta-subtle-message">
                <?php echo wp_kses_post( $message_html ); ?>
            </span>
            <span class="soico-cta-subtle-pr">PR</span>
        </div>
        <?php
        return ob_get_clean();
    }

    // ==========================================================================
    // カードローンブロック描画
    // ==========================================================================

    /**
     * カードローン結論ボックス描画
     */
    public function render_cardloan_conclusion_box( $attributes ) {
        $this->debug_log( 'render_cardloan_conclusion_box called', $attributes );

        $data = Soico_CTA_Securities_Data::get_instance();
        $company_slug = $attributes['company'] ?? 'aiful';
        $cardloan = $data->get_cardloan( $company_slug );

        if ( ! $cardloan ) {
            return $this->debug_comment( 'Cardloan not found: ' . $company_slug );
        }

        // カスタムアフィリエイトURLがあれば上書き
        $custom_url = ! empty( $attributes['customAffiliateUrl'] ) ? $attributes['customAffiliateUrl'] : '';
        if ( $custom_url ) {
            $cardloan['affiliate_url'] = $custom_url;
        }

        if ( empty( $cardloan['affiliate_url'] ) ) {
            return $this->debug_comment( 'No affiliate_url for cardloan: ' . $company_slug );
        }

        $show_features = $attributes['showFeatures'] ?? true;
        $custom_title = $attributes['customTitle'] ?? '';
        $custom_features = $attributes['customFeatures'] ?? '';
        // ブロック属性で指定があればそれを使用、なければカードローンデータのbutton_noteを使用
        $button_note = ! empty( $attributes['buttonNote'] )
            ? $attributes['buttonNote']
            : ( $cardloan['button_note'] ?? '' );
        $button_note_size = absint( $cardloan['button_note_size'] ?? 11 );
        if ( $button_note_size < 8 || $button_note_size > 16 ) {
            $button_note_size = 11;
        }

        $title = $custom_title ? $custom_title : sprintf(
            __( 'カードローンなら<span style="color: #00A95F;">%s</span>がおすすめ', 'soico-securities-cta' ),
            esc_html( $cardloan['name'] )
        );

        // カスタム特徴がある場合は使用（カスタム特徴の場合は注釈なし）
        $features = array();
        $feature_annotations = array();
        if ( ! empty( $custom_features ) ) {
            $features = array_filter( array_map( 'trim', explode( "\n", $custom_features ) ) );
        } elseif ( ! empty( $cardloan['features'] ) ) {
            $features = (array) $cardloan['features'];
            $feature_annotations = isset( $cardloan['feature_annotations'] ) ? (array) $cardloan['feature_annotations'] : array();
        }

        $tracking_attrs = $data->get_cardloan_tracking_attributes( $company_slug, 'conclusion_box' );

        ob_start();
        ?>
        <div class="soico-cta-cardloan-conclusion-box">
            <div class="soico-cta-cardloan-conclusion-header">
                <span class="soico-cta-cardloan-conclusion-label"><?php esc_html_e( '結論', 'soico-securities-cta' ); ?></span>
                <p class="soico-cta-cardloan-conclusion-title"><?php echo wp_kses_post( $title ); ?></p>
            </div>

            <?php if ( $show_features && ! empty( $features ) ) : ?>
                <ul class="soico-cta-cardloan-conclusion-features">
                    <?php foreach ( $features as $index => $feature ) :
                        $annotation = isset( $feature_annotations[ $index ] ) ? trim( $feature_annotations[ $index ] ) : '';
                    ?>
                        <li>
                            <?php echo esc_html( $feature ); ?>
                            <?php if ( ! empty( $annotation ) ) : ?>
                                <span class="soico-cta-cardloan-feature-annotation"><?php echo wp_kses_post( $annotation ); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="soico-cta-cardloan-conclusion-action">
                <a href="<?php echo esc_url( $cardloan['affiliate_url'] ); ?>"
                   class="soico-cta-cardloan-button soico-cta-cardloan-button-primary"
                   style="background-color: <?php echo esc_attr( $cardloan['button_color'] ?? '#00A95F' ); ?>"
                   target="_blank" rel="noopener noreferrer sponsored"
                   <?php echo $tracking_attrs; ?>>
                    <?php echo esc_html( $cardloan['button_text'] ?? $cardloan['name'] . 'に申し込む' ); ?>
                </a>
                <?php if ( $button_note ) : ?>
                <p class="soico-cta-cardloan-button-note" style="font-size: <?php echo esc_attr( $button_note_size ); ?>px;"><?php echo wp_kses_post( $button_note ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * カードローンインラインCTA描画
     */
    public function render_cardloan_inline_cta( $attributes ) {
        $this->debug_log( 'render_cardloan_inline_cta called', $attributes );

        $data = Soico_CTA_Securities_Data::get_instance();
        $company_slug = $attributes['company'] ?? 'aiful';
        $cardloan = $data->get_cardloan( $company_slug );

        if ( ! $cardloan ) {
            return $this->debug_comment( 'Cardloan not found: ' . $company_slug );
        }

        // カスタムアフィリエイトURLがあれば上書き
        $custom_url = ! empty( $attributes['customAffiliateUrl'] ) ? $attributes['customAffiliateUrl'] : '';
        if ( $custom_url ) {
            $cardloan['affiliate_url'] = $custom_url;
        }

        if ( empty( $cardloan['affiliate_url'] ) ) {
            return $this->debug_comment( 'No affiliate_url for cardloan inline: ' . $company_slug );
        }

        $style = $attributes['style'] ?? 'default';
        $tracking_attrs = $data->get_cardloan_tracking_attributes( $company_slug, 'inline_cta' );

        $feature_text = ! empty( $attributes['featureText'] )
            ? $attributes['featureText']
            : ( ! empty( $cardloan['features'] ) ? $cardloan['features'][0] : '' );

        // ボタンテキスト（カスタム or デフォルト）
        $button_text = ! empty( $attributes['buttonText'] )
            ? $attributes['buttonText']
            : __( '詳細はこちら', 'soico-securities-cta' );

        ob_start();
        ?>
        <div class="soico-cta-cardloan-inline soico-cta-cardloan-inline-<?php echo esc_attr( $style ); ?>">
            <div class="soico-cta-cardloan-inline-content">
                <strong class="soico-cta-cardloan-inline-name"><?php echo esc_html( $cardloan['name'] ); ?></strong>
                <?php if ( $feature_text ) : ?>
                    <span class="soico-cta-cardloan-inline-feature"><?php echo esc_html( $feature_text ); ?></span>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url( $cardloan['affiliate_url'] ); ?>"
               class="soico-cta-cardloan-inline-button"
               style="background-color: <?php echo esc_attr( $cardloan['button_color'] ?? '#00A95F' ); ?>"
               target="_blank" rel="noopener noreferrer sponsored"
               <?php echo $tracking_attrs; ?>>
                <?php echo esc_html( $button_text ); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * カードローン単体ボタン描画
     */
    public function render_cardloan_single_button( $attributes ) {
        $this->debug_log( 'render_cardloan_single_button called', $attributes );

        $data = Soico_CTA_Securities_Data::get_instance();
        $company_slug = $attributes['company'] ?? 'aiful';
        $cardloan = $data->get_cardloan( $company_slug );

        if ( ! $cardloan ) {
            return $this->debug_comment( 'Cardloan not found: ' . $company_slug );
        }

        // カスタムアフィリエイトURLがあれば上書き
        $custom_url = ! empty( $attributes['customAffiliateUrl'] ) ? $attributes['customAffiliateUrl'] : '';
        if ( $custom_url ) {
            $cardloan['affiliate_url'] = $custom_url;
        }

        if ( empty( $cardloan['affiliate_url'] ) ) {
            return $this->debug_comment( 'No affiliate_url for cardloan button: ' . $company_slug );
        }

        $button_text = ! empty( $attributes['buttonText'] )
            ? $attributes['buttonText']
            : ( ! empty( $cardloan['button_text'] )
                ? $cardloan['button_text']
                : $cardloan['name'] . 'の公式サイトを見る' );
        $show_pr = $attributes['showPR'] ?? true;
        // ブロック属性で指定があればそれを使用、なければカードローンデータのbutton_noteを使用
        $button_note = ! empty( $attributes['buttonNote'] )
            ? $attributes['buttonNote']
            : ( $cardloan['button_note'] ?? '' );
        $button_note_size = absint( $cardloan['button_note_size'] ?? 11 );
        if ( $button_note_size < 8 || $button_note_size > 16 ) {
            $button_note_size = 11;
        }
        $tracking_attrs = $data->get_cardloan_tracking_attributes( $company_slug, 'single_button' );

        ob_start();
        ?>
        <div class="soico-cta-cardloan-single-button-wrapper">
            <a href="<?php echo esc_url( $cardloan['affiliate_url'] ); ?>"
               class="soico-cta-cardloan-button soico-cta-cardloan-button-primary"
               style="background-color: <?php echo esc_attr( $cardloan['button_color'] ?? '#00A95F' ); ?>"
               target="_blank" rel="noopener noreferrer sponsored"
               <?php echo $tracking_attrs; ?>>
                <?php echo esc_html( $button_text ); ?>
            </a>
            <?php if ( $button_note ) : ?>
                <p class="soico-cta-cardloan-button-note" style="font-size: <?php echo esc_attr( $button_note_size ); ?>px;"><?php echo wp_kses_post( $button_note ); ?></p>
            <?php endif; ?>
            <?php if ( $show_pr ) : ?>
                <p class="soico-cta-cardloan-pr-label">PR:<?php echo esc_html( $cardloan['name'] ); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * カードローン比較表描画
     */
    public function render_cardloan_comparison_table( $attributes ) {
        $this->debug_log( 'render_cardloan_comparison_table called', $attributes );

        $data = Soico_CTA_Securities_Data::get_instance();
        $limit = $attributes['limit'] ?? 3;
        $show_interest_rate = $attributes['showInterestRate'] ?? true;
        $show_limit_amount = $attributes['showLimitAmount'] ?? true;
        $show_review_time = $attributes['showReviewTime'] ?? true;

        // デザイン設定から商材別注釈を取得
        $design_settings = get_option( 'soico_cardloan_design_settings', array() );
        $table_notes_by_company = isset( $design_settings['table_notes_by_company'] ) ? (array) $design_settings['table_notes_by_company'] : array();
        $table_notes_size = absint( $design_settings['table_notes_size'] ?? 11 );
        if ( $table_notes_size < 8 || $table_notes_size > 14 ) {
            $table_notes_size = 11;
        }

        // 全カードローンデータを取得（商材名表示用）
        $all_cardloans = $data->get_all_cardloans();

        $cardloans = $data->get_enabled_cardloans( $limit );

        if ( empty( $cardloans ) ) {
            return $this->debug_comment( 'No enabled cardloans found for comparison_table' );
        }

        // カスタムアフィリエイトURLがあれば上書き
        $custom_urls = ! empty( $attributes['customAffiliateUrls'] ) ? (array) $attributes['customAffiliateUrls'] : array();
        foreach ( $custom_urls as $slug => $url ) {
            if ( ! empty( $url ) && isset( $cardloans[ $slug ] ) ) {
                $cardloans[ $slug ]['affiliate_url'] = $url;
            }
        }

        $rank = 1;
        ob_start();
        ?>
        <div class="soico-cta-cardloan-comparison-wrapper">
            <table class="soico-cta-cardloan-comparison-table">
                <thead>
                    <tr>
                        <th class="soico-cta-col-rank"><?php esc_html_e( 'No.', 'soico-securities-cta' ); ?></th>
                        <th class="soico-cta-col-name"><?php esc_html_e( 'カードローン', 'soico-securities-cta' ); ?></th>
                        <?php if ( $show_interest_rate ) : ?>
                            <th class="soico-cta-col-interest"><?php esc_html_e( '金利', 'soico-securities-cta' ); ?></th>
                        <?php endif; ?>
                        <?php if ( $show_limit_amount ) : ?>
                            <th class="soico-cta-col-limit"><?php esc_html_e( '限度額', 'soico-securities-cta' ); ?></th>
                        <?php endif; ?>
                        <?php if ( $show_review_time ) : ?>
                            <th class="soico-cta-col-review"><?php esc_html_e( '審査時間', 'soico-securities-cta' ); ?></th>
                        <?php endif; ?>
                        <th class="soico-cta-col-action"><?php esc_html_e( '申し込み', 'soico-securities-cta' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $cardloans as $slug => $cardloan ) :
                        $tracking_attrs = $data->get_cardloan_tracking_attributes( $slug, 'comparison_table' );
                    ?>
                        <tr>
                            <td class="soico-cta-col-rank">
                                <span class="soico-cta-cardloan-number"><?php echo esc_html( $rank ); ?></span>
                            </td>
                            <td class="soico-cta-col-name">
                                <strong><?php echo esc_html( $cardloan['name'] ); ?></strong>
                                <?php if ( ! empty( $cardloan['badge'] ) ) : ?>
                                    <span class="soico-cta-badge" style="background-color: <?php echo esc_attr( $cardloan['badge_color'] ?? '#00A95F' ); ?>">
                                        <?php echo esc_html( $cardloan['badge'] ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <?php if ( $show_interest_rate ) : ?>
                                <td class="soico-cta-col-interest">
                                    <span class="soico-cta-interest"><?php echo esc_html( $cardloan['interest_rate'] ?? '-' ); ?></span>
                                </td>
                            <?php endif; ?>
                            <?php if ( $show_limit_amount ) : ?>
                                <td class="soico-cta-col-limit">
                                    <span class="soico-cta-limit"><?php echo esc_html( $cardloan['limit_amount'] ?? '-' ); ?></span>
                                </td>
                            <?php endif; ?>
                            <?php if ( $show_review_time ) : ?>
                                <td class="soico-cta-col-review">
                                    <span class="soico-cta-review"><?php echo esc_html( $cardloan['review_time'] ?? '-' ); ?></span>
                                </td>
                            <?php endif; ?>
                            <td class="soico-cta-col-action">
                                <?php if ( ! empty( $cardloan['affiliate_url'] ) ) : ?>
                                    <a href="<?php echo esc_url( $cardloan['affiliate_url'] ); ?>"
                                       class="soico-cta-cardloan-table-button"
                                       style="background-color: <?php echo esc_attr( $cardloan['button_color'] ?? '#00A95F' ); ?>"
                                       target="_blank" rel="noopener noreferrer sponsored"
                                       <?php echo $tracking_attrs; ?>>
                                        <?php esc_html_e( '詳細はこちら', 'soico-securities-cta' ); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php
                        $rank++;
                    endforeach; ?>
                </tbody>
            </table>
            <p class="soico-cta-cardloan-table-note">PR | <?php printf( esc_html__( '情報は%s時点', 'soico-securities-cta' ), date_i18n( 'Y年n月' ) ); ?></p>
            <?php if ( ! empty( $table_notes_by_company ) ) : ?>
            <div class="soico-cta-cardloan-table-notes-by-company" style="font-size: <?php echo esc_attr( $table_notes_size ); ?>px;">
                <?php foreach ( $table_notes_by_company as $company_slug => $notes ) :
                    if ( empty( $notes ) ) continue;
                    $company_name = isset( $all_cardloans[ $company_slug ]['name'] ) ? $all_cardloans[ $company_slug ]['name'] : $company_slug;
                ?>
                <div class="soico-cta-cardloan-company-notes" style="margin-bottom: 8px;">
                    <strong><?php echo esc_html( $company_name ); ?>注釈</strong>
                    <ul style="margin: 4px 0 0 1.2em; padding: 0; list-style: disc;">
                        <?php foreach ( $notes as $note ) : ?>
                        <li><?php echo wp_kses_post( $note ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * カードローン控えめバナー描画
     */
    public function render_cardloan_subtle_banner( $attributes ) {
        $this->debug_log( 'render_cardloan_subtle_banner called', $attributes );

        $data = Soico_CTA_Securities_Data::get_instance();
        $company_slug = $attributes['company'] ?? 'aiful';
        $cardloan = $data->get_cardloan( $company_slug );

        if ( ! $cardloan ) {
            return $this->debug_comment( 'Cardloan not found: ' . $company_slug );
        }

        if ( empty( $cardloan['affiliate_url'] ) ) {
            return $this->debug_comment( 'No affiliate_url for cardloan banner: ' . $company_slug );
        }

        $custom_message = ! empty( $attributes['message'] ) ? $attributes['message'] : '';
        $tracking_attrs = $data->get_cardloan_tracking_attributes( $company_slug, 'subtle_banner' );

        // リンク生成
        $link_html = '<a href="' . esc_url( $cardloan['affiliate_url'] ) . '" target="_blank" rel="noopener noreferrer sponsored"' . $tracking_attrs . '>' . esc_html( $cardloan['name'] ) . '</a>';

        if ( $custom_message ) {
            if ( strpos( $custom_message, $cardloan['name'] ) !== false ) {
                $message_html = str_replace( $cardloan['name'], $link_html, $custom_message );
            } else {
                $message_html = $custom_message . ' → ' . $link_html;
            }
        } else {
            $message_html = sprintf(
                __( '💰 お金が必要なら → %s（最短即日融資）', 'soico-securities-cta' ),
                $link_html
            );
        }

        ob_start();
        ?>
        <div class="soico-cta-cardloan-subtle-banner">
            <span class="soico-cta-cardloan-subtle-message">
                <?php echo wp_kses_post( $message_html ); ?>
            </span>
            <span class="soico-cta-cardloan-subtle-pr">PR</span>
        </div>
        <?php
        return ob_get_clean();
    }

    // ==========================================================================
    // 仮想通貨ブロック レンダリング
    // ==========================================================================

    /**
     * 仮想通貨結論ボックス描画
     */
    public function render_crypto_conclusion_box( $attributes ) {
        $this->debug_log( 'render_crypto_conclusion_box called', $attributes );

        $data = Soico_CTA_Securities_Data::get_instance();
        $exchange_slug = $attributes['exchange'] ?? 'gmo_coin';
        $crypto = $data->get_crypto( $exchange_slug );

        if ( ! $crypto ) {
            return $this->debug_comment( 'Crypto exchange not found: ' . $exchange_slug );
        }

        if ( empty( $crypto['affiliate_url'] ) ) {
            return $this->debug_comment( 'No affiliate_url for crypto: ' . $exchange_slug );
        }

        $show_features = $attributes['showFeatures'] ?? true;
        $custom_title = $attributes['customTitle'] ?? '';
        $custom_features = $attributes['customFeatures'] ?? '';

        $title = $custom_title ? $custom_title : sprintf(
            __( '仮想通貨を始めるなら<span style="color: #F7931A;">%s</span>がおすすめ', 'soico-securities-cta' ),
            esc_html( $crypto['name'] )
        );

        // カスタム特徴がある場合は使用
        $features = array();
        if ( ! empty( $custom_features ) ) {
            $features = array_filter( array_map( 'trim', explode( "\n", $custom_features ) ) );
        } elseif ( ! empty( $crypto['features'] ) ) {
            $features = (array) $crypto['features'];
        }

        $tracking_attrs = $data->get_crypto_tracking_attributes( $exchange_slug, 'conclusion_box' );

        ob_start();
        ?>
        <div class="soico-cta-crypto-conclusion-box">
            <div class="soico-cta-crypto-conclusion-header">
                <span class="soico-cta-crypto-conclusion-label"><?php esc_html_e( '結論', 'soico-securities-cta' ); ?></span>
                <p class="soico-cta-crypto-conclusion-title"><?php echo wp_kses_post( $title ); ?></p>
            </div>

            <?php if ( $show_features && ! empty( $features ) ) : ?>
                <ul class="soico-cta-crypto-conclusion-features">
                    <?php foreach ( $features as $feature ) : ?>
                        <li><?php echo esc_html( $feature ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="soico-cta-crypto-conclusion-action">
                <a href="<?php echo esc_url( $crypto['affiliate_url'] ); ?>"
                   class="soico-cta-crypto-button soico-cta-crypto-button-primary"
                   style="background-color: <?php echo esc_attr( $crypto['button_color'] ?? '#F7931A' ); ?>"
                   target="_blank" rel="noopener noreferrer sponsored"
                   <?php echo $tracking_attrs; ?>>
                    <?php echo esc_html( $crypto['button_text'] ?? $crypto['name'] . 'で口座開設' ); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 仮想通貨インラインCTA描画
     */
    public function render_crypto_inline_cta( $attributes ) {
        $this->debug_log( 'render_crypto_inline_cta called', $attributes );

        $data = Soico_CTA_Securities_Data::get_instance();
        $exchange_slug = $attributes['exchange'] ?? 'gmo_coin';
        $crypto = $data->get_crypto( $exchange_slug );

        if ( ! $crypto ) {
            return $this->debug_comment( 'Crypto exchange not found: ' . $exchange_slug );
        }

        if ( empty( $crypto['affiliate_url'] ) ) {
            return $this->debug_comment( 'No affiliate_url for crypto inline: ' . $exchange_slug );
        }

        $style = $attributes['style'] ?? 'default';
        $tracking_attrs = $data->get_crypto_tracking_attributes( $exchange_slug, 'inline_cta' );

        $feature_text = ! empty( $attributes['featureText'] )
            ? $attributes['featureText']
            : ( ! empty( $crypto['features'] ) ? $crypto['features'][0] : '' );

        ob_start();
        ?>
        <div class="soico-cta-crypto-inline soico-cta-crypto-inline-<?php echo esc_attr( $style ); ?>">
            <div class="soico-cta-crypto-inline-content">
                <strong class="soico-cta-crypto-inline-name"><?php echo esc_html( $crypto['name'] ); ?></strong>
                <?php if ( $feature_text ) : ?>
                    <span class="soico-cta-crypto-inline-feature"><?php echo esc_html( $feature_text ); ?></span>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url( $crypto['affiliate_url'] ); ?>"
               class="soico-cta-crypto-inline-button"
               style="background-color: <?php echo esc_attr( $crypto['button_color'] ?? '#F7931A' ); ?>"
               target="_blank" rel="noopener noreferrer sponsored"
               <?php echo $tracking_attrs; ?>>
                <?php esc_html_e( '詳細を見る →', 'soico-securities-cta' ); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 仮想通貨単体ボタン描画
     */
    public function render_crypto_single_button( $attributes ) {
        $this->debug_log( 'render_crypto_single_button called', $attributes );

        $data = Soico_CTA_Securities_Data::get_instance();
        $exchange_slug = $attributes['exchange'] ?? 'gmo_coin';
        $crypto = $data->get_crypto( $exchange_slug );

        if ( ! $crypto ) {
            return $this->debug_comment( 'Crypto exchange not found: ' . $exchange_slug );
        }

        if ( empty( $crypto['affiliate_url'] ) ) {
            return $this->debug_comment( 'No affiliate_url for crypto button: ' . $exchange_slug );
        }

        $button_text = ! empty( $attributes['buttonText'] )
            ? $attributes['buttonText']
            : ( ! empty( $crypto['button_text'] )
                ? $crypto['button_text']
                : $crypto['name'] . 'の公式サイトを見る' );
        $show_pr = $attributes['showPR'] ?? true;
        $tracking_attrs = $data->get_crypto_tracking_attributes( $exchange_slug, 'single_button' );

        ob_start();
        ?>
        <div class="soico-cta-crypto-single-button-wrapper">
            <a href="<?php echo esc_url( $crypto['affiliate_url'] ); ?>"
               class="soico-cta-crypto-button soico-cta-crypto-button-primary"
               style="background-color: <?php echo esc_attr( $crypto['button_color'] ?? '#F7931A' ); ?>"
               target="_blank" rel="noopener noreferrer sponsored"
               <?php echo $tracking_attrs; ?>>
                <?php echo esc_html( $button_text ); ?>
            </a>
            <?php if ( $show_pr ) : ?>
                <p class="soico-cta-crypto-pr-label">PR</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 仮想通貨比較表描画
     */
    public function render_crypto_comparison_table( $attributes ) {
        $this->debug_log( 'render_crypto_comparison_table called', $attributes );

        $data = Soico_CTA_Securities_Data::get_instance();
        $limit = $attributes['limit'] ?? 3;
        $show_fees = $attributes['showFees'] ?? true;
        $show_coins = $attributes['showCoins'] ?? true;

        // デザイン設定から商材別注釈を取得
        $design_settings = get_option( 'soico_crypto_design_settings', array() );
        $table_notes_by_company = isset( $design_settings['table_notes_by_company'] ) ? (array) $design_settings['table_notes_by_company'] : array();
        $table_notes_size = absint( $design_settings['table_notes_size'] ?? 11 );
        if ( $table_notes_size < 8 || $table_notes_size > 14 ) {
            $table_notes_size = 11;
        }

        // 全取引所データを取得（商材名表示用）
        $all_cryptos = $data->get_all_cryptos();

        $cryptos = $data->get_enabled_cryptos( $limit );

        if ( empty( $cryptos ) ) {
            return $this->debug_comment( 'No enabled crypto exchanges found for comparison_table' );
        }

        $rank = 1;
        ob_start();
        ?>
        <div class="soico-cta-crypto-comparison-wrapper">
            <table class="soico-cta-crypto-comparison-table">
                <colgroup>
                    <col class="soico-cta-col-rank-w">
                    <col class="soico-cta-col-name-w">
                    <?php if ( $show_fees ) : ?><col class="soico-cta-col-fee-w"><?php endif; ?>
                    <?php if ( $show_coins ) : ?><col class="soico-cta-col-coins-w"><?php endif; ?>
                    <col class="soico-cta-col-action-w">
                </colgroup>
                <thead>
                    <tr>
                        <th class="soico-cta-col-rank"><?php esc_html_e( '順位', 'soico-securities-cta' ); ?></th>
                        <th class="soico-cta-col-name"><?php esc_html_e( '取引所', 'soico-securities-cta' ); ?></th>
                        <?php if ( $show_fees ) : ?>
                            <th class="soico-cta-col-fee"><?php esc_html_e( '手数料', 'soico-securities-cta' ); ?></th>
                        <?php endif; ?>
                        <?php if ( $show_coins ) : ?>
                            <th class="soico-cta-col-coins"><?php esc_html_e( '通貨数', 'soico-securities-cta' ); ?></th>
                        <?php endif; ?>
                        <th class="soico-cta-col-action"><?php esc_html_e( '口座開設', 'soico-securities-cta' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $cryptos as $slug => $crypto ) :
                        if ( empty( $crypto['affiliate_url'] ) ) continue;

                        $rank_class = '';
                        switch ( $rank ) {
                            case 1: $rank_class = 'soico-cta-rank-gold'; break;
                            case 2: $rank_class = 'soico-cta-rank-silver'; break;
                            case 3: $rank_class = 'soico-cta-rank-bronze'; break;
                            default: $rank_class = 'soico-cta-rank-default'; break;
                        }

                        $row_class = $rank === 1 ? 'soico-cta-row-highlight' : '';
                        $tracking_attrs = $data->get_crypto_tracking_attributes( $slug, 'comparison_table' );
                    ?>
                    <tr class="<?php echo esc_attr( $row_class ); ?>">
                        <td class="soico-cta-col-rank">
                            <span class="soico-cta-rank <?php echo esc_attr( $rank_class ); ?>"><?php echo esc_html( $rank ); ?></span>
                        </td>
                        <td class="soico-cta-col-name">
                            <?php if ( ! empty( $crypto['affiliate_url'] ) ) : ?>
                                <a href="<?php echo esc_url( $crypto['affiliate_url'] ); ?>"
                                   class="soico-cta-crypto-name-link"
                                   target="_blank" rel="noopener noreferrer sponsored"
                                   <?php echo $tracking_attrs; ?>>
                                    <strong><?php echo esc_html( $crypto['name'] ); ?></strong>
                                </a>
                            <?php else : ?>
                                <strong><?php echo esc_html( $crypto['name'] ); ?></strong>
                            <?php endif; ?>
                            <?php if ( ! empty( $crypto['badge'] ) ) : ?>
                                <span class="soico-cta-badge" style="background: <?php echo esc_attr( $crypto['badge_color'] ?? '#1A5276' ); ?>"><?php echo esc_html( $crypto['badge'] ); ?></span>
                            <?php endif; ?>
                        </td>
                        <?php if ( $show_fees ) : ?>
                            <td class="soico-cta-col-fee">
                                <span class="soico-cta-crypto-fee"><?php echo esc_html( $crypto['trading_fee'] ?? '-' ); ?></span>
                            </td>
                        <?php endif; ?>
                        <?php if ( $show_coins ) : ?>
                            <td class="soico-cta-col-coins">
                                <span class="soico-cta-crypto-coins"><?php echo esc_html( $crypto['coins_count'] ?? '-' ); ?></span>
                            </td>
                        <?php endif; ?>
                        <td class="soico-cta-col-action">
                            <a href="<?php echo esc_url( $crypto['affiliate_url'] ); ?>"
                               class="soico-cta-crypto-table-button"
                               target="_blank" rel="noopener noreferrer sponsored"
                               <?php echo $tracking_attrs; ?>>
                                <?php echo esc_html( $rank === 1 ? '無料で口座開設' : '詳細を見る' ); ?>
                            </a>
                        </td>
                    </tr>
                    <?php
                        $rank++;
                    endforeach; ?>
                </tbody>
            </table>
            <p class="soico-cta-crypto-table-note">PR | <?php printf( esc_html__( '情報は%s時点', 'soico-securities-cta' ), date_i18n( 'Y年n月' ) ); ?></p>
            <?php if ( ! empty( $table_notes_by_company ) ) : ?>
            <div class="soico-cta-crypto-table-notes-by-company" style="font-size: <?php echo esc_attr( $table_notes_size ); ?>px;">
                <?php foreach ( $table_notes_by_company as $company_slug => $notes ) :
                    if ( empty( $notes ) ) continue;
                    $company_name = isset( $all_cryptos[ $company_slug ]['name'] ) ? $all_cryptos[ $company_slug ]['name'] : $company_slug;
                ?>
                <div class="soico-cta-crypto-company-notes" style="margin-bottom: 8px;">
                    <strong><?php echo esc_html( $company_name ); ?>注釈</strong>
                    <ul style="margin: 4px 0 0 1.2em; padding: 0; list-style: disc;">
                        <?php foreach ( $notes as $note ) : ?>
                        <li><?php echo wp_kses_post( $note ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 仮想通貨控えめバナー描画
     */
    public function render_crypto_subtle_banner( $attributes ) {
        $this->debug_log( 'render_crypto_subtle_banner called', $attributes );

        $data = Soico_CTA_Securities_Data::get_instance();
        $exchange_slug = $attributes['exchange'] ?? 'gmo_coin';
        $crypto = $data->get_crypto( $exchange_slug );

        if ( ! $crypto ) {
            return $this->debug_comment( 'Crypto exchange not found: ' . $exchange_slug );
        }

        if ( empty( $crypto['affiliate_url'] ) ) {
            return $this->debug_comment( 'No affiliate_url for crypto banner: ' . $exchange_slug );
        }

        $custom_message = ! empty( $attributes['message'] ) ? $attributes['message'] : '';
        $tracking_attrs = $data->get_crypto_tracking_attributes( $exchange_slug, 'subtle_banner' );

        // リンク生成
        $link_html = '<a href="' . esc_url( $crypto['affiliate_url'] ) . '" target="_blank" rel="noopener noreferrer sponsored"' . $tracking_attrs . '>' . esc_html( $crypto['name'] ) . '</a>';

        if ( $custom_message ) {
            if ( strpos( $custom_message, $crypto['name'] ) !== false ) {
                $message_html = str_replace( $crypto['name'], $link_html, $custom_message );
            } else {
                $message_html = $custom_message . ' → ' . $link_html;
            }
        } else {
            $message_html = sprintf(
                __( '₿ 仮想通貨を始めるなら → %s（取引手数料無料）', 'soico-securities-cta' ),
                $link_html
            );
        }

        ob_start();
        ?>
        <div class="soico-cta-crypto-subtle-banner">
            <span class="soico-cta-crypto-subtle-message">
                <?php echo wp_kses_post( $message_html ); ?>
            </span>
            <span class="soico-cta-crypto-subtle-pr">PR</span>
        </div>
        <?php
        return ob_get_clean();
    }
}
