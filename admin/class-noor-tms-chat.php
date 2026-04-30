<?php
/**
 * Live chat inbox page for admin users.
 *
 * @package Noor_TMS\Admin
 */

namespace Noor_TMS\Admin;

use Noor_TMS\Includes\DatabaseHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Class Chat
 */
class Chat {

	/**
	 * Render live chat inbox.
	 */
	public function page_chat(): void {
		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$search    = sanitize_text_field( wp_unslash( $_GET['noor_search'] ?? '' ) );
		$status    = sanitize_key( $_GET['status_filter'] ?? '' );
		$paged     = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$thread_id = max( 0, (int) ( $_GET['thread_id'] ?? 0 ) );

		$result = DatabaseHandler::get_chat_threads(
			[
				'per_page' => 20,
				'page'     => $paged,
				'search'   => $search,
				'status'   => $status,
			]
		);

		$threads     = $result['rows'];
		$total       = (int) $result['total'];
		$total_pages = (int) ceil( $total / 20 );
		$statuses    = $this->statuses();

		if ( $thread_id <= 0 && ! empty( $threads ) ) {
			$thread_id = (int) $threads[0]['id'];
		}

		$active_thread   = $thread_id > 0 ? DatabaseHandler::get_chat_thread( $thread_id ) : null;
		$active_messages = $active_thread ? DatabaseHandler::get_chat_messages( (int) $active_thread['id'], 0, 250 ) : [];
		$last_message_id = ! empty( $active_messages ) ? (int) end( $active_messages )['id'] : 0;
		reset( $active_messages );
		?>
		<div class="wrap noor-tms-wrap noor-chat-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Live Chat Inbox', 'noor-tms' ); ?></h1>
			<hr class="wp-header-end">

			<?php $this->render_notices(); ?>

			<form method="get" action="" class="noor-chat-admin-filter">
				<input type="hidden" name="page" value="noor-tms-chat" />
				<input type="search" name="noor_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email, phone...', 'noor-tms' ); ?>" />
				<select name="status_filter">
					<option value=""><?php esc_html_e( 'All Statuses', 'noor-tms' ); ?></option>
					<?php foreach ( $statuses as $status_key => $status_label ) : ?>
						<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $status, $status_key ); ?>><?php echo esc_html( $status_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Filter', 'noor-tms' ); ?></button>
			</form>

			<div class="noor-chat-admin-layout">
				<aside class="noor-chat-admin-list">
					<?php if ( empty( $threads ) ) : ?>
						<p class="noor-chat-admin-empty"><?php esc_html_e( 'No chat conversations found.', 'noor-tms' ); ?></p>
					<?php else : ?>
						<?php foreach ( $threads as $thread ) : ?>
							<?php
							$thread_url = add_query_arg(
								[
									'page'         => 'noor-tms-chat',
									'thread_id'    => (int) $thread['id'],
									'noor_search'  => $search,
									'status_filter'=> $status,
								],
								admin_url( 'admin.php' )
							);
							$is_active  = (int) $thread['id'] === $thread_id;
							$display    = (string) ( $thread['requester_name'] ?: __( 'Website Visitor', 'noor-tms' ) );
							$preview    = wp_trim_words( (string) ( $thread['last_message'] ?? '' ), 14, '...' );
							$time_label = ! empty( $thread['last_message_at'] ) ? mysql2date( 'Y-m-d H:i', (string) $thread['last_message_at'] ) : '';
							?>
							<a href="<?php echo esc_url( $thread_url ); ?>" class="noor-chat-thread-card <?php echo $is_active ? 'is-active' : ''; ?>">
								<div class="noor-chat-thread-card__top">
									<strong><?php echo esc_html( $display ); ?></strong>
									<span class="noor-status-badge noor-status-<?php echo esc_attr( (string) $thread['status'] ); ?>"><?php echo esc_html( $this->status_label( (string) $thread['status'] ) ); ?></span>
								</div>
								<div class="noor-chat-thread-card__meta"><?php echo esc_html( (string) ( $thread['requester_email'] ?: $thread['requester_phone'] ) ); ?></div>
								<div class="noor-chat-thread-card__preview"><?php echo esc_html( $preview ?: __( 'No messages yet.', 'noor-tms' ) ); ?></div>
								<?php if ( '' !== $time_label ) : ?>
									<div class="noor-chat-thread-card__time"><?php echo esc_html( $time_label ); ?></div>
								<?php endif; ?>
							</a>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php if ( $total_pages > 1 ) : ?>
						<div class="tablenav-pages" style="margin-top:12px;">
							<?php
							echo paginate_links(
								[
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
									'total'     => $total_pages,
									'current'   => $paged,
								]
							);
							?>
						</div>
					<?php endif; ?>
				</aside>

				<section class="noor-chat-admin-panel">
					<?php if ( ! $active_thread ) : ?>
						<p class="noor-chat-admin-empty"><?php esc_html_e( 'Select a conversation to view and reply.', 'noor-tms' ); ?></p>
					<?php else : ?>
						<div class="noor-chat-admin-panel__head">
							<div>
								<h2><?php echo esc_html( (string) ( $active_thread['requester_name'] ?: __( 'Website Visitor', 'noor-tms' ) ) ); ?></h2>
								<p>
									<?php echo esc_html( (string) ( $active_thread['requester_email'] ?: __( 'No email provided', 'noor-tms' ) ) ); ?>
									<?php if ( ! empty( $active_thread['requester_phone'] ) ) : ?>
										 | <?php echo esc_html( (string) $active_thread['requester_phone'] ); ?>
									<?php endif; ?>
								</p>
							</div>
							<form method="post" action="" class="noor-chat-status-form">
								<?php wp_nonce_field( 'noor_tms_chat_status', 'noor_tms_chat_status_nonce' ); ?>
								<input type="hidden" name="noor_tms_chat_action" value="update_chat_status" />
								<input type="hidden" name="thread_id" value="<?php echo esc_attr( (int) $active_thread['id'] ); ?>" />
								<select name="new_status">
									<?php foreach ( $statuses as $status_key => $status_label ) : ?>
										<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( (string) $active_thread['status'], $status_key ); ?>><?php echo esc_html( $status_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="submit" class="button button-secondary"><?php esc_html_e( 'Update Status', 'noor-tms' ); ?></button>
							</form>
						</div>

						<div class="noor-chat-admin-messages">
							<?php if ( empty( $active_messages ) ) : ?>
								<p class="noor-chat-admin-empty"><?php esc_html_e( 'No messages in this thread yet.', 'noor-tms' ); ?></p>
							<?php else : ?>
								<?php foreach ( $active_messages as $msg ) : ?>
									<?php
									$role = sanitize_key( (string) ( $msg['sender_role'] ?? 'visitor' ) );
									$is_agent = 'agent' === $role;
									$is_system = 'system' === $role;
									$role_label = $is_agent
										? __( 'Agent', 'noor-tms' )
										: ( $is_system ? __( 'System', 'noor-tms' ) : __( 'Visitor', 'noor-tms' ) );
									?>
								<div class="noor-chat-admin-message <?php echo $is_agent ? 'is-agent' : ( $is_system ? 'is-system' : 'is-visitor' ); ?>"
									data-msg-id="<?php echo (int) $msg['id']; ?>">
									<div class="noor-chat-admin-message__meta"><?php echo esc_html( $role_label ); ?> • <?php echo esc_html( mysql2date( 'Y-m-d H:i', (string) $msg['created_at'] ) ); ?></div>
									<div class="noor-chat-admin-message__text"><?php echo nl2br( esc_html( (string) ( $msg['message_text'] ?? '' ) ) ); ?></div>
								</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>

						<form method="post" action="" class="noor-chat-admin-reply">
							<?php wp_nonce_field( 'noor_tms_chat_reply', 'noor_tms_chat_reply_nonce' ); ?>
							<input type="hidden" name="noor_tms_chat_action" value="send_chat_reply" />
							<input type="hidden" name="thread_id" value="<?php echo esc_attr( (int) $active_thread['id'] ); ?>" />
							<textarea name="reply_message" rows="4" required placeholder="<?php esc_attr_e( 'Type your reply...', 'noor-tms' ); ?>"></textarea>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Send Reply', 'noor-tms' ); ?></button>
						</form>
					<?php endif; ?>
				</section>
			</div>
		</div>

		<?php if ( $active_thread ) : ?>
		<script>
		/* global jQuery */
		( function ( $ ) {
			'use strict';

			// ── Config ────────────────────────────────────────────────────────
			const THREAD_ID    = <?php echo (int) $active_thread['id']; ?>;
			const AJAX_URL     = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const NONCE        = <?php echo wp_json_encode( wp_create_nonce( 'noor_tms_ajax' ) ); ?>;
			const LOADED_AT    = <?php echo wp_json_encode( current_time( 'mysql' ) ); ?>;
			const POLL_MS      = 5000;   // message poll interval
			const PING_MS      = 20000;  // thread-list ping interval

			// ── State ─────────────────────────────────────────────────────────
			let lastMsgId        = <?php echo (int) $last_message_id; ?>;
			let isFetching       = false;
			let isSending        = false;
			let badgeShown       = false;
			let titleFlashTimer  = null;
			const originalTitle  = document.title;

			// ── Helpers ───────────────────────────────────────────────────────
			function escHtml( str ) {
				return String( str )
					.replace( /&/g,  '&amp;'  )
					.replace( /</g,  '&lt;'   )
					.replace( />/g,  '&gt;'   )
					.replace( /"/g,  '&quot;' )
					.replace( /'/g,  '&#039;' );
			}

			function scrollToBottom() {
				const el = document.querySelector( '.noor-chat-admin-messages' );
				if ( el ) { el.scrollTop = el.scrollHeight; }
			}

			function flashTitle( msg ) {
				if ( titleFlashTimer ) { return; }
				titleFlashTimer = setInterval( function () {
					document.title = ( document.title === originalTitle ) ? msg : originalTitle;
				}, 1500 );
				$( window ).one( 'focus', function () {
					clearInterval( titleFlashTimer );
					titleFlashTimer = null;
					document.title  = originalTitle;
				} );
			}

			// ── Append messages ───────────────────────────────────────────────
			function appendMessages( messages ) {
				if ( ! messages || ! messages.length ) { return; }

				const $container    = $( '.noor-chat-admin-messages' );
				let   hasNewVisitor = false;

				messages.forEach( function ( msg ) {
					const id = Number( msg.id || 0 );
					if ( ! id ) { return; }

					// Skip if already rendered.
					if ( $container.find( '[data-msg-id="' + id + '"]' ).length ) {
						if ( id > lastMsgId ) { lastMsgId = id; }
						return;
					}

					const role      = String( msg.sender_role || 'visitor' );
					const cssRole   = role === 'agent'  ? 'is-agent'
					                : role === 'system' ? 'is-system'
					                :                     'is-visitor';
					const roleLabel = role === 'agent'  ? 'Agent'
					                : role === 'system' ? 'System'
					                :                     'Visitor';
					const time      = String( msg.created_at || '' ).slice( 0, 16 ).replace( 'T', ' ' );
					const text      = escHtml( msg.message_text || '' ).replace( /\n/g, '<br>' );

					$container.append(
						'<div class="noor-chat-admin-message ' + cssRole + '" data-msg-id="' + id + '">' +
							'<div class="noor-chat-admin-message__meta">' + escHtml( roleLabel ) + ' &bull; ' + escHtml( time ) + '</div>' +
							'<div class="noor-chat-admin-message__text">' + text + '</div>' +
						'</div>'
					);

					if ( id > lastMsgId ) { lastMsgId = id; }
					if ( role === 'visitor' ) { hasNewVisitor = true; }
				} );

				scrollToBottom();

				if ( hasNewVisitor && ! document.hasFocus() ) {
					flashTitle( '💬 New message!' );
				}
			}

			// ── Message polling ───────────────────────────────────────────────
			function fetchNewMessages() {
				if ( isFetching ) { return; }
				isFetching = true;

				$.post( AJAX_URL, {
					action:    'noor_tms_admin_chat_fetch',
					nonce:     NONCE,
					thread_id: THREAD_ID,
					after_id:  lastMsgId,
				} )
				.done( function ( r ) {
					if ( r && r.success && r.data && r.data.messages ) {
						appendMessages( r.data.messages );
					}
				} )
				.always( function () { isFetching = false; } );
			}

			// ── Thread-list ping ─────────────────────────────────────────────
			function pingThreadList() {
				$.post( AJAX_URL, {
					action:    'noor_tms_admin_chat_ping',
					nonce:     NONCE,
					since:     LOADED_AT,
					thread_id: THREAD_ID,
				} )
				.done( function ( r ) {
					if ( r && r.success && r.data && r.data.new_thread_count > 0 ) {
						showThreadBadge( r.data.new_thread_count );
					}
				} );
			}

			function showThreadBadge( count ) {
				if ( badgeShown ) { return; }
				badgeShown = true;

				const $aside = $( '.noor-chat-admin-list' );
				const label  = count + ' new conversation' + ( count !== 1 ? 's' : '' );
				const $badge = $(
					'<div style="' +
						'background:#2271b1;color:#fff;padding:8px 12px;border-radius:4px;' +
						'margin-bottom:10px;font-size:13px;cursor:pointer;' +
					'">' +
						'&#128172; ' + escHtml( label ) + ' — click to refresh' +
					'</div>'
				).on( 'click', function () { window.location.reload(); } );

				$aside.prepend( $badge );
				flashTitle( '💬 New chat!' );
			}

			// ── AJAX reply (replaces the full-page form POST) ─────────────────
			const $replyForm     = $( '.noor-chat-admin-reply' );
			const $replyTextarea = $replyForm.find( 'textarea[name="reply_message"]' );
			const $replyBtn      = $replyForm.find( 'button[type="submit"]' );

			$replyForm.on( 'submit', function ( e ) {
				e.preventDefault();

				const message = $.trim( $replyTextarea.val() );
				if ( ! message || isSending ) { return; }

				isSending = true;
				$replyBtn.prop( 'disabled', true ).text( '<?php echo esc_js( __( 'Sending…', 'noor-tms' ) ); ?>' );

				$.post( AJAX_URL, {
					action:    'noor_tms_admin_chat_reply',
					nonce:     NONCE,
					thread_id: THREAD_ID,
					message:   message,
				} )
				.done( function ( r ) {
					if ( r && r.success && r.data && r.data.message ) {
						appendMessages( [ r.data.message ] );
						$replyTextarea.val( '' ).trigger( 'focus' );
					} else {
						const errMsg = ( r && r.data && r.data.message ) ? r.data.message : '<?php echo esc_js( __( 'An error occurred.', 'noor-tms' ) ); ?>';
						window.alert( errMsg );
					}
				} )
				.fail( function () {
					window.alert( '<?php echo esc_js( __( 'Network error. Please try again.', 'noor-tms' ) ); ?>' );
				} )
				.always( function () {
					isSending = false;
					$replyBtn.prop( 'disabled', false ).text( '<?php echo esc_js( __( 'Send Reply', 'noor-tms' ) ); ?>' );
				} );
			} );

			// ── Kick-off ──────────────────────────────────────────────────────
			scrollToBottom();

			setInterval( fetchNewMessages, POLL_MS );
			setInterval( pingThreadList,   PING_MS );

		} )( jQuery );
		</script>
		<?php endif; ?>
		<?php
	}

	/**
	 * Handle chat actions early on admin_init to keep redirects safe.
	 */
	public function maybe_handle_actions(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}

		$page = sanitize_key( wp_unslash( $_REQUEST['page'] ?? '' ) );
		if ( 'noor-tms-chat' !== $page ) {
			return;
		}

		$action = sanitize_key( $_POST['noor_tms_chat_action'] ?? '' );
		if ( '' === $action ) {
			return;
		}

		if ( ! current_user_can( 'noor_tms_manage' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'noor-tms' ) );
		}

		$thread_id = max( 0, (int) ( $_POST['thread_id'] ?? 0 ) );
		if ( $thread_id <= 0 ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'noor-tms-chat', 'msg' => 'chat_action_error' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'send_chat_reply' === $action ) {
			if ( ! check_admin_referer( 'noor_tms_chat_reply', 'noor_tms_chat_reply_nonce' ) ) {
				wp_die( esc_html__( 'Nonce verification failed.', 'noor-tms' ) );
			}

			$message = sanitize_textarea_field( wp_unslash( $_POST['reply_message'] ?? '' ) );
			if ( '' === $message ) {
				wp_safe_redirect( add_query_arg( [ 'page' => 'noor-tms-chat', 'thread_id' => $thread_id, 'msg' => 'chat_reply_empty' ], admin_url( 'admin.php' ) ) );
				exit;
			}

			$message_id = DatabaseHandler::insert_chat_message( $thread_id, 'agent', $message, get_current_user_id() );
			if ( ! $message_id ) {
				wp_safe_redirect( add_query_arg( [ 'page' => 'noor-tms-chat', 'thread_id' => $thread_id, 'msg' => 'chat_action_error' ], admin_url( 'admin.php' ) ) );
				exit;
			}

			DatabaseHandler::update_chat_thread_status( $thread_id, 'in_progress' );
			do_action( 'noor_tms_chat_message_sent', $thread_id, 'agent', $message, get_current_user_id() );

			wp_safe_redirect( add_query_arg( [ 'page' => 'noor-tms-chat', 'thread_id' => $thread_id, 'msg' => 'chat_reply_sent' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'update_chat_status' === $action ) {
			if ( ! check_admin_referer( 'noor_tms_chat_status', 'noor_tms_chat_status_nonce' ) ) {
				wp_die( esc_html__( 'Nonce verification failed.', 'noor-tms' ) );
			}

			$new_status = sanitize_key( $_POST['new_status'] ?? '' );
			$updated = DatabaseHandler::update_chat_thread_status( $thread_id, $new_status );

			wp_safe_redirect(
				add_query_arg(
					[
						'page'      => 'noor-tms-chat',
						'thread_id' => $thread_id,
						'msg'       => $updated ? 'chat_status_updated' : 'chat_action_error',
					],
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	/**
	 * @return array<string, string>
	 */
	private function statuses(): array {
		return [
			'open'        => __( 'Open', 'noor-tms' ),
			'in_progress' => __( 'In Progress', 'noor-tms' ),
			'resolved'    => __( 'Resolved', 'noor-tms' ),
			'closed'      => __( 'Closed', 'noor-tms' ),
		];
	}

	/**
	 * @param string $status
	 * @return string
	 */
	private function status_label( string $status ): string {
		$statuses = $this->statuses();
		return $statuses[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Render page notices.
	 */
	private function render_notices(): void {
		$msg = sanitize_key( $_GET['msg'] ?? '' );

		$messages = [
			'chat_reply_sent'    => [ 'success', __( 'Reply sent successfully.', 'noor-tms' ) ],
			'chat_status_updated'=> [ 'success', __( 'Chat status updated.', 'noor-tms' ) ],
			'chat_reply_empty'   => [ 'error', __( 'Reply message cannot be empty.', 'noor-tms' ) ],
			'chat_action_error'  => [ 'error', __( 'Unable to complete chat action.', 'noor-tms' ) ],
		];

		if ( ! isset( $messages[ $msg ] ) ) {
			return;
		}

		$notice = $messages[ $msg ];
		$type   = (string) $notice[0];
		$text   = (string) $notice[1];
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
	}
}
