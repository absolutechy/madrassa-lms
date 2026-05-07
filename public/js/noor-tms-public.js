/**
 * Noor-TMS – Front-End Portal JavaScript
 *
 * Mirrors the admin JS but works with the theme-side pages.
 * All AJAX calls hit admin-ajax.php with the same action names as admin.
 */

/* global noorTMS, jQuery */
( function ( $ ) {
    'use strict';

    // -----------------------------------------------------------------------
    // 1.  Save all-subject report (bulk marks form) via AJAX
    // -----------------------------------------------------------------------
    $( document ).on( 'submit', '#noor-tms-result-form', function ( e ) {
        e.preventDefault();

        const $form     = $( this );
        const $btn      = $( '#noor-save-result-btn' );
        const $feedback = $( '#noor-result-feedback' );
        const $waBtn    = $( '#noor-wa-report-btn' );

        $btn.prop( 'disabled', true ).text( noorTMS.i18n.saving );
        $feedback.text( '' ).removeClass( 'is-error is-success' );
        $waBtn.hide();

        $.ajax( {
            url:    noorTMS.ajaxUrl,
            method: 'POST',
            data:   $form.serialize() + '&action=noor_tms_save_report',
        } )
        .done( function ( response ) {
            if ( response.success ) {
                $feedback.addClass( 'is-success' ).text( response.data.message );

                if ( response.data.wa_url ) {
                    $waBtn.attr( 'href', response.data.wa_url ).show();
                }

                setTimeout( function () {
                    window.location.reload();
                }, 1800 );
            } else {
                $feedback.addClass( 'is-error' ).text( response.data.message || noorTMS.i18n.error );
            }
        } )
        .fail( function () {
            $feedback.addClass( 'is-error' ).text( noorTMS.i18n.error );
        } )
        .always( function () {
            $btn.prop( 'disabled', false ).text( noorTMS.i18n.saveReport );
        } );
    } );

    // -----------------------------------------------------------------------
    // 2.  Student quick-actions dropdown
    // -----------------------------------------------------------------------
    $( '.noor-actions-dropdown' ).prop( 'hidden', true );

    $( document ).on( 'click', '.noor-actions-toggle', function ( e ) {
        e.preventDefault();
        e.stopPropagation();

        const $menu = $( this ).closest( '.noor-actions-menu' );
        const $dropdown = $menu.find( '.noor-actions-dropdown' );
        const willOpen = ! $menu.hasClass( 'is-open' );

        closeAllActionMenus();

        if ( willOpen ) {
            $menu.addClass( 'is-open' );
            $( this ).attr( 'aria-expanded', 'true' );
            $dropdown.prop( 'hidden', false );
        }
    } );

    $( document ).on( 'click', function () {
        closeAllActionMenus();
    } );

    $( document ).on( 'click', '.noor-actions-dropdown', function ( e ) {
        e.stopPropagation();
    } );

    $( document ).on( 'click', '.noor-actions-link', function () {
        closeAllActionMenus();
    } );

    $( document ).on( 'keyup', function ( e ) {
        if ( e.key === 'Escape' ) {
            closeAllActionMenus();
        }
    } );

    function closeAllActionMenus() {
        $( '.noor-actions-menu' ).each( function () {
            const $menu = $( this );
            $menu.removeClass( 'is-open' );
            $menu.find( '.noor-actions-toggle' ).attr( 'aria-expanded', 'false' );
            $menu.find( '.noor-actions-dropdown' ).prop( 'hidden', true );
        } );
    }

    // -----------------------------------------------------------------------
    // 3.  Delete student row
    // -----------------------------------------------------------------------
    $( document ).on( 'click', '.noor-delete-student', function () {
        if ( ! confirm( noorTMS.i18n.confirmDelete ) ) return;

        const $btn      = $( this );
        const studentId = $btn.data( 'id' );
        const nonce     = $btn.data( 'nonce' ) || noorTMS.nonce;
        const $row      = $btn.closest( 'tr' );

        $btn.prop( 'disabled', true ).text( noorTMS.i18n.deleting );

        $.ajax( {
            url:    noorTMS.ajaxUrl,
            method: 'POST',
            data:   { action: 'noor_tms_delete_student', student_id: studentId, nonce: nonce },
        } )
        .done( function ( response ) {
            if ( response.success ) {
                alert( response.data.message || 'Student deleted successfully.' );
                $row.fadeOut( 300, function () { $( this ).remove(); } );
            } else {
                alert( response.data.message || noorTMS.i18n.error );
                $btn.prop( 'disabled', false ).text( 'Delete' );
            }
        } )
        .fail( function () {
            alert( noorTMS.i18n.error );
            $btn.prop( 'disabled', false ).text( 'Delete' );
        } );
    } );

    // -----------------------------------------------------------------------
    // 4.  Delete result row
    // -----------------------------------------------------------------------
    $( document ).on( 'click', '.noor-delete-result', function () {
        if ( ! confirm( noorTMS.i18n.confirmDelete ) ) return;

        const $btn     = $( this );
        const resultId = $btn.data( 'id' );
        const nonce    = $btn.data( 'nonce' );
        const $row     = $btn.closest( 'tr' );

        $btn.prop( 'disabled', true ).text( noorTMS.i18n.deleting );

        $.ajax( {
            url:    noorTMS.ajaxUrl,
            method: 'POST',
            data:   { action: 'noor_tms_delete_result', result_id: resultId, nonce: nonce },
        } )
        .done( function ( response ) {
            if ( response.success ) {
                $row.fadeOut( 300, function () { $( this ).remove(); } );
            } else {
                alert( response.data.message || noorTMS.i18n.error );
                $btn.prop( 'disabled', false ).text( 'Delete' );
            }
        } )
        .fail( function () {
            alert( noorTMS.i18n.error );
            $btn.prop( 'disabled', false ).text( 'Delete' );
        } );
    } );

    // -----------------------------------------------------------------------
    // 5.  Delete class card
    // -----------------------------------------------------------------------
    $( document ).on( 'click', '.noor-delete-class', function () {
        const $btn    = $( this );
        const name    = $btn.data( 'name' ) || '';
        const classId = $btn.data( 'id' );
        const nonce   = $btn.data( 'nonce' );
        const msg     = name
            ? noorTMS.i18n.confirmDelete + ' (' + name + ')'
            : noorTMS.i18n.confirmDelete;

        if ( ! confirm( msg ) ) return;

        $btn.prop( 'disabled', true ).text( noorTMS.i18n.deleting );

        $.ajax( {
            url:    noorTMS.ajaxUrl,
            method: 'POST',
            data:   { action: 'noor_tms_delete_class', class_id: classId, nonce: nonce },
        } )
        .done( function ( response ) {
            if ( response.success ) {
                $( '#noor-class-card-' + classId ).fadeOut( 300, function () { $( this ).remove(); } );
            } else {
                alert( response.data.message || noorTMS.i18n.error );
                $btn.prop( 'disabled', false ).text( 'Delete' );
            }
        } )
        .fail( function () {
            alert( noorTMS.i18n.error );
            $btn.prop( 'disabled', false ).text( 'Delete' );
        } );
    } );

    // -----------------------------------------------------------------------
    // 6.  Subject row management (class form)
    // -----------------------------------------------------------------------
    $( document ).on( 'click', '#noor-add-subject', function () {
        const $row = $(
            '<div class="noor-subject-row">' +
                '<input type="text" name="subjects[]" placeholder="' +
                    escHtml( noorTMS.i18n.subjectPlaceholder ) + '" />' +
                '<button type="button" class="noor-btn noor-btn--danger noor-btn--sm noor-remove-subject">' +
                    '&times;' +
                '</button>' +
            '</div>'
        );
        $( '#noor-subjects-list' ).append( $row );
        $row.find( 'input' ).trigger( 'focus' );
    } );

    $( document ).on( 'click', '.noor-remove-subject', function () {
        const $list = $( '#noor-subjects-list' );
        if ( $list.find( '.noor-subject-row' ).length > 1 ) {
            $( this ).closest( '.noor-subject-row' ).remove();
        } else {
            $( this ).closest( '.noor-subject-row' ).find( 'input' ).val( '' );
        }
    } );

    // -----------------------------------------------------------------------
    // 7.  Settings – toggle API field visibility based on provider
    // -----------------------------------------------------------------------
    $( document ).on( 'change', '#gateway_provider', toggleApiRows );
    $( document ).ready( function () { toggleApiRows(); } );

    function toggleApiRows() {
        const val    = $( '#gateway_provider' ).val();
        const isCtc  = val === 'click_to_chat';
        const isMock = val === 'mock';
        const hide   = isCtc || isMock;

        $( '.noor-api-row' ).toggle( ! hide );
        $( '#noor-ctc-hint' ).toggle( isCtc );
    }

    // -----------------------------------------------------------------------
    // Utility – tiny HTML-escape
    // -----------------------------------------------------------------------
    function escHtml( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;'  )
            .replace( />/g, '&gt;'  )
            .replace( /"/g, '&quot;')
            .replace( /'/g, '&#039;');
    }

    // -----------------------------------------------------------------------
    // 8.  Attendance – mark attendance (public portal)
    // -----------------------------------------------------------------------

    // Toggle class selector visibility when mode changes.
    $( document ).on( 'change', '#noor-att-mode', function () {
        $( '#noor-class-label' ).toggle( $( this ).val() !== 'global' );
    } );

    // Quick mark – set all selects to a given status.
    $( document ).on( 'click', '.noor-mark-all', function () {
        const status = $( this ).data( 'status' );
        $( '.noor-att-status' ).val( status ).trigger( 'change' );
    } );

    // Highlight row colour when status changes.
    $( document ).on( 'change', '.noor-att-status', function () {
        const $row = $( this ).closest( 'tr' );
        $row.removeClass( 'noor-att-present noor-att-absent noor-att-late noor-att-excused' );
        $row.addClass( 'noor-att-' + $( this ).val() );
    } );

    // Submit attendance form via AJAX.
    $( document ).on( 'submit', '#noor-pub-att-form', function ( e ) {
        e.preventDefault();

        const $form     = $( this );
        const $btn      = $( '#noor-save-pub-att-btn' );
        const $feedback = $( '#noor-pub-att-feedback' );

        $btn.prop( 'disabled', true ).text( noorTMS.i18n.saving );
        $feedback.text( '' ).removeClass( 'is-error is-success' );

        $.ajax( {
            url:    noorTMS.ajaxUrl,
            method: 'POST',
            data:   $form.serialize() + '&action=noor_tms_save_student_attendance&nonce=' + encodeURIComponent( noorTMS.nonce ),
        } )
        .done( function ( r ) {
            if ( r.success ) {
                $feedback.addClass( 'is-success' ).text( r.data.message );
            } else {
                $feedback.addClass( 'is-error' ).text( r.data.message || noorTMS.i18n.error );
            }
        } )
        .fail( function () {
            $feedback.addClass( 'is-error' ).text( noorTMS.i18n.error );
        } )
        .always( function () {
            $btn.prop( 'disabled', false );
        } );
    } );

    // -----------------------------------------------------------------------
    // 8b.  Attendance Correction Modal (public portal, managers only)
    // -----------------------------------------------------------------------

    const $pubCorrModal   = $( '#noor-correction-modal' );
    const $pubModalSubmit = $( '#noor-modal-submit' );

    if ( $pubCorrModal.length ) {
        // Open correction modal.
        $( document ).on( 'click', '.noor-open-correction', function () {
            const $btn = $( this );
            $( '#noor-modal-att-id' ).val( $btn.data( 'id' ) );
            $( '#noor-modal-student' ).text( $btn.data( 'student' ) );
            $( '#noor-modal-date-slot' ).text( $btn.data( 'date' ) + ' — ' + $btn.data( 'slot' ) );
            $( '#noor-modal-current-status' ).text( $btn.data( 'status' ) );
            $( '#noor-modal-new-status' ).val( $btn.data( 'status' ) );
            $( '#noor-modal-reason' ).val( '' );
            $( '#noor-modal-feedback' ).text( '' ).removeClass( 'is-error is-success' );
            $pubCorrModal.prop( 'hidden', false );
            $( '#noor-modal-new-status' ).trigger( 'focus' );
        } );

        // Close modal.
        $( document ).on( 'click', '.noor-modal-close', closePubModal );
        $( document ).on( 'click', '#noor-correction-modal', function ( e ) {
            if ( e.target === this ) { closePubModal(); }
        } );

        function closePubModal() {
            $pubCorrModal.prop( 'hidden', true );
        }

        // Submit correction.
        $( document ).on( 'click', '#noor-modal-submit', function () {
            const attId     = $( '#noor-modal-att-id' ).val();
            const newStatus = $( '#noor-modal-new-status' ).val();
            const reason    = $( '#noor-modal-reason' ).val().trim();
            const $feedback = $( '#noor-modal-feedback' );

            if ( ! reason ) {
                $feedback.addClass( 'is-error' ).text( 'A reason is required.' );
                $( '#noor-modal-reason' ).trigger( 'focus' );
                return;
            }

            $pubModalSubmit.prop( 'disabled', true ).text( noorTMS.i18n.saving );
            $feedback.text( '' ).removeClass( 'is-error is-success' );

            $.post( noorTMS.ajaxUrl, {
                action:     'noor_tms_correct_attendance',
                nonce:      noorTMS.nonce,
                att_id:     attId,
                new_status: newStatus,
                reason:     reason,
            } )
            .done( function ( r ) {
                if ( r.success ) {
                    $feedback.addClass( 'is-success' ).text( r.data.message );
                    const $row   = $( '#noor-att-row-' + attId );
                    const $badge = $row.find( '.noor-badge' );
                    const labels  = { present: 'Present', absent: 'Absent', late: 'Late', excused: 'Excused' };
                    const classes = {
                        present: 'noor-badge--active',
                        absent:  'noor-badge--inactive',
                        late:    'noor-badge--late',
                        excused: 'noor-badge--excused',
                    };
                    $badge.removeClass( 'noor-badge--active noor-badge--inactive noor-badge--late noor-badge--excused' )
                          .addClass( classes[ newStatus ] || '' )
                          .text( labels[ newStatus ] || newStatus );
                    $row.find( '.noor-open-correction' ).data( 'status', newStatus );
                    setTimeout( closePubModal, 1400 );
                } else {
                    $feedback.addClass( 'is-error' ).text( r.data.message || noorTMS.i18n.error );
                }
            } )
            .fail( function () {
                $feedback.addClass( 'is-error' ).text( noorTMS.i18n.error );
            } )
            .always( function () {
                $pubModalSubmit.prop( 'disabled', false ).text( 'Save Correction' );
            } );
        } );
    }

    // -----------------------------------------------------------------------
    // 8.  Public support popup + request submission
    // -----------------------------------------------------------------------

    const $supportModal = $( '#noor-support-modal' );

    function openSupportModal() {
        if ( ! $supportModal.length ) return;
        $( '#noor-support-source-url' ).val( window.location.href );
        $supportModal.addClass( 'is-open' ).attr( 'aria-hidden', 'false' );
        $( 'body' ).addClass( 'noor-modal-open' );
        $( '#noor-support-name' ).trigger( 'focus' );
    }

    function closeSupportModal() {
        if ( ! $supportModal.length ) return;
        $supportModal.removeClass( 'is-open' ).attr( 'aria-hidden', 'true' );
        $( 'body' ).removeClass( 'noor-modal-open' );
    }

    $( document ).on( 'click', '[data-noor-support-open]', function ( e ) {
        e.preventDefault();
        openSupportModal();
    } );

    $( document ).on( 'click', '[data-noor-support-close]', function () {
        closeSupportModal();
    } );

    $( document ).on( 'click', '#noor-support-modal', function ( e ) {
        if ( e.target === this ) {
            closeSupportModal();
        }
    } );

    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' ) {
            closeSupportModal();
        }
    } );

    $( document ).on( 'submit', '#noor-support-form', function ( e ) {
        e.preventDefault();

        const $form = $( this );
        const $btn = $( '#noor-support-submit-btn' );
        const $feedback = $( '#noor-support-feedback' );
        const originalLabel = $btn.data( 'original-label' ) || $btn.text();

        $btn.data( 'original-label', originalLabel );
        $btn.prop( 'disabled', true ).text( noorTMS.i18n.sending || noorTMS.i18n.saving );
        $feedback.text( '' ).removeClass( 'is-error is-success' );

        $.ajax( {
            url: noorTMS.ajaxUrl,
            method: 'POST',
            data: $form.serialize() + '&action=noor_tms_submit_support_request&nonce=' + encodeURIComponent( noorTMS.nonce ),
        } )
        .done( function ( response ) {
            if ( response.success ) {
                const msg = response.data && response.data.message
                    ? response.data.message
                    : noorTMS.i18n.supportSent;
                $feedback.addClass( 'is-success' ).text( msg );
                $form.get( 0 ).reset();
                $( '#noor-support-source-url' ).val( window.location.href );
            } else {
                const err = response.data && response.data.message
                    ? response.data.message
                    : noorTMS.i18n.error;
                $feedback.addClass( 'is-error' ).text( err );
            }
        } )
        .fail( function ( xhr ) {
            const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : noorTMS.i18n.error;
            $feedback.addClass( 'is-error' ).text( msg );
        } )
        .always( function () {
            $btn.prop( 'disabled', false ).text( originalLabel );
        } );
    } );

    // -----------------------------------------------------------------------
    // 9.  Floating chat widget
    // -----------------------------------------------------------------------

    const $chatWidget = $( '[data-noor-chat-widget]' );

    if ( $chatWidget.length ) {
        const chatConfig = noorTMS.chat || {};
        const storageKey = chatConfig.storageKey || 'noor_tms_chat_state_v1';
        const pollMs = Number( chatConfig.pollMs || 7000 );

        const $chatPanel = $( '#noor-chat-panel' );
        const $chatToggle = $( '[data-noor-chat-toggle]' );
        const $chatIdentity = $( '[data-noor-chat-identity]' );
        const $chatThread = $( '[data-noor-chat-thread]' );
        const $chatMessages = $( '#noor-chat-messages' );
        const $chatFeedback = $( '#noor-chat-feedback' );
        const $chatBootstrapForm = $( '#noor-chat-bootstrap-form' );
        const $chatSendForm = $( '#noor-chat-send-form' );
        const $chatBootstrapBtn = $( '#noor-chat-bootstrap-btn' );
        const $chatSendBtn = $( '#noor-chat-send-btn' );
        const $chatMessageInput = $( '#noor-chat-message' );

        const chatState = {
            threadId: 0,
            visitorToken: '',
            lastMessageId: 0,
            status: 'open',
            pollTimer: null,
            isFetching: false,
            isBootstrapped: false,
        };

        setChatSourceUrl();

        $( document ).on( 'click', '[data-noor-chat-open]', function ( e ) {
            e.preventDefault();
            openChatPanel();
            if ( ! chatState.isBootstrapped ) {
                bootstrapChatFromStorage();
            }
        } );

        $chatToggle.on( 'click', function () {
            if ( $chatPanel.prop( 'hidden' ) ) {
                openChatPanel();
                if ( ! chatState.isBootstrapped ) {
                    bootstrapChatFromStorage();
                }
            } else {
                closeChatPanel();
            }
        } );

        $( document ).on( 'click', '[data-noor-chat-close]', function () {
            closeChatPanel();
        } );

        $chatBootstrapForm.on( 'submit', function ( e ) {
            e.preventDefault();

            const name = String( $( '#noor-chat-name' ).val() || '' ).trim();
            const email = String( $( '#noor-chat-email' ).val() || '' ).trim();
            const phone = String( $( '#noor-chat-phone' ).val() || '' ).trim();

            if ( ! name || ( ! email && ! phone ) ) {
                setChatFeedback( noorTMS.i18n.chatNeedIdentity || noorTMS.i18n.error, true );
                return;
            }

            bootstrapChat( {
                chat_name: name,
                chat_email: email,
                chat_phone: phone,
                chat_source_url: window.location.href,
            } );
        } );

        $chatSendForm.on( 'submit', function ( e ) {
            e.preventDefault();

            const message = String( $chatMessageInput.val() || '' ).trim();
            if ( ! message ) {
                setChatFeedback( noorTMS.i18n.chatNeedMessage || noorTMS.i18n.error, true );
                return;
            }

            if ( ! chatState.threadId ) {
                setChatFeedback( noorTMS.i18n.chatTryAgain || noorTMS.i18n.error, true );
                return;
            }

            sendChatMessage( message );
        } );

        $( document ).on( 'keydown', function ( e ) {
            if ( e.key === 'Escape' && ! $chatPanel.prop( 'hidden' ) ) {
                closeChatPanel();
            }
        } );

        // Attempt restoring an existing chat thread in the background.
        bootstrapChatFromStorage();

        function openChatPanel() {
            $chatPanel.prop( 'hidden', false );
            $chatWidget.addClass( 'is-open' );
            $chatToggle.attr( 'aria-expanded', 'true' );
        }

        function closeChatPanel() {
            $chatPanel.prop( 'hidden', true );
            $chatWidget.removeClass( 'is-open' );
            $chatToggle.attr( 'aria-expanded', 'false' );
        }

        function setChatSourceUrl() {
            $( '#noor-chat-source-url' ).val( window.location.href );
        }

        function readChatStorage() {
            try {
                const raw = window.localStorage.getItem( storageKey );
                if ( ! raw ) return null;
                const parsed = JSON.parse( raw );
                if ( ! parsed || typeof parsed !== 'object' ) return null;
                return parsed;
            } catch ( err ) {
                return null;
            }
        }

        function writeChatStorage() {
            try {
                window.localStorage.setItem(
                    storageKey,
                    JSON.stringify( {
                        threadId: chatState.threadId,
                        visitorToken: chatState.visitorToken,
                        lastMessageId: chatState.lastMessageId,
                    } )
                );
            } catch ( err ) {
                // Ignore storage write issues.
            }
        }

        function clearChatStorage() {
            try {
                window.localStorage.removeItem( storageKey );
            } catch ( err ) {
                // Ignore storage errors.
            }
        }

        function bootstrapChatFromStorage() {
            const saved = readChatStorage();
            if ( ! saved ) {
                return;
            }

            if ( saved.threadId ) {
                chatState.threadId = Number( saved.threadId ) || 0;
            }
            if ( saved.visitorToken ) {
                chatState.visitorToken = String( saved.visitorToken );
            }

            if ( chatState.threadId && chatState.visitorToken ) {
                bootstrapChat( {
                    thread_id: chatState.threadId,
                    visitor_token: chatState.visitorToken,
                }, true );
            }
        }

        function bootstrapChat( extraData, silent ) {
            const payload = $.extend( {
                action: 'noor_tms_chat_bootstrap',
                nonce: noorTMS.nonce,
                thread_id: chatState.threadId,
                visitor_token: chatState.visitorToken,
            }, extraData || {} );

            if ( ! silent ) {
                $chatBootstrapBtn.prop( 'disabled', true ).text( noorTMS.i18n.sending || noorTMS.i18n.chatStart );
                setChatFeedback( '' );
            }

            $.ajax( {
                url: noorTMS.ajaxUrl,
                method: 'POST',
                data: payload,
            } )
            .done( function ( response ) {
                if ( ! response || ! response.success || ! response.data || ! response.data.thread ) {
                    if ( ! silent ) {
                        setChatFeedback( noorTMS.i18n.chatTryAgain || noorTMS.i18n.error, true );
                    } else {
                        // Silent resume failed — the saved session no longer exists in the DB.
                        // Reset state so the next identity-form submit starts a brand-new thread.
                        chatState.threadId      = 0;
                        chatState.visitorToken  = '';
                        chatState.isBootstrapped = false;
                        clearChatStorage();
                    }
                    return;
                }

                const thread = response.data.thread;
                chatState.threadId = Number( thread.id || 0 );
                chatState.visitorToken = String( thread.visitor_token || chatState.visitorToken || '' );
                chatState.status = String( thread.status || 'open' );
                chatState.isBootstrapped = chatState.threadId > 0;
                chatState.lastMessageId = 0;

                $chatMessages.empty();
                appendChatMessages( response.data.messages || [] );
                writeChatStorage();
                applyChatStatus();

                if ( chatState.isBootstrapped ) {
                    $chatIdentity.prop( 'hidden', true );
                    $chatThread.prop( 'hidden', false );
                    setChatFeedback( noorTMS.i18n.chatConnected || '' );
                    $chatMessageInput.trigger( 'focus' );
                    startChatPolling();
                }
            } )
            .fail( function () {
                if ( ! silent ) {
                    setChatFeedback( noorTMS.i18n.chatTryAgain || noorTMS.i18n.error, true );
                } else {
                    chatState.threadId      = 0;
                    chatState.visitorToken  = '';
                    chatState.isBootstrapped = false;
                    clearChatStorage();
                }
            } )
            .always( function () {
                if ( ! silent ) {
                    $chatBootstrapBtn.prop( 'disabled', false ).text( noorTMS.i18n.chatStart || 'Start Chat' );
                }
            } );
        }

        function sendChatMessage( message ) {
            $chatSendBtn.prop( 'disabled', true ).text( noorTMS.i18n.chatSending || noorTMS.i18n.sending );
            setChatFeedback( '' );

            $.ajax( {
                url: noorTMS.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'noor_tms_chat_send',
                    nonce: noorTMS.nonce,
                    thread_id: chatState.threadId,
                    visitor_token: chatState.visitorToken,
                    message: message,
                },
            } )
            .done( function ( response ) {
                if ( ! response || ! response.success || ! response.data ) {
                    setChatFeedback( noorTMS.i18n.chatTryAgain || noorTMS.i18n.error, true );
                    return;
                }

                if ( response.data.message ) {
                    appendChatMessages( [ response.data.message ] );
                }

                $chatMessageInput.val( '' );
                fetchChatMessages();
            } )
            .fail( function ( xhr ) {
                const serverMsg = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                    ? xhr.responseJSON.data.message
                    : '';
                setChatFeedback( serverMsg || noorTMS.i18n.chatTryAgain || noorTMS.i18n.error, true );
            } )
            .always( function () {
                $chatSendBtn.prop( 'disabled', false ).text( noorTMS.i18n.send || 'Send' );
            } );
        }

        function startChatPolling() {
            if ( chatState.pollTimer ) {
                window.clearInterval( chatState.pollTimer );
            }

            chatState.pollTimer = window.setInterval( function () {
                fetchChatMessages();
            }, Math.max( 3000, pollMs ) );
        }

        function fetchChatMessages() {
            if ( ! chatState.threadId || chatState.isFetching ) {
                return;
            }

            chatState.isFetching = true;

            $.ajax( {
                url: noorTMS.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'noor_tms_chat_fetch',
                    nonce: noorTMS.nonce,
                    thread_id: chatState.threadId,
                    visitor_token: chatState.visitorToken,
                    after_id: chatState.lastMessageId,
                },
            } )
            .done( function ( response ) {
                if ( ! response || ! response.success || ! response.data ) {
                    return;
                }

                if ( response.data.status ) {
                    chatState.status = String( response.data.status );
                    applyChatStatus();
                }

                appendChatMessages( response.data.messages || [] );
            } )
            .fail( function ( xhr ) {
                if ( xhr && xhr.status === 404 ) {
                    // Session gone from server — wipe everything so the widget
                    // resets to the identity form instead of polling forever.
                    clearChatStorage();
                    chatState.threadId       = 0;
                    chatState.visitorToken   = '';
                    chatState.isBootstrapped = false;
                    if ( chatState.pollTimer ) {
                        window.clearInterval( chatState.pollTimer );
                        chatState.pollTimer = null;
                    }
                }
            } )
            .always( function () {
                chatState.isFetching = false;
            } );
        }

        function appendChatMessages( messages ) {
            if ( ! Array.isArray( messages ) || ! messages.length ) {
                return;
            }

            let shouldScroll = false;

            messages.forEach( function ( msg ) {
                const id = Number( msg.id || 0 );
                if ( ! id ) {
                    return;
                }

                if ( $chatMessages.find( '[data-msg-id="' + id + '"]' ).length ) {
                    if ( id > chatState.lastMessageId ) {
                        chatState.lastMessageId = id;
                    }
                    return;
                }

                const role = String( msg.sender_role || 'visitor' );
                const text = String( msg.message_text || '' );
                const createdAt = String( msg.created_at || '' );

                const cssRole = role === 'agent'
                    ? 'is-agent'
                    : ( role === 'system' ? 'is-system' : 'is-visitor' );

                const roleLabel = role === 'agent'
                    ? 'Agent'
                    : ( role === 'system' ? 'System' : 'You' );

                const safeText = escHtml( text ).replace( /\n/g, '<br>' );

                const $bubble = $(
                    '<div class="noor-chat-bubble ' + cssRole + '" data-msg-id="' + id + '">' +
                        '<div class="noor-chat-bubble__meta">' + escHtml( roleLabel ) + formatChatTime( createdAt ) + '</div>' +
                        '<div class="noor-chat-bubble__text">' + safeText + '</div>' +
                    '</div>'
                );

                $chatMessages.append( $bubble );
                if ( id > chatState.lastMessageId ) {
                    chatState.lastMessageId = id;
                }
                shouldScroll = true;
            } );

            if ( shouldScroll ) {
                writeChatStorage();
                scrollChatToBottom();
            }
        }

        function applyChatStatus() {
            const isClosed = chatState.status === 'closed' || chatState.status === 'resolved';
            $chatSendBtn.prop( 'disabled', isClosed );
            $chatMessageInput.prop( 'disabled', isClosed );

            if ( isClosed ) {
                setChatFeedback( 'This chat has been marked as resolved. Start a new chat if needed.' );
            }
        }

        function scrollChatToBottom() {
            const node = $chatMessages.get( 0 );
            if ( ! node ) {
                return;
            }
            node.scrollTop = node.scrollHeight;
        }

        function formatChatTime( createdAt ) {
            if ( ! createdAt ) {
                return '';
            }

            const d = new Date( createdAt.replace( ' ', 'T' ) );
            if ( Number.isNaN( d.getTime() ) ) {
                return '';
            }

            const hh = String( d.getHours() ).padStart( 2, '0' );
            const mm = String( d.getMinutes() ).padStart( 2, '0' );
            return ' • ' + hh + ':' + mm;
        }

        function setChatFeedback( message, isError ) {
            const text = String( message || '' );
            $chatFeedback.text( text );
            $chatFeedback.removeClass( 'is-error is-success' );

            if ( ! text ) {
                return;
            }

            $chatFeedback.addClass( isError ? 'is-error' : 'is-success' );
        }
    }

} )( jQuery );
