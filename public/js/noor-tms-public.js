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

    // -----------------------------------------------------------------------
    // Fee Management (frontend portal — same AJAX endpoints as admin)
    // -----------------------------------------------------------------------

    // Shared HTML-escape utility (may already be defined in admin JS,
    // but this file is loaded independently on the frontend).
    function feeEscHtml( str ) {
        return String( str )
            .replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' ).replace( /"/g, '&quot;' )
            .replace( /'/g, '&#039;' );
    }

    // --- Generate invoices ---
    $( document ).on( 'click', '#noor-fee-generate-btn', function () {
        const $btn      = $( this );
        const $feedback = $( '#noor-fee-generate-feedback' );
        const month     = $btn.data( 'month' );
        const year      = $btn.data( 'year' );

        $btn.prop( 'disabled', true );
        $feedback.show().text( 'Generating…' ).removeClass( 'noor-notice--success noor-notice--error' );

        $.post( noorTMS.ajaxUrl, {
            action:        'noor_tms_fee_generate_invoices',
            month:         month,
            academic_year: year,
            nonce:         noorTMS.nonce,
        } )
        .done( function ( r ) {
            if ( r.success ) {
                $feedback.addClass( 'noor-notice--success' ).text( r.data.message );
                setTimeout( function () { window.location.reload(); }, 1400 );
            } else {
                $feedback.addClass( 'noor-notice--error' ).text( r.data.message || noorTMS.i18n.error );
                $btn.prop( 'disabled', false );
            }
        } )
        .fail( function () {
            $feedback.addClass( 'noor-notice--error' ).text( noorTMS.i18n.error );
            $btn.prop( 'disabled', false );
        } );
    } );

    // --- Void invoice ---
    $( document ).on( 'click', '.noor-fee-void-invoice', function () {
        if ( ! confirm( ( noorTMS.i18n.confirmVoid ) || 'Void this invoice? This cannot be undone.' ) ) { return; }

        const $btn = $( this );
        const id   = $btn.data( 'id' );
        $btn.prop( 'disabled', true ).text( '…' );

        $.post( noorTMS.ajaxUrl, {
            action:     'noor_tms_fee_void_invoice',
            invoice_id: id,
            nonce:      noorTMS.nonce,
        } )
        .done( function ( r ) {
            if ( r.success ) {
                const $row = $( '#noor-fee-inv-row-' + id );
                $row.find( '.noor-badge' )
                    .removeClass( 'noor-fee-status-unpaid noor-fee-status-partial noor-fee-status-paid' )
                    .addClass( 'noor-fee-status-voided' )
                    .text( 'Voided' );
                $btn.remove();
            } else {
                alert( r.data.message || noorTMS.i18n.error );
                $btn.prop( 'disabled', false ).text( 'Void' );
            }
        } )
        .fail( function () {
            alert( noorTMS.i18n.error );
            $btn.prop( 'disabled', false ).text( 'Void' );
        } );
    } );

    // --- Payment: search students ---
    $( document ).on( 'click', '#noor-fee-search-btn', feeSearchStudents );
    $( document ).on( 'keydown', '#noor-fee-student-search', function ( e ) {
        if ( e.key === 'Enter' ) { e.preventDefault(); feeSearchStudents(); }
    } );

    function feeSearchStudents() {
        const q        = $.trim( $( '#noor-fee-student-search' ).val() );
        const $spinner = $( '#noor-fee-search-spinner' );
        const $results = $( '#noor-fee-student-results' );

        if ( q.length < 2 ) {
            $results.html( '<p style="color:#64748b;font-size:.9rem;">Please enter at least 2 characters.</p>' );
            return;
        }

        $spinner.text( 'Searching…' );
        $results.empty();

        $.post( noorTMS.ajaxUrl, { action: 'noor_tms_fee_search_students', q: q, nonce: noorTMS.nonce } )
        .done( function ( r ) {
            $spinner.text( '' );
            if ( ! r.success || ! r.data.students.length ) {
                $results.html( '<p style="color:#64748b;font-size:.9rem;">No active students found.</p>' );
                return;
            }
            let html = '<ul class="noor-fee-student-list">';
            r.data.students.forEach( function ( s ) {
                html += '<li>' +
                    '<span><strong>' + feeEscHtml( s.name ) + '</strong>' +
                    ( s.class_name ? ' <span style="color:#64748b;font-size:.85rem;">(' + feeEscHtml( s.class_name ) + ')</span>' : '' ) +
                    '</span>' +
                    '<button type="button" class="noor-btn noor-btn--secondary noor-btn--sm noor-fee-select-student"' +
                    ' data-id="' + s.id + '" data-name="' + feeEscHtml( s.name ) + '">Select</button>' +
                    '</li>';
            } );
            html += '</ul>';
            $results.html( html );
        } )
        .fail( function () {
            $spinner.text( noorTMS.i18n.error );
        } );
    }

    // --- Payment: select student → load invoices ---
    $( document ).on( 'click', '.noor-fee-select-student', function () {
        const studentId   = $( this ).data( 'id' );
        const studentName = $( this ).data( 'name' );

        $( '#noor-fee-invoice-title' ).text( 'Invoices — ' + feeEscHtml( studentName ) );
        $( '#noor-fee-invoice-section' ).show();
        $( '#noor-fee-payment-section' ).hide();
        $( '#noor-fee-invoice-list' ).html( '<p>Loading…</p>' );

        $.post( noorTMS.ajaxUrl, { action: 'noor_tms_fee_get_invoices', student_id: studentId, nonce: noorTMS.nonce } )
        .done( function ( r ) {
            if ( ! r.success || ! r.data.invoices.length ) {
                $( '#noor-fee-invoice-list' ).html( '<p style="color:#64748b;font-size:.9rem;">No outstanding invoices.</p>' );
                return;
            }
            let html = '<table class="noor-table" style="margin-bottom:8px;"><thead><tr>' +
                '<th></th><th>Month</th><th>Fee</th>' +
                '<th class="noor-col-num">Net Due</th><th class="noor-col-num">Paid</th>' +
                '<th class="noor-col-num">Balance</th><th>Status</th>' +
                '</tr></thead><tbody>';
            r.data.invoices.forEach( function ( inv ) {
                html += '<tr class="noor-fee-inv-select-row" data-id="' + inv.id + '">' +
                    '<td><input type="radio" name="noor_fee_inv_pick" value="' + inv.id +
                    '" data-balance="' + inv.balance + '"></td>' +
                    '<td>' + feeEscHtml( inv.invoice_month ) + '</td>' +
                    '<td>' + feeEscHtml( inv.fee_title ) + '</td>' +
                    '<td class="noor-col-num">' + inv.net_due.toFixed( 2 ) + '</td>' +
                    '<td class="noor-col-num">' + inv.total_paid.toFixed( 2 ) + '</td>' +
                    '<td class="noor-col-num"><strong>' + inv.balance.toFixed( 2 ) + '</strong></td>' +
                    '<td><span class="noor-badge noor-fee-status-' + inv.status + '">' + feeEscHtml( inv.status ) + '</span></td>' +
                    '</tr>';
            } );
            html += '</tbody></table>';
            $( '#noor-fee-invoice-list' ).html( html );
        } )
        .fail( function () {
            $( '#noor-fee-invoice-list' ).html( '<p style="color:#b91c1c;">' + noorTMS.i18n.error + '</p>' );
        } );
    } );

    // Select invoice → show payment form.
    $( document ).on( 'change', 'input[name="noor_fee_inv_pick"]', function () {
        const invoiceId = $( this ).val();
        const balance   = parseFloat( $( this ).data( 'balance' ) ) || 0;

        $( '.noor-fee-inv-select-row' ).css( 'background', '' );
        $( this ).closest( 'tr' ).css( 'background', '#eff6ff' );

        $( '#noor-fee-invoice-id' ).val( invoiceId );
        $( '#noor-fee-amount' ).val( balance.toFixed( 2 ) ).attr( 'max', balance );
        $( '#noor-fee-balance-note' ).text( 'Outstanding balance: ' + balance.toFixed( 2 ) );
        $( '#noor-fee-payment-section' ).show();
        $( '#noor-fee-pay-feedback' ).text( '' ).removeClass( 'noor-notice--success noor-notice--error' );
    } );

    // Submit payment.
    $( document ).on( 'submit', '#noor-fee-payment-form', function ( e ) {
        e.preventDefault();

        const $form     = $( this );
        const $btn      = $( '#noor-fee-pay-btn' );
        const $feedback = $( '#noor-fee-pay-feedback' );

        $btn.prop( 'disabled', true ).text( 'Saving…' );
        $feedback.text( '' ).removeClass( 'noor-notice--success noor-notice--error' );

        $.post( noorTMS.ajaxUrl,
            $form.serialize() + '&action=noor_tms_fee_save_payment&nonce=' + encodeURIComponent( noorTMS.nonce )
        )
        .done( function ( r ) {
            if ( r.success ) {
                $feedback.addClass( 'noor-notice--success' ).text( r.data.message );
                $form[0].reset();
                $( '#noor-fee-payment-section' ).hide();
                $( '#noor-fee-invoice-section' ).hide();
                $( '#noor-fee-student-results' ).empty();
                $( '#noor-fee-student-search' ).val( '' );
            } else {
                $feedback.addClass( 'noor-notice--error' ).text( r.data.message || noorTMS.i18n.error );
            }
        } )
        .fail( function () {
            $feedback.addClass( 'noor-notice--error' ).text( noorTMS.i18n.error );
        } )
        .always( function () {
            $btn.prop( 'disabled', false ).text( 'Record Payment' );
        } );
    } );

} )( jQuery );
