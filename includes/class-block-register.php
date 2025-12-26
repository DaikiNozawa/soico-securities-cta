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
     * 登録するブロック一覧
     */
    private $blocks = array(
        'conclusion-box',
        'inline-cta',
        'single-button',
        'comparison-table',
        'subtle-banner',
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

        foreach ( $this->blocks as $block ) {
            // PHP配列ベースで登録（JSと競合しないよう、render_callbackのみ設定）
            $this->register_block_php( $block );
        }

        // 登録確認
        $registry = WP_Block_Type_Registry::get_instance();
        $registered = array();
        foreach ( $this->blocks as $block ) {
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
            'securities'    => $securities_data->get_enabled_securities(),
            'selectOptions' => $securities_data->get_securities_select_options(),
            'thirstyActive' => $thirsty->is_thirsty_active(),
            'designSettings'=> $securities_data->get_design_settings(),
            'nonce'         => wp_create_nonce( 'soico_cta_nonce' ),
            'i18n'          => array(
                'blockTitle'     => __( '証券CTA', 'soico-securities-cta' ),
                'conclusionBox'  => __( '結論ボックス', 'soico-securities-cta' ),
                'inlineCTA'      => __( 'インラインCTA', 'soico-securities-cta' ),
                'singleButton'   => __( 'CTAボタン', 'soico-securities-cta' ),
                'comparisonTable'=> __( '比較表', 'soico-securities-cta' ),
                'subtleBanner'   => __( '控えめバナー', 'soico-securities-cta' ),
                'selectCompany'  => __( '証券会社を選択', 'soico-securities-cta' ),
                'showFeatures'   => __( '特徴を表示', 'soico-securities-cta' ),
                'customTitle'    => __( 'カスタムタイトル', 'soico-securities-cta' ),
                'buttonText'     => __( 'ボタンテキスト', 'soico-securities-cta' ),
                'showPR'         => __( 'PR表記を表示', 'soico-securities-cta' ),
                'limit'          => __( '表示件数', 'soico-securities-cta' ),
                'showCommission' => __( '手数料を表示', 'soico-securities-cta' ),
                'message'        => __( 'メッセージ', 'soico-securities-cta' ),
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
        
        $title = $custom_title ? $custom_title : sprintf(
            __( '証券口座を開設するなら<span style="color: #E53935;">%s</span>がおすすめ', 'soico-securities-cta' ),
            esc_html( $security['name'] )
        );
        
        $tracking_attrs = $securities_data->get_tracking_attributes( $company_slug, 'conclusion_box' );
        
        ob_start();
        ?>
        <div class="soico-cta-conclusion-box">
            <div class="soico-cta-conclusion-header">
                <span class="soico-cta-conclusion-label"><?php esc_html_e( '結論', 'soico-securities-cta' ); ?></span>
                <h3 class="soico-cta-conclusion-title"><?php echo wp_kses_post( $title ); ?></h3>
            </div>
            
            <?php if ( $show_features && ! empty( $security['features'] ) ) : ?>
                <ul class="soico-cta-conclusion-features">
                    <?php foreach ( (array) $security['features'] as $feature ) : ?>
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
        
        $feature_text = ! empty( $security['features'] ) ? $security['features'][0] : '';
        
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
                        $rank_class = $rank === 1 ? 'soico-cta-rank-gold' : ( $rank === 2 ? 'soico-cta-rank-silver' : 'soico-cta-rank-bronze' );
                    ?>
                        <tr class="<?php echo $rank === 1 ? 'soico-cta-row-highlight' : ''; ?>">
                            <td class="soico-cta-col-rank">
                                <span class="soico-cta-rank <?php echo esc_attr( $rank_class ); ?>"><?php echo esc_html( $rank ); ?></span>
                            </td>
                            <td class="soico-cta-col-name">
                                <strong><?php echo esc_html( $security['name'] ); ?></strong>
                                <?php if ( ! empty( $security['badge'] ) ) : ?>
                                    <span class="soico-cta-badge" style="background-color: <?php echo esc_attr( $security['badge_color'] ?? '#E53935' ); ?>">
                                        <?php echo esc_html( $security['badge'] ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="soico-cta-col-features">
                                <?php echo esc_html( implode( ' / ', array_slice( (array) $security['features'], 0, 2 ) ) ); ?>
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
        $message = ! empty( $attributes['message'] )
            ? $attributes['message']
            : sprintf(
                __( '💡 証券口座をお探しなら → %s（国内株手数料0円）', 'soico-securities-cta' ),
                $security['name']
            );
        
        $tracking_attrs = $securities_data->get_tracking_attributes( $company_slug, 'subtle_banner' );
        
        ob_start();
        ?>
        <div class="soico-cta-subtle-banner">
            <span class="soico-cta-subtle-message">
                <?php echo wp_kses_post( str_replace( $security['name'], '<a href="' . esc_url( $security['affiliate_url'] ) . '" target="_blank" rel="noopener noreferrer sponsored"' . $tracking_attrs . '>' . esc_html( $security['name'] ) . '</a>', $message ) ); ?>
            </span>
            <span class="soico-cta-subtle-pr">PR</span>
        </div>
        <?php
        return ob_get_clean();
    }
}
