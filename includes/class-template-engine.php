<?php
/**
 * SOICO CTA Template Engine
 * Mustache風テンプレート処理エンジン
 *
 * サポートする構文:
 *   {{variable}}              - HTMLエスケープ済み変数
 *   {{{variable}}}            - 生HTML変数（エスケープなし）
 *   {{#if variable}}...{{/if}} - 条件分岐
 *   {{#unless variable}}...{{/unless}} - 逆条件
 *   {{#each array}}...{{/each}} - ループ
 *   {{@index}}                - ループ内インデックス（0始まり）
 *   {{@number}}               - ループ内番号（1始まり）
 *   {{this}}                  - ループ内の現在要素（スカラー値）
 *
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Soico_CTA_Template_Engine {

    /**
     * テンプレートを変数データでレンダリング
     *
     * @param string $template テンプレート文字列
     * @param array  $data     変数データ
     * @return string レンダリング結果HTML
     */
    public function render(string $template, array $data): string {
        $result = $template;

        // 1. {{#each ...}}...{{/each}} を処理
        $result = $this->process_each($result, $data);

        // 2. {{#if ...}}...{{/if}} を処理
        $result = $this->process_conditionals($result, $data);

        // 3. {{#unless ...}}...{{/unless}} を処理
        $result = $this->process_unless($result, $data);

        // 4. {{{raw}}} を処理（エスケープなし）
        $result = $this->process_raw_variables($result, $data);

        // 5. {{escaped}} を処理
        $result = $this->process_escaped_variables($result, $data);

        return $result;
    }

    /**
     * ファイルからテンプレートを読み込んでレンダリング
     *
     * @param string $file_path テンプレートファイルパス
     * @param array  $data      変数データ
     * @return string レンダリング結果
     */
    public function render_file(string $file_path, array $data): string {
        if (!file_exists($file_path)) {
            return '<!-- Template not found: ' . esc_html(basename($file_path)) . ' -->';
        }
        $template = file_get_contents($file_path);
        return $this->render($template, $data);
    }

    /**
     * {{#each array}}...{{/each}} ループ処理
     * ネストされたeachもサポート
     */
    private function process_each(string $template, array $data): string {
        $pattern = '/\{\{#each\s+(\w+)\}\}(.*?)\{\{\/each\}\}/s';

        return preg_replace_callback($pattern, function ($matches) use ($data) {
            $key = $matches[1];
            $inner = $matches[2];
            $items = $this->resolve_value($key, $data);

            if (!is_array($items) || empty($items)) {
                return '';
            }

            $output = '';
            $index = 0;
            foreach ($items as $item) {
                $item_template = $inner;

                // {{@index}} と {{@number}} を展開
                $item_template = str_replace('{{@index}}', (string) $index, $item_template);
                $item_template = str_replace('{{@number}}', (string) ($index + 1), $item_template);

                if (is_array($item)) {
                    // 配列要素の場合: 要素のキーを変数として展開
                    $merged_data = array_merge($data, $item);
                    $item_template = $this->process_conditionals($item_template, $merged_data);
                    $item_template = $this->process_unless($item_template, $merged_data);
                    $item_template = $this->process_raw_variables($item_template, $merged_data);
                    $item_template = $this->process_escaped_variables($item_template, $merged_data);
                } else {
                    // スカラー要素の場合: {{this}} を展開
                    $item_template = str_replace('{{{this}}}', (string) $item, $item_template);
                    $item_template = str_replace('{{this}}', esc_html((string) $item), $item_template);
                    // 親データの変数も展開
                    $item_template = $this->process_conditionals($item_template, $data);
                    $item_template = $this->process_unless($item_template, $data);
                    $item_template = $this->process_raw_variables($item_template, $data);
                    $item_template = $this->process_escaped_variables($item_template, $data);
                }

                $output .= $item_template;
                $index++;
            }

            return $output;
        }, $template);
    }

    /**
     * {{#if variable}}...{{/if}} 条件分岐処理
     * {{#if variable}}...{{else}}...{{/if}} もサポート
     */
    private function process_conditionals(string $template, array $data): string {
        $pattern = '/\{\{#if\s+(\w+)\}\}(.*?)(?:\{\{else\}\}(.*?))?\{\{\/if\}\}/s';

        return preg_replace_callback($pattern, function ($matches) use ($data) {
            $key = $matches[1];
            $true_block = $matches[2];
            $false_block = $matches[3] ?? '';

            $value = $this->resolve_value($key, $data);

            if ($this->is_truthy($value)) {
                return $true_block;
            }
            return $false_block;
        }, $template);
    }

    /**
     * {{#unless variable}}...{{/unless}} 逆条件処理
     */
    private function process_unless(string $template, array $data): string {
        $pattern = '/\{\{#unless\s+(\w+)\}\}(.*?)\{\{\/unless\}\}/s';

        return preg_replace_callback($pattern, function ($matches) use ($data) {
            $key = $matches[1];
            $inner = $matches[2];

            $value = $this->resolve_value($key, $data);

            if (!$this->is_truthy($value)) {
                return $inner;
            }
            return '';
        }, $template);
    }

    /**
     * {{{variable}}} 生HTML変数の展開（エスケープなし）
     */
    private function process_raw_variables(string $template, array $data): string {
        return preg_replace_callback('/\{\{\{(\w+)\}\}\}/', function ($matches) use ($data) {
            $value = $this->resolve_value($matches[1], $data);
            if (is_array($value)) {
                return '';
            }
            return (string) $value;
        }, $template);
    }

    /**
     * {{variable}} エスケープ済み変数の展開
     */
    private function process_escaped_variables(string $template, array $data): string {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($data) {
            $value = $this->resolve_value($matches[1], $data);
            if (is_array($value)) {
                return '';
            }
            return esc_html((string) $value);
        }, $template);
    }

    /**
     * ドット記法対応の値解決
     * 現在は単一キーのみだが、将来の拡張に備える
     */
    private function resolve_value(string $key, array $data) {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }
        return '';
    }

    /**
     * 値の真偽判定
     * 空文字列、null、false、空配列はfalse
     */
    private function is_truthy($value): bool {
        if (is_null($value)) return false;
        if ($value === false) return false;
        if ($value === '') return false;
        if (is_array($value) && empty($value)) return false;
        if ($value === '0' || $value === 0) return false;
        return true;
    }
}
