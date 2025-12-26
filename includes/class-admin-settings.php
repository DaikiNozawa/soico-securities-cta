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
     * AJAX: 証券会社保存
     */
    public function ajax_save_securities() {
        check_ajax_referer( 'soico_cta_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません' ) );
        }
        
        $securities = isset( $_POST['securities'] ) ? $_POST['securities'] : array();
        
        // features を配列に変換
        foreach ( $securities as $slug => &$data ) {
            if ( isset( $data['features'] ) && is_string( $data['features'] ) ) {
                $data['features'] = array_filter( array_map( 'trim', explode( "\n", $data['features'] ) ) );
            }
        }
        
        $securities_data = Soico_CTA_Securities_Data::get_instance();
        $result = $securities_data->save_securities( $securities );
        
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
}
