<?php
/**
 * 仮想通貨CTA管理画面設定クラス
 *
 * @package Soico_Securities_CTA
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 仮想通貨取引所の管理画面ページを管理するクラス
 */
class Soico_CTA_Admin_Crypto {

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

        // 仮想通貨用AJAX
        add_action( 'wp_ajax_soico_save_cryptos', array( $this, 'ajax_save_cryptos' ) );
        add_action( 'wp_ajax_soico_add_crypto', array( $this, 'ajax_add_crypto' ) );
        add_action( 'wp_ajax_soico_delete_crypto', array( $this, 'ajax_delete_crypto' ) );
    }

    /**
     * 管理メニュー追加
     */
    public function add_admin_menu() {
        add_menu_page(
            __( '仮想通貨CTA設定', 'soico-securities-cta' ),
            __( '仮想通貨CTA', 'soico-securities-cta' ),
            'manage_options',
            'soico-crypto-settings',
            array( $this, 'render_crypto_settings_page' ),
            'dashicons-money',
            82
        );

        add_submenu_page(
            'soico-crypto-settings',
            __( '取引所管理', 'soico-securities-cta' ),
            __( '取引所管理', 'soico-securities-cta' ),
            'manage_options',
            'soico-crypto-settings',
            array( $this, 'render_crypto_settings_page' )
        );

        add_submenu_page(
            'soico-crypto-settings',
            __( 'デザイン設定', 'soico-securities-cta' ),
            __( 'デザイン設定', 'soico-securities-cta' ),
            'manage_options',
            'soico-crypto-design',
            array( $this, 'render_crypto_design_page' )
        );

        add_submenu_page(
            'soico-crypto-settings',
            __( 'トラッキング設定', 'soico-securities-cta' ),
            __( 'トラッキング設定', 'soico-securities-cta' ),
            'manage_options',
            'soico-crypto-tracking',
            array( $this, 'render_crypto_tracking_page' )
        );
    }

    /**
     * 設定登録
     */
    public function register_settings() {
        // 仮想通貨デザイン設定
        register_setting( 'soico_crypto_design_group', 'soico_cta_crypto_design_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_crypto_design_settings' ),
        ) );

        // 仮想通貨トラッキング設定
        register_setting( 'soico_crypto_tracking_group', 'soico_cta_crypto_tracking_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_crypto_tracking_settings' ),
        ) );
    }

    /**
     * 管理画面アセット読み込み
     */
    public function enqueue_admin_assets( $hook ) {
        $is_crypto_page = strpos( $hook, 'soico-crypto' ) !== false;
        if ( ! $is_crypto_page ) {
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
                'confirmDeleteCrypto' => __( 'この取引所を削除しますか？', 'soico-securities-cta' ),
                'saving'              => __( '保存中...', 'soico-securities-cta' ),
                'saved'               => __( '保存しました', 'soico-securities-cta' ),
                'error'               => __( 'エラーが発生しました', 'soico-securities-cta' ),
            ),
        ) );
    }

    /**
     * 取引所管理ページ描画
     */
    public function render_crypto_settings_page() {
        $securities_data = Soico_CTA_Securities_Data::get_instance();
        $thirsty = Soico_CTA_Thirsty_Integration::get_instance();

        $cryptos = $securities_data->get_all_cryptos( false );
        $thirsty_links = $thirsty->get_all_links();
        ?>
        <div class="wrap soico-cta-admin soico-crypto-admin">
            <h1><?php esc_html_e( '取引所管理', 'soico-securities-cta' ); ?></h1>

            <?php echo $thirsty->get_not_installed_message(); ?>

            <div class="soico-cta-admin-content">
                <form id="soico-crypto-form">
                    <?php wp_nonce_field( 'soico_cta_admin_nonce', 'soico_cta_nonce' ); ?>

                    <div class="soico-cta-securities-list" id="cryptos-list">
                        <?php foreach ( $cryptos as $slug => $data ) : ?>
                            <?php $this->render_crypto_row( $slug, $data, $thirsty_links ); ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="soico-cta-actions">
                        <button type="button" class="button" id="add-crypto-btn">
                            <?php esc_html_e( '＋ 取引所を追加', 'soico-securities-cta' ); ?>
                        </button>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( '変更を保存', 'soico-securities-cta' ); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- 新規追加モーダル -->
            <div id="add-crypto-modal" class="soico-cta-modal" style="display:none;">
                <div class="soico-cta-modal-content">
                    <h2><?php esc_html_e( '取引所を追加', 'soico-securities-cta' ); ?></h2>
                    <form id="add-crypto-form">
                        <p>
                            <label><?php esc_html_e( 'スラッグ（英数字）', 'soico-securities-cta' ); ?></label>
                            <input type="text" name="slug" required pattern="[a-z0-9_-]+" />
                        </p>
                        <p>
                            <label><?php esc_html_e( '取引所名', 'soico-securities-cta' ); ?></label>
                            <input type="text" name="name" required />
                        </p>
                        <p class="soico-cta-modal-actions">
                            <button type="button" class="button" id="cancel-add-crypto">
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
     * 取引所行を描画
     */
    private function render_crypto_row( $slug, $data, $thirsty_links ) {
        ?>
        <div class="soico-cta-security-row soico-crypto-row" data-slug="<?php echo esc_attr( $slug ); ?>">
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
                <input type="hidden" name="cryptos[<?php echo esc_attr( $slug ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>" />
                <input type="hidden" name="cryptos[<?php echo esc_attr( $slug ); ?>][priority]" class="priority-input" value="<?php echo esc_attr( $data['priority'] ); ?>" />

                <div class="soico-cta-field-row">
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( '有効', 'soico-securities-cta' ); ?></label>
                        <input type="checkbox" name="cryptos[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( ! empty( $data['enabled'] ) ); ?> />
                    </div>

                    <div class="soico-cta-field">
                        <label><?php esc_html_e( '取引所名', 'soico-securities-cta' ); ?></label>
                        <input type="text" name="cryptos[<?php echo esc_attr( $slug ); ?>][name]" value="<?php echo esc_attr( $data['name'] ); ?>" />
                    </div>
                </div>

                <div class="soico-cta-field-row">
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( 'ThirstyAffiliateリンク', 'soico-securities-cta' ); ?></label>
                        <select name="cryptos[<?php echo esc_attr( $slug ); ?>][thirsty_link]">
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
                        <input type="url" name="cryptos[<?php echo esc_attr( $slug ); ?>][direct_url]" value="<?php echo esc_attr( $data['direct_url'] ?? '' ); ?>" />
                    </div>
                </div>

                <!-- 仮想通貨固有フィールド -->
                <div class="soico-cta-field-row soico-crypto-specific">
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( '取引手数料', 'soico-securities-cta' ); ?></label>
                        <input type="text" name="cryptos[<?php echo esc_attr( $slug ); ?>][trading_fee]" value="<?php echo esc_attr( $data['trading_fee'] ?? '' ); ?>" placeholder="例: 無料" />
                    </div>

                    <div class="soico-cta-field">
                        <label><?php esc_html_e( '取扱通貨数', 'soico-securities-cta' ); ?></label>
                        <input type="text" name="cryptos[<?php echo esc_attr( $slug ); ?>][coins_count]" value="<?php echo esc_attr( $data['coins_count'] ?? '' ); ?>" placeholder="例: 39種類" />
                    </div>

                    <div class="soico-cta-field">
                        <label><?php esc_html_e( '最低取引額', 'soico-securities-cta' ); ?></label>
                        <input type="text" name="cryptos[<?php echo esc_attr( $slug ); ?>][min_amount]" value="<?php echo esc_attr( $data['min_amount'] ?? '' ); ?>" placeholder="例: 1円" />
                    </div>
                </div>

                <div class="soico-cta-field">
                    <label><?php esc_html_e( '特徴（1行ずつ入力）', 'soico-securities-cta' ); ?></label>
                    <textarea name="cryptos[<?php echo esc_attr( $slug ); ?>][features]" rows="3"><?php echo esc_textarea( implode( "\n", (array) ( $data['features'] ?? array() ) ) ); ?></textarea>
                </div>

                <div class="soico-cta-field-row">
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( 'バッジテキスト', 'soico-securities-cta' ); ?></label>
                        <input type="text" name="cryptos[<?php echo esc_attr( $slug ); ?>][badge]" value="<?php echo esc_attr( $data['badge'] ?? '' ); ?>" placeholder="例: おすすめ" />
                    </div>

                    <div class="soico-cta-field">
                        <label><?php esc_html_e( 'バッジ色', 'soico-securities-cta' ); ?></label>
                        <input type="text" class="color-picker" name="cryptos[<?php echo esc_attr( $slug ); ?>][badge_color]" value="<?php echo esc_attr( $data['badge_color'] ?? '#4CAF50' ); ?>" />
                    </div>
                </div>

                <div class="soico-cta-field-row">
                    <div class="soico-cta-field">
                        <label><?php esc_html_e( 'ボタンテキスト', 'soico-securities-cta' ); ?></label>
                        <input type="text" name="cryptos[<?php echo esc_attr( $slug ); ?>][button_text]" value="<?php echo esc_attr( $data['button_text'] ?? '' ); ?>" />
                    </div>

                    <div class="soico-cta-field">
                        <label><?php esc_html_e( 'ボタン色', 'soico-securities-cta' ); ?></label>
                        <input type="text" class="color-picker" name="cryptos[<?php echo esc_attr( $slug ); ?>][button_color]" value="<?php echo esc_attr( $data['button_color'] ?? '#F7931A' ); ?>" />
                    </div>
                </div>

                <div class="soico-cta-field-actions">
                    <button type="button" class="button-link button-link-delete soico-cta-delete-crypto">
                        <?php esc_html_e( '削除', 'soico-securities-cta' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 仮想通貨デザイン設定ページ描画
     */
    public function render_crypto_design_page() {
        $settings = get_option( 'soico_cta_crypto_design_settings', array() );
        ?>
        <div class="wrap soico-cta-admin soico-crypto-admin">
            <h1><?php esc_html_e( '仮想通貨CTA デザイン設定', 'soico-securities-cta' ); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'soico_crypto_design_group' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'メインカラー（ボタン）', 'soico-securities-cta' ); ?></th>
                        <td>
                            <input type="text" class="color-picker" name="soico_cta_crypto_design_settings[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ?? '#F7931A' ); ?>" />
                            <p class="description"><?php esc_html_e( 'CTAボタンのメインカラー（ビットコインオレンジ推奨）', 'soico-securities-cta' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'セカンダリカラー', 'soico-securities-cta' ); ?></th>
                        <td>
                            <input type="text" class="color-picker" name="soico_cta_crypto_design_settings[secondary_color]" value="<?php echo esc_attr( $settings['secondary_color'] ?? '#E67E22' ); ?>" />
                            <p class="description"><?php esc_html_e( 'ボーダーやアクセントに使用', 'soico-securities-cta' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( '角丸の半径', 'soico-securities-cta' ); ?></th>
                        <td>
                            <input type="number" name="soico_cta_crypto_design_settings[border_radius]" value="<?php echo esc_attr( $settings['border_radius'] ?? 8 ); ?>" min="0" max="30" /> px
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * 仮想通貨トラッキング設定ページ描画
     */
    public function render_crypto_tracking_page() {
        $settings = get_option( 'soico_cta_crypto_tracking_settings', array() );
        ?>
        <div class="wrap soico-cta-admin soico-crypto-admin">
            <h1><?php esc_html_e( '仮想通貨CTA トラッキング設定', 'soico-securities-cta' ); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'soico_crypto_tracking_group' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'GTMトラッキング', 'soico-securities-cta' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="soico_cta_crypto_tracking_settings[gtm_enabled]" value="1" <?php checked( ! empty( $settings['gtm_enabled'] ) ); ?> />
                                <?php esc_html_e( 'GTMデータ属性を出力する', 'soico-securities-cta' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'CTAボタンにGTM用のdata属性を付与します', 'soico-securities-cta' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'イベントカテゴリ', 'soico-securities-cta' ); ?></th>
                        <td>
                            <input type="text" name="soico_cta_crypto_tracking_settings[event_category]" value="<?php echo esc_attr( $settings['event_category'] ?? 'CTA Click' ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'イベントアクション', 'soico-securities-cta' ); ?></th>
                        <td>
                            <input type="text" name="soico_cta_crypto_tracking_settings[event_action]" value="<?php echo esc_attr( $settings['event_action'] ?? 'crypto_affiliate' ); ?>" class="regular-text" />
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
data-gtm-action="<?php echo esc_html( $settings['event_action'] ?? 'crypto_affiliate' ); ?>"
data-gtm-label="[取引所スラッグ]"
data-cta-type="[CTAタイプ]"
                </pre>
            </div>
        </div>
        <?php
    }

    /**
     * 仮想通貨デザイン設定サニタイズ
     */
    public function sanitize_crypto_design_settings( $input ) {
        return array(
            'primary_color'   => sanitize_hex_color( $input['primary_color'] ?? '#F7931A' ),
            'secondary_color' => sanitize_hex_color( $input['secondary_color'] ?? '#E67E22' ),
            'border_radius'   => absint( $input['border_radius'] ?? 8 ),
        );
    }

    /**
     * 仮想通貨トラッキング設定サニタイズ
     */
    public function sanitize_crypto_tracking_settings( $input ) {
        return array(
            'gtm_enabled'    => ! empty( $input['gtm_enabled'] ),
            'event_category' => sanitize_text_field( $input['event_category'] ?? 'CTA Click' ),
            'event_action'   => sanitize_text_field( $input['event_action'] ?? 'crypto_affiliate' ),
        );
    }

    /**
     * 仮想通貨データのサニタイズ
     *
     * @param array $data
     * @return array
     */
    private function sanitize_crypto_data( $data ) {
        $sanitized = array();

        foreach ( $data as $slug => $item ) {
            $slug = sanitize_key( $slug );

            // featuresを適切に配列に変換
            $features = $item['features'] ?? array();
            if ( is_string( $features ) ) {
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
                'trading_fee'  => sanitize_text_field( $item['trading_fee'] ?? '' ),
                'coins_count'  => sanitize_text_field( $item['coins_count'] ?? '' ),
                'min_amount'   => sanitize_text_field( $item['min_amount'] ?? '' ),
                'badge'        => sanitize_text_field( $item['badge'] ?? '' ),
                'badge_color'  => sanitize_hex_color( $item['badge_color'] ?? '' ),
                'button_text'  => sanitize_text_field( $item['button_text'] ?? '' ),
                'button_color' => sanitize_hex_color( $item['button_color'] ?? '#F7931A' ),
            );
        }

        return $sanitized;
    }

    /**
     * デバッグログ出力
     */
    private function debug_log( $message, $context = array() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_message = '[SOICO CTA Crypto Admin] ' . $message;
            if ( ! empty( $context ) ) {
                $log_message .= ' | ' . wp_json_encode( $context, JSON_UNESCAPED_UNICODE );
            }
            error_log( $log_message );
        }
    }

    /**
     * AJAX: 仮想通貨取引所保存
     */
    public function ajax_save_cryptos() {
        $this->debug_log( 'ajax_save_cryptos called' );

        // Nonce検証
        if ( ! check_ajax_referer( 'soico_cta_admin_nonce', 'nonce', false ) ) {
            $this->debug_log( 'Nonce verification failed' );
            wp_send_json_error( array( 'message' => 'セキュリティ検証に失敗しました' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            $this->debug_log( 'Permission denied' );
            wp_send_json_error( array( 'message' => '権限がありません' ) );
        }

        $cryptos = isset( $_POST['cryptos'] ) ? $_POST['cryptos'] : array();
        $this->debug_log( 'Received cryptos', array( 'count' => count( $cryptos ), 'slugs' => array_keys( $cryptos ) ) );

        if ( empty( $cryptos ) ) {
            $this->debug_log( 'No cryptos data received' );
            wp_send_json_error( array( 'message' => 'データが送信されていません' ) );
        }

        // features を配列に変換
        foreach ( $cryptos as $slug => &$data ) {
            if ( isset( $data['features'] ) && is_string( $data['features'] ) ) {
                $data['features'] = array_filter( array_map( 'trim', explode( "\n", $data['features'] ) ) );
            }
        }

        // サニタイズ
        $sanitized = $this->sanitize_crypto_data( $cryptos );

        $this->debug_log( 'Sanitized crypto data', array(
            'slugs' => array_keys( $sanitized ),
            'count' => count( $sanitized ),
        ) );

        // 保存
        $current = get_option( 'soico_cta_crypto_data', array() );
        $result = update_option( 'soico_cta_crypto_data', $sanitized );

        // データが同じ場合は成功とみなす
        if ( ! $result && $current === $sanitized ) {
            $this->debug_log( 'Crypto data unchanged, treating as success' );
            $result = true;
        }

        $this->debug_log( 'Save result', array( 'result' => $result ) );

        // キャッシュクリア
        Soico_CTA_Securities_Data::get_instance()->clear_crypto_cache();

        if ( $result ) {
            wp_send_json_success( array( 'message' => '保存しました' ) );
        } else {
            wp_send_json_error( array( 'message' => '保存に失敗しました' ) );
        }
    }

    /**
     * AJAX: 仮想通貨取引所追加
     */
    public function ajax_add_crypto() {
        check_ajax_referer( 'soico_cta_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません' ) );
        }

        $slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
        $name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';

        if ( empty( $slug ) || empty( $name ) ) {
            wp_send_json_error( array( 'message' => 'スラッグと名前は必須です' ) );
        }

        $cryptos = get_option( 'soico_cta_crypto_data', array() );

        // 既存チェック
        if ( isset( $cryptos[ $slug ] ) ) {
            wp_send_json_error( array( 'message' => '既に存在するスラッグです' ) );
        }

        // 優先順位を最後に設定
        $max_priority = 0;
        foreach ( $cryptos as $item ) {
            $max_priority = max( $max_priority, $item['priority'] ?? 0 );
        }

        $cryptos[ $slug ] = array(
            'name'         => $name,
            'slug'         => $slug,
            'priority'     => $max_priority + 1,
            'enabled'      => true,
            'thirsty_link' => 0,
            'direct_url'   => '',
            'features'     => array(),
            'trading_fee'  => '',
            'coins_count'  => '',
            'min_amount'   => '',
            'badge'        => '',
            'badge_color'  => '',
            'button_text'  => '',
            'button_color' => '#F7931A',
        );

        $result = update_option( 'soico_cta_crypto_data', $cryptos );

        // キャッシュクリア
        Soico_CTA_Securities_Data::get_instance()->clear_crypto_cache();

        if ( $result ) {
            wp_send_json_success( array( 'message' => '追加しました', 'reload' => true ) );
        } else {
            wp_send_json_error( array( 'message' => '追加に失敗しました' ) );
        }
    }

    /**
     * AJAX: 仮想通貨取引所削除
     */
    public function ajax_delete_crypto() {
        check_ajax_referer( 'soico_cta_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません' ) );
        }

        $slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';

        if ( empty( $slug ) ) {
            wp_send_json_error( array( 'message' => 'スラッグが必要です' ) );
        }

        $cryptos = get_option( 'soico_cta_crypto_data', array() );

        if ( ! isset( $cryptos[ $slug ] ) ) {
            wp_send_json_error( array( 'message' => '指定された取引所が見つかりません' ) );
        }

        unset( $cryptos[ $slug ] );

        $result = update_option( 'soico_cta_crypto_data', $cryptos );

        // キャッシュクリア
        Soico_CTA_Securities_Data::get_instance()->clear_crypto_cache();

        if ( $result ) {
            wp_send_json_success( array( 'message' => '削除しました' ) );
        } else {
            wp_send_json_error( array( 'message' => '削除に失敗しました' ) );
        }
    }
}
