<?php
/**
 * SOICO CTA v2 ブロックレンダラー
 *
 * 3レイヤーHTML装飾 + Mustacheテンプレートによるv2レンダリング
 *
 * レイヤー1: ブロック単位 (customHtmlBefore/Inner/After, fullCustomHtml)
 * レイヤー2: 会社単位 (company custom_html)
 * レイヤー3: ブロック種別単位 (テンプレートファイル or DB上書き)
 *
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Soico_CTA_Block_Register_V2 {

    /** @var Soico_CTA_Template_Engine */
    private $engine;

    /** @var Soico_CTA_Securities_Data */
    private $data;

    /** @var string テンプレートディレクトリ */
    private $template_dir;

    /**
     * ブロック種別と対応するテンプレート名のマッピング
     * cardloan系ブロックも同じテンプレートを共有（カテゴリ属性で区別）
     */
    private const BLOCK_TEMPLATE_MAP = [
        'conclusion-box'           => 'conclusion-box',
        'inline-cta'               => 'inline-cta',
        'single-button'            => 'single-button',
        'comparison-table'         => 'comparison-table',
        'subtle-banner'            => 'subtle-banner',
        'cardloan-conclusion-box'  => 'conclusion-box',
        'cardloan-inline-cta'      => 'inline-cta',
        'cardloan-single-button'   => 'single-button',
        'cardloan-comparison-table'=> 'comparison-table',
        'cardloan-subtle-banner'   => 'subtle-banner',
        'crypto-conclusion-box'    => 'conclusion-box',
        'crypto-inline-cta'        => 'inline-cta',
        'crypto-single-button'     => 'single-button',
        'crypto-comparison-table'  => 'comparison-table',
        'crypto-subtle-banner'     => 'subtle-banner',
    ];

    public function __construct() {
        $this->engine = new Soico_CTA_Template_Engine();
        $this->data = Soico_CTA_Securities_Data::get_instance();
        $this->template_dir = SOICO_CTA_PLUGIN_DIR . 'templates/';

        add_action('wp_enqueue_scripts', [$this, 'enqueue_v2_assets']);
    }

    /**
     * v2用CSS/JSを条件付きで読み込み
     */
    public function enqueue_v2_assets() {
        // v2ブロックが存在するページでのみ読み込み
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post || !has_blocks($post->post_content)) {
            return;
        }

        // v2ブロックの存在チェック（version="2"を含むか）
        if (strpos($post->post_content, '"version":"2"') !== false ||
            strpos($post->post_content, '"version": "2"') !== false) {
            wp_enqueue_style(
                'soico-cta-v2',
                SOICO_CTA_PLUGIN_URL . 'assets/css/frontend-v2.css',
                [],
                SOICO_CTA_VERSION . '.v2'
            );
        }
    }

    /**
     * メインレンダリングメソッド
     * class-block-register.phpの各render関数から呼び出される
     *
     * @param string $block_type ブロック種別（例: 'conclusion-box', 'cardloan-inline-cta'）
     * @param array  $attributes ブロック属性
     * @return string レンダリング済みHTML
     */
    public function render(string $block_type, array $attributes): string {
        $is_cardloan = strpos($block_type, 'cardloan-') === 0;
        $is_crypto = strpos($block_type, 'crypto-') === 0;
        $category = $is_crypto ? 'crypto' : ($is_cardloan ? 'cardloan' : 'securities');

        // 比較表は複数会社を処理
        if ($this->is_comparison_block($block_type)) {
            return $this->render_comparison($block_type, $attributes, $category);
        }

        // 単一会社ブロック（cryptoは "exchange" 属性を使用）
        $company_slug = $is_crypto ? ($attributes['exchange'] ?? '') : ($attributes['company'] ?? '');
        $company = $this->get_company($company_slug, $is_cardloan, $is_crypto);

        if (!$company) {
            return '<!-- SOICO CTA: company not found -->';
        }

        // URLオーバーライド判定（直接URL → ThirstyAffiliates → デフォルト）
        $url_override = $this->resolve_url_override($attributes);
        if ($url_override) {
            $company['affiliate_url'] = $url_override;
        }

        $context = $this->build_context($block_type, $attributes, $company, $category);

        // フルカスタムモード
        if (!empty($attributes['fullCustomMode']) && !empty($attributes['fullCustomHtml'])) {
            $html = $this->engine->render($attributes['fullCustomHtml'], $context);
        } else {
            $template = $this->load_template($block_type, $attributes['variant'] ?? 'default');
            $html = $this->engine->render($template, $context);
        }

        return $this->wrap_output($html, $block_type, $attributes, $company, $category);
    }

    /**
     * 比較テーブル（カード型）のレンダリング
     */
    private function render_comparison(string $block_type, array $attributes, string $category): string {
        $is_cardloan = $category === 'cardloan';
        $is_crypto = $category === 'crypto';
        // cryptoは "exchanges" 属性を使用
        $companies_slugs = $is_crypto ? ($attributes['exchanges'] ?? []) : ($attributes['companies'] ?? []);
        $limit = $attributes['limit'] ?? 3;

        // 会社データ取得
        if (empty($companies_slugs)) {
            if ($is_crypto) {
                $companies = $this->data->get_enabled_cryptos($limit);
            } elseif ($is_cardloan) {
                $companies = $this->data->get_enabled_cardloans($limit);
            } else {
                $companies = $this->data->get_enabled_securities($limit);
            }
        } else {
            $companies = [];
            foreach (array_slice($companies_slugs, 0, $limit) as $slug) {
                $company = $this->get_company($slug, $is_cardloan, $is_crypto);
                if ($company) {
                    $companies[$slug] = $company;
                }
            }
        }

        if (empty($companies)) {
            return '<!-- SOICO CTA: no companies found -->';
        }

        // 会社ごとのURLオーバーライドを適用
        foreach ($companies as $cmp_slug => &$cmp_data) {
            $url_override = $this->resolve_url_override_for($attributes, $cmp_slug);
            if ($url_override) {
                $cmp_data['affiliate_url'] = $url_override;
            }
        }
        unset($cmp_data);

        // 各会社のカードデータを構築
        $cards = [];
        $rank = 1;
        foreach ($companies as $slug => $company) {
            $tracking_attrs = $is_cardloan
                ? $this->data->get_cardloan_tracking_attributes($slug, 'comparison-table')
                : $this->data->get_tracking_attributes($slug, 'comparison-table');

            $button_text = $company['button_text'] ?: ($rank === 1 ? ($is_cardloan ? '申し込む' : '口座開設') : '詳細を見る');

            $card = [
                'rank'            => $rank,
                'rank_class'      => $this->get_rank_class($rank),
                'is_first'        => $rank === 1,
                'company_name'    => $company['name'] ?? '',
                'company_slug'    => $slug,
                'affiliate_url'   => $company['affiliate_url'] ?? '#',
                'features'        => $company['features'] ?? [],
                'features_text'   => implode(' / ', array_slice($company['features'] ?? [], 0, 3)),
                'commission'      => $company['commission'] ?? '',
                'interest_rate'   => $company['interest_rate'] ?? '',
                'limit_amount'    => $company['limit_amount'] ?? '',
                'review_time'     => $company['review_time'] ?? '',
                'badge'           => $company['badge'] ?? '',
                'badge_color'     => $company['badge_color'] ?? '',
                'has_badge'       => !empty($company['badge']),
                'button_text'     => $button_text,
                'button_color'    => $company['button_color'] ?? '#164C95',
                'tracking_attrs'  => $tracking_attrs,
                'company_html'    => $company['custom_html'] ?? '',
                'logo_url'        => $company['logo_url'] ?? '',
                'has_logo'        => !empty($company['logo_url']),
                'description'     => $company['description'] ?? '',
            ];

            // ボタンHTMLを生成
            $card['button'] = $this->build_button_html($card);

            $cards[] = $card;
            $rank++;
        }

        $context = [
            'cards'               => $cards,
            'category'            => $category,
            'is_cardloan'         => $is_cardloan,
            'show_commission'     => !empty($attributes['showCommission']),
            'show_interest_rate'  => !empty($attributes['showInterestRate']),
            'show_limit_amount'   => !empty($attributes['showLimitAmount']),
            'show_review_time'    => !empty($attributes['showReviewTime']),
            'current_date'        => wp_date('Y年n月'),
            'custom_html_before'  => $attributes['customHtmlBefore'] ?? '',
            'custom_html_after'   => $attributes['customHtmlAfter'] ?? '',
            'custom_html_inner'   => $attributes['customHtmlInner'] ?? '',
        ];

        // フルカスタムモード
        if (!empty($attributes['fullCustomMode']) && !empty($attributes['fullCustomHtml'])) {
            $html = $this->engine->render($attributes['fullCustomHtml'], $context);
        } else {
            $template = $this->load_template($block_type, $attributes['variant'] ?? 'default');
            $html = $this->engine->render($template, $context);
        }

        $variant = $attributes['variant'] ?? 'default';

        return sprintf(
            '<div class="soico-cta-v2 soico-cta-v2-comparison" data-version="2" data-variant="%s" data-category="%s">%s</div>',
            esc_attr($variant),
            esc_attr($category),
            $html
        );
    }

    /**
     * 単一会社ブロック用のテンプレートコンテキスト構築
     */
    private function build_context(string $block_type, array $attributes, array $company, string $category): array {
        $is_cardloan = $category === 'cardloan';
        $slug = $company['slug'] ?? ($attributes['company'] ?? '');

        $tracking_attrs = $is_cardloan
            ? $this->data->get_cardloan_tracking_attributes($slug, $block_type)
            : $this->data->get_tracking_attributes($slug, $block_type);

        // 特徴リスト: カスタム指定があればそちらを優先
        $features = $company['features'] ?? [];
        if (!empty($attributes['customFeatures'])) {
            $features = array_filter(array_map('trim', explode("\n", $attributes['customFeatures'])));
        }

        // タイトル: カスタム指定があればそちらを優先
        $default_title = $is_cardloan
            ? 'カードローンなら' . ($company['name'] ?? '') . 'がおすすめ'
            : ($company['name'] ?? '') . 'がおすすめ';
        $title = !empty($attributes['customTitle']) ? $attributes['customTitle'] : $default_title;

        // ボタンテキスト
        $button_text = !empty($attributes['buttonText'])
            ? $attributes['buttonText']
            : (!empty($company['button_text']) ? $company['button_text'] : ($is_cardloan ? '申し込む' : '口座開設はこちら'));

        // 注釈テキスト
        $is_crypto = $category === 'crypto';
        $default_note = $is_crypto ? '※最短即日で口座開設 ※各種手数料無料' : ($is_cardloan ? '※最短即日融資 ※WEB完結可能' : '※最短5分で申込完了 ※口座開設・維持費無料');
        $note = !empty($attributes['customNote']) ? $attributes['customNote'] : $default_note;

        // ラベルテキスト
        $label = !empty($attributes['customLabel']) ? $attributes['customLabel'] : '結論';

        $context = [
            // 会社データ
            'company_name'    => $company['name'] ?? '',
            'company_slug'    => $slug,
            'affiliate_url'   => $company['affiliate_url'] ?? '#',
            'features'        => $features,
            'features_text'   => implode(' / ', array_slice($features, 0, 3)),
            'commission'      => $company['commission'] ?? '',
            'interest_rate'   => $company['interest_rate'] ?? '',
            'limit_amount'    => $company['limit_amount'] ?? '',
            'review_time'     => $company['review_time'] ?? '',
            'badge'           => $company['badge'] ?? '',
            'badge_color'     => $company['badge_color'] ?? '',
            'has_badge'       => !empty($company['badge']),
            'logo_url'        => $company['logo_url'] ?? '',
            'has_logo'        => !empty($company['logo_url']),
            'description'     => $company['description'] ?? '',

            // テキスト
            'title'           => $title,
            'label'           => $label,
            'button_text'     => $button_text,
            'note'            => $note,

            // フラグ
            'show_features'   => !empty($attributes['showFeatures']),
            'show_pr'         => $attributes['showPR'] ?? true,
            'is_cardloan'     => $is_cardloan,
            'category'        => $category,

            // カスタムHTML（レイヤー1: ブロック単位）
            'custom_html_before' => $attributes['customHtmlBefore'] ?? '',
            'custom_html_inner'  => $attributes['customHtmlInner'] ?? '',
            'custom_html_after'  => $attributes['customHtmlAfter'] ?? '',

            // カスタムHTML（レイヤー2: 会社単位）
            'company_html'    => $company['custom_html'] ?? '',

            // トラッキング
            'tracking_attrs'  => $tracking_attrs,

            // ユーティリティ
            'current_date'    => wp_date('Y年n月'),
            'pr_label'        => '<span class="soico-v2__pr">PR</span>',
        ];

        // ボタンHTMLを生成
        $context['button'] = $this->build_button_html($context);

        // 特徴テキスト（inline-cta用）
        if (!empty($attributes['featureText'])) {
            $context['feature_text'] = $attributes['featureText'];
        } elseif (!empty($features)) {
            $context['feature_text'] = $features[0];
        } else {
            $context['feature_text'] = '';
        }

        // メッセージ（subtle-banner用）
        $context['message'] = $attributes['message'] ?? '';

        return $context;
    }

    /**
     * ボタンHTMLを生成
     */
    private function build_button_html(array $context): string {
        $url = esc_url($context['affiliate_url'] ?? '#');
        $text = esc_html($context['button_text'] ?? '詳細を見る');
        $tracking = $context['tracking_attrs'] ?? '';

        return sprintf(
            '<a href="%s" class="soico-v2__button" target="_blank" rel="noopener noreferrer sponsored"%s>%s</a>',
            $url,
            $tracking,
            $text
        );
    }

    /**
     * 出力HTMLをラッパーで囲む
     */
    private function wrap_output(string $html, string $block_type, array $attributes, array $company, string $category): string {
        $template_name = self::BLOCK_TEMPLATE_MAP[$block_type] ?? $block_type;
        $variant = $attributes['variant'] ?? 'default';
        $slug = $company['slug'] ?? '';

        return sprintf(
            '<div class="soico-cta-v2 soico-cta-v2-%s" data-version="2" data-variant="%s" data-category="%s" data-company="%s">%s</div>',
            esc_attr($template_name),
            esc_attr($variant),
            esc_attr($category),
            esc_attr($slug),
            $html
        );
    }

    /**
     * テンプレートを読み込み
     * DB上書き > ファイルデフォルトの優先順
     */
    private function load_template(string $block_type, string $variant = 'default'): string {
        $template_name = self::BLOCK_TEMPLATE_MAP[$block_type] ?? $block_type;

        // 1. DB上書きテンプレートをチェック
        $db_templates = get_option('soico_cta_v2_templates', []);
        $db_key = $template_name . '_' . $variant;
        if (!empty($db_templates[$db_key])) {
            return $db_templates[$db_key];
        }

        // バリアント付きファイルをチェック
        if ($variant !== 'default') {
            $variant_file = $this->template_dir . $template_name . '-' . $variant . '.html';
            if (file_exists($variant_file)) {
                return file_get_contents($variant_file);
            }
        }

        // 2. デフォルトテンプレートファイル
        $file_path = $this->template_dir . $template_name . '.html';
        if (file_exists($file_path)) {
            return file_get_contents($file_path);
        }

        return '<!-- SOICO CTA: template not found for ' . esc_html($template_name) . ' -->';
    }

    /**
     * 会社データを取得
     */
    private function get_company(string $slug, bool $is_cardloan, bool $is_crypto = false): ?array {
        if (empty($slug)) {
            return null;
        }
        if ($is_crypto) {
            return $this->data->get_crypto($slug);
        }
        return $is_cardloan ? $this->data->get_cardloan($slug) : $this->data->get_security($slug);
    }

    /**
     * 比較ブロックかどうか
     */
    private function is_comparison_block(string $block_type): bool {
        return in_array($block_type, ['comparison-table', 'cardloan-comparison-table', 'crypto-comparison-table'], true);
    }

    /**
     * 順位に応じたCSSクラスを返す
     */
    private function get_rank_class(int $rank): string {
        switch ($rank) {
            case 1: return 'soico-v2__rank--gold';
            case 2: return 'soico-v2__rank--silver';
            case 3: return 'soico-v2__rank--bronze';
            default: return 'soico-v2__rank--default';
        }
    }

    /**
     * ブロック属性のURL差し替えを解決する（単一会社用）
     *
     * 優先順位:
     *   1. customAffiliateUrl  （任意URL直接入力）
     *   2. customThirstyLinkId （ThirstyAffiliatesリンクID）
     *   3. null               （呼び出し元で従来のデータ層URLを使う）
     *
     * @param array $attributes ブロック属性
     * @return string|null
     */
    private function resolve_url_override(array $attributes): ?string {
        if (!empty($attributes['customAffiliateUrl'])) {
            $url = esc_url_raw($attributes['customAffiliateUrl']);
            if ($url) {
                return $url;
            }
        }

        if (!empty($attributes['customThirstyLinkId']) && intval($attributes['customThirstyLinkId']) > 0) {
            $thirsty = Soico_CTA_Thirsty_Integration::get_instance();
            $url = $thirsty->get_affiliate_url(intval($attributes['customThirstyLinkId']));
            if ($url) {
                return $url;
            }
        }

        return null;
    }

    /**
     * 比較表用: 会社ごとのURL差し替えを解決する
     *
     * @param array  $attributes ブロック属性
     * @param string $slug       会社/取引所スラッグ
     * @return string|null
     */
    private function resolve_url_override_for(array $attributes, string $slug): ?string {
        $custom_urls = !empty($attributes['customAffiliateUrls']) ? (array) $attributes['customAffiliateUrls'] : [];
        if (!empty($custom_urls[$slug])) {
            $url = esc_url_raw($custom_urls[$slug]);
            if ($url) {
                return $url;
            }
        }

        $custom_ids = !empty($attributes['customThirstyLinkIds']) ? (array) $attributes['customThirstyLinkIds'] : [];
        if (!empty($custom_ids[$slug]) && intval($custom_ids[$slug]) > 0) {
            $thirsty = Soico_CTA_Thirsty_Integration::get_instance();
            $url = $thirsty->get_affiliate_url(intval($custom_ids[$slug]));
            if ($url) {
                return $url;
            }
        }

        return null;
    }
}
