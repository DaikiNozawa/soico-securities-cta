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
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var TextControl = wp.components.TextControl;
    var RangeControl = wp.components.RangeControl;
    // ServerSideRender は REST API 404問題のため使用せず、静的プレビューを採用

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

    // ==========================================================================
    // 静的プレビューコンポーネント（REST API不要）
    // ==========================================================================

    /**
     * 証券会社名を取得
     */
    function getCompanyName(slug) {
        for (var i = 0; i < companyOptions.length; i++) {
            if (companyOptions[i].value === slug) {
                return companyOptions[i].label;
            }
        }
        return slug;
    }

    /**
     * 結論ボックス Edit
     */
    function editConclusionBox(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();
        var companyName = getCompanyName(attributes.company);

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
            // 静的プレビュー（REST API不要）
            el('div', { className: 'soico-cta-editor-preview soico-cta-static-preview' },
                el('div', { className: 'soico-cta-preview-box', style: { border: '2px solid #1E88E5', borderRadius: '8px', padding: '20px', background: '#f8f9fa' } },
                    el('div', { style: { marginBottom: '10px' } },
                        el('span', { style: { background: '#1E88E5', color: '#fff', padding: '4px 12px', borderRadius: '4px', fontSize: '12px', fontWeight: 'bold' } }, '結論')
                    ),
                    el('h3', { style: { margin: '10px 0', fontSize: '18px' } },
                        attributes.customTitle || '証券口座を開設するなら' + companyName + 'がおすすめ'
                    ),
                    attributes.showFeatures && el('ul', { style: { margin: '10px 0', paddingLeft: '20px', color: '#666' } },
                        el('li', null, '特徴1（実際の表示はフロントエンドで確認）'),
                        el('li', null, '特徴2'),
                        el('li', null, '特徴3')
                    ),
                    el('div', { style: { marginTop: '15px' } },
                        el('span', { style: { background: '#FF6B35', color: '#fff', padding: '12px 24px', borderRadius: '4px', display: 'inline-block' } },
                            companyName + 'で口座開設（無料）'
                        )
                    ),
                    el('p', { style: { fontSize: '12px', color: '#999', marginTop: '10px' } },
                        '※エディタプレビュー - 実際の表示はフロントエンドで確認してください'
                    )
                )
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
        var companyName = getCompanyName(attributes.company);

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
            el('div', { className: 'soico-cta-editor-preview soico-cta-static-preview' },
                el('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 16px', background: attributes.style === 'subtle' ? '#f5f5f5' : '#e3f2fd', borderRadius: '6px', border: '1px solid #ddd' } },
                    el('div', null,
                        el('strong', null, companyName),
                        el('span', { style: { marginLeft: '10px', color: '#666', fontSize: '14px' } }, '特徴テキスト')
                    ),
                    el('span', { style: { background: '#FF6B35', color: '#fff', padding: '6px 12px', borderRadius: '4px', fontSize: '13px' } }, '詳細を見る →')
                ),
                el('p', { style: { fontSize: '11px', color: '#999', marginTop: '5px', marginBottom: '0' } }, '※エディタプレビュー')
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
        var companyName = getCompanyName(attributes.company);
        var buttonText = attributes.buttonText || companyName + 'の公式サイトを見る';

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
            el('div', { className: 'soico-cta-editor-preview soico-cta-static-preview', style: { textAlign: 'center' } },
                el('span', { style: { background: '#FF6B35', color: '#fff', padding: '14px 28px', borderRadius: '6px', display: 'inline-block', fontSize: '16px', fontWeight: 'bold' } },
                    buttonText
                ),
                attributes.showPR && el('p', { style: { fontSize: '12px', color: '#999', marginTop: '8px', marginBottom: '0' } }, 'PR'),
                el('p', { style: { fontSize: '11px', color: '#999', marginTop: '5px', marginBottom: '0' } }, '※エディタプレビュー')
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

        // プレビュー用のサンプルデータ
        var sampleRows = [];
        for (var i = 0; i < Math.min(attributes.limit, 3); i++) {
            var rank = i + 1;
            var name = companyOptions[i] ? companyOptions[i].label : '証券会社' + rank;
            sampleRows.push(
                el('tr', { key: i, style: { background: rank === 1 ? '#fff3e0' : '#fff' } },
                    el('td', { style: { padding: '10px', textAlign: 'center', fontWeight: 'bold', color: rank === 1 ? '#FF6B35' : '#666' } }, rank),
                    el('td', { style: { padding: '10px' } }, name),
                    el('td', { style: { padding: '10px', color: '#666' } }, '特徴1 / 特徴2'),
                    attributes.showCommission && el('td', { style: { padding: '10px' } }, '0円〜'),
                    el('td', { style: { padding: '10px' } },
                        el('span', { style: { background: '#FF6B35', color: '#fff', padding: '4px 10px', borderRadius: '4px', fontSize: '12px' } }, '詳細')
                    )
                )
            );
        }

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
            el('div', { className: 'soico-cta-editor-preview soico-cta-static-preview' },
                el('table', { style: { width: '100%', borderCollapse: 'collapse', border: '1px solid #ddd', fontSize: '14px' } },
                    el('thead', null,
                        el('tr', { style: { background: '#f5f5f5' } },
                            el('th', { style: { padding: '10px', borderBottom: '1px solid #ddd' } }, '順位'),
                            el('th', { style: { padding: '10px', borderBottom: '1px solid #ddd' } }, '証券会社'),
                            el('th', { style: { padding: '10px', borderBottom: '1px solid #ddd' } }, '特徴'),
                            attributes.showCommission && el('th', { style: { padding: '10px', borderBottom: '1px solid #ddd' } }, '手数料'),
                            el('th', { style: { padding: '10px', borderBottom: '1px solid #ddd' } }, '口座開設')
                        )
                    ),
                    el('tbody', null, sampleRows)
                ),
                el('p', { style: { fontSize: '11px', color: '#999', marginTop: '8px', marginBottom: '0' } },
                    '※エディタプレビュー（' + attributes.limit + '件表示設定）'
                )
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
        var companyName = getCompanyName(attributes.company);
        var message = attributes.message || '💡 証券口座をお探しなら → ' + companyName + '（国内株手数料0円）';

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
            el('div', { className: 'soico-cta-editor-preview soico-cta-static-preview' },
                el('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '10px 14px', background: '#fafafa', border: '1px solid #eee', borderRadius: '4px', fontSize: '14px' } },
                    el('span', null, message),
                    el('span', { style: { background: '#eee', color: '#666', padding: '2px 6px', borderRadius: '2px', fontSize: '11px' } }, 'PR')
                ),
                el('p', { style: { fontSize: '11px', color: '#999', marginTop: '5px', marginBottom: '0' } }, '※エディタプレビュー')
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
