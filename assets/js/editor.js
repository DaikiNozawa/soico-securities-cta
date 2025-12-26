/**
 * SOICO Securities CTA - Gutenberg Block Editor
 *
 * @package Soico_Securities_CTA
 */

(function(wp) {
    'use strict';

    var el = wp.element.createElement;
    var registerBlockType = wp.blocks.registerBlockType;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var ServerSideRender = wp.serverSideRender;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var TextControl = wp.components.TextControl;
    var RangeControl = wp.components.RangeControl;
    var __ = wp.i18n.__;

    // Get localized data
    var data = window.soicoCTAData || {};
    var selectOptions = data.selectOptions || [];
    var i18n = data.i18n || {};

    // Convert select options to WordPress format
    var companyOptions = selectOptions.map(function(opt) {
        return { value: opt.value, label: opt.label };
    });

    // Fallback if no options available
    if (companyOptions.length === 0) {
        companyOptions = [
            { value: 'sbi', label: 'SBI証券' },
            { value: 'monex', label: 'マネックス証券' },
            { value: 'rakuten', label: '楽天証券' }
        ];
    }

    // Style options for inline-cta
    var styleOptions = [
        { value: 'default', label: 'デフォルト' },
        { value: 'subtle', label: '控えめ' }
    ];

    // =========================================================================
    // Block 1: Conclusion Box (結論ボックス)
    // =========================================================================
    registerBlockType('soico-cta/conclusion-box', {
        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el('div', blockProps,
                el(InspectorControls, null,
                    el(PanelBody, { title: i18n.selectCompany || '証券会社設定', initialOpen: true },
                        el(SelectControl, {
                            label: i18n.selectCompany || '証券会社を選択',
                            value: attributes.company,
                            options: companyOptions,
                            onChange: function(value) {
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
                        attributes: attributes
                    })
                )
            );
        },
        save: function() {
            return null; // Dynamic block - rendered by PHP
        }
    });

    // =========================================================================
    // Block 2: Inline CTA (インラインCTA)
    // =========================================================================
    registerBlockType('soico-cta/inline-cta', {
        edit: function(props) {
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
                        attributes: attributes
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });

    // =========================================================================
    // Block 3: Single Button (CTAボタン)
    // =========================================================================
    registerBlockType('soico-cta/single-button', {
        edit: function(props) {
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
                        attributes: attributes
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });

    // =========================================================================
    // Block 4: Comparison Table (比較表)
    // =========================================================================
    registerBlockType('soico-cta/comparison-table', {
        edit: function(props) {
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
                        attributes: attributes
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });

    // =========================================================================
    // Block 5: Subtle Banner (控えめバナー)
    // =========================================================================
    registerBlockType('soico-cta/subtle-banner', {
        edit: function(props) {
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
                        attributes: attributes
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });

})(window.wp);
