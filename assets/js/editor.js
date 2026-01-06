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
    var DEBUG = true;

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
    var unregisterBlockType = wp.blocks.unregisterBlockType;
    var getBlockType = wp.blocks.getBlockType;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var RangeControl = wp.components.RangeControl;

    log('WordPress コンポーネント読み込み完了');

    // ==========================================================================
    // ローカライズデータ
    // ==========================================================================
    var data = window.soicoCTAData || {};
    var selectOptions = data.selectOptions || [];
    var cardloanSelectOptions = data.cardloanSelectOptions || [];
    var i18n = data.i18n || {};

    log('ローカライズデータ:', {
        selectOptions: selectOptions,
        securitiesCount: selectOptions.length,
        cardloanSelectOptions: cardloanSelectOptions,
        cardloansCount: cardloanSelectOptions.length,
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

    // カードローン会社選択肢
    var cardloanOptions = cardloanSelectOptions.map(function(opt) {
        return { value: opt.value, label: opt.label };
    });

    // フォールバック
    if (cardloanOptions.length === 0) {
        warn('カードローンデータがありません。デフォルト値を使用します。');
        cardloanOptions = [
            { value: 'aiful', label: 'アイフル' },
            { value: 'promise', label: 'プロミス' },
            { value: 'acom', label: 'アコム' }
        ];
    }

    log('利用可能なカードローン会社:', cardloanOptions);

    // スタイルオプション
    var styleOptions = [
        { value: 'default', label: 'デフォルト' },
        { value: 'subtle', label: '控えめ' }
    ];

    // ==========================================================================
    // ヘルパー関数
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
     * カードローン会社名を取得
     */
    function getCardloanName(slug) {
        for (var i = 0; i < cardloanOptions.length; i++) {
            if (cardloanOptions[i].value === slug) {
                return cardloanOptions[i].label;
            }
        }
        return slug;
    }

    /**
     * 動的ブロック用save関数（PHPでレンダリング）
     */
    function saveDynamic() {
        return null;
    }

    // ==========================================================================
    // Edit関数定義（静的プレビュー方式）
    // ==========================================================================

    /**
     * 結論ボックス Edit
     */
    function EditConclusionBox(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();
        var companyName = getCompanyName(attributes.company);

        // カスタム特徴のプレビュー用配列
        var previewFeatures = [];
        if (attributes.customFeatures) {
            previewFeatures = attributes.customFeatures.split('\n').filter(function(f) { return f.trim(); });
        }
        if (previewFeatures.length === 0) {
            previewFeatures = ['特徴1（証券会社管理で設定）', '特徴2', '特徴3'];
        }

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
                    }),
                    el(TextareaControl, {
                        label: 'カスタム特徴',
                        value: attributes.customFeatures,
                        onChange: function(value) {
                            setAttributes({ customFeatures: value });
                        },
                        help: '1行につき1つの特徴。空欄の場合は証券会社管理で設定した特徴を表示',
                        rows: 4
                    })
                )
            ),
            // 静的プレビュー
            el('div', { className: 'soico-cta-editor-preview soico-cta-static-preview' },
                el('div', { className: 'soico-cta-preview-box', style: { border: '2px solid #1E88E5', borderRadius: '8px', padding: '20px', background: '#f8f9fa' } },
                    el('div', { style: { marginBottom: '10px' } },
                        el('span', { style: { background: '#1E88E5', color: '#fff', padding: '4px 12px', borderRadius: '4px', fontSize: '12px', fontWeight: 'bold' } }, '結論')
                    ),
                    el('h3', { style: { margin: '10px 0', fontSize: '18px' } },
                        attributes.customTitle || '証券口座を開設するなら' + companyName + 'がおすすめ'
                    ),
                    attributes.showFeatures && el('ul', { style: { margin: '10px 0', paddingLeft: '20px', color: '#666' } },
                        previewFeatures.map(function(feature, idx) {
                            return el('li', { key: idx }, feature);
                        })
                    ),
                    el('div', { style: { marginTop: '15px' } },
                        el('span', { style: { background: '#FF6B35', color: '#fff', padding: '12px 24px', borderRadius: '4px', display: 'inline-block' } },
                            companyName + 'で口座開設（無料）'
                        )
                    ),
                    el('p', { style: { fontSize: '12px', color: '#999', marginTop: '10px' } },
                        '※エディタプレビュー'
                    )
                )
            )
        );
    }

    /**
     * インラインCTA Edit
     */
    function EditInlineCTA(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();
        var companyName = getCompanyName(attributes.company);
        var featureText = attributes.featureText || '特徴（証券会社管理で設定）';

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
                    }),
                    el(TextControl, {
                        label: '特徴テキスト',
                        value: attributes.featureText,
                        onChange: function(value) {
                            setAttributes({ featureText: value });
                        },
                        help: '空欄の場合は証券会社管理で設定した特徴を表示'
                    })
                )
            ),
            el('div', { className: 'soico-cta-editor-preview soico-cta-static-preview' },
                el('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 16px', background: attributes.style === 'subtle' ? '#f5f5f5' : '#e3f2fd', borderRadius: '6px', border: '1px solid #ddd' } },
                    el('div', null,
                        el('strong', null, companyName),
                        el('span', { style: { marginLeft: '10px', color: '#666', fontSize: '14px' } }, featureText)
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
    function EditSingleButton(props) {
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
    function EditComparisonTable(props) {
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
    function EditSubtleBanner(props) {
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

    // ==========================================================================
    // カードローン用Edit関数定義
    // ==========================================================================

    /**
     * カードローン結論ボックス Edit
     */
    function EditCardloanConclusionBox(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();
        var companyName = getCardloanName(attributes.company);

        var previewFeatures = [];
        if (attributes.customFeatures) {
            previewFeatures = attributes.customFeatures.split('\n').filter(function(f) { return f.trim(); });
        }
        if (previewFeatures.length === 0) {
            previewFeatures = ['特徴1（カードローン管理で設定）', '特徴2', '特徴3'];
        }

        return el('div', blockProps,
            el(InspectorControls, null,
                el(PanelBody, {
                    title: i18n.selectCardloan || 'カードローン設定',
                    initialOpen: true
                },
                    el(SelectControl, {
                        label: i18n.selectCardloan || 'カードローンを選択',
                        value: attributes.company,
                        options: cardloanOptions,
                        onChange: function(value) {
                            log('カードローン変更: ' + value);
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
                    }),
                    el(TextareaControl, {
                        label: 'カスタム特徴',
                        value: attributes.customFeatures,
                        onChange: function(value) {
                            setAttributes({ customFeatures: value });
                        },
                        help: '1行につき1つの特徴。空欄の場合はカードローン管理で設定した特徴を表示',
                        rows: 4
                    })
                )
            ),
            el('div', { className: 'soico-cta-editor-preview soico-cta-static-preview soico-cardloan-preview' },
                el('div', { className: 'soico-cta-preview-box', style: { border: '2px solid #4CAF50', borderRadius: '8px', padding: '20px', background: '#f1f8e9' } },
                    el('div', { style: { marginBottom: '10px' } },
                        el('span', { style: { background: '#4CAF50', color: '#fff', padding: '4px 12px', borderRadius: '4px', fontSize: '12px', fontWeight: 'bold' } }, '結論')
                    ),
                    el('h3', { style: { margin: '10px 0', fontSize: '18px' } },
                        attributes.customTitle || 'カードローンなら' + companyName + 'がおすすめ'
                    ),
                    attributes.showFeatures && el('ul', { style: { margin: '10px 0', paddingLeft: '20px', color: '#666' } },
                        previewFeatures.map(function(feature, idx) {
                            return el('li', { key: idx }, feature);
                        })
                    ),
                    el('div', { style: { marginTop: '15px' } },
                        el('span', { style: { background: '#4CAF50', color: '#fff', padding: '12px 24px', borderRadius: '4px', display: 'inline-block' } },
                            companyName + 'に申し込む'
                        )
                    ),
                    el('p', { style: { fontSize: '12px', color: '#999', marginTop: '10px' } },
                        '※エディタプレビュー'
                    )
                )
            )
        );
    }

    /**
     * カードローンインラインCTA Edit
     */
    function EditCardloanInlineCTA(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();
        var companyName = getCardloanName(attributes.company);
        var featureText = attributes.featureText || '特徴（カードローン管理で設定）';

        return el('div', blockProps,
            el(InspectorControls, null,
                el(PanelBody, { title: '設定', initialOpen: true },
                    el(SelectControl, {
                        label: i18n.selectCardloan || 'カードローンを選択',
                        value: attributes.company,
                        options: cardloanOptions,
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
                    }),
                    el(TextControl, {
                        label: '特徴テキスト',
                        value: attributes.featureText,
                        onChange: function(value) {
                            setAttributes({ featureText: value });
                        },
                        help: '空欄の場合はカードローン管理で設定した特徴を表示'
                    })
                )
            ),
            el('div', { className: 'soico-cta-editor-preview soico-cta-static-preview soico-cardloan-preview' },
                el('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 16px', background: attributes.style === 'subtle' ? '#f5f5f5' : '#e8f5e9', borderRadius: '6px', border: '1px solid #c8e6c9' } },
                    el('div', null,
                        el('strong', null, companyName),
                        el('span', { style: { marginLeft: '10px', color: '#666', fontSize: '14px' } }, featureText)
                    ),
                    el('span', { style: { background: '#4CAF50', color: '#fff', padding: '6px 12px', borderRadius: '4px', fontSize: '13px' } }, '詳細を見る →')
                ),
                el('p', { style: { fontSize: '11px', color: '#999', marginTop: '5px', marginBottom: '0' } }, '※エディタプレビュー')
            )
        );
    }

    /**
     * カードローンCTAボタン Edit
     */
    function EditCardloanSingleButton(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();
        var companyName = getCardloanName(attributes.company);
        var buttonText = attributes.buttonText || companyName + 'の公式サイトを見る';

        return el('div', blockProps,
            el(InspectorControls, null,
                el(PanelBody, { title: '設定', initialOpen: true },
                    el(SelectControl, {
                        label: i18n.selectCardloan || 'カードローンを選択',
                        value: attributes.company,
                        options: cardloanOptions,
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
            el('div', { className: 'soico-cta-editor-preview soico-cta-static-preview soico-cardloan-preview', style: { textAlign: 'center' } },
                el('span', { style: { background: '#4CAF50', color: '#fff', padding: '14px 28px', borderRadius: '6px', display: 'inline-block', fontSize: '16px', fontWeight: 'bold' } },
                    buttonText
                ),
                attributes.showPR && el('p', { style: { fontSize: '12px', color: '#999', marginTop: '8px', marginBottom: '0' } }, 'PR'),
                el('p', { style: { fontSize: '11px', color: '#999', marginTop: '5px', marginBottom: '0' } }, '※エディタプレビュー')
            )
        );
    }

    /**
     * カードローン比較表 Edit
     */
    function EditCardloanComparisonTable(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();

        var sampleRows = [];
        for (var i = 0; i < Math.min(attributes.limit, 3); i++) {
            var rank = i + 1;
            var name = cardloanOptions[i] ? cardloanOptions[i].label : 'カードローン' + rank;
            sampleRows.push(
                el('tr', { key: i, style: { background: rank === 1 ? '#e8f5e9' : '#fff' } },
                    el('td', { style: { padding: '10px', textAlign: 'center', fontWeight: 'bold', color: rank === 1 ? '#4CAF50' : '#666' } }, rank),
                    el('td', { style: { padding: '10px' } }, name),
                    attributes.showInterestRate && el('td', { style: { padding: '10px' } }, '3.0%〜18.0%'),
                    attributes.showLimitAmount && el('td', { style: { padding: '10px' } }, '800万円'),
                    attributes.showReviewTime && el('td', { style: { padding: '10px' } }, '最短25分'),
                    el('td', { style: { padding: '10px' } },
                        el('span', { style: { background: '#4CAF50', color: '#fff', padding: '4px 10px', borderRadius: '4px', fontSize: '12px' } }, '詳細')
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
                        label: i18n.showInterestRate || '金利を表示',
                        checked: attributes.showInterestRate,
                        onChange: function(value) {
                            setAttributes({ showInterestRate: value });
                        }
                    }),
                    el(ToggleControl, {
                        label: i18n.showLimitAmount || '限度額を表示',
                        checked: attributes.showLimitAmount,
                        onChange: function(value) {
                            setAttributes({ showLimitAmount: value });
                        }
                    }),
                    el(ToggleControl, {
                        label: i18n.showReviewTime || '審査時間を表示',
                        checked: attributes.showReviewTime,
                        onChange: function(value) {
                            setAttributes({ showReviewTime: value });
                        }
                    })
                )
            ),
            el('div', { className: 'soico-cta-editor-preview soico-cta-static-preview soico-cardloan-preview' },
                el('table', { style: { width: '100%', borderCollapse: 'collapse', border: '1px solid #c8e6c9', fontSize: '14px' } },
                    el('thead', null,
                        el('tr', { style: { background: '#e8f5e9' } },
                            el('th', { style: { padding: '10px', borderBottom: '1px solid #c8e6c9' } }, '順位'),
                            el('th', { style: { padding: '10px', borderBottom: '1px solid #c8e6c9' } }, '会社名'),
                            attributes.showInterestRate && el('th', { style: { padding: '10px', borderBottom: '1px solid #c8e6c9' } }, '金利'),
                            attributes.showLimitAmount && el('th', { style: { padding: '10px', borderBottom: '1px solid #c8e6c9' } }, '限度額'),
                            attributes.showReviewTime && el('th', { style: { padding: '10px', borderBottom: '1px solid #c8e6c9' } }, '審査時間'),
                            el('th', { style: { padding: '10px', borderBottom: '1px solid #c8e6c9' } }, '申込')
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
     * カードローン控えめバナー Edit
     */
    function EditCardloanSubtleBanner(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;
        var blockProps = useBlockProps();
        var companyName = getCardloanName(attributes.company);
        var message = attributes.message || '💡 カードローンをお探しなら → ' + companyName + '（最短即日融資）';

        return el('div', blockProps,
            el(InspectorControls, null,
                el(PanelBody, { title: '設定', initialOpen: true },
                    el(SelectControl, {
                        label: i18n.selectCardloan || 'カードローンを選択',
                        value: attributes.company,
                        options: cardloanOptions,
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
            el('div', { className: 'soico-cta-editor-preview soico-cta-static-preview soico-cardloan-preview' },
                el('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '10px 14px', background: '#f1f8e9', border: '1px solid #c8e6c9', borderRadius: '4px', fontSize: '14px' } },
                    el('span', null, message),
                    el('span', { style: { background: '#c8e6c9', color: '#2e7d32', padding: '2px 6px', borderRadius: '2px', fontSize: '11px' } }, 'PR')
                ),
                el('p', { style: { fontSize: '11px', color: '#999', marginTop: '5px', marginBottom: '0' } }, '※エディタプレビュー')
            )
        );
    }

    // ==========================================================================
    // ブロック登録
    // PHPで登録されたブロックを一度解除し、edit関数付きで再登録する
    // ==========================================================================

    log('=== ブロック再登録開始 ===');

    // 利用可能なカテゴリをログ出力
    if (wp.blocks && wp.blocks.getCategories) {
        var availableCategories = wp.blocks.getCategories();
        log('利用可能なカテゴリ:', availableCategories.map(function(c) { return c.slug; }));

        // カードローンカテゴリの存在確認
        var hasCardloanCategory = availableCategories.some(function(c) { return c.slug === 'soico-cardloan-cta'; });
        var hasSecuritiesCategory = availableCategories.some(function(c) { return c.slug === 'soico-securities-cta'; });
        log('カテゴリ存在確認:', {
            'soico-securities-cta': hasSecuritiesCategory,
            'soico-cardloan-cta': hasCardloanCategory
        });
    }

    /**
     * ブロックを再登録する
     * PHPで登録された設定を引き継ぎつつ、edit/save関数を追加
     */
    function reRegisterBlock(name, editFunc, blockConfig) {
        log('ブロック登録開始: ' + name, blockConfig);

        var existingBlock = getBlockType(name);
        log('既存ブロック確認: ' + name, existingBlock ? 'あり' : 'なし');

        if (existingBlock) {
            log('既存ブロックを解除: ' + name);
            try {
                unregisterBlockType(name);
                log('ブロック解除成功: ' + name);
            } catch (e) {
                error('ブロック解除エラー: ' + name, e);
            }
        }

        // カテゴリを決定（カードローンか証券か）
        var blockCategory = blockConfig.category || 'soico-securities-cta';
        log('使用カテゴリ: ' + blockCategory);

        // edit関数の確認
        if (typeof editFunc !== 'function') {
            error('edit関数が無効です: ' + name, typeof editFunc);
            return false;
        }

        // 新しい設定でブロックを登録
        var settings = {
            title: blockConfig.title,
            icon: blockConfig.icon,
            category: blockCategory,
            description: blockConfig.description,
            attributes: blockConfig.attributes,
            supports: {
                html: false
            },
            edit: editFunc,
            save: saveDynamic
        };

        log('登録設定:', {
            name: name,
            title: settings.title,
            icon: settings.icon,
            category: settings.category,
            hasEdit: typeof settings.edit === 'function',
            hasSave: typeof settings.save === 'function',
            attributeKeys: Object.keys(settings.attributes || {})
        });

        try {
            var result = registerBlockType(name, settings);
            if (result) {
                log('ブロック登録完了: ' + name + ' (カテゴリ: ' + blockCategory + ')');
                return true;
            } else {
                error('ブロック登録失敗（結果がnull）: ' + name);
                return false;
            }
        } catch (e) {
            error('ブロック登録エラー: ' + name, e);
            error('エラー詳細:', e.message, e.stack);
            return false;
        }
    }

    // ブロック定義
    var blockDefinitions = [
        {
            name: 'soico-cta/conclusion-box',
            title: i18n.conclusionBox || '結論ボックス',
            icon: 'megaphone',
            description: '記事冒頭に最適。証券会社のおすすめポイントと特徴リスト、CTAボタンを表示します。',
            attributes: {
                company: { type: 'string', default: 'sbi' },
                showFeatures: { type: 'boolean', default: true },
                customTitle: { type: 'string', default: '' },
                customFeatures: { type: 'string', default: '' }
            },
            edit: EditConclusionBox
        },
        {
            name: 'soico-cta/inline-cta',
            title: i18n.inlineCTA || 'インラインCTA',
            icon: 'migrate',
            description: '記事の途中に自然に挿入できる控えめなCTA。流れを邪魔しません。',
            attributes: {
                company: { type: 'string', default: 'sbi' },
                style: { type: 'string', default: 'default' },
                featureText: { type: 'string', default: '' }
            },
            edit: EditInlineCTA
        },
        {
            name: 'soico-cta/single-button',
            title: i18n.singleButton || 'CTAボタン',
            icon: 'button',
            description: 'シンプルなボタンのみ。任意の場所に配置できます。',
            attributes: {
                company: { type: 'string', default: 'sbi' },
                buttonText: { type: 'string', default: '' },
                showPR: { type: 'boolean', default: true }
            },
            edit: EditSingleButton
        },
        {
            name: 'soico-cta/comparison-table',
            title: i18n.comparisonTable || '比較表',
            icon: 'editor-table',
            description: '複数の証券会社を比較する表形式のCTA。ランキング記事に最適。',
            attributes: {
                companies: { type: 'array', default: ['sbi', 'monex', 'rakuten'] },
                limit: { type: 'number', default: 3 },
                showCommission: { type: 'boolean', default: true }
            },
            edit: EditComparisonTable
        },
        {
            name: 'soico-cta/subtle-banner',
            title: i18n.subtleBanner || '控えめバナー',
            icon: 'info-outline',
            description: 'テキストリンク形式の最も控えめなCTA。読者の邪魔をしません。',
            attributes: {
                company: { type: 'string', default: 'sbi' },
                message: { type: 'string', default: '' }
            },
            edit: EditSubtleBanner
        },
        // カードローンブロック
        {
            name: 'soico-cta/cardloan-conclusion-box',
            title: 'カードローン結論ボックス',
            icon: 'money-alt',
            category: 'soico-cardloan-cta',
            description: '記事冒頭に最適。おすすめのカードローンと特徴リスト、CTAボタンを表示します。',
            attributes: {
                company: { type: 'string', default: 'aiful' },
                showFeatures: { type: 'boolean', default: true },
                customTitle: { type: 'string', default: '' },
                customFeatures: { type: 'string', default: '' }
            },
            edit: EditCardloanConclusionBox
        },
        {
            name: 'soico-cta/cardloan-inline-cta',
            title: 'カードローンインラインCTA',
            icon: 'money-alt',
            category: 'soico-cardloan-cta',
            description: '記事の途中に自然に挿入できる控えめなカードローンCTA。',
            attributes: {
                company: { type: 'string', default: 'aiful' },
                style: { type: 'string', default: 'default' },
                featureText: { type: 'string', default: '' }
            },
            edit: EditCardloanInlineCTA
        },
        {
            name: 'soico-cta/cardloan-single-button',
            title: 'カードローンCTAボタン',
            icon: 'money-alt',
            category: 'soico-cardloan-cta',
            description: 'シンプルなカードローンCTAボタン。任意の場所に配置できます。',
            attributes: {
                company: { type: 'string', default: 'aiful' },
                buttonText: { type: 'string', default: '' },
                showPR: { type: 'boolean', default: true }
            },
            edit: EditCardloanSingleButton
        },
        {
            name: 'soico-cta/cardloan-comparison-table',
            title: 'カードローン比較表',
            icon: 'money-alt',
            category: 'soico-cardloan-cta',
            description: '複数のカードローンを比較する表形式のCTA。ランキング記事に最適。',
            attributes: {
                companies: { type: 'array', default: ['aiful', 'promise', 'acom'] },
                limit: { type: 'number', default: 3 },
                showInterestRate: { type: 'boolean', default: true },
                showLimitAmount: { type: 'boolean', default: true },
                showReviewTime: { type: 'boolean', default: true }
            },
            edit: EditCardloanComparisonTable
        },
        {
            name: 'soico-cta/cardloan-subtle-banner',
            title: 'カードローン控えめバナー',
            icon: 'money-alt',
            category: 'soico-cardloan-cta',
            description: 'テキストリンク形式の最も控えめなカードローンCTA。',
            attributes: {
                company: { type: 'string', default: 'aiful' },
                message: { type: 'string', default: '' }
            },
            edit: EditCardloanSubtleBanner
        }
    ];

    // 各ブロックを登録
    var registrationResults = { success: [], failed: [] };

    log('=== ブロック定義数: ' + blockDefinitions.length + ' ===');

    blockDefinitions.forEach(function(block, index) {
        log('--- ブロック ' + (index + 1) + '/' + blockDefinitions.length + ' ---');
        try {
            var result = reRegisterBlock(block.name, block.edit, block);
            if (result) {
                registrationResults.success.push(block.name);
            } else {
                registrationResults.failed.push(block.name);
            }
        } catch (e) {
            error('ブロック登録中に例外: ' + block.name, e);
            registrationResults.failed.push(block.name);
        }
    });

    log('=== ブロック登録完了 ===');
    log('成功: ' + registrationResults.success.length + '件', registrationResults.success);
    if (registrationResults.failed.length > 0) {
        warn('失敗: ' + registrationResults.failed.length + '件', registrationResults.failed);
    }

    // 証券とカードローンの登録状況を個別にチェック
    var securitiesBlocks = ['soico-cta/conclusion-box', 'soico-cta/inline-cta', 'soico-cta/single-button', 'soico-cta/comparison-table', 'soico-cta/subtle-banner'];
    var cardloanBlocks = ['soico-cta/cardloan-conclusion-box', 'soico-cta/cardloan-inline-cta', 'soico-cta/cardloan-single-button', 'soico-cta/cardloan-comparison-table', 'soico-cta/cardloan-subtle-banner'];

    log('=== 登録状況サマリー ===');
    log('証券ブロック:');
    securitiesBlocks.forEach(function(name) {
        var registered = getBlockType(name);
        log('  ' + (registered ? '✓' : '✗') + ' ' + name);
    });

    log('カードローンブロック:');
    cardloanBlocks.forEach(function(name) {
        var registered = getBlockType(name);
        log('  ' + (registered ? '✓' : '✗') + ' ' + name);
    });

    // ==========================================================================
    // グローバル診断関数
    // ==========================================================================
    window.soicoCTADiagnostics = function() {
        console.group('[SOICO CTA] 診断レポート');

        console.log('=== データ状態 ===');
        console.log('soicoCTAData:', window.soicoCTAData);
        console.log('証券データ数:', (window.soicoCTAData && window.soicoCTAData.selectOptions) ? window.soicoCTAData.selectOptions.length : 0);
        console.log('カードローンデータ数:', (window.soicoCTAData && window.soicoCTAData.cardloanSelectOptions) ? window.soicoCTAData.cardloanSelectOptions.length : 0);

        console.log('=== カテゴリ状態 ===');
        if (wp.blocks && wp.blocks.getCategories) {
            var cats = wp.blocks.getCategories();
            cats.forEach(function(cat) {
                console.log('  - ' + cat.slug + ': ' + cat.title);
            });
        }

        console.log('=== ブロック登録状態 ===');
        var allBlocks = securitiesBlocks.concat(cardloanBlocks);
        allBlocks.forEach(function(name) {
            var block = getBlockType(name);
            if (block) {
                console.log('  ✓ ' + name + ' (カテゴリ: ' + block.category + ')');
            } else {
                console.log('  ✗ ' + name + ' (未登録)');
            }
        });

        console.log('=== 全ブロック一覧（SOICO関連） ===');
        if (wp.blocks && wp.blocks.getBlockTypes) {
            var allRegistered = wp.blocks.getBlockTypes();
            allRegistered.forEach(function(block) {
                if (block.name.indexOf('soico-cta') === 0) {
                    console.log('  ' + block.name + ' -> ' + block.category);
                }
            });
        }

        console.groupEnd();
        return '診断完了。上記のログを確認してください。';
    };

    log('=== SOICO CTA 初期化完了 ===');
    log('診断コマンド: soicoCTADiagnostics() をコンソールで実行してください');

})(window.wp);
