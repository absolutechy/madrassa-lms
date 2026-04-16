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
    // 2.  Delete student row
    // -----------------------------------------------------------------------
    $( document ).on( 'click', '.noor-delete-student', function () {
        if ( ! confirm( noorTMS.i18n.confirmDelete ) ) return;

        const $btn      = $( this );
        const studentId = $btn.data( 'id' );
        const nonce     = $btn.data( 'nonce' );
        const $row      = $btn.closest( 'tr' );

        $btn.prop( 'disabled', true ).text( noorTMS.i18n.deleting );

        $.ajax( {
            url:    noorTMS.ajaxUrl,
            method: 'POST',
            data:   { action: 'noor_tms_delete_student', student_id: studentId, nonce: nonce },
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
    // 3.  Delete result row
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
    // 4.  Delete class card
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
    // 5.  Subject row management (class form)
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
    // 6.  Settings – toggle API field visibility based on provider
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
    // 7.  Attendance – mark attendance (public portal)
    // -----------------------------------------------------------------------

    // Quick mark – set all selects to a given status.
    $( document ).on( 'click', '.noor-mark-all', function () {
        const status = $( this ).data( 'status' );
        $( '.noor-att-status' ).val( status );
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

} )( jQuery );
