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

} )( jQuery );
