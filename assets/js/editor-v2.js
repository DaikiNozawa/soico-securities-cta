/**
 * SOICO CTA v2 - Gutenberg Block Editor Extension
 *
 * 既存ブロックにv2属性（version, variant, カスタムHTML）のUIを追加
 * 既存editor.jsと共存し、InspectorControlsにv2パネルを追加する
 *
 * @since 2.0.0
 */

(function(wp) {
    'use strict';

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
    var addFilter = wp.hooks.addFilter;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;

    // v2対応ブロック名の一覧
    var V2_BLOCKS = [
        'soico-cta/conclusion-box',
        'soico-cta/inline-cta',
        'soico-cta/single-button',
        'soico-cta/comparison-table',
        'soico-cta/subtle-banner',
        'soico-cta/cardloan-conclusion-box',
        'soico-cta/cardloan-inline-cta',
        'soico-cta/cardloan-single-button',
        'soico-cta/cardloan-comparison-table',
        'soico-cta/cardloan-subtle-banner',
        'soico-cta/crypto-conclusion-box',
        'soico-cta/crypto-inline-cta',
        'soico-cta/crypto-single-button',
        'soico-cta/crypto-comparison-table',
        'soico-cta/crypto-subtle-banner'
    ];

    /**
     * ブロックがv2対応かチェック
     */
    function isV2Block(name) {
        return V2_BLOCKS.indexOf(name) !== -1;
    }

    /**
     * ブロック属性にv2用属性を追加
     */
    addFilter(
        'blocks.registerBlockType',
        'soico-cta/v2-attributes',
        function(settings, name) {
            if (!isV2Block(name)) {
                return settings;
            }

            // 既存属性にv2属性をマージ
            settings.attributes = Object.assign({}, settings.attributes, {
                version: {
                    type: 'string',
                    default: '1'
                },
                variant: {
                    type: 'string',
                    default: 'default'
                },
                customHtmlBefore: {
                    type: 'string',
                    default: ''
                },
                customHtmlInner: {
                    type: 'string',
                    default: ''
                },
                customHtmlAfter: {
                    type: 'string',
                    default: ''
                },
                customNote: {
                    type: 'string',
                    default: ''
                },
                customLabel: {
                    type: 'string',
                    default: ''
                },
                fullCustomMode: {
                    type: 'boolean',
                    default: false
                },
                fullCustomHtml: {
                    type: 'string',
                    default: ''
                }
            });

            return settings;
        }
    );

    /**
     * ブロックエディタにv2設定パネルを追加
     */
    var withV2Controls = createHigherOrderComponent(function(BlockEdit) {
        return function(props) {
            if (!isV2Block(props.name)) {
                return el(BlockEdit, props);
            }

            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var isV2 = attributes.version === '2';
            var isFullCustom = attributes.fullCustomMode;

            // バージョン表示ラベル
            var versionLabel = isV2 ? 'v2 (新デザイン)' : 'v1 (従来)';

            // v2設定パネル
            var v2Panel = el(InspectorControls, {},
                el(PanelBody, {
                    title: 'CTA バージョン',
                    initialOpen: true
                },
                    el(SelectControl, {
                        label: 'デザインバージョン',
                        value: attributes.version || '1',
                        options: [
                            { value: '1', label: 'v1 — 従来デザイン' },
                            { value: '2', label: 'v2 — 新デザイン' }
                        ],
                        onChange: function(value) {
                            setAttributes({ version: value });
                        },
                        help: isV2
                            ? 'v2: soico.jpに統一されたクリーンなデザイン'
                            : 'v1: 既存のデザインがそのまま表示されます'
                    })
                ),

                // v2選択時のみ追加設定を表示
                isV2 && el(PanelBody, {
                    title: 'v2 デザイン設定',
                    initialOpen: true
                },
                    el(SelectControl, {
                        label: 'バリアント',
                        value: attributes.variant || 'default',
                        options: [
                            { value: 'default', label: 'デフォルト' },
                            { value: 'minimal', label: 'ミニマル（装飾なし）' },
                            { value: 'card', label: 'カード（枠+影）' },
                            { value: 'highlight', label: 'ハイライト（背景色）' }
                        ],
                        onChange: function(value) {
                            setAttributes({ variant: value });
                        }
                    })
                ),

                // v2 HTML装飾パネル
                isV2 && el(PanelBody, {
                    title: 'HTML装飾',
                    initialOpen: false
                },
                    // フルカスタムモード
                    el(ToggleControl, {
                        label: 'フルカスタムモード',
                        checked: !!attributes.fullCustomMode,
                        onChange: function(value) {
                            setAttributes({ fullCustomMode: value });
                        },
                        help: 'ONにするとブロック全体のHTMLを自由に記述できます'
                    }),

                    isFullCustom && el(TextareaControl, {
                        label: 'カスタムHTML',
                        value: attributes.fullCustomHtml || '',
                        onChange: function(value) {
                            setAttributes({ fullCustomHtml: value });
                        },
                        help: '変数: {{company_name}}, {{affiliate_url}}, {{features_text}}, {{{button}}}, {{{pr_label}}} 等',
                        rows: 12
                    }),

                    // 通常モード（3スロット）
                    !isFullCustom && el(Fragment, {},
                        el(TextareaControl, {
                            label: '上部HTML',
                            value: attributes.customHtmlBefore || '',
                            onChange: function(value) {
                                setAttributes({ customHtmlBefore: value });
                            },
                            help: 'CTAの上部に挿入されるHTML（キャンペーン告知等）',
                            rows: 3
                        }),
                        el(TextareaControl, {
                            label: '内部HTML',
                            value: attributes.customHtmlInner || '',
                            onChange: function(value) {
                                setAttributes({ customHtmlInner: value });
                            },
                            help: '特徴リストとボタンの間に挿入されるHTML',
                            rows: 3
                        }),
                        el(TextareaControl, {
                            label: '下部HTML',
                            value: attributes.customHtmlAfter || '',
                            onChange: function(value) {
                                setAttributes({ customHtmlAfter: value });
                            },
                            help: 'CTAの下部に挿入されるHTML（免責事項等）',
                            rows: 3
                        })
                    )
                ),

                // v2 テキスト上書きパネル
                isV2 && el(PanelBody, {
                    title: 'テキスト上書き',
                    initialOpen: false
                },
                    el(TextControl, {
                        label: 'ラベル',
                        value: attributes.customLabel || '',
                        onChange: function(value) {
                            setAttributes({ customLabel: value });
                        },
                        help: '空欄時は「結論」がデフォルト'
                    }),
                    el(TextControl, {
                        label: '注釈テキスト',
                        value: attributes.customNote || '',
                        onChange: function(value) {
                            setAttributes({ customNote: value });
                        },
                        help: '空欄時はデフォルトの注釈が表示されます'
                    })
                )
            );

            // v2選択時のプレビューバッジ
            var versionBadge = isV2
                ? el('div', {
                    style: {
                        background: '#164C95',
                        color: '#fff',
                        padding: '2px 8px',
                        borderRadius: '2px',
                        fontSize: '10px',
                        fontWeight: '600',
                        display: 'inline-block',
                        marginBottom: '8px'
                    }
                }, 'v2 ' + (attributes.variant || 'default'))
                : null;

            return el(Fragment, {},
                v2Panel,
                versionBadge,
                el(BlockEdit, props)
            );
        };
    }, 'withV2Controls');

    addFilter(
        'editor.BlockEdit',
        'soico-cta/v2-controls',
        withV2Controls
    );

    console.log('[SOICO CTA v2] Editor extension loaded');

})(window.wp);
