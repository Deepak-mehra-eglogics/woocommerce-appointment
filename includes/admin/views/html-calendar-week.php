<div class="wrap woocommerce">
	<h2><?php esc_html_e( 'Calendar', 'woocommerce-appointments' ); ?> <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wc_appointment&page=add_appointment' ) ); ?>" class="add-new-h2"><?php esc_html_e( 'Add New Appointment', 'woocommerce-appointments' ); ?></a></h2>

	<form method="get" id="mainform" enctype="multipart/form-data" class="wc_appointments_calendar_form week_view">
		<input type="hidden" name="post_type" value="wc_appointment" />
		<input type="hidden" name="page" value="appointment_calendar" />
		<input type="hidden" name="view" value="<?php esc_attr_e( $view ); ?>" />
		<input type="hidden" name="tab" value="calendar" />
		<?php
		wc_enqueue_js( "
			// -------------------------------------
			// Calendar filters
			// -------------------------------------
			$( '.tablenav select, .tablenav input' ).on( 'change', function() {
				$( '#mainform' ).submit();
			});

			// -------------------------------------
			// Calendar week picker
			// -------------------------------------
			$( '.calendar_day' ).datepicker({
				dateFormat: 'yy-mm-dd',
				numberOfMonths: 1,
				showOtherMonths: true,
				changeMonth: true,
				showButtonPanel: true,
				minDate: null
			}).attr('size', $( '.calendar_day' ).val().length );

			// -------------------------------------
			// Display current time on calendar
			// -------------------------------------
			var current_date = $( '.body_wrapper .current' );
			var d = new Date();
			var calendar_h = $( '.hours' ).height();

			if ( current_date.length ) {
				var current_time = d.getHours() * 60 + d.getMinutes();
				var current_time_locale = d.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'}).toLowerCase();
				var indicator_top = Math.round( calendar_h / ( 60 * 24 ) * current_time );
				current_date.append( '<div class=\"indicator tips\" title=\"'+ current_time_locale +'\"></div>' );
				$( '.indicator' ).css( {top: indicator_top} );
				$( '.indicator' ).tipTip();

				$( 'html, body' ).animate({
					scrollTop: $( '.indicator' ).offset().top - 300
				}, 'slow' );
			}

			setInterval( set_indicator, 60000 );

			function set_indicator() {
				var dt = new Date();
				var current_time = dt.getHours() * 60 + dt.getMinutes();
				var current_time_locale_updated = dt.toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'}).toLowerCase();
				var indicator_top = Math.round( calendar_h / ( 60 * 24 ) * current_time );
				$( '.indicator' ).css( {top: indicator_top} );
				$( '.indicator' ).attr( 'title', current_time_locale_updated );
				$( '.indicator' ).tipTip();
			}

			// -------------------------------------
			// Fixed header on scroll.
			// -------------------------------------
			$( window ).on( 'load resize scroll', function() {
				var el              = $( '.calendar_presentation' ),
				    floatingHeader  = $( '.calendar_header' ),
					floatingBody    = $( '.calendar_body' ),
					offset          = el.offset(),
					scrollTop       = $( window ).scrollTop(),
					windowWidth     = $( window ).width(),
					adminBarHeight  = ( windowWidth > 600 ? $( '#wpadminbar' ).outerHeight() : 0 ),
					adminBarHeight  = $( 'body' ).hasClass( 'has-woocommerce-navigation' ) ? 0 : adminBarHeight,
					wcBarHeight     = ( $( '.woocommerce-layout__header' ) ? $( '.woocommerce-layout__header' ).outerHeight() : 0 ),
					scrollTopOffset = ( scrollTop + adminBarHeight + wcBarHeight );

					//console.log( scrollTopOffset + ' > ' + offset.top );

					if ((scrollTopOffset > offset.top) && (scrollTopOffset < offset.top + el.height())) {
						fixed_header();
					} else {
						floatingHeader.prop( 'style', false );
						floatingBody.prop( 'style', false );
					}
			});

			function fixed_header() {
				var floatingHeader   = $( '.calendar_header' ),
					floatingBody     = $( '.calendar_body' ),
				    windowWidth      = $( window ).width(),
					adminBarHeight   = ( windowWidth > 600 ? $( '#wpadminbar' ).outerHeight() : 0 ),
					adminBarHeight  = $( 'body' ).hasClass( 'has-woocommerce-navigation' ) ? 0 : adminBarHeight,
					wcBarHeight      = ( $( '.woocommerce-layout__header' ) ? $( '.woocommerce-layout__header' ).outerHeight() : 0 ),
					scrollTopOffset  = ( adminBarHeight + wcBarHeight );
					contentWrapWidth = $( '.calendar_presentation' ).width(),
					headerHeight     = floatingHeader.outerHeight();

				floatingHeader.css( {
					'border-bottom' : '1px solid #ddd',
					'position'      : 'fixed',
					'top'           : scrollTopOffset,
					'width'         : contentWrapWidth,
					'z-index'       : '11'
				} );
				floatingBody.css( {
					'margin-top': headerHeight
				} );
			}

			// -------------------------------------
			// Scroll to clicked hours label
			// -------------------------------------
			$( '.hours label' ).on( 'click', function(){
				var e = $(this);
				$('html, body').animate({
					scrollTop: e.position().top
				}, 300);
			});

			// -------------------------------------
			// Overlapping events algorythm.
			// -------------------------------------
			$('.events:not(.allday)').each( function( index, el ) {
				var by_time_events = $(el).find('.event_card');
				set_overlapping_width( by_time_events, el );
			});

			$('.events.allday').each( function( index, el ) {
				var all_day_events = $(el).find('.event_card');
				set_overlapping_width( all_day_events, el );
			});

			function set_overlapping_width( events = [], el ) {
				// Map overlapping events.
				var eventArray = jQuery.map(events, function (element, index) {
		            var event  = $(element);
					var id     = event.data('id');
					var start  = event.data('start');
					var end    = event.data('end') - 1;
		            var complexEvent = {
		                'id'   : id,
						'start': start,
						'end'  : end
		            };
		            return complexEvent;
		        }).sort(function (a, b) {
		            return a.start - b.start;
		        });

				// Get overlapping events
				var results = []; // list of all events
				var index = []; // array of overlapped events
				var skip = []; // array of overlapped events to skip
			    for (var i = 0, l = eventArray.length; i < l; i++) {
			        var oEvent    = eventArray[i];
			        var nOverlaps = 0;
					var xOverlaps = 0;
			        for (var j = 0; j < l; j++) {
			            var oCompareEvent = eventArray[j];
						if ( (oEvent.start <= oCompareEvent.end) && (oEvent.end >= oCompareEvent.start) ) {
							nOverlaps++;
							index.push( oCompareEvent.id );
							if ( (oEvent.start === oCompareEvent.end) || (oEvent.end === oCompareEvent.start) ) {
								xOverlaps++;
								skip.push( oCompareEvent.id );
				            }
			            }

			        }

					// Skip events that have all overlaps
					// with same start/end times.
					if ((nOverlaps-1) === xOverlaps && 1 < nOverlaps) {
						continue;
					}

					// Modify overlapped events.
			        if (1 < nOverlaps) {
						var event_id        = oEvent.id;
						var event_count     = nOverlaps;
						var event_index     = index.filter(i => i === event_id).length;
						var event_new_index = event_index - 1; // reduce by one to skip first event in index.

						var event           = $(el).find('.event_card[data-id='+event_id+']');
						var event_width     = event.width();
						var event_new_width = Math.floor(((100 / event_count) * 10) / 10);
						var event_left      = event.position().left;
						var event_new_left  = Math.abs(event_left + (event_new_width * event_new_index));

						event.css({
					        'width': event_new_width + '%',
					        'left' : event_new_left + '%'
					    });

						/*
						results.push({
			                id         : event_id,
			                eventCount : event_count,
							eventIndex : event_index,
							eventWidth : event_width,
							eventNWidth: event_new_width,
							eventLeft  : event_left,
							eventNLeft : event_new_left
			            });
						*/
			        }

			    }

		        //console.log(results);
			}
		" );
		?>
		<div class="calendar_wrapper">
			<?php
			// Variables.
			$calendar_scale    = apply_filters( 'woocommerce_appointments_calendar_view_day_scale', 60 );
			$current_timestamp = current_time( 'timestamp' );
			?>
			<div class="calendar_presentation">
				<div class="calendar_header">
					<?php require 'html-calendar-nav.php'; ?>
					<div class="header_wrapper">
						<div class="header_labels">
							<label class="empty_label"></label>
							<label class="allday_label"><?php esc_html_e( 'All Day', 'woocommerce-appointments' ); ?></label>
						</div>
						<div class="header_days">
							<?php $index = 0; ?>
							<div class="days_wrapper">
								<?php
								$start_of_week = $week_start;
								while ( $start_of_week <= $week_end ) {
									$current_on_cal = date( 'Y-m-d', $start_of_week ) === date( 'Y-m-d', $current_timestamp );
									$current_class  = $current_on_cal ? ' current' : '';
									$past_on_cal    = date( 'Y-m-d', $start_of_week ) < date( 'Y-m-d', $current_timestamp );
									$current_class .= $past_on_cal ? ' past' : '';
									echo "<div class='header_column$current_class' data-time='" . date( 'Y-m-d', $start_of_week ) . "'>";
										echo '<div class="header_label week_label"><a href="' . admin_url( 'edit.php?post_type=wc_appointment&page=appointment_calendar&view=day&tab=calendar&calendar_day=' . date( 'Y-m-d', $start_of_week ) ) . '" title="' . date_i18n( wc_appointments_date_format(), $start_of_week ) . '">' . date_i18n( 'D', $start_of_week ) . ' <span class="daynum">' . date_i18n( 'j', $start_of_week ) . '</span></a></div>';
										echo '<div class="header_allday">';
											echo '<div class="events allday">';
												$this->list_events(
													date( 'd', $start_of_week ),
													date( 'm', $start_of_week ),
													date( 'Y', $start_of_week ),
													'all_day'
												);
											echo '</div>';
										echo '</div>';
									echo '</div>';

									$start_of_week = strtotime( '+1 day', $start_of_week );
									$index ++;
								}
								?>
							</div>
						</div>
					</div>
				</div>
				<div class="calendar_body">
					<div class="body_labels">
						<div class="hours">
							<?php
							for ( $i = 0; $i < 24; $i ++ ) :
								if ( 24 != $i ) {
									echo '<div class="hour_label"><label>' . date_i18n( wc_appointments_time_format(), strtotime( "midnight +{$i} hour" ) ) . '</label></div>';
								}
							endfor;
							?>
						</div>
					</div>
					<div class="body_days">
						<?php $index = 0; ?>
						<div class="body_wrapper">
							<?php
							$start_of_week = $week_start;
							while ( $start_of_week <= $week_end ) {
								$current_on_cal = date( 'Y-m-d', $start_of_week ) === date( 'Y-m-d', $current_timestamp );
								$current_class  = $current_on_cal ? ' current' : '';
								$past_on_cal    = date( 'Y-m-d', $start_of_week ) < date( 'Y-m-d', $current_timestamp );
								$current_class .= $past_on_cal ? ' past' : '';
								echo "<div class='body_column$current_class' data-time='" . date( 'Y-m-d', $start_of_week ) . "'>";
									echo '<div class="events bytime">';
										$this->list_events(
											date( 'd', $start_of_week ),
											date( 'm', $start_of_week ),
											date( 'Y', $start_of_week ),
											'by_time'
										);
									echo '</div>';
								echo '</div>';

								$start_of_week = strtotime( '+1 day', $start_of_week );
								$index ++;
							}
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>
