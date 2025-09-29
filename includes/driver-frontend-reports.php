<?php
/**
 * Driver Reports: Admin page and REST endpoint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Dashboard summary widget
add_action( 'wp_dashboard_setup', function () {
	wp_add_dashboard_widget( 'wom_driver_summary_widget', __( 'Driver Delivery Summary', 'woocommerce-orders-map' ), 'wom_render_driver_summary_widget' );
} );

function wom_render_driver_summary_widget() {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'shop_manager' ) ) {
		echo esc_html__( 'Insufficient permissions.', 'woocommerce-orders-map' );
		return;
	}
	$start = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
	$end   = gmdate( 'Y-m-d' );
	$report = wom_get_driver_report_data( $start, $end, 0 );
	$completed = (int) $report['completed'];
	$failed = (int) $report['failed'];
	$rate = $report['completion_rate'];
	echo '<ul style="margin:0">';
	echo '<li>' . esc_html__( 'Last 7 days', 'woocommerce-orders-map' ) . '</li>';
	echo '<li>' . esc_html__( 'Completed', 'woocommerce-orders-map' ) . ': ' . (int) $completed . '</li>';
	echo '<li>' . esc_html__( 'Failed', 'woocommerce-orders-map' ) . ': ' . (int) $failed . '</li>';
	echo '<li>' . esc_html__( 'Completion Rate', 'woocommerce-orders-map' ) . ': ' . esc_html( $rate ) . '%</li>';
	echo '</ul>';
}

// Admin menu entry under WooCommerce -> Reports
add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		__( 'Driver Delivery Reports', 'woocommerce-orders-map' ),
		__( 'Driver Delivery Reports', 'woocommerce-orders-map' ),
		'manage_woocommerce',
		'wom-driver-reports',
		'wom_render_driver_reports_page'
	);
} );

function wom_render_driver_reports_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$start = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : gmdate( 'Y-m-01' );
	$end   = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : gmdate( 'Y-m-d' );
	$driver = isset( $_GET['driver'] ) ? absint( $_GET['driver'] ) : 0;

	$export_url = add_query_arg( array(
		'page'   => 'wom-driver-reports',
		'wom_csv' => 1,
		'start'  => $start,
		'end'    => $end,
		'driver' => $driver,
	), admin_url( 'admin.php' ) );

	// Export handler
	if ( isset( $_GET['wom_csv'] ) ) {
		wom_output_driver_report_csv( $start, $end, $driver );
		return;
	}

	$report = wom_get_driver_report_data( $start, $end, $driver );
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Driver Delivery Reports', 'woocommerce-orders-map' ); ?></h1>
		<form method="get">
			<input type="hidden" name="page" value="wom-driver-reports" />
			<label><?php esc_html_e( 'Start', 'woocommerce-orders-map' ); ?> <input type="date" name="start" value="<?php echo esc_attr( $start ); ?>" /></label>
			<label><?php esc_html_e( 'End', 'woocommerce-orders-map' ); ?> <input type="date" name="end" value="<?php echo esc_attr( $end ); ?>" /></label>
			<label><?php esc_html_e( 'Driver (user ID)', 'woocommerce-orders-map' ); ?> <input type="number" name="driver" value="<?php echo esc_attr( $driver ); ?>" /></label>
			<button class="button button-primary" type="submit"><?php esc_html_e( 'Filter', 'woocommerce-orders-map' ); ?></button>
			<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'woocommerce-orders-map' ); ?></a>
		</form>

		<h2><?php esc_html_e( 'Chart', 'woocommerce-orders-map' ); ?></h2>
		<div style="max-width:920px">
			<label>
				<?php esc_html_e( 'Graph Type', 'woocommerce-orders-map' ); ?>
				<select id="wom-graph-type">
					<option value="bar">Bar</option>
					<option value="line">Line</option>
					<option value="pie">Pie</option>
					<option value="doughnut">Doughnut</option>
				</select>
			</label>
			<canvas id="wom-report-chart" height="120"></canvas>
		</div>

		<h2><?php esc_html_e( 'Summary', 'woocommerce-orders-map' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'Completed', 'woocommerce-orders-map' ); ?>: <?php echo (int) $report['completed']; ?></li>
			<li><?php esc_html_e( 'Failed', 'woocommerce-orders-map' ); ?>: <?php echo (int) $report['failed']; ?></li>
			<li><?php esc_html_e( 'Completion Rate', 'woocommerce-orders-map' ); ?>: <?php echo esc_html( $report['completion_rate'] . '%' ); ?></li>
			<li><?php esc_html_e( 'Avg. Assign → Deliver (mins)', 'woocommerce-orders-map' ); ?>: <?php echo esc_html( $report['avg_minutes'] ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'Orders', 'woocommerce-orders-map' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'woocommerce-orders-map' ); ?></th>
					<th><?php esc_html_e( 'Driver', 'woocommerce-orders-map' ); ?></th>
					<th><?php esc_html_e( 'Assigned At', 'woocommerce-orders-map' ); ?></th>
					<th><?php esc_html_e( 'Delivered At', 'woocommerce-orders-map' ); ?></th>
					<th><?php esc_html_e( 'Driver Status', 'woocommerce-orders-map' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $report['rows'] as $row ) : ?>
					<tr>
						<td>#<?php echo (int) $row['order_id']; ?></td>
						<td><?php echo (int) $row['driver_id']; ?></td>
						<td><?php echo esc_html( $row['assigned_at'] ); ?></td>
						<td><?php echo esc_html( $row['delivered_at'] ); ?></td>
						<td><?php echo esc_html( $row['driver_status'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
    // Enqueue Chart.js from CDN and add inline script to render chart
    wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true );
    $chart_rows = $report['rows'];
    $script = 'document.addEventListener("DOMContentLoaded",function(){
        var ctx = document.getElementById("wom-report-chart").getContext("2d");
        var typeSel = document.getElementById("wom-graph-type");
        function aggregate(rows){
          var map = { Completed: 0, Failed: 0 };
          (rows||[]).forEach(function(r){ if(r.driver_status==="delivered") map.Completed++; else if(r.driver_status==="failed") map.Failed++; });
          return { labels: Object.keys(map), values: Object.values(map) };
        }
        var agg = aggregate(' . wp_json_encode( $chart_rows ) . ');
        var config = { type: "bar", data: { labels: agg.labels, datasets: [{ label: "Deliveries", data: agg.values, backgroundColor: ["#36a2eb","#ff6384"] }] }, options: { responsive: true, plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } } };
        var chart = new Chart(ctx, config);
        typeSel.addEventListener("change", function(){ chart.config.type = typeSel.value; chart.update(); });
    });';
    wp_add_inline_script( 'chartjs', $script );
}

// REST endpoint for JSON report
add_action( 'rest_api_init', function () {
	register_rest_route( 'wom/v1', '/reports/driver', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => function ( WP_REST_Request $req ) {
			if ( current_user_can( 'manage_woocommerce' ) ) { return true; }
			return is_user_logged_in();
		},
		'callback'            => function ( WP_REST_Request $request ) {
			$start  = sanitize_text_field( (string) $request->get_param( 'start' ) );
			$end    = sanitize_text_field( (string) $request->get_param( 'end' ) );
			$driver = absint( $request->get_param( 'driver' ) );
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				$driver = get_current_user_id();
			}
			if ( empty( $start ) ) { $start = gmdate( 'Y-m-01' ); }
			if ( empty( $end ) ) { $end = gmdate( 'Y-m-d' ); }
			return new WP_REST_Response( wom_get_driver_report_data( $start, $end, $driver ), 200 );
		},
	) );
} );

function wom_get_driver_report_data( $start, $end, $driver_id = 0 ) {
	$start_ts = strtotime( $start . ' 00:00:00' );
	$end_ts   = strtotime( $end . ' 23:59:59' );

	$args = array(
		'limit'      => -1,
		'orderby'    => 'date',
		'order'      => 'DESC',
		'return'     => 'ids',
		'date_created' => $start_ts && $end_ts ? wc_string_to_datetime( $start )->getTimestamp() . '...' . wc_string_to_datetime( $end )->getTimestamp() : '',
	);

	$meta_query = array();
	if ( $driver_id ) {
		$meta_query[] = array(
			'key'   => WOM_META_ASSIGNED_DRIVER,
			'value' => (string) $driver_id,
		);
	}
	if ( $meta_query ) {
		$args['meta_query'] = $meta_query;
	}

	$order_ids = wc_get_orders( $args );
	$rows = array();
	$completed = 0;
	$failed = 0;
	$durations = array();

	foreach ( $order_ids as $order_id ) {
		$driver_status = get_post_meta( $order_id, WOM_META_DRIVER_STATUS, true );
		$assigned_at   = get_post_meta( $order_id, '_wom_assigned_at', true );
		$delivered_at  = get_post_meta( $order_id, '_wom_delivered_at', true );

		if ( 'delivered' === $driver_status ) { $completed++; }
		if ( 'failed' === $driver_status ) { $failed++; }

		if ( $assigned_at && $delivered_at ) {
			$durations[] = max( 0, (int) $delivered_at - (int) $assigned_at );
		}

		$rows[] = array(
			'order_id'      => $order_id,
			'driver_id'     => (int) get_post_meta( $order_id, WOM_META_ASSIGNED_DRIVER, true ),
			'assigned_at'   => $assigned_at ? gmdate( 'Y-m-d H:i', (int) $assigned_at ) : '',
			'delivered_at'  => $delivered_at ? gmdate( 'Y-m-d H:i', (int) $delivered_at ) : '',
			'driver_status' => $driver_status ?: '',
		);
	}

	$avg_minutes = 0;
	if ( $durations ) {
		$avg_minutes = round( array_sum( $durations ) / count( $durations ) / 60 );
	}
	$completion_rate = ($completed + $failed) > 0 ? round( $completed * 100 / ($completed + $failed), 1 ) : 0;

	return array(
		'completed'       => $completed,
		'failed'          => $failed,
		'completion_rate' => $completion_rate,
		'avg_minutes'     => $avg_minutes,
		'rows'            => $rows,
	);
}

function wom_output_driver_report_csv( $start, $end, $driver ) {
	$report = wom_get_driver_report_data( $start, $end, $driver );
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=driver-report-' . gmdate( 'Ymd-His' ) . '.csv' );
	$fh = fopen( 'php://output', 'w' );
	fputcsv( $fh, array( 'order_id', 'driver_id', 'assigned_at', 'delivered_at', 'driver_status' ) );
	foreach ( $report['rows'] as $row ) {
		fputcsv( $fh, $row );
	}
	fclose( $fh );
	exit;
}
