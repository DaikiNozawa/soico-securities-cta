/**
 * SOICO Securities CTA - 管理画面JavaScript
 *
 * @package Soico_Securities_CTA
 */

(function($) {
    'use strict';

    // 設定オブジェクト
    var config = window.soicoCTAAdmin || {};

    /**
     * 初期化
     */
    function init() {
        console.log('SOICO CTA Admin: 初期化開始');

        // 各機能を個別に初期化（エラーがあっても他の機能は動作するように）
        try {
            initColorPickers();
        } catch(e) {
            console.warn('カラーピッカー初期化エラー:', e);
        }

        try {
            initSortable();
        } catch(e) {
            console.warn('ソート機能初期化エラー:', e);
        }

        try {
            initToggleDetails();
        } catch(e) {
            console.warn('トグル機能初期化エラー:', e);
        }

        try {
            initFormSubmit();
        } catch(e) {
            console.warn('フォーム送信初期化エラー:', e);
        }

        try {
            initAddSecurity();
        } catch(e) {
            console.warn('追加機能初期化エラー:', e);
        }

        try {
            initDeleteSecurity();
        } catch(e) {
            console.warn('削除機能初期化エラー:', e);
        }

        try {
            initModal();
        } catch(e) {
            console.warn('モーダル初期化エラー:', e);
        }

        console.log('SOICO CTA Admin: 初期化完了');
    }

    /**
     * カラーピッカー初期化
     */
    function initColorPickers() {
        if (typeof $.fn.wpColorPicker === 'function') {
            $('.color-picker').wpColorPicker();
        }
    }

    /**
     * ソート機能初期化
     */
    function initSortable() {
        if (typeof $.fn.sortable === 'function') {
            $('#securities-list').sortable({
                handle: '.soico-cta-drag-handle',
                placeholder: 'soico-cta-sortable-placeholder',
                update: function(event, ui) {
                    updatePriorities();
                }
            });
        }
    }

    /**
     * 優先順位更新
     */
    function updatePriorities() {
        $('.soico-cta-security-row').each(function(index) {
            $(this).find('.priority-input').val(index + 1);
            $(this).find('.soico-cta-security-priority').text('優先順位: ' + (index + 1));
        });
    }

    /**
     * 詳細トグル
     */
    function initToggleDetails() {
        // ヘッダークリックで詳細を展開/折りたたみ
        $(document).on('click', '.soico-cta-security-header', function(e) {
            // ドラッグハンドルのクリックは無視
            if ($(e.target).hasClass('soico-cta-drag-handle') || $(e.target).closest('.soico-cta-drag-handle').length) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            var $row = $(this).closest('.soico-cta-security-row');
            var $details = $row.find('.soico-cta-security-details');

            $details.slideToggle(200);
        });

        // 詳細ボタンのクリック
        $(document).on('click', '.soico-cta-toggle-details', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $row = $(this).closest('.soico-cta-security-row');
            var $details = $row.find('.soico-cta-security-details');

            $details.slideToggle(200);
        });
    }

    /**
     * フォーム送信
     */
    function initFormSubmit() {
        $('#soico-cta-securities-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('[type="submit"]');
            var originalText = $submitBtn.text();

            $submitBtn.prop('disabled', true).text(config.i18n && config.i18n.saving ? config.i18n.saving : '保存中...');

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'soico_cta_save_securities',
                    nonce: config.nonce,
                    securities: getFormData($form)
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(config.i18n && config.i18n.saved ? config.i18n.saved : '保存しました', 'success');
                    } else {
                        showNotice(response.data && response.data.message ? response.data.message : 'エラーが発生しました', 'error');
                    }
                },
                error: function() {
                    showNotice(config.i18n && config.i18n.error ? config.i18n.error : 'エラーが発生しました', 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    /**
     * フォームデータ取得
     */
    function getFormData($form) {
        var data = {};

        $form.find('.soico-cta-security-row').each(function() {
            var $row = $(this);
            var slug = $row.data('slug');

            data[slug] = {
                slug: slug,
                name: $row.find('input[name$="[name]"]').val(),
                enabled: $row.find('input[name$="[enabled]"]').is(':checked') ? 1 : 0,
                priority: $row.find('input[name$="[priority]"]').val(),
                thirsty_link: $row.find('select[name$="[thirsty_link]"]').val(),
                direct_url: $row.find('input[name$="[direct_url]"]').val(),
                features: $row.find('textarea[name$="[features]"]').val(),
                commission: $row.find('input[name$="[commission]"]').val(),
                badge: $row.find('input[name$="[badge]"]').val(),
                badge_color: $row.find('input[name$="[badge_color]"]').val(),
                button_text: $row.find('input[name$="[button_text]"]').val(),
                button_color: $row.find('input[name$="[button_color]"]').val()
            };
        });

        return data;
    }

    /**
     * 証券会社追加
     */
    function initAddSecurity() {
        $('#add-security-btn').on('click', function() {
            $('#add-security-modal').show();
        });

        $('#add-security-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('[type="submit"]');

            $submitBtn.prop('disabled', true);

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'soico_cta_add_security',
                    nonce: config.nonce,
                    slug: $form.find('input[name="slug"]').val(),
                    name: $form.find('input[name="name"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data && response.data.reload) {
                            location.reload();
                        } else {
                            showNotice(response.data && response.data.message ? response.data.message : '追加しました', 'success');
                            closeModal();
                        }
                    } else {
                        showNotice(response.data && response.data.message ? response.data.message : 'エラーが発生しました', 'error');
                    }
                },
                error: function() {
                    showNotice('エラーが発生しました', 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false);
                }
            });
        });
    }

    /**
     * 証券会社削除
     */
    function initDeleteSecurity() {
        $(document).on('click', '.soico-cta-delete-security', function(e) {
            e.preventDefault();

            var confirmMsg = config.i18n && config.i18n.confirmDelete ? config.i18n.confirmDelete : 'この証券会社を削除しますか？';
            if (!confirm(confirmMsg)) {
                return;
            }

            var $row = $(this).closest('.soico-cta-security-row');
            var slug = $row.data('slug');

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'soico_cta_delete_security',
                    nonce: config.nonce,
                    slug: slug
                },
                success: function(response) {
                    if (response.success) {
                        $row.slideUp(200, function() {
                            $(this).remove();
                            updatePriorities();
                        });
                        showNotice(response.data && response.data.message ? response.data.message : '削除しました', 'success');
                    } else {
                        showNotice(response.data && response.data.message ? response.data.message : 'エラーが発生しました', 'error');
                    }
                },
                error: function() {
                    showNotice('エラーが発生しました', 'error');
                }
            });
        });
    }

    /**
     * モーダル
     */
    function initModal() {
        $('#cancel-add-security').on('click', closeModal);

        $(document).on('click', '.soico-cta-modal', function(e) {
            if ($(e.target).hasClass('soico-cta-modal')) {
                closeModal();
            }
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    }

    /**
     * モーダルを閉じる
     */
    function closeModal() {
        $('#add-security-modal').hide();
        var form = document.getElementById('add-security-form');
        if (form) {
            form.reset();
        }
    }

    /**
     * 通知表示
     */
    function showNotice(message, type) {
        var $notice = $('<div class="soico-cta-notice"></div>')
            .addClass(type === 'error' ? 'error' : '')
            .text(message)
            .appendTo('body');

        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // DOM Ready
    $(document).ready(init);

})(jQuery);
