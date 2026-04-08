#!/bin/bash
# ===========================================
# SOICO Securities CTA - デプロイスクリプト
# ローカルのコード変更をXserverに同期
# ===========================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_DIR/.env"

# .env 読み込み
if [ ! -f "$ENV_FILE" ]; then
    echo "❌ .env ファイルが見つかりません"
    echo "   cp .env.example .env で作成してください"
    exit 1
fi

source "$ENV_FILE"

# 必須変数チェック
for var in XSERVER_HOST XSERVER_USER XSERVER_PORT XSERVER_PLUGIN_PATH; do
    if [ -z "${!var:-}" ]; then
        echo "❌ $var が .env に設定されていません"
        exit 1
    fi
done

SSH_KEY="${SSH_KEY_PATH:-$HOME/.ssh/id_rsa}"
SSH_OPTS="-p $XSERVER_PORT -i $SSH_KEY -o StrictHostKeyChecking=accept-new"

MODE="${1:-sync}"

case "$MODE" in
    sync)
        echo "📦 プラグインをXserverに同期中..."
        echo "⚠️  --delete なし（サーバー側の余分なファイルは残ります）"
        echo "   完全同期が必要な場合は: $0 sync-clean"
        rsync -avz \
            --exclude '.git' \
            --exclude '.env' \
            --exclude '.env.example' \
            --exclude 'dev/' \
            --exclude '.DS_Store' \
            --exclude '.claude/' \
            --exclude 'node_modules/' \
            -e "ssh $SSH_OPTS" \
            "$PROJECT_DIR/" \
            "$XSERVER_USER@$XSERVER_HOST:$XSERVER_PLUGIN_PATH/"
        echo "✅ デプロイ完了"
        ;;

    sync-clean)
        echo "📦 プラグインをXserverに完全同期中（サーバー側の余分なファイルを削除）..."
        echo "⚠️  --delete あり：サーバー側のみのファイルは削除されます"
        read -p "続行しますか？ (y/N): " confirm
        if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
            echo "中止しました"
            exit 0
        fi
        rsync -avz --delete \
            --exclude '.git' \
            --exclude '.env' \
            --exclude '.env.example' \
            --exclude 'dev/' \
            --exclude '.DS_Store' \
            --exclude '.claude/' \
            --exclude 'node_modules/' \
            -e "ssh $SSH_OPTS" \
            "$PROJECT_DIR/" \
            "$XSERVER_USER@$XSERVER_HOST:$XSERVER_PLUGIN_PATH/"
        echo "✅ 完全同期完了"
        ;;

    watch)
        echo "👀 ファイル変更を監視中... (Ctrl+C で停止)"
        echo "   ※ fswatch が必要: brew install fswatch"
        fswatch -o "$PROJECT_DIR" \
            --exclude '\.git' \
            --exclude '\.env' \
            --exclude 'dev/' \
            --exclude '\.DS_Store' \
            | while read -r; do
                echo "$(date '+%H:%M:%S') 🔄 変更検出、同期中..."
                rsync -avz \
                    --exclude '.git' \
                    --exclude '.env' \
                    --exclude '.env.example' \
                    --exclude 'dev/' \
                    --exclude '.DS_Store' \
                    --exclude '.claude/' \
                    --exclude 'node_modules/' \
                    -e "ssh $SSH_OPTS" \
                    "$PROJECT_DIR/" \
                    "$XSERVER_USER@$XSERVER_HOST:$XSERVER_PLUGIN_PATH/"
                echo "$(date '+%H:%M:%S') ✅ 同期完了"
            done
        ;;

    test-ssh)
        echo "🔑 SSH接続テスト..."
        ssh $SSH_OPTS "$XSERVER_USER@$XSERVER_HOST" \
            "echo '✅ SSH接続成功' && ls -la $XSERVER_PLUGIN_PATH/ 2>/dev/null || echo '⚠️ プラグインディレクトリが見つかりません: $XSERVER_PLUGIN_PATH'"
        ;;

    *)
        echo "使い方: $0 [sync|watch|test-ssh]"
        echo ""
        echo "  sync      コードをXserverに同期（デフォルト）"
        echo "  watch     ファイル変更を監視して自動同期"
        echo "  test-ssh  SSH接続テスト"
        ;;
esac
