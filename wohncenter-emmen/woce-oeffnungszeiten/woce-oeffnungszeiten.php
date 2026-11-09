<?php
/**
 * Plugin Name: Wohncenter Öffnungszeiten
 * Description: Reguläre Öffnungszeiten und Sonderöffnungszeiten (mit CSV-/Excel-Import). Shortcodes [oeffnungszeiten] und [sonderoeffnungszeiten]; vergangene Sonderdaten werden automatisch ausgeblendet.
 * Version:     1.1.0
 * Author:      proimprove
 * Text Domain: woce-oeffnungszeiten
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Woce_Oeffnungszeiten {

	const OPT_ENTRIES = 'woce_loez_entries';
	const OPT_COLUMNS = 'woce_loez_columns';
	const OPT_REGULAR = 'woce_loez_regular';
	const CAP         = 'edit_pages';
	const SLUG        = 'woce-oeffnungszeiten';

	const WEEKDAYS = array( 'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag' );

	/** Reihenfolge der Wochentage in der Tabelle der regulären Öffnungszeiten (Index in WEEKDAYS). */
	const WEEK_ORDER = array( 1, 2, 3, 4, 5, 6, 0 );

	public static function init() {
		add_shortcode( 'sonderoeffnungszeiten', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'oeffnungszeiten', array( __CLASS__, 'shortcode_regular' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_woce_loez_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_woce_loez_save_regular', array( __CLASS__, 'handle_save_regular' ) );
		add_action( 'admin_post_woce_loez_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_woce_loez_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_woce_loez_purge', array( __CLASS__, 'handle_purge' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Daten
	 * ------------------------------------------------------------------ */

	public static function columns() {
		$cols = get_option( self::OPT_COLUMNS );
		if ( ! is_array( $cols ) || ! $cols ) {
			$cols = array( 'Möbelgeschäfte', 'Lebensmittel' );
		}
		return array_values( $cols );
	}

	/** @return array<int, array{date:string,label:string,values:string[]}> */
	public static function entries() {
		$entries = get_option( self::OPT_ENTRIES, array() );
		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Reguläre Öffnungszeiten. Ohne gespeicherte Daten gelten die Zeiten,
	 * die beim Erstellen des Plugins auf der Website standen.
	 *
	 * @return array{days:int[], rows:array<int, array{label:string, hours:array<int,string>}>}
	 */
	public static function regular() {
		$reg = get_option( self::OPT_REGULAR );
		if ( is_array( $reg ) && isset( $reg['rows'], $reg['days'] ) ) {
			return $reg;
		}
		$row = function ( $label, $weekday, $friday, $saturday ) {
			return array(
				'label' => $label,
				'hours' => array( 1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => $friday, 6 => $saturday, 0 => '' ),
			);
		};
		return array(
			'days' => array( 1, 2, 3, 4, 5, 6 ),
			'rows' => array(
				$row( 'Möbelgeschäfte', '09.00 – 19.00', '09.00 – 20.00', '09.00 – 17.00' ),
				$row( 'Lebensmittel', '08.00 – 19.00', '08.00 – 20.00', '08.00 – 17.00' ),
				$row( 'Restaurant', '08.00 – 17.00', '08.00 – 20.00', '08.00 – 17.00' ),
			),
		);
	}

	private static function store( array $entries ) {
		$clean = array();
		foreach ( $entries as $e ) {
			$date = self::parse_date( isset( $e['date'] ) ? $e['date'] : '' );
			if ( ! $date ) {
				continue;
			}
			$values = array();
			foreach ( (array) ( isset( $e['values'] ) ? $e['values'] : array() ) as $v ) {
				$values[] = sanitize_text_field( $v );
			}
			// Pro Datum nur ein Eintrag – spätere überschreiben frühere.
			$clean[ $date ] = array(
				'date'   => $date,
				'label'  => sanitize_text_field( isset( $e['label'] ) ? $e['label'] : '' ),
				'values' => $values,
			);
		}
		ksort( $clean );
		update_option( self::OPT_ENTRIES, array_values( $clean ), false );
		return count( $clean );
	}

	/** Heute in der Zeitzone der Website (Y-m-d). */
	private static function today() {
		return wp_date( 'Y-m-d' );
	}

	/** Akzeptiert 13.5.2026, 13.05.26, 2026-05-13 sowie Excel-Seriennummern. */
	public static function parse_date( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $m ) ) {
			list( , $y, $mo, $d ) = $m;
		} elseif ( preg_match( '/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{2,4})$/', $raw, $m ) ) {
			list( , $d, $mo, $y ) = $m;
			if ( strlen( $y ) === 2 ) {
				$y = '20' . $y;
			}
		} elseif ( preg_match( '/^\d{5}$/', $raw ) ) {
			// Excel-Seriennummer (Tage seit 30.12.1899).
			$ts = ( (int) $raw - 25569 ) * 86400;
			return gmdate( 'Y-m-d', $ts );
		} else {
			return '';
		}
		if ( ! checkdate( (int) $mo, (int) $d, (int) $y ) ) {
			return '';
		}
		return sprintf( '%04d-%02d-%02d', $y, $mo, $d );
	}

	public static function weekday( $ymd ) {
		$dt = DateTime::createFromFormat( '!Y-m-d', $ymd );
		return $dt ? self::WEEKDAYS[ (int) $dt->format( 'w' ) ] : '';
	}

	public static function format_date( $ymd ) {
		$dt = DateTime::createFromFormat( '!Y-m-d', $ymd );
		return $dt ? $dt->format( 'j.n.Y' ) : $ymd;
	}

	/* ------------------------------------------------------------------ *
	 * Frontend
	 * ------------------------------------------------------------------ */

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'vergangene' => 'nein',
				'leer'       => 'Zurzeit sind keine Sonderöffnungszeiten geplant.',
			),
			$atts,
			'sonderoeffnungszeiten'
		);

		$today    = self::today();
		$show_all = in_array( strtolower( $atts['vergangene'] ), array( 'ja', '1', 'true' ), true );
		$cols     = self::columns();

		$rows = array_filter(
			self::entries(),
			function ( $e ) use ( $today, $show_all ) {
				return $show_all || $e['date'] >= $today;
			}
		);

		if ( ! $rows ) {
			return $atts['leer'] ? '<p class="woce-loez-empty">' . esc_html( $atts['leer'] ) . '</p>' : '';
		}

		ob_start();
		?>
		<div class="table-1 woce-loez">
			<table width="100%">
				<thead>
					<tr>
						<th align="left"><strong>Wochentag</strong></th>
						<th align="left"><strong>Datum</strong></th>
						<th align="left"><strong>Feiertag</strong></th>
						<?php foreach ( $cols as $c ) : ?>
							<th align="left"><strong><?php echo esc_html( $c ); ?></strong></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $e ) : ?>
						<tr>
							<td align="left"><?php echo esc_html( self::weekday( $e['date'] ) ); ?></td>
							<td align="left"><?php echo esc_html( self::format_date( $e['date'] ) ); ?></td>
							<td align="left"><?php echo esc_html( $e['label'] ); ?></td>
							<?php foreach ( $cols as $i => $c ) : ?>
								<td align="left"><?php echo esc_html( isset( $e['values'][ $i ] ) ? $e['values'][ $i ] : '' ); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function shortcode_regular() {
		$reg  = self::regular();
		$days = array_values( array_intersect( self::WEEK_ORDER, $reg['days'] ) );
		if ( ! $reg['rows'] || ! $days ) {
			return '';
		}
		ob_start();
		?>
		<div class="table-1 woce-loez-regular">
			<table width="100%">
				<thead>
					<tr>
						<th align="left"><strong>Sparte</strong></th>
						<?php foreach ( $days as $d ) : ?>
							<th align="left"><strong><?php echo esc_html( self::WEEKDAYS[ $d ] ); ?></strong></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $reg['rows'] as $r ) : ?>
						<tr>
							<td align="left"><?php echo esc_html( $r['label'] ); ?></td>
							<?php foreach ( $days as $d ) : ?>
								<td align="left"><?php echo esc_html( isset( $r['hours'][ $d ] ) ? $r['hours'][ $d ] : '' ); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ *
	 * Admin
	 * ------------------------------------------------------------------ */

	public static function admin_menu() {
		add_menu_page(
			'Öffnungszeiten',
			'Öffnungszeiten',
			self::CAP,
			self::SLUG,
			array( __CLASS__, 'render_admin' ),
			'dashicons-clock',
			21
		);
	}

	private static function redirect( $msg ) {
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'woce_msg' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( $action );
	}

	public static function handle_save() {
		self::guard( 'woce_loez_save' );

		// Spalten (Sparten)
		$cols = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', wp_unslash( $_POST['columns'] ?? '' ) ) ) ) );
		if ( $cols ) {
			update_option( self::OPT_COLUMNS, array_values( $cols ), false );
		}

		$rows    = isset( $_POST['rows'] ) ? (array) wp_unslash( $_POST['rows'] ) : array();
		$entries = array();
		foreach ( $rows as $r ) {
			if ( ! empty( $r['delete'] ) ) {
				continue;
			}
			$entries[] = array(
				'date'   => $r['date'] ?? '',
				'label'  => $r['label'] ?? '',
				'values' => isset( $r['values'] ) ? (array) $r['values'] : array(),
			);
		}

		// Vergangene Einträge, die im Formular ausgeblendet waren, erhalten.
		if ( empty( $_POST['showing_past'] ) ) {
			$today = self::today();
			foreach ( self::entries() as $e ) {
				if ( $e['date'] < $today ) {
					array_unshift( $entries, $e );
				}
			}
		}

		$n = self::store( $entries );
		self::redirect( "Gespeichert ($n Einträge)." );
	}

	public static function handle_save_regular() {
		self::guard( 'woce_loez_save_regular' );

		$days = array();
		foreach ( (array) ( $_POST['days'] ?? array() ) as $d ) {
			$d = (int) $d;
			if ( $d >= 0 && $d <= 6 ) {
				$days[] = $d;
			}
		}

		$rows = array();
		foreach ( (array) wp_unslash( $_POST['regular'] ?? array() ) as $r ) {
			$label = sanitize_text_field( $r['label'] ?? '' );
			if ( ! empty( $r['delete'] ) || '' === $label ) {
				continue;
			}
			$hours = array();
			foreach ( self::WEEK_ORDER as $d ) {
				$hours[ $d ] = sanitize_text_field( $r['hours'][ $d ] ?? '' );
			}
			$rows[] = array( 'label' => $label, 'hours' => $hours );
		}

		update_option( self::OPT_REGULAR, array( 'days' => array_values( array_unique( $days ) ), 'rows' => $rows ), false );
		self::redirect( 'Reguläre Öffnungszeiten gespeichert.' );
	}

	public static function handle_purge() {
		self::guard( 'woce_loez_purge' );
		$today  = self::today();
		$before = self::entries();
		$keep   = array_filter(
			$before,
			function ( $e ) use ( $today ) {
				return $e['date'] >= $today;
			}
		);
		self::store( $keep );
		self::redirect( ( count( $before ) - count( $keep ) ) . ' vergangene Einträge gelöscht.' );
	}

	public static function handle_import() {
		self::guard( 'woce_loez_import' );

		$text = '';
		if ( ! empty( $_FILES['csv']['tmp_name'] ) && is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) {
			$text = (string) file_get_contents( $_FILES['csv']['tmp_name'] );
		} elseif ( ! empty( $_POST['paste'] ) ) {
			$text = wp_unslash( $_POST['paste'] );
		}
		if ( '' === trim( $text ) ) {
			self::redirect( 'Import: keine Daten gefunden.' );
		}

		list( $imported, $errors ) = self::parse_table( $text );

		$mode     = ( $_POST['mode'] ?? 'merge' ) === 'replace' ? 'replace' : 'merge';
		$existing = 'replace' === $mode ? array() : self::entries();
		$n        = self::store( array_merge( $existing, $imported ) );

		$msg = sprintf( 'Import: %d Zeilen übernommen, total %d Einträge.', count( $imported ), $n );
		if ( $errors ) {
			$msg .= ' Übersprungen (ungültiges Datum): Zeile ' . implode( ', ', array_slice( $errors, 0, 15 ) ) . '.';
		}
		self::redirect( $msg );
	}

	/**
	 * Erwartetes Format (Kopfzeile optional, Trennzeichen ; , oder Tab):
	 * [Wochentag;] Datum; Feiertag; <Sparte 1>; <Sparte 2>; …
	 */
	private static function parse_table( $text ) {
		// BOM entfernen, Excel-Exporte in Windows-1252 nach UTF-8 wandeln.
		$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'Windows-1252' );
		}
		$lines = preg_split( '/\r\n|\r|\n/', trim( $text ) );

		$first = $lines[0];
		$delim = "\t";
		if ( substr_count( $first, "\t" ) === 0 ) {
			$delim = substr_count( $first, ';' ) >= substr_count( $first, ',' ) ? ';' : ',';
		}

		$entries = array();
		$errors  = array();
		$skip_wd = null; // Hat die Tabelle eine Wochentag-Spalte vorne?

		foreach ( $lines as $i => $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cells = array_map( 'trim', str_getcsv( $line, $delim ) );

			if ( null === $skip_wd ) {
				$skip_wd = in_array( $cells[0], self::WEEKDAYS, true ) || 0 === strcasecmp( $cells[0], 'Wochentag' );
			}
			if ( $skip_wd ) {
				array_shift( $cells );
			}

			$date = self::parse_date( $cells[0] ?? '' );
			if ( ! $date ) {
				// Kopfzeile still überspringen, sonst als Fehler melden.
				if ( 0 !== $i ) {
					$errors[] = $i + 1;
				}
				continue;
			}
			$entries[] = array(
				'date'   => $date,
				'label'  => $cells[1] ?? '',
				'values' => array_slice( $cells, 2 ),
			);
		}
		return array( $entries, $errors );
	}

	public static function handle_export() {
		self::guard( 'woce_loez_export' );
		$cols = self::columns();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=sonderoeffnungszeiten-' . self::today() . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM, damit Excel Umlaute korrekt anzeigt.
		fputcsv( $out, array_merge( array( 'Wochentag', 'Datum', 'Feiertag' ), $cols ), ';' );
		foreach ( self::entries() as $e ) {
			$row = array( self::weekday( $e['date'] ), self::format_date( $e['date'] ), $e['label'] );
			foreach ( $cols as $i => $c ) {
				$row[] = $e['values'][ $i ] ?? '';
			}
			fputcsv( $out, $row, ';' );
		}
		fclose( $out );
		exit;
	}

	public static function render_admin() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$cols      = self::columns();
		$today     = self::today();
		$show_past = ! empty( $_GET['vergangene'] );
		$all       = self::entries();
		$rows      = $show_past ? $all : array_values(
			array_filter(
				$all,
				function ( $e ) use ( $today ) {
					return $e['date'] >= $today;
				}
			)
		);
		$past_count = count( $all ) - count( array_filter( $all, function ( $e ) use ( $today ) { return $e['date'] >= $today; } ) );
		$base       = admin_url( 'admin.php?page=' . self::SLUG );
		?>
		<div class="wrap">
			<h1>Öffnungszeiten</h1>

			<?php if ( ! empty( $_GET['woce_msg'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( wp_unslash( $_GET['woce_msg'] ) ); ?></p></div>
			<?php endif; ?>

			<?php self::render_regular_admin(); ?>

			<hr>
			<h2>Sonderöffnungszeiten</h2>
			<p>
				Auf einer Seite einbinden mit dem Shortcode <code>[sonderoeffnungszeiten]</code>.
				Daten vor dem heutigen Tag (<?php echo esc_html( self::format_date( $today ) ); ?>) werden auf der Website automatisch ausgeblendet.
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'woce_loez_save' ); ?>
				<input type="hidden" name="action" value="woce_loez_save">
				<input type="hidden" name="showing_past" value="<?php echo $show_past ? '1' : ''; ?>">

				<p>
					<?php if ( $show_past ) : ?>
						<a href="<?php echo esc_url( $base ); ?>">Nur aktuelle anzeigen</a>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( 'vergangene', 1, $base ) ); ?>">Vergangene anzeigen (<?php echo (int) $past_count; ?>)</a>
					<?php endif; ?>
				</p>

				<div style="overflow-x:auto"><table class="widefat striped" id="woce-loez-table" style="min-width:820px">
					<thead>
						<tr>
							<th style="width:6.5em">Wochentag</th>
							<th style="width:10em">Datum</th>
							<th>Feiertag / Anlass</th>
							<?php foreach ( $cols as $c ) : ?>
								<th style="width:9em"><?php echo esc_html( $c ); ?></th>
							<?php endforeach; ?>
							<th style="width:3.5em">Löschen</th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $rows as $i => $e ) {
							self::render_row( $i, $e, $cols, $e['date'] < $today );
						}
						?>
					</tbody>
				</table></div>
				<template id="woce-loez-row"><?php self::render_row( '__i__', array( 'date' => '', 'label' => '', 'values' => array() ), $cols, false ); ?></template>

				<p><button type="button" class="button" id="woce-loez-add">+ Zeile hinzufügen</button></p>

				<details style="margin:1em 0">
					<summary>Spalten (Sparten) anpassen</summary>
					<p>
						<input type="text" name="columns" class="regular-text" value="<?php echo esc_attr( implode( ', ', $cols ) ); ?>">
						<br><span class="description">Kommagetrennt, z.&nbsp;B. «Möbelgeschäfte, Lebensmittel, Restaurant». Reihenfolge = Spaltenreihenfolge.</span>
					</p>
				</details>

				<?php submit_button( 'Speichern' ); ?>
			</form>

			<hr>
			<h3>Import</h3>
			<p>
				CSV-Datei oder direkt aus Excel kopierte Zeilen. Spalten:
				<code>Datum; Feiertag; <?php echo esc_html( implode( '; ', $cols ) ); ?></code>
				(eine führende Spalte «Wochentag» und eine Kopfzeile werden automatisch erkannt).
				Datum als <code>13.5.2026</code> oder <code>2026-05-13</code>.
			</p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'woce_loez_import' ); ?>
				<input type="hidden" name="action" value="woce_loez_import">
				<p><input type="file" name="csv" accept=".csv,.txt,text/csv"></p>
				<p><textarea name="paste" rows="6" class="large-text code" placeholder="… oder hier aus Excel einfügen"></textarea></p>
				<p>
					<label><input type="radio" name="mode" value="merge" checked> Ergänzen (gleiches Datum wird überschrieben)</label><br>
					<label><input type="radio" name="mode" value="replace"> Alle bestehenden Einträge ersetzen</label>
				</p>
				<?php submit_button( 'Importieren', 'secondary' ); ?>
			</form>

			<hr>
			<h3>Export &amp; Aufräumen</h3>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=woce_loez_export' ), 'woce_loez_export' ) ); ?>">Als CSV exportieren</a>
				<?php if ( $past_count ) : ?>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=woce_loez_purge' ), 'woce_loez_purge' ) ); ?>"
						onclick="return confirm('<?php echo (int) $past_count; ?> vergangene Einträge endgültig löschen?');">Vergangene Einträge löschen (<?php echo (int) $past_count; ?>)</a>
				<?php endif; ?>
			</p>
		</div>

		<script>
		(function () {
			var tbody = document.querySelector('#woce-loez-table tbody');
			var tpl = document.getElementById('woce-loez-row');
			var days = <?php echo wp_json_encode( self::WEEKDAYS ); ?>;
			var n = <?php echo (int) count( $rows ); ?>;
			document.getElementById('woce-loez-add').addEventListener('click', function () {
				tbody.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, 'n' + (n++)));
				tbody.lastElementChild.querySelector('input[type=date]').focus();
			});
			var rtbody = document.querySelector('#woce-loez-regular tbody');
			var rtpl = document.getElementById('woce-loez-regular-row');
			var rn = 0;
			document.getElementById('woce-loez-regular-add').addEventListener('click', function () {
				rtbody.insertAdjacentHTML('beforeend', rtpl.innerHTML.replace(/__i__/g, 'n' + (rn++)));
				rtbody.lastElementChild.querySelector('input[type=text]').focus();
			});
			tbody.addEventListener('change', function (ev) {
				if (ev.target.type !== 'date') return;
				var d = ev.target.value ? new Date(ev.target.value + 'T12:00:00') : null;
				ev.target.closest('tr').querySelector('.woce-wd').textContent = d ? days[d.getDay()] : '';
			});
		})();
		</script>
		<?php
	}

	private static function render_regular_admin() {
		$reg = self::regular();
		?>
		<h2>Reguläre Öffnungszeiten</h2>
		<p>Auf einer Seite einbinden mit dem Shortcode <code>[oeffnungszeiten]</code>. Nur angehakte Wochentage erscheinen auf der Website.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'woce_loez_save_regular' ); ?>
			<input type="hidden" name="action" value="woce_loez_save_regular">

			<div style="overflow-x:auto"><table class="widefat striped" id="woce-loez-regular" style="min-width:1250px">
				<thead>
					<tr>
						<th style="min-width:11em">Sparte</th>
						<?php foreach ( self::WEEK_ORDER as $d ) : ?>
							<th style="min-width:8.5em">
								<label>
									<input type="checkbox" name="days[]" value="<?php echo (int) $d; ?>" <?php checked( in_array( $d, $reg['days'], true ) ); ?>>
									<?php echo esc_html( self::WEEKDAYS[ $d ] ); ?>
								</label>
							</th>
						<?php endforeach; ?>
						<th style="width:3.5em">Löschen</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $reg['rows'] as $i => $r ) {
						self::render_regular_row( $i, $r );
					}
					?>
				</tbody>
			</table></div>
			<template id="woce-loez-regular-row"><?php self::render_regular_row( '__i__', array( 'label' => '', 'hours' => array() ) ); ?></template>

			<p><button type="button" class="button" id="woce-loez-regular-add">+ Sparte hinzufügen</button></p>
			<?php submit_button( 'Reguläre Öffnungszeiten speichern' ); ?>
		</form>
		<?php
	}

	private static function render_regular_row( $i, $r ) {
		$name = 'regular[' . $i . ']';
		?>
		<tr>
			<td><input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $r['label'] ); ?>" placeholder="z. B. Restaurant"></td>
			<?php foreach ( self::WEEK_ORDER as $d ) : ?>
				<td><input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[hours][<?php echo (int) $d; ?>]" value="<?php echo esc_attr( $r['hours'][ $d ] ?? '' ); ?>" placeholder="09.00 – 19.00"></td>
			<?php endforeach; ?>
			<td><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete]" value="1" title="Beim Speichern löschen"></td>
		</tr>
		<?php
	}

	private static function render_row( $i, $e, $cols, $is_past ) {
		$name = 'rows[' . $i . ']';
		?>
		<tr<?php echo $is_past ? ' style="opacity:.5"' : ''; ?>>
			<td class="woce-wd"><?php echo esc_html( $e['date'] ? self::weekday( $e['date'] ) : '' ); ?></td>
			<td><input type="date" name="<?php echo esc_attr( $name ); ?>[date]" value="<?php echo esc_attr( $e['date'] ); ?>"></td>
			<td><input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $e['label'] ); ?>"></td>
			<?php foreach ( $cols as $ci => $c ) : ?>
				<td><input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[values][<?php echo (int) $ci; ?>]" value="<?php echo esc_attr( $e['values'][ $ci ] ?? '' ); ?>" placeholder="z. B. 09-17 Uhr"></td>
			<?php endforeach; ?>
			<td><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete]" value="1" title="Beim Speichern löschen"></td>
		</tr>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Aktivierung: aktuelle Daten der Website als Startbestand
	 * ------------------------------------------------------------------ */

	public static function activate() {
		if ( false !== get_option( self::OPT_ENTRIES ) ) {
			return;
		}
		$seed = array(
			array( '2026-05-13', 'Vorfeiertag', '09-17 Uhr', '08-17 Uhr' ),
			array( '2026-05-14', 'Auffahrt', 'geschlossen', 'geschlossen' ),
			array( '2026-05-25', 'Pfingstmontag', '09-17 Uhr', '09-17 Uhr' ),
			array( '2026-06-03', 'Vorfeiertag', '09-17 Uhr', '08-17 Uhr' ),
			array( '2026-06-04', 'Fronleichnam', 'geschlossen', 'geschlossen' ),
			array( '2026-07-31', 'Vorfeiertag', '09-17 Uhr', '08-17 Uhr' ),
			array( '2026-08-01', 'Bundesfeiertag', 'geschlossen', 'geschlossen' ),
			array( '2026-08-14', 'Vorfeiertag', '09-17 Uhr', '08-17 Uhr' ),
			array( '2026-08-15', 'Maria Himmelfahrt', 'geschlossen', 'geschlossen' ),
			array( '2026-10-31', 'Vorfeiertag', '09-17 Uhr', '08-17 Uhr' ),
			array( '2026-11-01', 'Allerheiligen', 'geschlossen', 'geschlossen' ),
			array( '2026-12-07', 'Vorfeiertag', '09-17 Uhr', '08-17 Uhr' ),
			array( '2026-12-08', 'Maria Empfängnis', '10-17 Uhr', '10-17 Uhr' ),
			array( '2026-12-13', 'Sonntagsverkauf', '10-17 Uhr', '10-17 Uhr' ),
			array( '2026-12-20', 'Sonntagsverkauf', '10-17 Uhr', '10-17 Uhr' ),
			array( '2026-12-23', 'Abendverkauf', '09-20 Uhr', '08-20 Uhr' ),
			array( '2026-12-24', 'Vorfeiertag', '09-16 Uhr', '07.30-16 Uhr' ),
			array( '2026-12-25', 'Weihnachten', 'geschlossen', 'geschlossen' ),
			array( '2026-12-26', 'Stefanstag', 'geschlossen', 'geschlossen' ),
			array( '2026-12-31', 'Silvester', '09-16 Uhr', '08-16 Uhr' ),
			array( '2027-01-01', 'Neujahr', 'geschlossen', 'geschlossen' ),
			array( '2027-01-02', 'Berchtoldstag', '09-17 Uhr', '09-17 Uhr' ),
		);
		$entries = array();
		foreach ( $seed as $s ) {
			$entries[] = array( 'date' => $s[0], 'label' => $s[1], 'values' => array( $s[2], $s[3] ) );
		}
		self::store( $entries );
	}
}

register_activation_hook( __FILE__, array( 'Woce_Oeffnungszeiten', 'activate' ) );
Woce_Oeffnungszeiten::init();
