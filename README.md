# SOICO Securities CTA

証券アフィリエイト用WordPress Gutenbergブロックプラグイン

## 概要

証券会社のアフィリエイトリンクを効果的に配置するためのGutenbergブロックを提供します。結論ボックス、インラインCTA、比較表など、5種類のブロックを使い分けることで、読者に最適なタイミングでCTAを表示できます。

## 動作要件

- WordPress 6.0以上
- PHP 7.4以上

## インストール

1. プラグインフォルダを `/wp-content/plugins/soico-securities-cta/` にアップロード
2. WordPress管理画面「プラグイン」から有効化
3. 左メニューに「証券CTA」が追加されます

## クイックスタート

### 1. 証券会社を設定

1. 管理画面 → 証券CTA → 証券会社管理
2. 各証券会社の「詳細」をクリック
3. アフィリエイトリンクを設定（ThirstyAffiliateリンク or 直接URL）
4. 「変更を保存」をクリック

### 2. 記事でブロックを挿入

1. 投稿編集画面を開く
2. `/` を入力して「証券」と検索
3. 表示されたブロックを選択
4. 右サイドバーで証券会社とオプションを設定

## 利用可能なブロック

### 1. 結論ボックス

記事冒頭に最適な目立つCTA。証券会社の特徴リストとボタンを表示します。

**設定項目:**
- 証券会社を選択
- 特徴を表示（ON/OFF）
- カスタムタイトル

**使用例:** 「結論から言うと、〇〇証券がおすすめ」という記事構成

### 2. インラインCTA

記事の流れを邪魔しない控えめなCTA。途中で自然に挿入できます。

**設定項目:**
- 証券会社を選択
- スタイル（デフォルト/控えめ）

**使用例:** 証券会社名に言及したタイミングで挿入

### 3. CTAボタン

シンプルなボタンのみ。任意の場所に配置可能。

**設定項目:**
- 証券会社を選択
- ボタンテキスト
- PR表記を表示（ON/OFF）

**使用例:** 記事末尾やセクション終わり

### 4. 比較表

複数の証券会社をランキング形式で比較。

**設定項目:**
- 表示件数（1〜10）
- 手数料を表示（ON/OFF）

**使用例:** 「おすすめ証券会社ランキング」記事

### 5. 控えめバナー

テキストリンク形式の最も控えめなCTA。

**設定項目:**
- 証券会社を選択
- メッセージ

**使用例:** 記事内の補足情報

## 管理画面の設定

### 証券会社管理

| 項目 | 説明 |
|------|------|
| 有効 | ブロックで選択可能にするかどうか |
| ThirstyAffiliateリンク | ThirstyAffiliatesで作成したリンク（推奨） |
| 直接URL | ThirstyAffiliates未使用時のアフィリエイトURL |
| 特徴 | 1行ずつ入力。結論ボックスや比較表で表示 |
| 手数料 | 比較表で表示される手数料情報 |
| バッジ | 「おすすめ」などのラベル |
| ボタンテキスト | CTAボタンに表示するテキスト |
| ボタン色 | ボタンの背景色 |

**表示順の変更:** ドラッグ&ドロップで並べ替え可能

### デザイン設定

| 項目 | 説明 |
|------|------|
| メインカラー | CTAボタンの背景色（デフォルト: #FF6B35） |
| セカンダリカラー | ボーダーやアクセント色 |
| 角丸の半径 | ボタンやボックスの角の丸み |

### トラッキング設定

| 項目 | 説明 |
|------|------|
| GTMトラッキング | Google Tag Manager用のdata属性を出力 |
| イベントカテゴリ | GTMイベントのカテゴリ名 |
| イベントアクション | GTMイベントのアクション名 |

## ThirstyAffiliatesとの連携

アフィリエイトリンクの管理には[ThirstyAffiliates](https://wordpress.org/plugins/thirstyaffiliates/)プラグインの使用を推奨します。

**メリット:**
- リンクのクローク（短縮URL化）
- クリック計測
- リンク切れチェック
- 一括置換

## GTMでのイベント計測

GTMトラッキングを有効にすると、CTAボタンに以下のdata属性が付与されます：

```html
<a href="..."
   data-gtm-category="CTA Click"
   data-gtm-action="securities_affiliate"
   data-gtm-label="sbi"
   data-cta-type="conclusion_box">
```

GTMで計測する場合は、これらの属性をトリガーに使用してください。

## よくある質問

### Q: ブロックがエディタに表示されません

A: 以下を確認してください：
1. プラグインが有効化されているか
2. WordPress 6.0以上を使用しているか
3. ブラウザのキャッシュをクリア

### Q: CTAが記事に表示されません

A: 以下を確認してください：
1. 「証券会社管理」で該当の証券会社が「有効」になっているか
2. アフィリエイトURL（ThirstyAffiliateリンクまたは直接URL）が設定されているか

### Q: 色を変更したい

A: 2つの方法があります：
1. 「デザイン設定」でサイト全体のメインカラーを変更
2. 「証券会社管理」で各証券会社のボタン色を個別に設定

### Q: 表示順を変更したい

A: 「証券会社管理」でドラッグ&ドロップで並べ替えてください。比較表では優先順位の高い順に表示されます。

## 開発者向け情報

### ファイル構造

```
soico-securities-cta/
├── soico-securities-cta.php    # メインプラグインファイル
├── includes/
│   ├── class-block-register.php    # ブロック登録
│   ├── class-securities-data.php   # データ管理
│   ├── class-admin-settings.php    # 管理画面
│   └── class-thirsty-integration.php
├── blocks/
│   ├── conclusion-box/block.json
│   ├── inline-cta/block.json
│   ├── single-button/block.json
│   ├── comparison-table/block.json
│   └── subtle-banner/block.json
└── assets/
    ├── js/
    │   ├── editor.js     # ブロックエディタ
    │   ├── admin.js      # 管理画面
    │   └── frontend.js   # フロントエンド
    └── css/
        ├── editor.css
        ├── admin.css
        └── frontend.css
```

### フック

**フィルター:**
- `soico_cta_securities_data` - 証券会社データの取得時
- `block_categories_all` - ブロックカテゴリの登録

**アクション:**
- `init` - ブロック登録
- `enqueue_block_editor_assets` - エディタアセット読み込み

## 更新履歴

### 1.0.0
- 初回リリース
- 5種類のCTAブロック
- block.jsonベースのブロック登録（WordPress 6.6+推奨方式）
- ThirstyAffiliates連携
- GTMトラッキング対応

## ライセンス

GPL v2 or later

## 作者

SOICO Inc.
