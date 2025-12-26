<?php
/**
 * 管理画面設定クラス
 *
 * @package Soico_Securities_CTA
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 管理画面の設定ページを管理するクラス
 */
class Soico_CTA_Admin_Settings {
    
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
        $this->init_hooks();
    }
    
    /**
     * フック初期化
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_soico_cta_save_securities', array( $this, 'ajax_save_securities' ) );
        add_action( 'wp_ajax_soico_cta_add_security', array( $this, 'ajax_add_security' ) );
        add_action( 'wp_ajax_soico_cta_delete_security', array( $this, 'ajax_delete_security' ) );
    }
    
    /**
     * 管理メニュー追加
     */
    public function add_admin_menu() {
        add_menu_page(
            __( '証券CTA設定', 'soico-securities-cta' ),
            __( '証券CTA', 'soico-securities-cta' ),
            'manage_options',
            'soico-cta-settings',
            array( $this, 'render_settings_page' ),
            'dashicons-money-alt',
            80
        );
        
        add_submenu_page(
            'soico-cta-settings',
            __( '証券会社管理', 'soico-securities-cta' ),
            __( '証券会社管理', 'soico-securities-cta' ),
            'manage_options',
            'soico-cta-settings',
            array( $this, 'render_settings_page' )
        );
        
        add_submenu_page(
            'soico-cta-settings',
            __( 'デザイン設定', 'soico-securities-cta' ),
            __( 'デザイン設定', 'soico-securities-cta' ),
            'manage_options',
            'soico-cta-design',
            array( $this, 'render_design_page' )
        );
        
        add_submenu_page(
            'soico-cta-settings',
            __( 'トラッキング設定', 'soico-securities-cta' ),
            __( 'トラッキング設定', 'soico-securities-cta' ),
            'manage_options',
            'soico-cta-tracking',
            array( $this, 'render_tracking_page' )
        );

        add_submenu_page(
            'soico-cta-settings',
            __( '使い方ガイド', 'soico-securities-cta' ),
            __( '使い方ガイド', 'soico-securities-cta' ),
            'manage_options',
            'soico-cta-guide',
            array( $this, 'render_guide_page' )
        );

        add_submenu_page(
            'soico-cta-settings',
            __( '診断ツール', 'soico-securities-cta' ),
            __( '診断ツール', 'soico-securities-cta' ),
            'manage_options',
            'soico-cta-diagnostics',
            array( $this, 'render_diagnostics_page' )
        );
    }
    
    /**
     * 設定登録
     */
    public function register_settings() {
        // デザイン設定
        register_setting( 'soico_cta_design_group', 'soico_cta_design_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_design_settings' ),
        ) );
        
        // トラッキング設定
        register_setting( 'soico_cta_tracking_group', 'soico_cta_tracking_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_tracking_settings' ),
        ) );
    }
    
    /**
     * 管理画面アセット読み込み
     */
    public function enqueue_admin_assets( $hook ) {
        // 設定ページのみ
        if ( strpos( $hook, 'soico-cta' ) === false ) {
            return;
        }
        
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        
        wp_enqueue_style(
            'soico-cta-admin',
            SOICO_CTA_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SOICO_CTA_VERSION
        );
        
        wp_enqueue_script(
            'soico-cta-admin',
            SOICO_CTA_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-color-picker', 'jquery-ui-sortable' ),
            SOICO_CTA_VERSION,
            true
        );
        
        wp_localize_script( 'soico-cta-admin', 'soicoCTAAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'soico_cta_admin_nonce' ),
            'i18n'    => array(
                'confirmDelete' => __( 'この証券会社を削除しますか？', 'soico-securities-cta' ),
                'saving'        => __( '保存中...', 'soico-securities-cta' ),
                'saved'         => __( '保存しました', 'soico-securities-cta' ),
                'error'         => __( 'エラーが発生しました', 'soico-securities-cta' ),
            ),
        ) );
    }
    
    /**
     * 設定ページ描画
     */
    public function render_settings_page() {
        $securities_data = Soico_CTA_Securities_Data::get_instance();
        $thirsty = Soico_CTA_Thirsty_Integration::get_instance();
        
        $securities = $securities_data->get_all_securities( false );
        $thirsty_links = $thirsty->get_all_links();
        ?>
        <div class="wrap soico-cta-admin">
            <h1><?php esc_html_e( '証券会社管理', 'soico-securities-cta' ); ?></h1>
            
            <?php echo $thirsty->get_not_installed_message(); ?>
            
            <div class="soico-cta-admin-content">
                <form id="soico-cta-securities-form">
                    <?php wp_nonce_field( 'soico_cta_admin_nonce', 'soico_cta_nonce' ); ?>
                    
                    <div class="soico-cta-securities-list" id="securities-list">
                        <?php foreach ( $securities as $slug => $data ) : ?>
                            <?php $this->render_security_row( $slug, $data, $thirsty_links ); ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="soico-cta-actions">
                        <button type="button" class="button" id="add-security-btn">
                            <?php esc_html_e( '＋ 証券会社を追加', 'soico-securities-cta' ); ?>
                        </button>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( '変更を保存', 'soico-securities-cta' ); ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- 新規追加モーダル -->
            <div id="add-security-modal" class="soico-cta-modal" style="display:none;">
                <div class="soico-cta-modal-content">
                    <h2><?php esc_html_e( '証券会社を追加', 'soico-securities-cta' ); ?></h2>
                    <form id="add-security-form">
                        <p>
                            <label><?php esc_html_e( 'スラッグ（英数字）', 'soico-securities-cta' ); ?></label>
                            <input type="text" name="slug" required pattern="[a-z0-9_-]+" />
                        </p>
                        <p>
                            <label><?php esc_html_e( '証券会社名', 'soico-securities-cta' ); ?></label>
                            <input type="text" name="name" required />
                        </p>
                        <p class="soico-cta-modal-actions">
                            <button type="button" class="button" id="cancel-add-security">
                                <?php esc_html_e( 'キャンセル', 'soico-securities-cta' ); ?>
                            </button>
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e( '追加', 'soico-securities-cta' ); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 証券会社行を描画
     */
    private function render_security_row( $slug, $data, $thirsty_links ) {
        ?>
        <div class="soico-cta-security-row" data-slug="<?php echo esc_attr( $slug ); ?>">
            <div class="soico-cta-security-header">
                <span class="dashicons dashicons-move soico-cta-drag-handle"></span>
                <span class="soico-cta-security-name"><?php echo esc_html( $data['name'] ); ?></span>
                <span class="soico-cta-security-priority">
                    <?php printf( __( '優先順位: %d', 'soico-securities-cta' ), $data['priority'] ); ?>
                </span>
                <button type="button" class="button-link soico-cta-toggle-details">
                    <?php esc_html_e( '詳細', 'soico-securities-cta' ); ?>
                </button>
            </div>
            
            <div class="soico-cta-security-details" style="display:none;">
                <input type="hidden" name="securities[<?php echo esc_attr( $slug ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>" />
                <input type="hidden" name="securities[<?php echo esc_attr( $slug ); ?>][priority]" class="priority-input" value="<?php echo esc_attr( $data['priority'] ); ?>" />
                
                <div class="soico-cta-field-row">
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( '有効', 'soico-securities-cta' ); ?></label>
                        <input type="checkbox" name="securities[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( ! empty( $data['enabled'] ) ); ?> />
                    </div>
                    
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( '証券会社名', 'soico-securities-cta' ); ?></label>
                        <input type="text" name="securities[<?php echo esc_attr( $slug ); ?>][name]" value="<?php echo esc_attr( $data['name'] ); ?>" />
                    </div>
                </div>
                
                <div class="soico-cta-field-row">
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( 'ThirstyAffiliateリンク', 'soico-securities-cta' ); ?></label>
                        <select name="securities[<?php echo esc_attr( $slug ); ?>][thirsty_link]">
                            <option value=""><?php esc_html_e( '-- 選択 --', 'soico-securities-cta' ); ?></option>
                            <?php foreach ( $thirsty_links as $link ) : ?>
                                <option value="<?php echo esc_attr( $link['id'] ); ?>" <?php selected( $data['thirsty_link'] ?? '', $link['id'] ); ?>>
                                    <?php echo esc_html( $link['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( '直接URL（ThirstyAffiliate未使用時）', 'soico-securities-cta' ); ?></label>
                        <input type="url" name="securities[<?php echo esc_attr( $slug ); ?>][direct_url]" value="<?php echo esc_attr( $data['direct_url'] ?? '' ); ?>" />
                    </div>
                </div>
                
                <div class="soico-cta-field">
                    <label><?php esc_html_e( '特徴（1行ずつ入力）', 'soico-securities-cta' ); ?></label>
                    <textarea name="securities[<?php echo esc_attr( $slug ); ?>][features]" rows="3"><?php echo esc_textarea( implode( "\n", (array) ( $data['features'] ?? array() ) ) ); ?></textarea>
                </div>
                
                <div class="soico-cta-field-row">
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( '手数料', 'soico-securities-cta' ); ?></label>
                        <input type="text" name="securities[<?php echo esc_attr( $slug ); ?>][commission]" value="<?php echo esc_attr( $data['commission'] ?? '' ); ?>" />
                    </div>
                    
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( 'バッジテキスト', 'soico-securities-cta' ); ?></label>
                        <input type="text" name="securities[<?php echo esc_attr( $slug ); ?>][badge]" value="<?php echo esc_attr( $data['badge'] ?? '' ); ?>" placeholder="例: おすすめ" />
                    </div>
                    
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( 'バッジ色', 'soico-securities-cta' ); ?></label>
                        <input type="text" class="color-picker" name="securities[<?php echo esc_attr( $slug ); ?>][badge_color]" value="<?php echo esc_attr( $data['badge_color'] ?? '#E53935' ); ?>" />
                    </div>
                </div>
                
                <div class="soico-cta-field-row">
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( 'ボタンテキスト', 'soico-securities-cta' ); ?></label>
                        <input type="text" name="securities[<?php echo esc_attr( $slug ); ?>][button_text]" value="<?php echo esc_attr( $data['button_text'] ?? '' ); ?>" />
                    </div>
                    
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( 'ボタン色', 'soico-securities-cta' ); ?></label>
                        <input type="text" class="color-picker" name="securities[<?php echo esc_attr( $slug ); ?>][button_color]" value="<?php echo esc_attr( $data['button_color'] ?? '#FF6B35' ); ?>" />
                    </div>
                </div>
                
                <div class="soico-cta-field-actions">
                    <button type="button" class="button-link button-link-delete soico-cta-delete-security">
                        <?php esc_html_e( '削除', 'soico-securities-cta' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * デザイン設定ページ描画
     */
    public function render_design_page() {
        $settings = get_option( 'soico_cta_design_settings', array() );
        ?>
        <div class="wrap soico-cta-admin">
            <h1><?php esc_html_e( 'デザイン設定', 'soico-securities-cta' ); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'soico_cta_design_group' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'メインカラー（ボタン）', 'soico-securities-cta' ); ?></th>
                        <td>
                            <input type="text" class="color-picker" name="soico_cta_design_settings[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ?? '#FF6B35' ); ?>" />
                            <p class="description"><?php esc_html_e( 'CTAボタンのメインカラー', 'soico-securities-cta' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'セカンダリカラー', 'soico-securities-cta' ); ?></th>
                        <td>
                            <input type="text" class="color-picker" name="soico_cta_design_settings[secondary_color]" value="<?php echo esc_attr( $settings['secondary_color'] ?? '#1E88E5' ); ?>" />
                            <p class="description"><?php esc_html_e( 'ボーダーやアクセントに使用', 'soico-securities-cta' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( '角丸の半径', 'soico-securities-cta' ); ?></th>
                        <td>
                            <input type="number" name="soico_cta_design_settings[border_radius]" value="<?php echo esc_attr( $settings['border_radius'] ?? 8 ); ?>" min="0" max="30" /> px
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * トラッキング設定ページ描画
     */
    public function render_tracking_page() {
        $settings = get_option( 'soico_cta_tracking_settings', array() );
        ?>
        <div class="wrap soico-cta-admin">
            <h1><?php esc_html_e( 'トラッキング設定', 'soico-securities-cta' ); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'soico_cta_tracking_group' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'GTMトラッキング', 'soico-securities-cta' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="soico_cta_tracking_settings[gtm_enabled]" value="1" <?php checked( ! empty( $settings['gtm_enabled'] ) ); ?> />
                                <?php esc_html_e( 'GTMデータ属性を出力する', 'soico-securities-cta' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'CTAボタンにGTM用のdata属性を付与します', 'soico-securities-cta' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'イベントカテゴリ', 'soico-securities-cta' ); ?></th>
                        <td>
                            <input type="text" name="soico_cta_tracking_settings[event_category]" value="<?php echo esc_attr( $settings['event_category'] ?? 'CTA Click' ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'イベントアクション', 'soico-securities-cta' ); ?></th>
                        <td>
                            <input type="text" name="soico_cta_tracking_settings[event_action]" value="<?php echo esc_attr( $settings['event_action'] ?? 'securities_affiliate' ); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="soico-cta-gtm-guide">
                <h3><?php esc_html_e( 'GTM設定ガイド', 'soico-securities-cta' ); ?></h3>
                <p><?php esc_html_e( '以下のデータ属性がCTAボタンに出力されます：', 'soico-securities-cta' ); ?></p>
                <pre>
data-gtm-category="<?php echo esc_html( $settings['event_category'] ?? 'CTA Click' ); ?>"
data-gtm-action="<?php echo esc_html( $settings['event_action'] ?? 'securities_affiliate' ); ?>"
data-gtm-label="[証券会社スラッグ]"
data-cta-type="[CTAタイプ]"
                </pre>
            </div>
        </div>
        <?php
    }
    
    /**
     * デザイン設定サニタイズ
     */
    public function sanitize_design_settings( $input ) {
        return array(
            'primary_color'   => sanitize_hex_color( $input['primary_color'] ?? '#FF6B35' ),
            'secondary_color' => sanitize_hex_color( $input['secondary_color'] ?? '#1E88E5' ),
            'border_radius'   => absint( $input['border_radius'] ?? 8 ),
        );
    }
    
    /**
     * トラッキング設定サニタイズ
     */
    public function sanitize_tracking_settings( $input ) {
        return array(
            'gtm_enabled'    => ! empty( $input['gtm_enabled'] ),
            'event_category' => sanitize_text_field( $input['event_category'] ?? 'CTA Click' ),
            'event_action'   => sanitize_text_field( $input['event_action'] ?? 'securities_affiliate' ),
        );
    }
    
    /**
     * デバッグログ出力
     */
    private function debug_log( $message, $context = array() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_message = '[SOICO CTA Admin] ' . $message;
            if ( ! empty( $context ) ) {
                $log_message .= ' | ' . wp_json_encode( $context, JSON_UNESCAPED_UNICODE );
            }
            error_log( $log_message );
        }
    }

    /**
     * AJAX: 証券会社保存
     */
    public function ajax_save_securities() {
        $this->debug_log( 'ajax_save_securities called' );

        // Nonce検証
        if ( ! check_ajax_referer( 'soico_cta_admin_nonce', 'nonce', false ) ) {
            $this->debug_log( 'Nonce verification failed' );
            wp_send_json_error( array( 'message' => 'セキュリティ検証に失敗しました' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            $this->debug_log( 'Permission denied' );
            wp_send_json_error( array( 'message' => '権限がありません' ) );
        }

        $securities = isset( $_POST['securities'] ) ? $_POST['securities'] : array();
        $this->debug_log( 'Received securities', array( 'count' => count( $securities ), 'slugs' => array_keys( $securities ) ) );

        if ( empty( $securities ) ) {
            $this->debug_log( 'No securities data received' );
            wp_send_json_error( array( 'message' => 'データが送信されていません' ) );
        }

        // features を配列に変換
        foreach ( $securities as $slug => &$data ) {
            if ( isset( $data['features'] ) && is_string( $data['features'] ) ) {
                $data['features'] = array_filter( array_map( 'trim', explode( "\n", $data['features'] ) ) );
            }
        }

        $securities_data = Soico_CTA_Securities_Data::get_instance();
        $result = $securities_data->save_securities( $securities );

        $this->debug_log( 'Save result', array( 'result' => $result ) );

        if ( $result ) {
            wp_send_json_success( array( 'message' => '保存しました' ) );
        } else {
            wp_send_json_error( array( 'message' => '保存に失敗しました' ) );
        }
    }
    
    /**
     * AJAX: 証券会社追加
     */
    public function ajax_add_security() {
        check_ajax_referer( 'soico_cta_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません' ) );
        }
        
        $slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
        $name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
        
        if ( empty( $slug ) || empty( $name ) ) {
            wp_send_json_error( array( 'message' => 'スラッグと名前は必須です' ) );
        }
        
        $securities_data = Soico_CTA_Securities_Data::get_instance();
        $result = $securities_data->add_security( array(
            'slug'    => $slug,
            'name'    => $name,
            'enabled' => true,
        ) );
        
        if ( $result ) {
            wp_send_json_success( array( 'message' => '追加しました', 'reload' => true ) );
        } else {
            wp_send_json_error( array( 'message' => '既に存在するスラッグか、追加に失敗しました' ) );
        }
    }
    
    /**
     * AJAX: 証券会社削除
     */
    public function ajax_delete_security() {
        check_ajax_referer( 'soico_cta_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません' ) );
        }
        
        $slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
        
        if ( empty( $slug ) ) {
            wp_send_json_error( array( 'message' => 'スラッグが必要です' ) );
        }
        
        $securities_data = Soico_CTA_Securities_Data::get_instance();
        $result = $securities_data->delete_security( $slug );
        
        if ( $result ) {
            wp_send_json_success( array( 'message' => '削除しました' ) );
        } else {
            wp_send_json_error( array( 'message' => '削除に失敗しました' ) );
        }
    }

    /**
     * 使い方ガイドページ描画
     */
    public function render_guide_page() {
        ?>
        <div class="wrap soico-cta-admin soico-cta-guide">
            <h1><?php esc_html_e( '使い方ガイド', 'soico-securities-cta' ); ?></h1>

            <div class="soico-cta-guide-section">
                <h2>🚀 クイックスタート</h2>
                <ol>
                    <li><strong>証券会社を設定</strong> - 「証券会社管理」で証券会社の情報とアフィリエイトリンクを設定します</li>
                    <li><strong>記事でブロックを挿入</strong> - 投稿編集画面で「/」を入力し、「証券」と検索してブロックを追加します</li>
                    <li><strong>サイドバーで設定</strong> - ブロックを選択し、右サイドバーで証券会社やオプションを選択します</li>
                </ol>
            </div>

            <div class="soico-cta-guide-section">
                <h2>📦 利用可能なブロック</h2>

                <div class="soico-cta-guide-block">
                    <h3>1. 結論ボックス</h3>
                    <p>記事冒頭に最適。証券会社のおすすめポイントと特徴リスト、CTAボタンを表示します。</p>
                    <p><strong>使用例:</strong> 「〇〇証券がおすすめ」という結論を最初に提示したい場合</p>
                    <p><strong>設定項目:</strong></p>
                    <ul>
                        <li>証券会社を選択</li>
                        <li>特徴を表示（ON/OFF）</li>
                        <li>カスタムタイトル（任意）</li>
                    </ul>
                </div>

                <div class="soico-cta-guide-block">
                    <h3>2. インラインCTA</h3>
                    <p>記事の途中に自然に挿入できる控えめなCTA。流れを邪魔しません。</p>
                    <p><strong>使用例:</strong> 記事中で証券会社に言及したタイミングで挿入</p>
                    <p><strong>設定項目:</strong></p>
                    <ul>
                        <li>証券会社を選択</li>
                        <li>スタイル（デフォルト/控えめ）</li>
                    </ul>
                </div>

                <div class="soico-cta-guide-block">
                    <h3>3. CTAボタン</h3>
                    <p>シンプルなボタンのみ。任意の場所に配置できます。</p>
                    <p><strong>使用例:</strong> 記事末尾やセクション終わりに</p>
                    <p><strong>設定項目:</strong></p>
                    <ul>
                        <li>証券会社を選択</li>
                        <li>ボタンテキスト（任意）</li>
                        <li>PR表記を表示（ON/OFF）</li>
                    </ul>
                </div>

                <div class="soico-cta-guide-block">
                    <h3>4. 比較表</h3>
                    <p>複数の証券会社を比較する表形式のCTA。ランキング記事に最適。</p>
                    <p><strong>使用例:</strong> 「おすすめ証券会社ランキング」記事</p>
                    <p><strong>設定項目:</strong></p>
                    <ul>
                        <li>表示件数（1〜10）</li>
                        <li>手数料を表示（ON/OFF）</li>
                    </ul>
                </div>

                <div class="soico-cta-guide-block">
                    <h3>5. 控えめバナー</h3>
                    <p>テキストリンク形式の最も控えめなCTA。読者の邪魔をしません。</p>
                    <p><strong>使用例:</strong> 記事内の補足情報として</p>
                    <p><strong>設定項目:</strong></p>
                    <ul>
                        <li>証券会社を選択</li>
                        <li>メッセージ（任意）</li>
                    </ul>
                </div>
            </div>

            <div class="soico-cta-guide-section">
                <h2>⚙️ 設定項目の説明</h2>

                <h3>証券会社管理</h3>
                <table class="widefat">
                    <tr><th>項目</th><th>説明</th></tr>
                    <tr><td>有効</td><td>チェックを外すと、そのの証券会社はブロックで選択できなくなります</td></tr>
                    <tr><td>ThirstyAffiliateリンク</td><td>ThirstyAffiliatesプラグインで作成したリンクを選択（推奨）</td></tr>
                    <tr><td>直接URL</td><td>ThirstyAffiliates未使用時に直接アフィリエイトURLを入力</td></tr>
                    <tr><td>特徴</td><td>1行ずつ入力。結論ボックスや比較表で表示されます</td></tr>
                    <tr><td>手数料</td><td>比較表で表示される手数料情報</td></tr>
                    <tr><td>バッジ</td><td>「おすすめ」などのラベル。比較表で表示されます</td></tr>
                    <tr><td>優先順位</td><td>ドラッグ&ドロップで並べ替え。比較表の表示順に影響します</td></tr>
                </table>

                <h3>デザイン設定</h3>
                <table class="widefat">
                    <tr><th>項目</th><th>説明</th></tr>
                    <tr><td>メインカラー</td><td>CTAボタンの背景色</td></tr>
                    <tr><td>セカンダリカラー</td><td>ボーダーやアクセントカラー</td></tr>
                    <tr><td>角丸の半径</td><td>ボタンやボックスの角の丸み</td></tr>
                </table>

                <h3>トラッキング設定</h3>
                <table class="widefat">
                    <tr><th>項目</th><th>説明</th></tr>
                    <tr><td>GTMトラッキング</td><td>Google Tag Manager用のdata属性を出力します</td></tr>
                    <tr><td>イベントカテゴリ/アクション</td><td>GTMで計測する際のイベント名</td></tr>
                </table>
            </div>

            <div class="soico-cta-guide-section">
                <h2>💡 Tips</h2>
                <ul>
                    <li><strong>ブロックの素早い挿入:</strong> エディタで「/結論」「/比較」と入力すると、該当ブロックが候補に表示されます</li>
                    <li><strong>ThirstyAffiliatesとの連携:</strong> アフィリエイトリンクの管理はThirstyAffiliatesプラグインの使用を推奨します。リンク切れチェックやクリック計測が可能になります</li>
                    <li><strong>PR表記:</strong> 各ブロックには自動でPR表記が含まれます（景品表示法対応）</li>
                </ul>
            </div>

            <div class="soico-cta-guide-section">
                <h2>❓ よくある質問</h2>

                <h4>Q: ブロックが表示されません</h4>
                <p>A: 「証券会社管理」で該当の証券会社が「有効」になっているか確認してください。また、アフィリエイトURL（ThirstyAffiliateリンクまたは直接URL）が設定されている必要があります。</p>

                <h4>Q: 色を変更したい</h4>
                <p>A: 「デザイン設定」でメインカラーを変更できます。また、各証券会社のボタン色は「証券会社管理」で個別に設定できます。</p>

                <h4>Q: 表示順を変更したい</h4>
                <p>A: 「証券会社管理」でドラッグ&ドロップで並べ替えてください。比較表では優先順位の高い順に表示されます。</p>
            </div>
        </div>

        <style>
            .soico-cta-guide { max-width: 900px; }
            .soico-cta-guide-section { background: #fff; padding: 20px 25px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px; }
            .soico-cta-guide-section h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 2px solid #FF6B35; }
            .soico-cta-guide-block { background: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #FF6B35; }
            .soico-cta-guide-block h3 { margin-top: 0; color: #23282d; }
            .soico-cta-guide-block ul { margin-left: 20px; }
            .soico-cta-guide table { margin: 15px 0; }
            .soico-cta-guide table th { background: #f1f1f1; text-align: left; }
            .soico-cta-guide h4 { margin-bottom: 5px; }
        </style>
        <?php
    }

    /**
     * 診断ツールページ描画
     */
    public function render_diagnostics_page() {
        $securities_data = Soico_CTA_Securities_Data::get_instance();
        $thirsty = Soico_CTA_Thirsty_Integration::get_instance();
        $block_register = Soico_CTA_Block_Register::get_instance();

        $securities = $securities_data->get_all_securities( false );
        $enabled_securities = $securities_data->get_enabled_securities();
        $registry = WP_Block_Type_Registry::get_instance();

        $blocks = array(
            'soico-cta/conclusion-box',
            'soico-cta/inline-cta',
            'soico-cta/single-button',
            'soico-cta/comparison-table',
            'soico-cta/subtle-banner',
        );
        ?>
        <div class="wrap soico-cta-admin">
            <h1><?php esc_html_e( '診断ツール', 'soico-securities-cta' ); ?></h1>

            <!-- ブロック登録状態 -->
            <div class="soico-cta-diag-section">
                <h2>📦 ブロック登録状態</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>ブロック名</th>
                            <th>登録済み</th>
                            <th>render_callback</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $blocks as $block_name ) :
                            $block_type = $registry->get_registered( $block_name );
                            $is_registered = ! empty( $block_type );
                            $has_callback = $is_registered && is_callable( $block_type->render_callback );
                        ?>
                        <tr>
                            <td><code><?php echo esc_html( $block_name ); ?></code></td>
                            <td><?php echo $is_registered ? '✅ 登録済み' : '❌ 未登録'; ?></td>
                            <td><?php echo $has_callback ? '✅ 設定済み' : '❌ 未設定'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- 証券会社データ状態 -->
            <div class="soico-cta-diag-section">
                <h2>🏦 証券会社データ状態</h2>
                <p>
                    <strong>全証券会社:</strong> <?php echo count( $securities ); ?>件 |
                    <strong>有効:</strong> <?php echo count( $enabled_securities ); ?>件
                </p>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>スラッグ</th>
                            <th>名前</th>
                            <th>有効</th>
                            <th>ThirstyLink ID</th>
                            <th>affiliate_url</th>
                            <th>特徴</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $securities as $slug => $data ) : ?>
                        <tr style="<?php echo empty( $data['enabled'] ) ? 'opacity: 0.5;' : ''; ?>">
                            <td><code><?php echo esc_html( $slug ); ?></code></td>
                            <td><?php echo esc_html( $data['name'] ); ?></td>
                            <td><?php echo ! empty( $data['enabled'] ) ? '✅' : '❌'; ?></td>
                            <td><?php echo ! empty( $data['thirsty_link'] ) ? esc_html( $data['thirsty_link'] ) : '<em>未設定</em>'; ?></td>
                            <td>
                                <?php if ( ! empty( $data['affiliate_url'] ) ) : ?>
                                    <a href="<?php echo esc_url( $data['affiliate_url'] ); ?>" target="_blank" style="word-break: break-all;">
                                        <?php echo esc_html( mb_strimwidth( $data['affiliate_url'], 0, 50, '...' ) ); ?>
                                    </a>
                                <?php else : ?>
                                    <span style="color: red;">❌ 未設定</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $features = $data['features'] ?? array();
                                if ( is_array( $features ) && ! empty( $features ) ) {
                                    echo esc_html( count( $features ) . '件' );
                                } else {
                                    echo '<em>なし</em>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ThirstyAffiliate状態 -->
            <div class="soico-cta-diag-section">
                <h2>🔗 ThirstyAffiliate連携状態</h2>
                <p>
                    <strong>ThirstyAffiliate:</strong>
                    <?php echo $thirsty->is_thirsty_active() ? '✅ 有効' : '❌ 無効または未インストール'; ?>
                </p>
                <?php
                $thirsty_links = $thirsty->get_all_links();
                if ( ! empty( $thirsty_links ) ) :
                ?>
                <p><strong>登録リンク数:</strong> <?php echo count( $thirsty_links ); ?>件</p>
                <details>
                    <summary>リンク一覧を表示</summary>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>名前</th>
                                <th>クローキングURL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $thirsty_links as $link ) : ?>
                            <tr>
                                <td><?php echo esc_html( $link['id'] ); ?></td>
                                <td><?php echo esc_html( $link['name'] ); ?></td>
                                <td><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank"><?php echo esc_html( $link['url'] ); ?></a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
                <?php endif; ?>
            </div>

            <!-- テストレンダリング -->
            <div class="soico-cta-diag-section">
                <h2>🧪 テストレンダリング</h2>
                <p>以下は「結論ボックス」ブロックのテストレンダリングです。正常に表示されれば、ブロックの描画機能は動作しています。</p>

                <?php
                // 最初の有効な証券会社を取得
                $test_security = reset( $enabled_securities );
                if ( $test_security ) :
                    $test_slug = $test_security['slug'] ?? key( $enabled_securities );
                ?>
                <div style="background: #f9f9f9; padding: 20px; margin: 15px 0; border: 1px solid #ddd;">
                    <p><strong>テスト対象:</strong> <?php echo esc_html( $test_security['name'] ); ?> (<?php echo esc_html( $test_slug ); ?>)</p>
                    <hr>
                    <?php
                    // フロントエンドCSSを読み込み
                    wp_enqueue_style(
                        'soico-cta-frontend-test',
                        SOICO_CTA_PLUGIN_URL . 'assets/css/frontend.css',
                        array(),
                        SOICO_CTA_VERSION
                    );

                    // render_conclusion_box を直接呼び出し
                    $rendered = $block_register->render_conclusion_box( array(
                        'company' => $test_slug,
                        'showFeatures' => true,
                        'customTitle' => '',
                    ) );

                    if ( empty( $rendered ) || strpos( $rendered, '<!--' ) === 0 ) {
                        echo '<div style="color: red; padding: 10px; background: #ffe0e0;">';
                        echo '<strong>⚠️ レンダリング結果が空またはコメントのみです</strong>';
                        if ( ! empty( $rendered ) ) {
                            echo '<pre>' . esc_html( $rendered ) . '</pre>';
                        }
                        echo '<p>考えられる原因:</p>';
                        echo '<ul>';
                        echo '<li>証券会社データが見つからない</li>';
                        echo '<li>affiliate_url が設定されていない（ThirstyAffiliateリンクまたは直接URLが必要）</li>';
                        echo '</ul>';
                        echo '</div>';
                    } else {
                        echo '<div style="color: green; margin-bottom: 10px;">✅ レンダリング成功</div>';
                        echo $rendered;
                    }
                    ?>
                </div>
                <?php else : ?>
                <div style="color: orange; padding: 10px; background: #fff3cd;">
                    ⚠️ 有効な証券会社がありません。「証券会社管理」で証券会社を有効にしてください。
                </div>
                <?php endif; ?>
            </div>

            <!-- テストショートコード -->
            <div class="soico-cta-diag-section">
                <h2>📝 テストショートコード</h2>
                <p>以下のショートコードを投稿や固定ページに貼り付けて、CTAブロックの動作をテストできます。</p>
                <table class="widefat">
                    <tr>
                        <td><code>[soico_cta_test type="conclusion-box" company="sbi"]</code></td>
                        <td>結論ボックスをテスト表示</td>
                    </tr>
                    <tr>
                        <td><code>[soico_cta_test type="comparison-table" limit="3"]</code></td>
                        <td>比較表をテスト表示</td>
                    </tr>
                    <tr>
                        <td><code>[soico_cta_test type="single-button" company="sbi"]</code></td>
                        <td>CTAボタンをテスト表示</td>
                    </tr>
                </table>
            </div>

            <!-- デバッグ情報 -->
            <div class="soico-cta-diag-section">
                <h2>🔧 デバッグ情報</h2>
                <table class="widefat">
                    <tr>
                        <th>項目</th>
                        <th>値</th>
                    </tr>
                    <tr>
                        <td>WordPress バージョン</td>
                        <td><?php echo get_bloginfo( 'version' ); ?></td>
                    </tr>
                    <tr>
                        <td>PHP バージョン</td>
                        <td><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <td>プラグインバージョン</td>
                        <td><?php echo SOICO_CTA_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td>WP_DEBUG</td>
                        <td><?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? '有効' : '無効'; ?></td>
                    </tr>
                    <tr>
                        <td>ブロックエディタ</td>
                        <td><?php echo function_exists( 'use_block_editor_for_posts' ) ? '利用可能' : '利用不可'; ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <style>
            .soico-cta-diag-section {
                background: #fff;
                padding: 20px 25px;
                margin: 20px 0;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }
            .soico-cta-diag-section h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 2px solid #FF6B35;
            }
            .soico-cta-diag-section table {
                margin-top: 10px;
            }
            .soico-cta-diag-section code {
                background: #f1f1f1;
                padding: 2px 6px;
                border-radius: 3px;
            }
        </style>
        <?php
    }
}
