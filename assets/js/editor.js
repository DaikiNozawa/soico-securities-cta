/**
 * SOICO Securities CTA - Gutenberg Block Editor
 *
 * @package Soico_Securities_CTA
 */

(function(wp) {
    'use strict';

    // ==========================================================================
    // デバッグ・ログユーティリティ
    // ==========================================================================
    var DEBUG = true; // 本番環境ではfalseに変更

    function log(message, data) {
        if (DEBUG && console && console.log) {
            if (data !== undefined) {
                console.log('[SOICO CTA] ' + message, data);
            } else {
                console.log('[SOICO CTA] ' + message);
            }
        }
    }

    function warn(message, data) {
        if (console && console.warn) {
            if (data !== undefined) {
                console.warn('[SOICO CTA] ' + message, data);
            } else {
                console.warn('[SOICO CTA] ' + message);
            }
        }
    }

    function error(message, data) {
        if (console && console.error) {
            if (data !== undefined) {
                console.error('[SOICO CTA] ' + message, data);
            } else {
                console.error('[SOICO CTA] ' + message);
            }
        }
    }

    log('=== 初期化開始 ===');

    // ==========================================================================
    // WordPress コンポーネント
    // ==========================================================================
    var el = wp.element.createElement;
    var registerBlockType = wp.blocks.registerBlockType;
    var getBlockType = wp.blocks.getBlockType;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var ServerSideRender = wp.serverSideRender;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var TextControl = wp.components.TextControl;
    var RangeControl = wp.components.RangeControl;

    log('WordPress コンポーネント読み込み完了');

    // ==========================================================================
    // ローカライズデータ
    // ==========================================================================
    var data = window.soicoCTAData || {};
    var selectOptions = data.selectOptions || [];
    var i18n = data.i18n || {};

    log('ローカライズデータ:', {
        selectOptions: selectOptions,
        securitiesCount: selectOptions.length,
        i18n: Object.keys(i18n)
    });

    // 証券会社選択肢
    var companyOptions = selectOptions.map(function(opt) {
        return { value: opt.value, label: opt.label };
    });

    // フォールバック
    if (companyOptions.length === 0) {
        warn('証券会社データがありません。デフォルト値を使用します。');
        companyOptions = [
            { value: 'sbi', label: 'SBI証券' },
            { value: 'monex', label: 'マネックス証券' },
            { value: 'rakuten', label: '楽天証券' }
        ];
    }

    log('利用可能な証券会社:', companyOptions);

    // スタイルオプション
    var styleOptions = [
        { value: 'default', label: 'デフォルト' },
        { value: 'subtle', label: '控えめ' }
    ];

    // ==========================================================================
    // ブロック登録関数
    // ==========================================================================

    /**
     * PHPで登録済みのブロックにedit関数を追加する
     * PHPのrender_callbackを保持したまま、JS側のedit/save関数を設定
     */
    function enhanceBlock(name, editFunction, saveFunction) {
        var existingBlock = getBlockType(name);

        if (existingBlock) {
            // PHPで既に登録されている場合、edit/save関数を直接設定
            log('既存ブロックを拡張: ' + name, {
                hasEdit: !!existingBlock.edit,
                hasRenderCallback: !!existingBlock.render_callback,
                attributes: Object.keys(existingBlock.attributes || {})
            });

            existingBlock.edit = editFunction;
            existingBlock.save = saveFunction;

            log('ブロック拡張完了: ' + name);
            return true;
        } else {
            // PHPで登録されていない場合は新規登録
            warn('ブロックが未登録のため新規登録: ' + name);
            return false;
        }
    }

    /**
     * 新規ブロック登録（PHPで登録されていない場合のフォールバック）
     */
    function registerNewBlock(name, settings) {
        try {
            registerBlockType(name, settings);
            log('新規ブロック登録完了: ' + name);
            return true;
        } catch (e) {
            error('ブロック登録エラー: ' + name, e);
            return false;
        }
    }

    // ==========================================================================
    // Edit関数定義
    // ==========================================================================

    /**
     * 結論ボックス Edit
     */
    function editConclusionBox(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();

        log('結論ボックス render', { company: attributes.company });

        return el('div', blockProps,
            el(InspectorControls, null,
                el(PanelBody, {
                    title: i18n.selectCompany || '証券会社設定',
                    initialOpen: true
                },
                    el(SelectControl, {
                        label: i18n.selectCompany || '証券会社を選択',
                        value: attributes.company,
                        options: companyOptions,
                        onChange: function(value) {
                            log('証券会社変更: ' + value);
                            setAttributes({ company: value });
                        }
                    }),
                    el(ToggleControl, {
                        label: i18n.showFeatures || '特徴を表示',
                        checked: attributes.showFeatures,
                        onChange: function(value) {
                            setAttributes({ showFeatures: value });
                        }
                    }),
                    el(TextControl, {
                        label: i18n.customTitle || 'カスタムタイトル',
                        value: attributes.customTitle,
                        onChange: function(value) {
                            setAttributes({ customTitle: value });
                        },
                        help: '空欄の場合はデフォルトタイトルを使用'
                    })
                )
            ),
            el('div', { className: 'soico-cta-editor-preview' },
                el(ServerSideRender, {
                    block: 'soico-cta/conclusion-box',
                    attributes: attributes,
                    EmptyResponsePlaceholder: function() {
                        return el('div', { className: 'soico-cta-placeholder' },
                            '結論ボックス: プレビューを読み込み中...'
                        );
                    },
                    ErrorResponsePlaceholder: function() {
                        return el('div', { className: 'soico-cta-error' },
                            '結論ボックス: プレビューを読み込めませんでした。証券会社の設定を確認してください。'
                        );
                    }
                })
            )
        );
    }

    /**
     * インラインCTA Edit
     */
    function editInlineCTA(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();

        return el('div', blockProps,
            el(InspectorControls, null,
                el(PanelBody, { title: '設定', initialOpen: true },
                    el(SelectControl, {
                        label: i18n.selectCompany || '証券会社を選択',
                        value: attributes.company,
                        options: companyOptions,
                        onChange: function(value) {
                            setAttributes({ company: value });
                        }
                    }),
                    el(SelectControl, {
                        label: 'スタイル',
                        value: attributes.style,
                        options: styleOptions,
                        onChange: function(value) {
                            setAttributes({ style: value });
                        }
                    })
                )
            ),
            el('div', { className: 'soico-cta-editor-preview' },
                el(ServerSideRender, {
                    block: 'soico-cta/inline-cta',
                    attributes: attributes,
                    EmptyResponsePlaceholder: function() {
                        return el('div', { className: 'soico-cta-placeholder' },
                            'インラインCTA: プレビューを読み込み中...'
                        );
                    }
                })
            )
        );
    }

    /**
     * CTAボタン Edit
     */
    function editSingleButton(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();

        return el('div', blockProps,
            el(InspectorControls, null,
                el(PanelBody, { title: '設定', initialOpen: true },
                    el(SelectControl, {
                        label: i18n.selectCompany || '証券会社を選択',
                        value: attributes.company,
                        options: companyOptions,
                        onChange: function(value) {
                            setAttributes({ company: value });
                        }
                    }),
                    el(TextControl, {
                        label: i18n.buttonText || 'ボタンテキスト',
                        value: attributes.buttonText,
                        onChange: function(value) {
                            setAttributes({ buttonText: value });
                        },
                        help: '空欄の場合はデフォルトテキストを使用'
                    }),
                    el(ToggleControl, {
                        label: i18n.showPR || 'PR表記を表示',
                        checked: attributes.showPR,
                        onChange: function(value) {
                            setAttributes({ showPR: value });
                        }
                    })
                )
            ),
            el('div', { className: 'soico-cta-editor-preview' },
                el(ServerSideRender, {
                    block: 'soico-cta/single-button',
                    attributes: attributes,
                    EmptyResponsePlaceholder: function() {
                        return el('div', { className: 'soico-cta-placeholder' },
                            'CTAボタン: プレビューを読み込み中...'
                        );
                    }
                })
            )
        );
    }

    /**
     * 比較表 Edit
     */
    function editComparisonTable(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();

        return el('div', blockProps,
            el(InspectorControls, null,
                el(PanelBody, { title: '設定', initialOpen: true },
                    el(RangeControl, {
                        label: i18n.limit || '表示件数',
                        value: attributes.limit,
                        onChange: function(value) {
                            setAttributes({ limit: value });
                        },
                        min: 1,
                        max: 10
                    }),
                    el(ToggleControl, {
                        label: i18n.showCommission || '手数料を表示',
                        checked: attributes.showCommission,
                        onChange: function(value) {
                            setAttributes({ showCommission: value });
                        }
                    })
                )
            ),
            el('div', { className: 'soico-cta-editor-preview' },
                el(ServerSideRender, {
                    block: 'soico-cta/comparison-table',
                    attributes: attributes,
                    EmptyResponsePlaceholder: function() {
                        return el('div', { className: 'soico-cta-placeholder' },
                            '比較表: プレビューを読み込み中...'
                        );
                    }
                })
            )
        );
    }

    /**
     * 控えめバナー Edit
     */
    function editSubtleBanner(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();

        return el('div', blockProps,
            el(InspectorControls, null,
                el(PanelBody, { title: '設定', initialOpen: true },
                    el(SelectControl, {
                        label: i18n.selectCompany || '証券会社を選択',
                        value: attributes.company,
                        options: companyOptions,
                        onChange: function(value) {
                            setAttributes({ company: value });
                        }
                    }),
                    el(TextControl, {
                        label: i18n.message || 'メッセージ',
                        value: attributes.message,
                        onChange: function(value) {
                            setAttributes({ message: value });
                        },
                        help: '空欄の場合はデフォルトメッセージを使用'
                    })
                )
            ),
            el('div', { className: 'soico-cta-editor-preview' },
                el(ServerSideRender, {
                    block: 'soico-cta/subtle-banner',
                    attributes: attributes,
                    EmptyResponsePlaceholder: function() {
                        return el('div', { className: 'soico-cta-placeholder' },
                            '控えめバナー: プレビューを読み込み中...'
                        );
                    }
                })
            )
        );
    }

    /**
     * 共通のsave関数（動的ブロック用）
     */
    function saveDynamic() {
        return null; // PHPでレンダリング
    }

    // ==========================================================================
    // ブロック登録実行
    // ==========================================================================

    log('=== ブロック登録開始 ===');

    // 利用可能なブロック一覧
    var blocks = [
        {
            name: 'soico-cta/conclusion-box',
            title: '結論ボックス',
            description: '記事冒頭に最適。証券会社のおすすめポイントと特徴リスト、CTAボタンを表示します。',
            edit: editConclusionBox
        },
        {
            name: 'soico-cta/inline-cta',
            title: 'インラインCTA',
            description: '記事の途中に自然に挿入できる控えめなCTA。流れを邪魔しません。',
            edit: editInlineCTA
        },
        {
            name: 'soico-cta/single-button',
            title: 'CTAボタン',
            description: 'シンプルなボタンのみ。任意の場所に配置できます。',
            edit: editSingleButton
        },
        {
            name: 'soico-cta/comparison-table',
            title: '比較表',
            description: '複数の証券会社を比較する表形式のCTA。ランキング記事に最適。',
            edit: editComparisonTable
        },
        {
            name: 'soico-cta/subtle-banner',
            title: '控えめバナー',
            description: 'テキストリンク形式の最も控えめなCTA。読者の邪魔をしません。',
            edit: editSubtleBanner
        }
    ];

    // 各ブロックの登録状態を確認・拡張
    var registrationResults = {
        enhanced: [],
        failed: []
    };

    blocks.forEach(function(block) {
        var result = enhanceBlock(block.name, block.edit, saveDynamic);
        if (result) {
            registrationResults.enhanced.push(block.name);
        } else {
            registrationResults.failed.push(block.name);
        }
    });

    // 登録結果をログ出力
    log('=== ブロック登録完了 ===');
    log('拡張成功:', registrationResults.enhanced);
    if (registrationResults.failed.length > 0) {
        warn('拡張失敗:', registrationResults.failed);
    }

    // 利用可能なブロック情報をコンソールに表示
    log('=== 利用可能なブロック ===');
    blocks.forEach(function(block) {
        log('📦 ' + block.title + ' (' + block.name + ')');
        log('   ' + block.description);
    });

    log('=== SOICO CTA 初期化完了 ===');

})(window.wp);
