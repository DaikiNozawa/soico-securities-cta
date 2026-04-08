# SOICO Securities CTA プラグイン — Claude引き継ぎプロンプト

以下をClaudeに渡して、本プロジェクトの開発を継続してください。

---

## プロンプト

```
あなたはSOICO Securities CTAプラグインの開発を引き継ぎます。
以下の仕様と現状を理解した上で、指示された作業を行ってください。

## プロジェクト概要

WordPress Gutenbergブロックプラグイン。証券・カードローン・仮想通貨のCTA（Call-to-Action）コンポーネントを提供する。
soico.jp（https://www.soico.jp/no1/）で本番運用中。

- リポジトリ: https://github.com/DaikiNozawa/soico-securities-cta
- 本番サイト: https://www.soico.jp/no1/
- サーバー: Xserver sv8169.xserver.jp（ユーザー: soico、SSHポート: 10022）
- プラグインパス: /home/soico/soico.jp/public_html/no1/wp-content/plugins/soico-securities-cta
- WordPressテーマ: SWELL

## 技術スタック

- PHP 8.1（ビルドプロセス不要、素のPHP + JS + CSS）
- WordPress 6.6+、Gutenberg Block API v3
- サーバーサイドレンダリング（PHPでHTML出力、JSXなし）
- ThirstyAffiliates連携（アフィリエイトリンク管理）
- GTMイベントトラッキング

## ファイル構成

```
soico-securities-cta/
├── soico-securities-cta.php          # メインプラグインファイル（エントリポイント）
├── includes/
│   ├── class-admin-settings.php      # 管理画面（証券・カードローン）
│   ├── class-admin-crypto.php        # 管理画面（仮想通貨）★新規
│   ├── class-block-register.php      # v1ブロック登録 + render関数（15ブロック）
│   ├── class-block-register-v2.php   # v2レンダラー（テンプレートエンジン経由）★新規
│   ├── class-template-engine.php     # Mustache風テンプレートエンジン ★新規
│   ├── class-securities-data.php     # データ管理（証券・カードローン・仮想通貨）
│   └── class-thirsty-integration.php # ThirstyAffiliates連携
├── blocks/                           # block.json定義（15ブロック）
│   ├── conclusion-box/               # 証券 結論ボックス
│   ├── inline-cta/                   # 証券 インラインCTA
│   ├── single-button/                # 証券 CTAボタン
│   ├── comparison-table/             # 証券 比較表
│   ├── subtle-banner/                # 証券 控えめバナー
│   ├── cardloan-*/                   # カードローン版（同構成 x5）
│   └── crypto-*/                     # 仮想通貨版（同構成 x5）
├── templates/                        # v2用HTMLテンプレート ★新規
│   ├── conclusion-box.html
│   ├── inline-cta.html
│   ├── single-button.html
│   ├── comparison-table.html
│   └── subtle-banner.html
├── assets/
│   ├── css/
│   │   ├── frontend.css              # v1フロントエンドCSS
│   │   ├── frontend-v2.css           # v2フロントエンドCSS ★新規
│   │   ├── editor.css                # エディタCSS
│   │   └── admin.css                 # 管理画面CSS
│   └── js/
│       ├── editor.js                 # v1エディタJS
│       ├── editor-v2.js              # v2エディタ拡張（version/variant/HTML装飾UI）★新規
│       ├── admin.js                  # 管理画面JS
│       └── frontend.js               # フロントエンドJS
└── dev/                              # 開発ツール
    ├── deploy.sh                     # Xserverデプロイスクリプト
    └── wp-api.sh                     # REST APIヘルパー
```

## v1/v2バージョンシステム

既存CTAを壊さずに新デザインに移行するため、バージョン属性方式を採用。

### 動作原理
- 全ブロックに `version` 属性あり（デフォルト: "1"）
- 各render関数の先頭でversion分岐:
  ```php
  if ( ( $attributes['version'] ?? '1' ) === '2' && $this->v2_renderer ) {
      return $this->v2_renderer->render( 'block-type', $attributes );
  }
  // 以下v1の既存コード
  ```
- v1: 既存のPHP直書きHTML（class-block-register.php内）
- v2: テンプレートエンジン経由（class-block-register-v2.php → templates/*.html）
- GutenbergエディタでCTAバージョンパネルからv1/v2を切替可能

### v2の3レイヤーHTML装飾
1. **ブロック単位（Gutenbergエディタ）**: customHtmlBefore/Inner/After, fullCustomMode/fullCustomHtml
2. **会社単位（管理画面）**: custom_html フィールド（未実装、データモデルに予約）
3. **ブロック種別単位（テンプレート管理）**: templates/*.html のカスタマイズ（管理画面UI未実装）

### v2テンプレートエンジン構文
- `{{variable}}` — HTMLエスケープ済み変数
- `{{{variable}}}` — 生HTML（エスケープなし）
- `{{#if variable}}...{{/if}}` — 条件分岐
- `{{#unless variable}}...{{/unless}}` — 逆条件
- `{{#each array}}...{{/each}}` — ループ（{{this}}, {{@index}}, {{@number}}）

### v2で使える変数
{{company_name}}, {{company_slug}}, {{affiliate_url}}, {{features}}, {{features_text}},
{{commission}}, {{interest_rate}}, {{limit_amount}}, {{review_time}},
{{badge}}, {{button}}, {{button_text}}, {{note}}, {{label}},
{{custom_html_before}}, {{custom_html_inner}}, {{custom_html_after}},
{{company_html}}, {{pr_label}}, {{tracking_attrs}}, {{current_date}}

## ブロック一覧（15ブロック）

### 証券ブロック（属性: company）
- soico-cta/conclusion-box — 結論ボックス
- soico-cta/inline-cta — インラインCTA
- soico-cta/single-button — CTAボタン
- soico-cta/comparison-table — 比較表（属性: companies配列）
- soico-cta/subtle-banner — 控えめバナー

### カードローンブロック（属性: company）
- soico-cta/cardloan-* — 同構成5種
- 追加フィールド: interest_rate, limit_amount, review_time

### 仮想通貨ブロック（属性: exchange ※companyではない）
- soico-cta/crypto-* — 同構成5種
- 追加フィールド: trading_fee, coins_count, min_amount
- 比較表の属性: exchanges配列（companiesではない）
- 本番で1,522個のCTAが使用中

## データ管理

### wp_options に保存されるデータ
- `soico_cta_securities_data` — 証券会社データ（slug → {name, features, commission, ...}）
- `soico_cta_cardloan_data` — カードローンデータ
- `soico_cta_crypto_data` — 仮想通貨取引所データ（bitflyer, gmo_coin, sbi_vc, coincheck）
- `soico_cta_design_settings` / `soico_cta_cardloan_design_settings` — デザイン設定
- `soico_cta_tracking_settings` / `soico_cta_cardloan_tracking_settings` — トラッキング設定
- `soico_cta_v2_templates` — v2テンプレート上書き（管理画面UI未実装）

## デザイン方針

### v1（現行）
- 証券: オレンジ系（#FF6B35）グラデーション、影
- カードローン: グリーン系（#00A95F）
- 仮想通貨: ビットコインオレンジ系（#F7931A）— 証券のCSSクラスを再利用

### v2（新デザイン、未展開）
- soico.jpのコーポレートブルー（#164C95）ベース
- フラット+微細ボーダー、SWELLテーマ統一
- 比較表: テーブル廃止 → カード型レイアウト
- CSS: frontend-v2.css（[data-version="2"]セレクタ）

## SWELLテーマとの注意点

- `a`タグの色がテーマCSSで上書きされるため、ボタンには `color: #fff !important` 必須
- `h2`/`h3`タグはez-toc（目次プラグイン）が拾うため、CTA内では`p`タグを使用
- テーブルスタイルの干渉: `!important`で上書き済み
- `.editor-content`内のスタイル競合に注意

## 開発ワークフロー

### デプロイ
```bash
cd ~/Desktop/soico-securities-cta
./dev/deploy.sh sync        # サーバーにコード同期（--deleteなし、安全）
./dev/deploy.sh sync-clean  # 完全同期（確認付き、サーバー側のみのファイルを削除）
./dev/deploy.sh test-ssh    # SSH接続テスト
./dev/deploy.sh watch       # ファイル変更監視＆自動同期
```

### REST API確認
```bash
./dev/wp-api.sh check          # 疎通チェック
./dev/wp-api.sh posts          # 最新投稿一覧
./dev/wp-api.sh find-cta       # CTAブロック使用投稿を検索
./dev/wp-api.sh plugins        # プラグイン一覧（認証必要）
./dev/wp-api.sh create-test-post  # テスト用下書き作成
```

### 重要な注意事項
- deploy.sh sync は --delete なし。サーバー側のみのファイルは消えない
- .env に認証情報あり（gitignore済み）
- 本番サーバーに直接デプロイするため、構文エラーは事前にチェック
- 既存のCTAを壊さないこと（version未指定=v1は常に旧コードパスを通る）

## 開発ロードマップ（残タスク）

### Phase 1 ✅ 完了
- v2レンダラー + テンプレートエンジン
- v2 CSS（soico.jp統一デザイン）
- Gutenberg v2設定パネル（version/variant/HTML装飾）
- block.json v2属性追加（全15ブロック）
- 既存render関数のversion分岐
- 仮想通貨ブロック復旧（5ブロック + 管理画面）

### Phase 2（次のステップ）
- 管理画面拡張: テンプレート管理画面（ブロック種別ごとのHTMLテンプレート編集 + プレビュー）
- 管理画面拡張: カスタムCSS入力欄
- 管理画面拡張: 会社データにcustom_html, logo_url, descriptionフィールド追加
- v2デザインの実際の適用と微調整

### Phase 3
- 計測基盤: クリック・インプレッション記録テーブル（soico_cta_events）
- カスタムREST APIエンドポイント:
  - GET /soico-cta/v1/analytics — CTR/インプレッション集計
  - GET /soico-cta/v1/blocks/usage — ブロック使用状況
  - PATCH /soico-cta/v1/company/:slug — 会社データ部分更新
- フロントエンドJS: Intersection Observer + クリック計測（tracking.js）

### Phase 4
- A/Bテスト機構（soico_cta_ab_testsテーブル）
- REST API: POST /soico-cta/v1/ab-test, GET /soico-cta/v1/ab-test/:id/result
- AI連携: POST /soico-cta/v1/optimize（母艦システム or 外部AIから呼び出し）
- Webhook（CTAクリック時のリアルタイム外部通知）
```

---

## 使い方

上記のプロンプトをClaude（Claude Code等）に渡した上で、以下のように指示してください：

**例1:** 「Phase 2のテンプレート管理画面を実装してください」
**例2:** 「v2デザインをbitFlyer記事でテストしたい。version=2に切り替えて確認してください」
**例3:** 「仮想通貨CTAの比較表デザインを改善してください」
**例4:** 「Phase 3の計測基盤を実装してください」
