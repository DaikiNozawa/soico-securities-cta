# SOICO Securities CTA — GAS連携仕様書

姉妹プロジェクト `anonymous-seo-a/cta`（Google Apps Script）から、本プラグインが提供する Gutenberg ブロックを WordPress REST API 経由で投稿に挿入するための仕様書。

| 項目 | 値 |
|---|---|
| 仕様書バージョン | **1.0.0** |
| 最終更新日 | 2026-04-09 |
| 対象プラグインバージョン | **soico-securities-cta v1.1.0** |
| 対象コミット | [`dd1dd73`](https://github.com/DaikiNozawa/soico-securities-cta/commit/dd1dd73) |
| 本番サイト | https://www.soico.jp/no1/ |

---

## 目次

1. [全15ブロック一覧](#1-全15ブロック一覧)
2. [属性スキーマ完全版](#2-属性スキーマ完全版)
3. [URL解決の優先順位](#3-url解決の優先順位)
4. [会社スラッグ一覧](#4-会社スラッグ一覧)
5. [ThirstyAffiliates 連携](#5-thirstyaffiliates-連携)
6. [GAS出力サンプル](#6-gas出力サンプル)
7. [既知の制約](#7-既知の制約)
8. [バージョン管理](#8-バージョン管理)

---

## 1. 全15ブロック一覧

ブロックは **証券5 + カードローン5 + 仮想通貨5 = 15種類**。各カテゴリ内に「結論ボックス・インラインCTA・単体ボタン・比較表・控えめバナー」の5タイプ。

| カテゴリ | ブロック名 (`name`) | 種別 | 用途 |
|---|---|---|---|
| 証券 (`soico-securities-cta`) | `soico-cta/conclusion-box` | 結論ボックス | 記事冒頭の結論型CTA。タイトル+特徴リスト+ボタン |
| 証券 | `soico-cta/inline-cta` | インラインCTA | 記事中段に挟む控えめなCTA |
| 証券 | `soico-cta/single-button` | 単体ボタン | シンプルなCTAボタンのみ |
| 証券 | `soico-cta/comparison-table` | 比較表 | 複数社の比較テーブル |
| 証券 | `soico-cta/subtle-banner` | 控えめバナー | テキストリンク形式の最小CTA |
| カードローン (`soico-cardloan-cta`) | `soico-cta/cardloan-conclusion-box` | 結論ボックス | カードローン版 |
| カードローン | `soico-cta/cardloan-inline-cta` | インラインCTA | カードローン版 |
| カードローン | `soico-cta/cardloan-single-button` | 単体ボタン | カードローン版 |
| カードローン | `soico-cta/cardloan-comparison-table` | 比較表 | カードローン版（金利・限度額・審査時間を表示可） |
| カードローン | `soico-cta/cardloan-subtle-banner` | 控えめバナー | カードローン版 |
| 仮想通貨 (`soico-crypto-cta`) | `soico-cta/crypto-conclusion-box` | 結論ボックス | 仮想通貨版 |
| 仮想通貨 | `soico-cta/crypto-inline-cta` | インラインCTA | 仮想通貨版 |
| 仮想通貨 | `soico-cta/crypto-single-button` | 単体ボタン | 仮想通貨版 |
| 仮想通貨 | `soico-cta/crypto-comparison-table` | 比較表 | 仮想通貨版（手数料・通貨数を表示可） |
| 仮想通貨 | `soico-cta/crypto-subtle-banner` | 控えめバナー | 仮想通貨版 |

> **属性名の規則**: 証券・カードローンは `company`（文字列）、仮想通貨だけは `exchange`（文字列）を使う。比較表は `companies` / `exchanges`（配列）。

---

## 2. 属性スキーマ完全版

各ブロックの `attributes` を [`blocks/*/block.json`](../blocks/) から完全に抽出。  
※ `version` / `variant` / `customHtml*` などの **v2 拡張属性** は `blocks/*/block.json` には記載されておらず、[`assets/js/editor-v2.js`](../assets/js/editor-v2.js) の `blocks.registerBlockType` フィルターで動的に追加される。GAS から渡す場合はブロックコメントの JSON に直接含めれば PHP 側で読まれる。

### 2.0 全ブロック共通の v2 拡張属性（editor-v2.js 経由で動的追加）

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `version` | string | `'1'` | `'2'` を指定すると V2 レンダラー（`class-block-register-v2.php`）を経由してテンプレート版で描画される |
| `variant` | string | `'default'` | テンプレートのバリアント（`templates/<block>-<variant>.html` を読み込む） |
| `customHtmlBefore` | string | `''` | レイヤー1: ブロック直前に差し込む生HTML |
| `customHtmlInner` | string | `''` | レイヤー1: ブロック内側に差し込む生HTML |
| `customHtmlAfter` | string | `''` | レイヤー1: ブロック直後に差し込む生HTML |
| `customNote` | string | `''` | 注釈テキスト上書き（v2 のみ） |
| `customLabel` | string | `''` | ラベルテキスト上書き（v2 のみ） |
| `fullCustomMode` | boolean | `false` | テンプレートを丸ごと `fullCustomHtml` で置き換える |
| `fullCustomHtml` | string | `''` | フルカスタムモード時の Mustache テンプレート |

> **重要**: GAS 連携で V2 デザインを使いたい場合は **必ず `"version": "2"` を含める**こと。省略すると V1（PHP直書きHTML）でレンダリングされる。本番では V2 が標準。

### 2.1 証券ブロック

#### `soico-cta/conclusion-box` （結論ボックス）

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `company` | string | `'sbi'` | 証券会社スラッグ。[§4.1](#41-証券会社) 参照 |
| `showFeatures` | boolean | `true` | 特徴リスト表示 |
| `customTitle` | string | `''` | カスタムタイトル（空ならデフォルト） |
| `customFeatures` | string | `''` | 改行区切りでカスタム特徴を上書き |
| `customAffiliateUrl` | string | `''` | URL差し替え（最優先） |
| `customThirstyLinkId` | number | `0` | ThirstyAffiliates リンクID（直接URL未指定時に使用） |

#### `soico-cta/inline-cta` （インラインCTA）

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `company` | string | `'sbi'` | 証券会社スラッグ |
| `style` | string | `'default'` | スタイル名（`default` / `subtle`） |
| `featureText` | string | `''` | 特徴テキスト1行（空なら会社データから自動取得） |
| `customAffiliateUrl` | string | `''` | URL差し替え |
| `customThirstyLinkId` | number | `0` | ThirstyAffiliates リンクID |

#### `soico-cta/single-button` （単体ボタン）

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `company` | string | `'sbi'` | 証券会社スラッグ |
| `buttonText` | string | `''` | ボタンテキスト（空なら会社データから） |
| `showPR` | boolean | `true` | PR表記を表示 |
| `customAffiliateUrl` | string | `''` | URL差し替え |
| `customThirstyLinkId` | number | `0` | ThirstyAffiliates リンクID |

#### `soico-cta/comparison-table` （比較表）

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `companies` | array | `['sbi','monex','rakuten']` | 表示する証券会社スラッグの配列 |
| `limit` | number | `3` | 表示件数 |
| `showCommission` | boolean | `true` | 手数料列を表示 |
| `customAffiliateUrls` | object | `{}` | `{slug: url}` 形式で会社ごとに URL 上書き |
| `customThirstyLinkIds` | object | `{}` | `{slug: link_id}` 形式で会社ごとに ThirstyAffiliates ID 指定 |

#### `soico-cta/subtle-banner` （控えめバナー）

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `company` | string | `'sbi'` | 証券会社スラッグ |
| `message` | string | `''` | カスタムメッセージ（空ならデフォルト） |
| `customAffiliateUrl` | string | `''` | URL差し替え |
| `customThirstyLinkId` | number | `0` | ThirstyAffiliates リンクID |

### 2.2 カードローンブロック

#### `soico-cta/cardloan-conclusion-box`

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `company` | string | `'aiful'` | カードローンスラッグ。[§4.2](#42-カードローン) 参照 |
| `showFeatures` | boolean | `true` | 特徴リスト表示 |
| `customTitle` | string | `''` | カスタムタイトル |
| `customFeatures` | string | `''` | カスタム特徴（改行区切り） |
| `buttonNote` | string | `''` | ボタン下注釈 |
| `customAffiliateUrl` | string | `''` | URL差し替え |
| `customThirstyLinkId` | number | `0` | ThirstyAffiliates リンクID |

#### `soico-cta/cardloan-inline-cta`

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `company` | string | `'aiful'` | カードローンスラッグ |
| `style` | string | `'default'` | スタイル名 |
| `featureText` | string | `''` | 特徴テキスト |
| `buttonText` | string | `''` | ボタンテキスト |
| `customAffiliateUrl` | string | `''` | URL差し替え |
| `customThirstyLinkId` | number | `0` | ThirstyAffiliates リンクID |

#### `soico-cta/cardloan-single-button`

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `company` | string | `'aiful'` | カードローンスラッグ |
| `buttonText` | string | `''` | ボタンテキスト |
| `showPR` | boolean | `true` | PR表記を表示 |
| `buttonNote` | string | `''` | ボタン下注釈 |
| `customAffiliateUrl` | string | `''` | URL差し替え |
| `customThirstyLinkId` | number | `0` | ThirstyAffiliates リンクID |

#### `soico-cta/cardloan-comparison-table`

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `companies` | array | `['aiful','promise','acom']` | 表示するカードローンスラッグ配列 |
| `limit` | number | `3` | 表示件数 |
| `showInterestRate` | boolean | `true` | 金利列を表示 |
| `showLimitAmount` | boolean | `true` | 限度額列を表示 |
| `showReviewTime` | boolean | `true` | 審査時間列を表示 |
| `customAffiliateUrls` | object | `{}` | `{slug: url}` 形式 |
| `customThirstyLinkIds` | object | `{}` | `{slug: link_id}` 形式 |

#### `soico-cta/cardloan-subtle-banner`

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `company` | string | `'aiful'` | カードローンスラッグ |
| `message` | string | `''` | カスタムメッセージ |
| `customAffiliateUrl` | string | `''` | URL差し替え |
| `customThirstyLinkId` | number | `0` | ThirstyAffiliates リンクID |

### 2.3 仮想通貨ブロック（属性名は `exchange` / `exchanges`）

#### `soico-cta/crypto-conclusion-box`

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `exchange` | string | `'gmo_coin'` | 取引所スラッグ。[§4.3](#43-仮想通貨取引所) 参照 |
| `showFeatures` | boolean | `true` | 特徴リスト表示 |
| `customTitle` | string | `''` | カスタムタイトル |
| `customFeatures` | string | `''` | カスタム特徴 |
| `customAffiliateUrl` | string | `''` | URL差し替え |
| `customThirstyLinkId` | number | `0` | ThirstyAffiliates リンクID |

#### `soico-cta/crypto-inline-cta`

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `exchange` | string | `'gmo_coin'` | 取引所スラッグ |
| `style` | string | `'default'` | スタイル名 |
| `featureText` | string | `''` | 特徴テキスト |
| `customAffiliateUrl` | string | `''` | URL差し替え |
| `customThirstyLinkId` | number | `0` | ThirstyAffiliates リンクID |

#### `soico-cta/crypto-single-button`

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `exchange` | string | `'gmo_coin'` | 取引所スラッグ |
| `buttonText` | string | `''` | ボタンテキスト |
| `showPR` | boolean | `true` | PR表記を表示 |
| `customAffiliateUrl` | string | `''` | URL差し替え |
| `customThirstyLinkId` | number | `0` | ThirstyAffiliates リンクID |

#### `soico-cta/crypto-comparison-table`

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `exchanges` | array | `['gmo_coin','coincheck','sbi_vc']` | 表示する取引所スラッグ配列 |
| `limit` | number | `3` | 表示件数 |
| `showFees` | boolean | `true` | 手数料列を表示 |
| `showCoins` | boolean | `true` | 通貨数列を表示 |
| `customAffiliateUrls` | object | `{}` | `{slug: url}` 形式 |
| `customThirstyLinkIds` | object | `{}` | `{slug: link_id}` 形式 |

#### `soico-cta/crypto-subtle-banner`

| 属性 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `exchange` | string | `'gmo_coin'` | 取引所スラッグ |
| `message` | string | `''` | カスタムメッセージ |
| `customAffiliateUrl` | string | `''` | URL差し替え |
| `customThirstyLinkId` | number | `0` | ThirstyAffiliates リンクID |

---

## 3. URL解決の優先順位

CTAボタンのリンク先 URL は、以下の優先順位で解決される。**V1 (`version="1"` または未指定) と V2 (`version="2"`) で経路が異なるが、優先順位ロジックは同一。**

### 3.1 単一会社ブロック（11個 + subtle-banner系3個 = 12個）

```
擬似コード:
function resolve_url(attributes, company_slug):
    # 1. 直接URL（最優先）
    if attributes.customAffiliateUrl is not empty:
        return esc_url_raw(attributes.customAffiliateUrl)

    # 2. ThirstyAffiliates リンクID
    if attributes.customThirstyLinkId > 0:
        url = thirsty_integration.get_affiliate_url(attributes.customThirstyLinkId)
        if url:
            return url     # 例: https://www.soico.jp/no1/recommends/<slug>/

    # 3. 会社データ層のデフォルト（管理画面で設定）
    company = get_company(company_slug)
    # 会社データ自体にも resolve ロジックがある:
    if company.thirsty_link > 0:
        return thirsty_integration.get_affiliate_url(company.thirsty_link)
    if company.direct_url is not empty:
        return company.direct_url

    return null  # → ブロックは「No affiliate_url」コメントのみ出力（リンク無し）
```

### 3.2 比較表ブロック（3個）

比較表は **会社ごと** に上記ロジックを適用：

```
function resolve_url_for(attributes, slug):
    # 1. 会社別 直接URL
    if attributes.customAffiliateUrls[slug] is not empty:
        return esc_url_raw(attributes.customAffiliateUrls[slug])

    # 2. 会社別 ThirstyAffiliates リンクID
    if attributes.customThirstyLinkIds[slug] > 0:
        return thirsty_integration.get_affiliate_url(attributes.customThirstyLinkIds[slug])

    # 3. 会社データ層のデフォルト（同上）
    return null
```

### 3.3 V1 と V2 の経路差

| version 属性 | レンダラー | URL解決を行う場所 |
|---|---|---|
| `'1'` または未指定 | [`includes/class-block-register.php`](../includes/class-block-register.php) の各 `render_*()` | `private resolve_affiliate_url()` / `resolve_affiliate_url_for()` |
| `'2'` | [`includes/class-block-register-v2.php`](../includes/class-block-register-v2.php) `render()` / `render_comparison()` | `private resolve_url_override()` / `resolve_url_override_for()` |

両クラスで実装は別だが**同じロジックを2重実装している**。属性名は両方で同じ (`customAffiliateUrl` / `customThirstyLinkId` / `customAffiliateUrls` / `customThirstyLinkIds`)。

### 3.4 「customAffiliateUrl 未指定なら何が使われるか」の総まとめ

| ケース | 結果 |
|---|---|
| `customAffiliateUrl` あり | 直接URL がそのまま使われる（最優先） |
| `customAffiliateUrl` 空 + `customThirstyLinkId` あり | ThirstyAffiliates のクローキングURL（例: `https://www.soico.jp/no1/recommends/<slug>/`） |
| 両方空 | 会社データ層の `thirsty_link` → `direct_url` の順 → どちらも空なら空白URL |

### 3.5 ThirstyAffiliates クローキングURL の形式

```
https://<wp_home_url>/<link_prefix>/<thirstylink_post_name>/
```

- `link_prefix`: ThirstyAffiliates 設定 `ta_settings['ta_link_prefix']`（本番は `recommends`）
- `<thirstylink_post_name>`: thirstylink 投稿の slug

例: 本番では link_id `16757` (`acom_checked` slug) → `https://www.soico.jp/no1/recommends/acom_checked/`

---

## 4. 会社スラッグ一覧

> **重要**: 証券・カードローンは **コードに会社スラッグが定義されていない**。WordPress オプション (`soico_cta_securities_data` / `soico_cta_cardloan_data` / `soico_cta_crypto_data`) に保存され、管理画面で編集される。**唯一コードでデフォルト初期化されているのは仮想通貨のみ**（[`includes/class-securities-data.php` L1073-1119](../includes/class-securities-data.php#L1073-L1119) の `initialize_crypto_defaults()`）。
>
> 以下は **（A）block.json の default 値に登場するスラッグ**と **（B）2026-04-09 時点の本番DBに登録されているスラッグ** の両方を併記する。

### 4.1 証券会社

#### コードのデフォルト（block.json `default` 値のみ）

| スラッグ | 出現箇所 |
|---|---|
| `sbi` | 各単体ブロックの `company` デフォルト |
| `monex` | `comparison-table` の `companies` デフォルト |
| `rakuten` | `comparison-table` の `companies` デフォルト |

#### 本番DB登録分（2026-04-09 取得）

| スラッグ | 名称 | enabled | thirsty_link ID | direct_url |
|---|---|---|---|---|
| `sbi` | SBI証券 | ✅ | 5550 | (空) |
| `rakuten` | 楽天証券 | ✅ | 5551 | (空) |
| `monex` | マネックス証券 | ✅ | 5556 | (空) |
| `matsui` | 松井証券 | ✅ | 5573 | (空) |
| `moomoo` | moomoo証券 | ✅ | 5574 | (空) |
| `okasan` | 岡三証券 | ✅ | 5575 | (空) |
| `mufjesmart` | 三菱UFJeスマート証券 | ✅ | 5555 | (空) |

→ **DB追加分**: `matsui`, `moomoo`, `okasan`, `mufjesmart`

### 4.2 カードローン

#### コードのデフォルト（block.json `default` 値のみ）

| スラッグ | 出現箇所 |
|---|---|
| `aiful` | 各単体ブロックの `company` デフォルト |
| `promise` | `cardloan-comparison-table` の `companies` デフォルト |
| `acom` | `cardloan-comparison-table` の `companies` デフォルト |

#### 本番DB登録分（2026-04-09 取得）

| スラッグ | 名称 | enabled | thirsty_link ID | direct_url |
|---|---|---|---|---|
| `aiful` | アイフル | ✅ | 6146 | (空) |
| `acom` | アコム | ✅ | 6145 | (空) |
| `promise` | プロミス | ✅ | 6148 | (空) |
| `lakealsa` | レイクALSA | ✅ | 6149 | (空) |
| `smbcmobit` | SMBCモビット | ✅ | 6151 | (空) |

→ **DB追加分**: `lakealsa`, `smbcmobit`

### 4.3 仮想通貨取引所

#### コードのデフォルト ([`initialize_crypto_defaults()`](../includes/class-securities-data.php#L1073-L1119))

| スラッグ | 名称 | コードでの初期値 |
|---|---|---|
| `gmo_coin` | GMOコイン | `direct_url`/`thirsty_link` 共に未設定（管理画面で要設定） |
| `coincheck` | コインチェック | 同上 |
| `sbi_vc` | SBI VCトレード | 同上 |

#### 本番DB登録分（2026-04-09 取得）

| スラッグ | 名称 | enabled | thirsty_link ID | direct_url |
|---|---|---|---|---|
| `gmo_coin` | GMOコイン | ✅ | 13289 | (空) |
| `coincheck` | コインチェック | ✅ | 13288 | (空) |
| `sbi_vc` | SBI VCトレード | ✅ | 13291 | (空) |
| `bitflyer` | bitFlyer | ✅ | 13287 | (空) |

→ **DB追加分**: `bitflyer`（コードのデフォルトには含まれていない）

> **GAS側への推奨**: スラッグはハードコードせず、`/wp-json/wp/v2/posts` に投稿する前に、別途プラグイン管理画面で登録された一覧をどこか（GASのスクリプトプロパティ等）に保持しておく。新規会社追加時はその一覧を更新する運用にする。

---

## 5. ThirstyAffiliates 連携

### 5.1 仕様

[`includes/class-thirsty-integration.php`](../includes/class-thirsty-integration.php) の `get_affiliate_url($link_id)` が中核：

```php
public function get_affiliate_url( $link_id ) {
    if ( empty( $link_id ) ) return false;
    if ( ! is_thirsty_active() ) return false;

    $post = get_post( absint($link_id) );
    if ( ! $post || $post->post_type !== 'thirstylink' ) return false;

    $link_prefix = get_option('ta_settings')['ta_link_prefix'] ?? 'recommends';
    $cloaked_url = home_url( '/' . $link_prefix . '/' . $post->post_name . '/' );

    // フォールバック: get_post_meta($link_id, '_ta_destination_url', true)
    return $cloaked_url ?: $destination;
}
```

### 5.2 GAS側で link_id を取得する方法

ThirstyAffiliates は WordPress カスタム投稿タイプ `thirstylink` を使う。標準の REST API で取得可能：

```
GET https://www.soico.jp/no1/wp-json/wp/v2/thirstylink/<link_id>
GET https://www.soico.jp/no1/wp-json/wp/v2/thirstylink?per_page=100&search=<keyword>
```

レスポンス例:
```json
{
  "id": 16757,
  "slug": "acom_checked",
  "title": { "rendered": "アコム_checked" }
}
```

→ この `id` を `customThirstyLinkId` に渡す。

### 5.3 実例

ID `16757` (`acom_checked`) を `cardloan-inline-cta` で指定すると：

```
https://www.soico.jp/no1/recommends/acom_checked/
```

に差し替わる。本番で動作確認済み（記事 [ID 7235](https://www.soico.jp/no1/?p=7235)）。

---

## 6. GAS出力サンプル

WordPress の `content` フィールドに渡すブロックHTMLは、Gutenberg のシリアライズ形式に従う：

```
<!-- wp:<block_name> <attributes_json> /-->
```

属性は **完全な有効JSON**（改行なし1行が安全）。`/-->` は **空ブロック（self-closing）** を表す。

### 6.1 証券インラインCTA

#### パターン1: ThirstyAffiliates ID 指定（推奨）

```html
<!-- wp:soico-cta/inline-cta {"company":"sbi","customThirstyLinkId":5550,"version":"2"} /-->
```

→ 本番DB登録の SBI証券 が表示され、リンクは ThirstyAffiliates ID 5550 のクローキングURL。

#### パターン2: 直接URL指定

```html
<!-- wp:soico-cta/inline-cta {"company":"sbi","customAffiliateUrl":"https://example.com/affiliate/sbi-special","version":"2"} /-->
```

→ 表示名は SBI証券、リンクのみ任意のURLに差し替え。

#### パターン3: スラッグのみ（デフォルト任せ）

```html
<!-- wp:soico-cta/inline-cta {"company":"sbi","version":"2"} /-->
```

→ 会社データ層の `thirsty_link` (5550) が解決され、結果的にパターン1と同じURLになる。

### 6.2 カードローンインラインCTA

#### パターン1: ThirstyAffiliates ID 指定

```html
<!-- wp:soico-cta/cardloan-inline-cta {"company":"acom","customThirstyLinkId":16757,"version":"2"} /-->
```

→ アコムの表示で、リンクは ID 16757 (`acom_checked`) に差し替え。

#### パターン2: 直接URL指定

```html
<!-- wp:soico-cta/cardloan-inline-cta {"company":"acom","customAffiliateUrl":"https://px.a8.net/svt/ejp?a8mat=ABC123","version":"2"} /-->
```

#### パターン3: スラッグのみ

```html
<!-- wp:soico-cta/cardloan-inline-cta {"company":"acom","version":"2"} /-->
```

### 6.3 仮想通貨インラインCTA

#### パターン1: ThirstyAffiliates ID 指定

```html
<!-- wp:soico-cta/crypto-inline-cta {"exchange":"bitflyer","customThirstyLinkId":13287,"version":"2"} /-->
```

#### パターン2: 直接URL指定

```html
<!-- wp:soico-cta/crypto-inline-cta {"exchange":"bitflyer","customAffiliateUrl":"https://bitflyer.com/?ref=xxx","version":"2"} /-->
```

#### パターン3: スラッグのみ

```html
<!-- wp:soico-cta/crypto-inline-cta {"exchange":"bitflyer","version":"2"} /-->
```

### 6.4 比較表（会社ごとに上書き）

```html
<!-- wp:soico-cta/cardloan-comparison-table {"companies":["aiful","acom","promise"],"limit":3,"customThirstyLinkIds":{"aiful":6146,"acom":16757,"promise":6148},"version":"2"} /-->
```

→ 3社の比較表で、`acom` 行のみ上書きID（16757 = `acom_checked`）が使われる。他2社は会社データ層の thirsty_link が使われる。

### 6.5 GAS から REST API に投稿するときの content フィールド構造

```javascript
// GAS 側のサンプル
const blockHtml = '<!-- wp:soico-cta/cardloan-inline-cta '
  + JSON.stringify({
      company: 'acom',
      customThirstyLinkId: 16757,
      version: '2'
    })
  + ' /-->';

const payload = {
  title: '記事タイトル',
  status: 'draft',
  content: '<p>本文段落</p>\n\n' + blockHtml + '\n\n<p>続きの段落</p>',
};

UrlFetchApp.fetch(
  'https://www.soico.jp/no1/wp-json/wp/v2/posts',
  {
    method: 'post',
    contentType: 'application/json',
    headers: {
      Authorization: 'Basic ' + Utilities.base64Encode('user:app_password'),
    },
    payload: JSON.stringify(payload),
  }
);
```

---

## 7. 既知の制約

### 7.1 V1 / V2 の差異

| 項目 | V1 (`version` 未指定 or `'1'`) | V2 (`version` = `'2'`) |
|---|---|---|
| HTML生成 | PHP内に直書き（`<?php ... ?>`） | テンプレートエンジン (`templates/*.html`) |
| デザイン | 既存（証券: オレンジ系 / カードローン: グリーン系 / 仮想通貨: オレンジ系） | soico.jp ブルー (#164C95) ベース、フラット |
| カスタムHTML | 不可 | レイヤー1: ブロック単位、レイヤー2: 会社単位、レイヤー3: テンプレート単位 |
| URL差し替え | ✅ 同一仕様 | ✅ 同一仕様 |
| 本番デフォルト | （v1 既存記事に残存） | **v2 が標準**（GAS連携でも v2 を推奨） |

### 7.2 比較表での複数会社URL上書き

`customAffiliateUrls` / `customThirstyLinkIds` は **オブジェクト**で、キーは会社スラッグ。比較表に表示される会社のうち**指定したスラッグだけ上書き**され、未指定の会社はデフォルト動作。

```json
{
  "companies": ["aiful", "acom", "promise", "smbcmobit"],
  "customThirstyLinkIds": {
    "acom": 16757
  }
}
```

→ `acom` のみ上書き、他3社は会社データ層のデフォルト。

### 7.3 エスケープ仕様

ブロックコメント内の JSON は WordPress のブロックパーサが厳密にパースするため、以下を守ること：

1. **完全な有効JSON** であること（末尾カンマ不可、シングルクォート不可）
2. **JSON文字列内の特殊文字はエスケープ済み**であること（例: `"` は `\"`、改行は `\n`）
3. **全角文字はそのままUTF-8で書ける**（エスケープ不要）
4. ブロックコメントは **`<!-- wp:... -->`** 形式で、属性JSON前後に必ず半角スペース
5. 空ブロック（save関数が `null`）の場合は **`/-->`** で閉じる（PHPレンダリング型ブロックは全て該当）
6. **属性JSON内に `-->` を含めてはいけない**（HTMLコメントの終端と衝突）

### 7.4 WordPress REST API での content フィールド注意点

- `content` に渡すのは **HTML文字列**。Gutenberg の独自Markdownや MDX ではない
- `content.raw` に渡すと差し戻し編集も可能。`content.rendered` は読み取り専用
- 改行は `\n` で渡す。WordPressは `wpautop` で段落 `<p>` を自動付与する
- ブロックコメントとブロックコメントの間は **空行2つ**（`\n\n`）が標準
- 認証は **Application Password**（`Basic <base64(user:app_pw)>`）を推奨
- 投稿後に `?context=edit` でブロック構造を確認可能（要 `edit_posts` 権限）

```
GET https://www.soico.jp/no1/wp-json/wp/v2/posts/<id>?context=edit&_fields=content.raw
```

### 7.5 ブロックエディタで開いた際の検証エラー

`block.json` の `attributes` に**ない属性**を含めると、Gutenberg エディタで「**ブロックエラー**」（attribute not registered）が出る。本仕様書の表に書かれている属性のみ使うこと。

ただし、`version` / `variant` / `customHtml*` / `fullCustomMode` / `fullCustomHtml` / `customNote` / `customLabel` の **9つの v2 拡張属性は editor-v2.js が動的に追加する**ため、ブロックエディタを開いた時点で初めて登録される。**GAS から書き込んだ直後にエディタを開くと一瞬エラーが出る可能性**があるが、保存・更新には影響しない。

### 7.6 PHP/WordPress 要件

- PHP 7.4 以上（本番は PHP 8.1）
- WordPress 6.0 以上
- ThirstyAffiliates プラグインが有効化されていない場合、`customThirstyLinkId` は無視される（直接URL or デフォルトにフォールバック）

---

## 8. バージョン管理

| 項目 | 値 |
|---|---|
| 仕様書バージョン | **1.0.0** |
| 最終更新日 | **2026-04-09** |
| 対象プラグインバージョン | **soico-securities-cta v1.1.0** |
| 対象プラグイン定数 | `SOICO_CTA_VERSION = '1.1.0'`（[soico-securities-cta.php L23](../soico-securities-cta.php#L23)） |
| 対象 commit | [`dd1dd73`](https://github.com/DaikiNozawa/soico-securities-cta/commit/dd1dd73) — fix: V2レンダラーにもURL差し替え機能を適用 |
| プラグインリポジトリ | https://github.com/DaikiNozawa/soico-securities-cta |
| 本番サイト | https://www.soico.jp/no1/ |

### 更新履歴

| バージョン | 日付 | 変更内容 |
|---|---|---|
| 1.0.0 | 2026-04-09 | 初版作成。V2 復元 + ThirstyAffiliates 選択機能追加（コミット `dd1dd73`）に対応 |

### この仕様書を更新するタイミング

- ブロックを追加・削除した時
- ブロック属性を追加・削除した時
- URL解決ロジック（`resolve_affiliate_url()` / `resolve_url_override()`）を変更した時
- 会社・カードローン・仮想通貨の登録スラッグが大きく変わった時
- ThirstyAffiliates 連携仕様が変わった時
- プラグインのメジャーバージョンを上げた時

更新時は本ファイル末尾の「更新履歴」にエントリを追加し、`仕様書バージョン` と `最終更新日` を更新すること。

---

## 関連ファイル

- ブロック登録: [`includes/class-block-register.php`](../includes/class-block-register.php)
- V2 レンダラー: [`includes/class-block-register-v2.php`](../includes/class-block-register-v2.php)
- 会社データ管理: [`includes/class-securities-data.php`](../includes/class-securities-data.php)
- ThirstyAffiliates 連携: [`includes/class-thirsty-integration.php`](../includes/class-thirsty-integration.php)
- エディタJS（v1 ブロック登録）: [`assets/js/editor.js`](../assets/js/editor.js)
- エディタJS（v2 拡張属性）: [`assets/js/editor-v2.js`](../assets/js/editor-v2.js)
- ブロック定義: [`blocks/`](../blocks/)
- v2テンプレート: [`templates/`](../templates/)
- 引継ぎ資料: [`PROMPT_FOR_CLAUDE.md`](../PROMPT_FOR_CLAUDE.md)
