/**
 * Noor-TMS Admin JavaScript
 *
 * Handles:
 *  1. AJAX full-report save (all subjects in one form submit + WhatsApp report link)
 *  2. AJAX student row deletion
 *  3. AJAX result row deletion
 *  4. AJAX class deletion
 *  5. Subject row management on the class create/edit form
 *  6. Dynamic row injection after successful result save
 */

/* global noorTMS, jQuery */
( function ( $ ) {
    'use strict';

    // -----------------------------------------------------------------------
    // 1.  Save all-subject report via AJAX
    // -----------------------------------------------------------------------
    $( '#noor-tms-result-form' ).on( 'submit', function ( e ) {
        e.preventDefault();

        const $form     = $( this );
        const $btn      = $( '#noor-save-result-btn' );
        const $feedback = $( '#noor-result-feedback' );
        const $waBtn    = $( '#noor-wa-report-btn' );

        $btn.prop( 'disabled', true ).text( noorTMS.i18n.saving );
        $feedback.text( '' ).removeClass( 'is-error is-success' );
        $waBtn.hide();

        // Serialize the whole form plus the AJAX action name.
        // jQuery correctly serializes nested names like subjects[0][obtained].
        $.ajax( {
            url:    noorTMS.ajaxUrl,
            method: 'POST',
            data:   $form.serialize() + '&action=noor_tms_save_report',
        } )
        .done( function ( response ) {
            if ( response.success ) {
                $feedback.addClass( 'is-success' ).text( response.data.message );

                // Show the click-to-chat WhatsApp button when a URL was returned.
                if ( response.data.wa_url ) {
                    $waBtn.attr( 'href', response.data.wa_url ).show();
                }

                // Reload after a short delay so the summary card reflects the new data.
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
            $btn.prop( 'disabled', false ).text( noorTMS.i18n.saveReport || 'Save All Results' );
        } );
    } );

    // -----------------------------------------------------------------------
    // 2.  Delete student row via AJAX
    // -----------------------------------------------------------------------
    $( document ).on( 'click', '.noor-delete-student', function () {
        if ( ! confirm( noorTMS.i18n.confirmDelete ) ) {
            return;
        }

        const $btn      = $( this );
        const studentId = $btn.data( 'id' );
        const $row      = $btn.closest( 'tr' );

        $btn.prop( 'disabled', true ).text( noorTMS.i18n.deleting );

        $.ajax( {
            url:    noorTMS.ajaxUrl,
            method: 'POST',
            data:   { action: 'noor_tms_delete_student', student_id: studentId, nonce: noorTMS.nonce },
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
    // 3.  Delete result row via AJAX
    // -----------------------------------------------------------------------
    $( document ).on( 'click', '.noor-delete-result', function () {
        if ( ! confirm( noorTMS.i18n.confirmDelete ) ) {
            return;
        }

        const $btn     = $( this );
        const resultId = $btn.data( 'id' );
        const $row     = $btn.closest( 'tr' );

        $btn.prop( 'disabled', true ).text( noorTMS.i18n.deleting );

        $.ajax( {
            url:    noorTMS.ajaxUrl,
            method: 'POST',
            data:   { action: 'noor_tms_delete_result', result_id: resultId, nonce: noorTMS.nonce },
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
    // 4.  Delete class card via AJAX
    // -----------------------------------------------------------------------
    $( document ).on( 'click', '.noor-delete-class', function () {
        const $btn    = $( this );
        const name    = $btn.data( 'name' ) || '';
        const classId = $btn.data( 'id' );

        if ( ! confirm( name
            ? noorTMS.i18n.confirmDelete + ' (' + name + ')'
            : noorTMS.i18n.confirmDelete ) ) {
            return;
        }

        $btn.prop( 'disabled', true ).text( noorTMS.i18n.deleting );

        $.ajax( {
            url:    noorTMS.ajaxUrl,
            method: 'POST',
            data:   { action: 'noor_tms_delete_class', class_id: classId, nonce: noorTMS.nonce },
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
    // 5.  Subject row management (class create / edit form)
    // -----------------------------------------------------------------------
    $( '#noor-add-subject' ).on( 'click', function () {
        const $row = $(
            '<div class="noor-subject-row">' +
                '<input type="text" name="subjects[]" class="regular-text noor-subject-input"' +
                ' placeholder="' + escHtml( noorTMS.i18n.subjectPlaceholder || 'Subject name' ) + '" />' +
                '<button type="button" class="button button-small noor-remove-subject" title="Remove">&times;</button>' +
            '</div>'
        );
        $( '#noor-subjects-list' ).append( $row );
        $row.find( '.noor-subject-input' ).trigger( 'focus' );
    } );

    $( document ).on( 'click', '.noor-remove-subject', function () {
        const $list = $( '#noor-subjects-list' );
        // Always keep at least one row so the admin isn't confused.
        if ( $list.find( '.noor-subject-row' ).length > 1 ) {
            $( this ).closest( '.noor-subject-row' ).remove();
        } else {
            $( this ).closest( '.noor-subject-row' ).find( '.noor-subject-input' ).val( '' );
        }
    } );

    // -----------------------------------------------------------------------
    // 6.  Helper – prepend a new result row to the results table
    // -----------------------------------------------------------------------
    function prependResultRow( r, waUrl, is_ctc ) {
        const $tbody    = $( '#noor-results-tbody' );
        const colCount  = $tbody.closest( 'table' ).find( 'thead th' ).length;

        // Remove "no results" placeholder if present.
        $tbody.find( 'td[colspan]' ).closest( 'tr' ).remove();

        const pctClass = r.pct >= 50 ? 'pass' : 'fail';

        const waCell = ( is_ctc && waUrl )
            ? '<td><a href="' + escHtml( waUrl ) + '" target="_blank" rel="noopener"' +
              ' class="button button-small noor-wa-btn">&#128172; Send</a></td>'
            : ( is_ctc ? '<td><span class="description">&mdash;</span></td>' : '' );

        const $newRow = $( '<tr>' )
            .attr( 'id', 'noor-result-row-' + r.id )
            .html(
                '<td>' + escHtml( r.student_name )  + '</td>' +
                '<td>' + escHtml( r.subject )        + '</td>' +
                '<td>' + escHtml( r.marks_obtained + ' / ' + r.total_marks ) + '</td>' +
                '<td><span class="noor-pct noor-pct-' + pctClass + '">' + escHtml( String( r.pct ) ) + '%</span></td>' +
                '<td>' + escHtml( r.exam_date )      + '</td>' +
                waCell +
                '<td>' +
                    '<button type="button" class="button button-small button-link-delete noor-delete-result" ' +
                    'data-id="' + r.id + '" data-nonce="">' +
                    'Delete</button>' +
                '</td>'
            );

        $tbody.prepend( $newRow );
    }

    // -----------------------------------------------------------------------
    // 7.  Tiny HTML-escape utility
    // -----------------------------------------------------------------------
    function escHtml( str ) {
        return String( str )
            .replace( /&/g,  '&amp;'  )
            .replace( /</g,  '&lt;'   )
            .replace( />/g,  '&gt;'   )
            .replace( /"/g,  '&quot;' )
            .replace( /'/g,  '&#039;' );
    }

    // -----------------------------------------------------------------------
    // 8.  Teacher CRUD
    // -----------------------------------------------------------------------

    // Delete teacher row.
    $( document ).on( 'click', '.noor-delete-teacher', function () {
        if ( ! window.confirm( noorTMS.i18n.confirmDelete ) ) { return; }

        const $btn      = $( this );
        const teacherId = $btn.data( 'id' );

        $btn.prop( 'disabled', true ).text( noorTMS.i18n.deleting );

        $.post( noorTMS.ajaxUrl, {
            action:     'noor_tms_delete_teacher',
            teacher_id: teacherId,
            nonce:      noorTMS.nonce,
        } )
        .done( function ( response ) {
            if ( response.success ) {
                $( '#noor-teacher-row-' + teacherId ).fadeOut( 300, function () { $( this ).remove(); } );
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

    // Subject assignment: update subject dropdown when class changes.
    $( document ).on( 'change', '.noor-assign-class', function () {
        const classId  = parseInt( $( this ).val(), 10 );
        const $row     = $( this ).closest( '.noor-assignment-row' );
        const $subjSel = $row.find( '.noor-assign-subject' );
        const subjectsJson = $( '#noor-subjects-json' );

        if ( ! subjectsJson.length ) { return; }

        let subjects = {};
        try { subjects = JSON.parse( subjectsJson.text() ); } catch ( e ) { return; }

        $subjSel.empty().append( '<option value="0">— Subject —</option>' );
        if ( classId && subjects[ classId ] ) {
            subjects[ classId ].forEach( function ( sub ) {
                $subjSel.append( '<option value="' + escHtml( sub.id ) + '">' + escHtml( sub.subject_name ) + '</option>' );
            } );
        }
    } );

    // Add another subject assignment row.
    $( '#noor-add-assignment' ).on( 'click', function () {
        const $list = $( '#noor-subject-assignments' );
        const idx   = $list.find( '.noor-assignment-row' ).length;
        const $tpl  = $list.find( '.noor-assignment-row' ).first().clone();

        // Reset selects and update name indices.
        $tpl.find( 'select, input' ).each( function () {
            const name = $( this ).attr( 'name' );
            if ( name ) {
                $( this ).attr( 'name', name.replace( /\[\d+\]/, '[' + idx + ']' ) ).val( '' );
            }
        } );
        $tpl.find( '.noor-assign-subject' ).empty().append( '<option value="0">— Subject —</option>' );

        $list.append( $tpl );
    } );

    // Remove an assignment row.
    $( document ).on( 'click', '.noor-remove-assignment', function () {
        const $list = $( '#noor-subject-assignments' );
        if ( $list.find( '.noor-assignment-row' ).length > 1 ) {
            $( this ).closest( '.noor-assignment-row' ).remove();
        }
    } );

    // -----------------------------------------------------------------------
    // 9.  Student & Teacher Attendance (admin pages)
    // -----------------------------------------------------------------------

    // Quick mark – set all status selects on the page to a given status.
    $( document ).on( 'click', '.noor-mark-all', function () {
        const status = $( this ).data( 'status' );
        $( '.noor-att-status' ).val( status );
    } );

    // Save student attendance.
    $( '#noor-student-att-form' ).on( 'submit', function ( e ) {
        e.preventDefault();

        const $form     = $( this );
        const $btn      = $( '#noor-save-student-att-btn' );
        const $feedback = $( '#noor-student-att-feedback' );

        $btn.prop( 'disabled', true ).text( noorTMS.i18n.saving );
        $feedback.text( '' ).removeClass( 'is-error is-success' );

        $.post( noorTMS.ajaxUrl, $form.serialize() + '&action=noor_tms_save_student_attendance' )
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
            $btn.prop( 'disabled', false ).text( 'Save Attendance' );
        } );
    } );

    // Save teacher attendance.
    $( '#noor-teacher-att-form' ).on( 'submit', function ( e ) {
        e.preventDefault();

        const $form     = $( this );
        const $btn      = $( '#noor-save-teacher-att-btn' );
        const $feedback = $( '#noor-teacher-att-feedback' );

        $btn.prop( 'disabled', true ).text( noorTMS.i18n.saving );
        $feedback.text( '' ).removeClass( 'is-error is-success' );

        $.post( noorTMS.ajaxUrl, $form.serialize() + '&action=noor_tms_save_teacher_attendance' )
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
            $btn.prop( 'disabled', false ).text( 'Save Attendance' );
        } );
    } );

    // -----------------------------------------------------------------------
    // 10. Fee Management
    // -----------------------------------------------------------------------

    // --- 10a. Delete fee structure ---
    $( document ).on( 'click', '.noor-fee-delete-structure', function () {
        if ( ! confirm( noorTMS.i18n.confirmDelete ) ) { return; }

        const $btn = $( this );
        const id   = $btn.data( 'id' );
        $btn.prop( 'disabled', true ).text( noorTMS.i18n.deleting );

        $.post( noorTMS.ajaxUrl, {
            action:       'noor_tms_fee_delete_structure',
            structure_id: id,
            nonce:        noorTMS.nonce,
        } )
        .done( function ( r ) {
            if ( r.success ) {
                $( '#noor-fee-struct-row-' + id ).fadeOut( 300, function () { $( this ).remove(); } );
            } else {
                alert( r.data.message || noorTMS.i18n.error );
                $btn.prop( 'disabled', false ).text( 'Delete' );
            }
        } )
        .fail( function () {
            alert( noorTMS.i18n.error );
            $btn.prop( 'disabled', false ).text( 'Delete' );
        } );
    } );

    // --- 10b. Void invoice ---
    $( document ).on( 'click', '.noor-fee-void-invoice', function () {
        if ( ! confirm( noorTMS.i18n.confirmVoid || 'Void this invoice? This cannot be undone.' ) ) { return; }

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
                $row.find( '.noor-status-badge' )
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

    // --- 10c. Generate invoices ---
    $( '#noor-fee-generate-btn' ).on( 'click', function () {
        const $btn      = $( this );
        const $feedback = $( '#noor-fee-generate-feedback' );
        const month     = $btn.data( 'month' );
        const year      = $btn.data( 'year' );

        $btn.prop( 'disabled', true );
        $feedback.text( noorTMS.i18n.saving || 'Generating…' ).removeClass( 'is-error is-success' );

        $.post( noorTMS.ajaxUrl, {
            action:        'noor_tms_fee_generate_invoices',
            month:         month,
            academic_year: year,
            nonce:         noorTMS.nonce,
        } )
        .done( function ( r ) {
            if ( r.success ) {
                $feedback.addClass( 'is-success' ).text( r.data.message );
                setTimeout( function () { window.location.reload(); }, 1500 );
            } else {
                $feedback.addClass( 'is-error' ).text( r.data.message || noorTMS.i18n.error );
                $btn.prop( 'disabled', false );
            }
        } )
        .fail( function () {
            $feedback.addClass( 'is-error' ).text( noorTMS.i18n.error );
            $btn.prop( 'disabled', false );
        } );
    } );

    // --- 10d. Payment entry – student search ---
    $( '#noor-fee-search-btn' ).on( 'click', feeSearchStudents );
    $( '#noor-fee-student-search' ).on( 'keydown', function ( e ) {
        if ( e.key === 'Enter' ) { e.preventDefault(); feeSearchStudents(); }
    } );

    function feeSearchStudents() {
        const q         = $.trim( $( '#noor-fee-student-search' ).val() );
        const $spinner  = $( '#noor-fee-search-spinner' );
        const $results  = $( '#noor-fee-student-results' );

        if ( q.length < 2 ) {
            $results.html( '<p class="description">Please enter at least 2 characters.</p>' );
            return;
        }

        $spinner.text( 'Searching…' ).removeClass( 'is-error is-success' );
        $results.empty();

        $.post( noorTMS.ajaxUrl, {
            action: 'noor_tms_fee_search_students',
            q:      q,
            nonce:  noorTMS.nonce,
        } )
        .done( function ( r ) {
            $spinner.text( '' );
            if ( ! r.success ) {
                $results.html( '<p class="description">' + escHtml( r.data.message ) + '</p>' );
                return;
            }
            const students = r.data.students;
            if ( ! students.length ) {
                $results.html( '<p class="description">No active students found.</p>' );
                return;
            }
            let html = '<ul class="noor-fee-student-list">';
            students.forEach( function ( s ) {
                html += '<li>' +
                    '<span><span class="noor-fee-student-name">' + escHtml( s.name ) + '</span>' +
                    ( s.class_name ? ' <span class="noor-fee-student-meta">(' + escHtml( s.class_name ) + ')</span>' : '' ) +
                    '</span>' +
                    '<button type="button" class="button button-small noor-fee-select-student"' +
                    ' data-id="' + s.id + '" data-name="' + escHtml( s.name ) + '">Select</button>' +
                    '</li>';
            } );
            html += '</ul>';
            $results.html( html );
        } )
        .fail( function () {
            $spinner.addClass( 'is-error' ).text( noorTMS.i18n.error );
        } );
    }

    // --- 10e. Payment entry – select student → load invoices ---
    $( document ).on( 'click', '.noor-fee-select-student', function () {
        const studentId   = $( this ).data( 'id' );
        const studentName = $( this ).data( 'name' );

        $( '#noor-fee-invoice-title' ).text( 'Outstanding Invoices — ' + escHtml( studentName ) );
        $( '#noor-fee-invoice-section' ).show();
        $( '#noor-fee-payment-section' ).hide();
        $( '#noor-fee-invoice-list' ).html( '<p>Loading…</p>' );

        $.post( noorTMS.ajaxUrl, {
            action:     'noor_tms_fee_get_invoices',
            student_id: studentId,
            nonce:      noorTMS.nonce,
        } )
        .done( function ( r ) {
            if ( ! r.success ) {
                $( '#noor-fee-invoice-list' ).html( '<p class="description">' + escHtml( r.data.message ) + '</p>' );
                return;
            }
            const invoices = r.data.invoices;
            if ( ! invoices.length ) {
                $( '#noor-fee-invoice-list' ).html( '<p class="description">No outstanding invoices for this student.</p>' );
                return;
            }

            let html = '<table class="noor-fee-invoice-table">' +
                '<thead><tr>' +
                '<th></th><th>Month</th><th>Fee</th>' +
                '<th style="text-align:right;">Net Due</th>' +
                '<th style="text-align:right;">Paid</th>' +
                '<th style="text-align:right;">Balance</th>' +
                '<th>Status</th>' +
                '</tr></thead><tbody>';

            invoices.forEach( function ( inv ) {
                const statusClass = 'noor-fee-status-' + inv.status;
                html += '<tr class="noor-fee-inv-select-row" data-id="' + inv.id + '"' +
                    ' data-balance="' + inv.balance + '">' +
                    '<td><input type="radio" name="noor_fee_inv_pick" value="' + inv.id +
                    '" data-balance="' + inv.balance + '"></td>' +
                    '<td>' + escHtml( inv.invoice_month ) + '</td>' +
                    '<td>' + escHtml( inv.fee_title ) + '</td>' +
                    '<td style="text-align:right;">' + inv.net_due.toFixed( 2 ) + '</td>' +
                    '<td style="text-align:right;">' + inv.total_paid.toFixed( 2 ) + '</td>' +
                    '<td style="text-align:right;"><strong>' + inv.balance.toFixed( 2 ) + '</strong></td>' +
                    '<td><span class="noor-status-badge ' + statusClass + '">' + escHtml( inv.status ) + '</span></td>' +
                    '</tr>';
            } );
            html += '</tbody></table>';
            $( '#noor-fee-invoice-list' ).html( html );
        } )
        .fail( function () {
            $( '#noor-fee-invoice-list' ).html( '<p class="description">' + noorTMS.i18n.error + '</p>' );
        } );
    } );

    // Select invoice row → show payment form.
    $( document ).on( 'change', 'input[name="noor_fee_inv_pick"]', function () {
        const invoiceId = $( this ).val();
        const balance   = parseFloat( $( this ).data( 'balance' ) ) || 0;

        // Highlight selected row.
        $( '.noor-fee-inv-select-row' ).removeClass( 'is-selected' );
        $( this ).closest( 'tr' ).addClass( 'is-selected' );

        $( '#noor-fee-invoice-id' ).val( invoiceId );
        $( '#noor-fee-amount' ).val( balance.toFixed( 2 ) ).attr( 'max', balance );
        $( '#noor-fee-balance-note' ).text( 'Outstanding balance: ' + balance.toFixed( 2 ) );
        $( '#noor-fee-payment-section' ).show();
        $( '#noor-fee-pay-feedback' ).text( '' ).removeClass( 'is-error is-success' );
    } );

    // --- 10f. Submit payment ---
    $( '#noor-fee-payment-form' ).on( 'submit', function ( e ) {
        e.preventDefault();

        const $form     = $( this );
        const $btn      = $( '#noor-fee-pay-btn' );
        const $feedback = $( '#noor-fee-pay-feedback' );

        $btn.prop( 'disabled', true ).text( 'Saving…' );
        $feedback.text( '' ).removeClass( 'is-error is-success' );

        $.post( noorTMS.ajaxUrl, $form.serialize() + '&action=noor_tms_fee_save_payment&nonce=' + encodeURIComponent( noorTMS.nonce ) )
        .done( function ( r ) {
            if ( r.success ) {
                $feedback.addClass( 'is-success' ).text( r.data.message );
                $form[0].reset();
                $( '#noor-fee-payment-section' ).hide();
                $( '#noor-fee-invoice-section' ).hide();
                $( '#noor-fee-student-results' ).empty();
                $( '#noor-fee-student-search' ).val( '' );
            } else {
                $feedback.addClass( 'is-error' ).text( r.data.message || noorTMS.i18n.error );
            }
        } )
        .fail( function () {
            $feedback.addClass( 'is-error' ).text( noorTMS.i18n.error );
        } )
        .always( function () {
            $btn.prop( 'disabled', false ).text( 'Record Payment' );
        } );
    } );

} )( jQuery );
