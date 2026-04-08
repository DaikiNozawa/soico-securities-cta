#!/bin/bash
# ===========================================
# SOICO Securities CTA - WordPress REST API ヘルパー
# ブロックの動作確認・データ操作を自動化
# ===========================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_DIR/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo "❌ .env ファイルが見つかりません"
    exit 1
fi

source "$ENV_FILE"

API_BASE="${WP_SITE_URL}/wp-json/wp/v2"
AUTH_HEADER=""
if [ -n "${WP_API_USER:-}" ] && [ -n "${WP_API_APP_PASSWORD:-}" ]; then
    AUTH_TOKEN=$(printf '%s:%s' "$WP_API_USER" "$WP_API_APP_PASSWORD" | base64)
    AUTH_HEADER="Authorization: Basic $AUTH_TOKEN"
fi

# curlラッパー（認証ヘッダー付き）
auth_curl() {
    if [ -n "$AUTH_HEADER" ]; then
        curl -s -H "$AUTH_HEADER" "$@"
    else
        curl -s "$@"
    fi
}

CMD="${1:-help}"

case "$CMD" in
    # --- 公開API（認証不要） ---

    posts)
        echo "📝 最新の投稿一覧"
        curl -s "$API_BASE/posts?per_page=${2:-5}&_fields=id,title,date,status,link" \
            | python3 -m json.tool
        ;;

    post)
        if [ -z "${2:-}" ]; then echo "使い方: $0 post <ID>"; exit 1; fi
        echo "📝 投稿 #$2 の詳細"
        curl -s "$API_BASE/posts/$2?_fields=id,title,date,content,status" \
            | python3 -m json.tool
        ;;

    search-blocks)
        QUERY="${2:-soico-cta}"
        echo "🔍 ブロック '$QUERY' を含む投稿を検索中..."
        curl -s "$API_BASE/posts?per_page=100&_fields=id,title,link" \
            | python3 -c "
import json, sys
posts = json.load(sys.stdin)
print(f'取得した投稿数: {len(posts)}')
for p in posts:
    print(f'  ID: {p[\"id\"]} | {p[\"title\"][\"rendered\"]}')
    print(f'    URL: {p[\"link\"]}')
"
        ;;

    find-cta)
        echo "🔍 CTAブロックを使用している投稿を検索中..."
        PAGE=1
        FOUND=0
        while true; do
            RESPONSE=$(curl -s -w "\n%{http_code}" "$API_BASE/posts?per_page=10&page=$PAGE&_fields=id,title,content,link")
            HTTP_CODE=$(echo "$RESPONSE" | tail -1)
            BODY=$(echo "$RESPONSE" | sed '$d')
            if [ "$HTTP_CODE" != "200" ] || [ -z "$BODY" ] || [ "$BODY" = "[]" ]; then break; fi
            echo "$BODY" | python3 -c "
import json, sys, re
posts = json.load(sys.stdin)
if not posts: sys.exit(1)
for p in posts:
    content = p.get('content', {}).get('rendered', '')
    if 'soico-cta' in content or 'soico_cta' in content:
        blocks = re.findall(r'soico-cta/[\w-]+', content)
        blocks_str = ', '.join(set(blocks))
        print(f'  ID: {p[\"id\"]} | {p[\"title\"][\"rendered\"]}')
        print(f'    ブロック: {blocks_str}')
        print(f'    URL: {p[\"link\"]}')
" && FOUND=$((FOUND+1))
            PAGE=$((PAGE+1))
            if [ "$PAGE" -gt 10 ]; then break; fi
        done
        if [ "$FOUND" -eq 0 ]; then echo "CTAブロックを使用している投稿は見つかりませんでした（最新100件を検索）"; fi
        ;;

    # --- 認証必要API ---

    plugins)
        if [ -z "$AUTH_HEADER" ]; then echo "❌ 認証情報が必要です（.env に WP_API_USER, WP_API_APP_PASSWORD を設定）"; exit 1; fi
        echo "🔌 プラグイン一覧"
        auth_curl "${WP_SITE_URL}/wp-json/wp/v2/plugins?_fields=plugin,status,name,version" \
            | python3 -m json.tool
        ;;

    options)
        if [ -z "$AUTH_HEADER" ]; then echo "❌ 認証情報が必要です"; exit 1; fi
        echo "⚙️  CTA設定を取得中..."
        auth_curl "${WP_SITE_URL}/wp-json/wp/v2/settings" \
            | python3 -c "
import json, sys
data = json.load(sys.stdin)
for key, val in data.items():
    if 'soico' in key.lower() or 'cta' in key.lower():
        print(f'{key}: {json.dumps(val, ensure_ascii=False, indent=2)}')
" 2>/dev/null || echo "⚠️  設定の取得に失敗（権限を確認してください）"
        ;;

    create-test-post)
        if [ -z "$AUTH_HEADER" ]; then echo "❌ 認証情報が必要です"; exit 1; fi
        echo "📝 CTAテスト用の下書き投稿を作成中..."
        auth_curl -X POST "$API_BASE/posts" \
            -H "Content-Type: application/json" \
            -d '{
                "title": "[テスト] CTA ブロック動作確認",
                "content": "<!-- wp:soico-cta/conclusion-box {\"company\":\"sbi\"} /-->\n\n<!-- wp:soico-cta/comparison-table {\"companies\":[\"sbi\",\"monex\",\"rakuten\"]} /-->\n\n<!-- wp:soico-cta/inline-cta {\"company\":\"sbi\"} /-->\n\n<!-- wp:soico-cta/single-button {\"company\":\"sbi\"} /-->\n\n<!-- wp:soico-cta/subtle-banner {\"company\":\"sbi\"} /-->",
                "status": "draft"
            }' | python3 -c "
import json, sys
post = json.load(sys.stdin)
print(f'✅ テスト投稿を作成しました')
print(f'  ID: {post[\"id\"]}')
print(f'  編集: {post[\"link\"]}')
print(f'  プレビュー: {post.get(\"link\", \"\")}?preview=true')
"
        ;;

    check)
        echo "🏥 サイト疎通チェック..."
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$API_BASE/posts?per_page=1")
        if [ "$HTTP_CODE" = "200" ]; then
            echo "✅ REST API 正常 (HTTP $HTTP_CODE)"
        else
            echo "❌ REST API エラー (HTTP $HTTP_CODE)"
        fi

        if [ -n "$AUTH_HEADER" ]; then
            HTTP_CODE=$(auth_curl -o /dev/null -w "%{http_code}" "$API_BASE/users/me")
            if [ "$HTTP_CODE" = "200" ]; then
                echo "✅ 認証 正常 (HTTP $HTTP_CODE)"
            else
                echo "❌ 認証 エラー (HTTP $HTTP_CODE)"
            fi
        else
            echo "⚠️  認証未設定（一部機能が使えません）"
        fi
        ;;

    *)
        echo "SOICO CTA - WordPress REST API ヘルパー"
        echo ""
        echo "使い方: $0 <コマンド> [引数]"
        echo ""
        echo "--- 認証不要 ---"
        echo "  check              サイト疎通チェック"
        echo "  posts [件数]        最新投稿一覧（デフォルト5件）"
        echo "  post <ID>          投稿の詳細を表示"
        echo "  search-blocks      投稿一覧を取得"
        echo "  find-cta           CTAブロック使用投稿を検索"
        echo ""
        echo "--- 認証必要 ---"
        echo "  plugins            プラグイン一覧"
        echo "  options            CTA関連の設定を表示"
        echo "  create-test-post   テスト用下書き投稿を作成"
        echo ""
        ;;
esac
