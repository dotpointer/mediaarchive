<?php
	# 2015-11-26 12:21:50 - going live with new js version
	# 2016-06-17 12:31:00 - bugfix, missing camera, days and hours in search select boxes
	# 2016-07-17 13:13:01 - bugfix, open new window on original links
	# 2016-07-17 15:21:24 - adding pushstate and popstate handling to manage browser backing
	# 2016-08-26 15:46:07 - making it validate with jslint again
	# 2016-09-13 10:55:52 - guest mode
	# 2016-09-13 17:11:34 - database table prefixes
	# 2016-09-14 19:51:24 - absolute path to media
	# 2016-09-15 14:20:55 - bugfix, effect and viewoptions were not push stated
	# 2016-09-15 20:48:36
	# 2016-09-17 16:22:54 - table photos->media
	# 2016-09-22 22:29:11 - base 2 to base 3
	# 2016-09-25 21:42:42 - jslint
	# 2016-09-26 23:55:58 - login display, trash display
	# 2016-09-30 09:50:59 - greeting text
	# 2017-02-12 00:16:00 - trailing space removal

	require_once('functions.php');
	require_once('request.php');

	start_translations();

	$cameras = array();
	$labels = array();
	$years = array();
	$label_statistics = array();

	# are we logged in or guest mode
	if (is_logged_in(false)) {



		# get statistics
		$sql = 'SELECT COUNT(distinct id_media) AS labeled_media FROM '.DATABASE_TABLES_PREFIX.'relations_media_labels';
		$label_statistics['labeled_media'] = db_query($link, $sql);
		$label_statistics['labeled_media'] = (int)$label_statistics['labeled_media'][0]['labeled_media'];
		$sql = 'SELECT IFNULL(COUNT(a.id), 0) AS total_media FROM '.DATABASE_TABLES_PREFIX.'media AS a';
		$label_statistics['total_media'] = db_query($link, $sql);
		$label_statistics['total_media'] = (int)$label_statistics['total_media'][0]['total_media'];

		# get cameras
		$sql = 'SELECT
					c.id,
					c.make,
					c.model,
					count(*) AS images
				FROM
					'.DATABASE_TABLES_PREFIX.'cameras AS c,
					'.DATABASE_TABLES_PREFIX.'media AS p
				WHERE
					c.id=p.id_cameras
				GROUP BY
					p.id_cameras
				ORDER
					BY make, model
				';
		$cameras = db_query($link, $sql);

		# get years
		$sql =
				'SELECT
					YEAR(exposured) AS year
				FROM
					'.DATABASE_TABLES_PREFIX.'media
				GROUP BY
					YEAR(exposured)
				ORDER BY
					(exposured)
				';
		$years = db_query($link, $sql);

		# get labels
		$sql = 'SELECT
					l.id,
					l.title,
					r.amount
				FROM
					'.DATABASE_TABLES_PREFIX.'labels AS l
					LEFT JOIN (
						SELECT
							COUNT(*) AS amount, id_labels
						FROM
							'.DATABASE_TABLES_PREFIX.'relations_media_labels
						GROUP BY
							id_labels
					) AS r
					ON
						r.id_labels=l.id
				ORDER BY l.title';
		$labels = db_query($link, $sql);
	}

	# output mime header
	header('Content-Type: text/javascript');

?>/*jslint white: true, for: true, this:true, browser: true */
/*global window,$,jQuery,console,google,params */

var g = {
	cameras: <?php echo json_encode($cameras); ?>,
	effects: <?php echo json_encode(array(
		'hefe' => 'Hefe',
		'ig-1977' => '1977',
		'ig-amaro' => 'Amaro',
		'ig-brannan' => 'Brannan',
		'ig-earlybird' => 'Early Bird',
		'ig-hudson' => 'Hudson',
		'ig-inkwell' => 'Inkwell',
		'ig-kelvin' => 'Kelvin',
		'ig-lofi' => 'Lo-Fi',
		'ig-mayfair' => 'Myfair',
		'ig-nashville' => 'Nashville',
		'ig-rise' => 'Rise',
		'ig-sierra' => 'Sierra',
		'ig-sutro' => 'Sutro',
		'ig-toaster' => 'Toaster',
		'ig-walden' =>'Walden',
		'ig-valencia' => 'Valencia',
		'ig-willow' => 'Willow',
		'ig-xpro2' => 'X-PRO II',
		'noir' => 'Noir',
		"none" => t('No effect')
	)); ?>,
	find: false,
	guest_mode: <?php echo GUEST_MODE ? 'true' : 'false' ?>,
	labels: <?php echo json_encode($labels); ?>,
	label_statistics: <?php echo json_encode($label_statistics); ?>,
	logged_in: <?php echo is_logged_in(true) ? 'true' : 'false' ?>,
	locale:  "<?php echo $translations['current']['locale'] ?>",
	months:	<?php echo json_encode($months); ?>,
	msg: <?php echo json_encode(get_translation_texts()); ?>,
	viewoptions: "details",
	years: <?php echo json_encode($years); ?>
};

(function() {
	"use strict";

	jQuery.extend({
		postJSON: function (url, data, callback) {
			return jQuery.post(url, data, callback, "json");
		}
	});

	$(function() {

		// to clone an object
		g.clone_object = function(oldObject) {
			return jQuery.extend(true, {}, oldObject);
		};

		// to filter an object from properties with false values
		g.object_filter_false = function(input) {
			// var i;
			var tmp = {};

			// walk input object
			// for (i in input) {
			Object.keys(input).forEach(function (i) {
				if (input.hasOwnProperty(i)) {
					// is the value not false?
					if (input[i] !== false) {
						tmp[i] = input[i];
					}
				}
			//}
			});
			return tmp;
		};

		// to parse int, radix 10 (decimal)
		g.pi10 = function(x) {
			return parseInt(x, 10);
		};

		// to check if google maps library is ready
		g.maps_loaded = function() {
			// is the google maps library gone?
			if (typeof google !== "object" || google.maps === undefined ) {
				// then quit here
				return false;
			}

			return true;
		};

		// to make breadcrumbs
		g.print_breadcrumbs = function(path, name, up) {

			var tmppath;
			var breadcrumbs = [];
			// var i;
			var result = $("<div/>").addClass("breadcrumbs");
			var event_breadcrumb_click;

			if (path === undefined) {
				return false;
			}

			tmppath = path.split("/");

			// walk path parts
			// for (i in tmppath) {
			Object.keys(tmppath).forEach(function (i) {
				if (tmppath.hasOwnProperty(i)) {
					// does this path part have any length
					if (tmppath[i].length) {
						breadcrumbs.push(tmppath[i]);
					}
				}
			//}
			});

			// set default values
			name = name === undefined ? false : name;
			up = up === undefined ? false : up;

			// any breadcrumbs?
			//if (breadcrumbs.length) {
				// add a separator
				result.append(
					$("<span/>")
						.addClass("separator")
						.text("/")
				);
			//}

			// when clicking on a breadcrumb part
			event_breadcrumb_click = function(e) {

				// normal action - allow passthrough
				if (e.ctrlKey || e.shiftKey) {
					return true;
				}

				e.preventDefault();
				g.switch_page("folder", {
					path: $(this).attr("data-path")
				});
				return false;
			};


			// add it to the container
			result.append(
				$("<a/>")
					.attr({
						// href is for ctrl or shift click
						href: "?" + $.param(g.object_filter_false($.extend(g.clone_object(params), {
							path: "/" ,
							page: "folder"
						}))),
						// data-path is for js
						"data-path": "/"
					})
					.text(g.t("Media"))
					.click(event_breadcrumb_click)
			);

			// add a separator
			result.append(
				$("<span/>")
					.addClass("separator")
					.text("/")
			);


			// walk the breadcrumbs - the parts of the path
			//for (i in breadcrumbs) {
			Object.keys(breadcrumbs).forEach(function (i) {
				if (breadcrumbs.hasOwnProperty(i)) {

					// is this a part worth printing?
					if (breadcrumbs[i].length) {

						// add it to the container
						result.append(
							$("<a/>")
								.attr({
									// href is for ctrl or shift click
									href: "?" + $.param(g.object_filter_false($.extend(g.clone_object(params), {
										path: "/" + breadcrumbs.slice(0, g.pi10(i) + 1).join("/") + "/",
										page: "folder"
									}))),
									// data-path is for js
									"data-path": "/" + breadcrumbs.slice(0, g.pi10(i) + 1).join("/") + "/"
								})
								.text(breadcrumbs[i])
								.click(event_breadcrumb_click)
						);

						// add a separator
						result.append(
							$("<span/>")
								.addClass("separator")
								.text("/")
						);
					}
				}
			//}
			});

			// if we are not at root folder
			if (up && path !== "/") {
				if (name) {
					// add an up button
					result.append(
						$("<a/>")
							.attr({
								// href is for ctrl or shift click
								href: $.param(g.object_filter_false($.extend(g.clone_object(params), {
									path: path,
									page: "folder"
								}))),
								// data-path is for js
								"data-path":	path
							})
							.text(g.t("Up"))
							.click(function(e) {
								e.preventDefault();
								g.switch_page("folder", {
									path: $(this).attr("data-path")
								});
								return false;
							})
					);

				} else {

					result.append(
						$("<a/>")
							.attr({
								// href is for ctrl or shift click
								href: $.param(g.object_filter_false($.extend(g.clone_object(params), {
										path: "/" + breadcrumbs.slice(0, breadcrumbs.length - 1).join("/") + "/",
										page: "folder"
								}))),
								// data-path is for js
								"data-path":	"/" + breadcrumbs.slice(0, breadcrumbs.length - 1).join("/") + "/"
							})
							.text(g.t("Up"))
							.click(function(e) {
								e.preventDefault();
								g.switch_page("folder", {
									path: $(this).attr("data-path")
								});
								return false;
							})
					);
				}
			}

			result.append(
				$("<div/>")
					.addClass("clear_both")
			);
			return result;
		};

		// to translate texts
		g.t = function(s) {
			var i;
			// are the translation texts available?
			if (typeof g.msg !== "object") {
				return s;
			}

			// walk the translation texts
			for (i=0; i < g.msg.length; i+=1) {
				if (g.msg[0] !== undefined && g.msg[1] !== undefined && g.msg[i][0] === s) {
					return g.msg[i][1];
				}
			}

			return s;
		};

		g.is_logged_in = function(no_guest_mode) {

			if (g.guest_mode && !no_guest_mode) {
				return true;
			}

			return g.logged_in;
		};

		g.check_data_for_updates = function(data) {
			if (data.data.updates === null || typeof data.data.updates !== "object") {
				return false;
			}

			var current = false;

			// are there labels available
			if (data.data.updates.labels !== null && data.data.updates.labels !== undefined) {

				// replace the current label count with the new one
				g.labels = data.data.updates.labels;

				// walk id_label selections
				$("select[name=\"id_labels\"]").each(function() {
					// store current value
					current = $(this).val();

					// empty this one
					$(this).empty();

					// fill it
					$(this)
						.append(g.make_select_options_labels(current));

					if ($(this).parents("form").attr("id") === "form_findbar") {
						$(this).append(
							$("<option/>")
								.val(-1)
								.text(g.t("Unlabeled"))
								.attr("selected", (current === "-1") )
						);


					}
				});

			}

			// are there label statistics available?
			if (data.data.updates.label_statistics !== null && data.data.updates.label_statistics !== undefined) {

				g.label_statistics = data.data.updates.label_statistics;

				$(".label_statistics")
					.text(
						(g.label_statistics.total_media > 0)
						?
						((g.label_statistics.labeled_media) + " / " + g.label_statistics.total_media + "(" + Math.round( ( (g.label_statistics.labeled_media) / g.label_statistics.total_media) * 100) + "%)")
						:
						g.t("No media")
					);

			}

			return true;
		};

		// to make select options for all label items, takes selected value as argument
		g.make_select_options_labels = function(selected) {
			// var i;
			var tmp = $("<select/>").val("").text(g.t("All labels"));

			tmp.append(
				$("<option/>")
					.text(g.t("All labels"))
					.val("")
			);

			//for (i in g.labels) {
			Object.keys(g.labels).forEach(function (i) {
				if (g.labels.hasOwnProperty(i)) {

					tmp.append(
						$("<option/>")
							.text(g.labels[i].title + " (" + g.labels[i].amount + ")")
							.val(g.labels[i].id)
							.attr(
								g.pi10(g.labels[i].id) === g.pi10(selected) ? {selected: true} : {}
							)
					);

				}
			//}
			});
			return tmp.children();
		};

		// to render main interface
		g.main_render = function() {

			// add css
			if (!$("head link[src*=\"screen\"]").length) {
				$("head").append("<link rel=\"stylesheet\" href=\"include/screen.css\" type=\"text/css\" />");
			}

			$("body")
				.empty()
				.append(
					$("<main/>")
						.append(
							$("<header/>")
								.append(
									$("<div/>")
										.attr("id", "logo")
										.append(
											$("<div/>")
												.attr("id", "logo_text")
												.append(
                                                    $("<img/>")
                                                        .attr("src", "img/mediaarchive_logo_" +  g.locale + ".png")

                                                )
										)
								)
								.append(
									$("<div/>")
										.addClass("label_statistics")
										.text(

											(g.label_statistics.total_media > 0)
											?
											(g.label_statistics.labeled_media + " / " + g.label_statistics.total_media + "(" + Math.round( ( g.label_statistics.labeled_media / g.label_statistics.total_media) * 100) + "%)")
											:
											g.t("No media")
										)
								)
								.append(
									$("<ul/>")
										.addClass("menu")
										.append(
											$("<li/>")
												.append(
													$("<a/>")
														.attr("href", "#")
														.text(g.t("Labels"))
														.click(function(e) {
															e.preventDefault();
															g.switch_page("labels");
															return false;
														})
												)
										)
								)
						)
				)
				.append(
					$("<div/>")
						.addClass("content")
				)
				.append(
					$("<div/>")
						.addClass("effects")
						.append(
							$("<select/>")
								.attr("id", "effect")
								.append(
									function() {

										// var i;
										var tmp = $("<select/>");

										// walk effects
										//for (i in g.effects) {
										Object.keys(g.effects).forEach(function (i) {
											if (g.effects.hasOwnProperty(i)) {
												tmp
													.append(
														$("<option/>")
															.attr(
																params.effect === i ? {selected: true} : {}
															)
															.val(i)
															.text(g.effects[i])
													);
											}
										//}
										});

										return tmp.children();
									}()
								)
								.change(function(e) {
									e.preventDefault();

									// single item
									$(".item_display a img").removeClass().addClass("effect_" + $("#effect").val());

									if ($("#effect").val() !== "" && $("#effect").val() !== "none") {
										$(".item_display").addClass("effects_activated");
									} else {
										$(".item_display").removeClass("effects_activated");

									}

									// folders
									$(".thumbnail_small img").removeClass().addClass("effect_" + $("#effect").val());

									params.effect = $("#effect").val();

									// record this change as a new state
									g.push_state(undefined, {effect: params.effect}, true);

									return true;
								})
						)
				); // eof-effects

			if (g.is_logged_in(false)) {

				$("header")
					.append(
						$("<form/>")
							.attr({
								action: "?",
								id: "form_findbar",
								method: "get"
							})
							.append(

								$("<div/>")
									.attr("id", "findbar")
									.append(
										$("<input/>")
											.attr({
												type: "hidden",
												name: "page"
											})
											.val("find")
									)
									.append(
										$("<input/>")
											.attr({
												type: "hidden",
												name: "scending"
											})
											.val(
												"desc"
											)
									)
									.append(
										$("<input/>")
											.attr({
												type: "hidden",
												name: "findbarstate"
											})
											.val(
												""
											)
									)
									.append(
										$("<label/>")
											.text(g.t("Find"))
									)
									.append(
										$("<input/>")
											.attr({
												title: g.t("Find media matching this text"),
												type: "text",
												name: "find"
											})
											.addClass("text")
											.val(
												""
											)
									)
									.append("<br>")
									.append(
										$("<label/>")
											.text(g.t("Camera"))
									)
									.append(
										$("<select/>")
											.attr({
												title: g.t("Display media captured by this camera"),
												type: "text",
												name: "find"
											})
											.addClass("optional")
											.append(
												$("<option/>")
													.val("")
													.text(g.t("All cameras"))
											)
											// loop cameras

											.append(
												function() {
													// var i;
													var tmp = $("<select/>");

													// walk years
													//for (i in g.cameras) {
													Object.keys(g.cameras).forEach(function (i) {
														if (g.cameras.hasOwnProperty(i)) {
															tmp.append(
																$("<option/>")
																	.val(g.cameras[i].id)
																	.text(g.cameras[i].make + " " + g.cameras[i].model)
															);
														}
													//}
													});

													return tmp.children();
												}()
											)
									)
									.append("<br>")
									.append(
										$("<label/>")
											.text(g.t("Date"))
									)
									.append(
										$("<select/>")
											.attr({
												title: g.t("Display media from this year and forward."),
												type: "text",
												name: "yearfrom"
											})
											.addClass("optional")
											.append(
												$("<option/>")
													.val("")
													.text(g.t("Year"))
											)
											.append(
												function() {
													// var i;
													var tmp = $("<select/>");

													// walk years
													//for (i in g.years) {
													Object.keys(g.years).forEach(function (i) {
														if (g.years.hasOwnProperty(i)) {
															tmp.append(
																$("<option/>")
																	.val(g.years[i].year)
																	.text(g.years[i].year)
															);
														}
													//}
													});

													return tmp.children();
												}()
											)
											// loop year
									)
									.append(
										$("<select/>")
											.attr({
												title: g.t("Display media from this month and forward."),
												type: "text",
												name: "monthfrom"
											})
											.addClass("optional")
											.append(
												$("<option/>")
													.val("")
													.text(g.t("Month"))
											)
											.append(

												function() {

													// var i;
													var tmp = $("<select/>");

													// walk months
													//for (i in g.months) {
													Object.keys(g.months).forEach(function (i) {
														if (g.months.hasOwnProperty(i)) {

															tmp.append(
																$("<option/>")
																	.val(i)
																	.text(g.months[i])
															);

														}

													//}
													});

													return tmp.children();
												}()

											)
											// loop month
									)
									.append(
										$("<select/>")
											.attr({
												title: g.t("Display media from this day and forward."),
												type: "text",
												name: "dayfrom"
											})
											.addClass("optional")
											.append(
												$("<option/>")
													.val("")
													.text(g.t("Day"))
											)
											.append(
												function() {
													var i;
													var tmp = $("<select/>");

													// walk years
													for (i=0; i < 32; i = i + 1) {
														tmp.append(
															$("<option/>")
																.val(i)
																.text(i.toString().length < 2 ? "0" + i.toString() : i)
														);
													}

													return tmp.children();
												}()
											)
											// loop day
									)
									.append(
										$("<select/>")
											.attr({
												title: g.t("Display media from this hour and forward."),
												type: "text",
												name: "hourfrom"
											})
											.addClass("optional")
											.append(
												$("<option/>")
													.val("")
													.text(g.t("Hour"))
											)
											.append(
												function() {
													var i;
													var tmp = $("<select/>");

													// walk years
													for (i=0; i < 24; i = i + 1) {
														tmp.append(
															$("<option/>")
																.val(i)
																.text(i.toString().length < 2 ? "0" + i.toString() : i)
														);
													}

													return tmp.children();
												}()
											)
											// loop hour
									)
									.append(
										$("<span/>")
											.addClass("optional")
											.text(g.t("to"))
									)

									.append(
										$("<select/>")
											.attr({
												title: g.t("Display media to this year"),
												type: "text",
												name: "yearto"
											})
											.addClass("optional")
											.append(
												$("<option/>")
													.val("")
													.text(g.t("Year"))
											)
											.append(
												function() {
													// var i;
													var tmp = $("<select/>");
													// walk years
													//for (i in g.years) {
													Object.keys(g.years).forEach(function (i) {
														if (g.years.hasOwnProperty(i)) {
															tmp.append(
																$("<option/>")
																	.val(g.years[i].year)
																	.text(g.years[i].year)
															);

														}

													//}
													});

													return tmp.children();
												}()
											)
											// loop year
									)
									.append(
										$("<select/>")
											.attr({
												title: g.t("Display media to this month"),
												type: "text",
												name: "monthto"
											})
											.addClass("optional")
											.append(
												$("<option/>")
													.val("")
													.text(g.t("Month"))
											)
											.append(

												function() {
													// var i;
													var tmp = $("<select/>");
													// walk months
													//for (i in g.months) {
													Object.keys(g.months).forEach(function (i) {
														if (g.months.hasOwnProperty(i)) {
															tmp.append(
																$("<option/>")
																	.val(i)
																	.text(g.months[i])
															);
														}
													//}
													});

													return tmp.children();

												}()

											)
											// loop month
									)
									.append(
										$("<select/>")
											.attr({
												title: g.t("Display media to this day"),
												type: "text",
												name: "dayto"
											})
											.addClass("optional")
											.append(
												$("<option/>")
													.val("")
													.text(g.t("Day"))
											)
											.append(
												function() {
													var i;
													var tmp = $("<select/>");

													// walk years
													for (i=0; i < 32; i = i + 1) {
														tmp.append(
															$("<option/>")
																.val(i)
																.text(i.toString().length < 2 ? "0" + i.toString() : i)
														);
													}

													return tmp.children();
												}()
											)
											// loop day
									)
									.append(
										$("<select/>")
											.attr({
												title: g.t("Display media to this hour"),
												type: "text",
												name: "hourto"
											})
											.addClass("optional")
											.append(
												$("<option/>")
													.val("")
													.text(g.t("Hour"))
											)
											.append(
												function() {
													var i;
													var tmp = $("<select/>");

													// walk years
													for (i=0; i < 24; i = i + 1) {
														tmp.append(
															$("<option/>")
																.val(i)
																.text(i.toString().length < 2 ? "0" + i.toString() : i)
														);
													}

													return tmp.children();
												}()
											)
											// loop hour
									)
									.append("<br>")

									.append(
										$("<label/>")
											.text(g.t("Pixel dimensions"))
									)
									.append(
										$("<input/>")
											.attr({
												title: g.t("Display media with this minimum width in pixels"),
												type: "text",
												name: "widthfrom"
											})
											.addClass("text short optional")
											.val(
												"" // $request["find"]
											)
									)
									.append(
										$("<span/>")
											.addClass("optional")
											.text("X")
									)
									.append(
										$("<input/>")
											.attr({
												title: g.t("Display media with this minimum height in pixels"),
												type: "text",
												name: "heightfrom"
											})
											.addClass("text short optional")
											.val(
												"" // $request["find"]
											)
									)
									.append(
										$("<span/>")
											.addClass("optional")
											.text(g.t("to"))
									)
									.append(
										$("<input/>")
											.attr({
												title: g.t("Display media with this maximum width in pixels"),
												type: "text",
												name: "widthto"
											})
											.addClass("text short optional")
											.val(
												""
											)
									)
									.append(
										$("<span/>")
											.addClass("optional")
											.text("X")
									)
									.append(
										$("<input/>")
											.attr({
												title: g.t("Display media with this maximum height in pixels"),
												type: "text",
												name: "heightto"
											})
											.addClass("text short optional")
											.val(
												""
											)
									)
									.append("<br>")
									.append(
										$("<label/>")
											.text(g.t("Label"))
									)
									.append(
										$("<select/>")
											.attr({
												title: g.t("Display media with this label"),
												type: "text",
												name: "id_labels"
											})
											.addClass("optional")
											.append(
												g.make_select_options_labels()
											)
											.append(
												$("<option/>")
													.val(-1)
													.text(g.t("Unlabeled"))
											)
											// loop labels
									)
									.append("<br>")
									.append(
										$("<label/>")
											.text(g.t("Amount"))
									)
									.append(
										$("<select/>")
											.attr({
												title: g.t("Amount of media items to display per page"),
												type: "text",
												name: "limit"
											})
											.addClass("optional")
											.append(
												function() {
													// var i;
													var items = [25,100,250,500,1000,2500,5000];
													var opts = $("<select/>");

													//for (i in items) {
													Object.keys(items).forEach(function (i) {
														if (items.hasOwnProperty(i)) {
															opts.append(
																$("<option/>")
																.attr(
																	items[i] === 250 ? {selected: "selected"} : {}
																)
																.val(items[i])
																.text(items[i])
															);
														}
													//}
													});
													return opts.children();
												}()
											)
											// loop labels
									)
									.append("<br>")

									.append(
										$("<button/>")
											.attr({
												"id": "findbar_button_scending",
												title: "Sort the displayed items in this order."
											})
											.html(
												"&darr;"
											)
									)
									.append("<br>")
									.append(
										$("<input/>")
											.attr({
												type: "submit",
												title: g.t("Send the request")
											})
											.addClass("submit")
											.val(g.t("Find"))
									)
									.append(
										$("<button/>")
											.text(g.t("..."))
											.attr("id", "findbar_button_more")
									)
									.append(
										$("<input/>")
											.attr({
												type: "reset",
												title: g.t("Reset the form")
											})
											.addClass("reset optional")
											.val(g.t("Reset"))
									)
							)
							.append(
								$("<div/>")
									.addClass("clear_both")
							)
					)

					.append(

						$("<div/>")
							.attr("id", "switchbar")
							.append(
										g.is_logged_in(true)
										?
										$("<button/>")
											.attr({
												"id": "switchbar_button_tools",
												title: g.t("Toggle tools displayment.")
											})
											.text(
												g.t("Tools") + " " + ((params.tools_display !== null && params.tools_display) ? g.t("on") : g.t("off") )
											)
											.click(function(e) {

												e.preventDefault();

												if (!g.is_logged_in(true)) {
													return false;
												}

												params.tools_display = params.tools_display !== null ? params.tools_display : false;

												if (params.tools_display) {
													$("#form_labelling").removeClass("on");
												} else {
													$("#form_labelling").addClass("on");
												}

												params.tools_display = !params.tools_display;

												$(this).text(g.t("Tools") + " " + (params.tools_display ? g.t("on") : g.t("off")));

												return false;
											})
										:
<?php
										if (LOGIN_MODE) {
?>
										$("<button/>")
											.attr({
												"id": "switchbar_button_login",
												title: g.t("Login")
											})
											.text(
												g.t("Login")
											)
											.click(function(e) {

												e.preventDefault();

												if (g.is_logged_in(true)) {
													return false;
												}

												window.location = "?page=login";

												return false;
											})
<?php									} else { ?>
											""
<?php									} ?>

							)
					)
							.append(
								$("<div/>")
									.addClass("clear_both")
							)


					;


					$("#form_findbar input[type=\"reset\"]").click();


					$("#form_findbar").submit(function(e) {

						g.switch_page("find", {
							dayfrom:	$("#form_findbar select[name=\"dayfrom\"]").val(),
							dayto:		$("#form_findbar select[name=\"dayto\"]").val(),
							find:		$("#form_findbar input[name=\"find\"]").val(),
							heightfrom:	$("#form_findbar input[name=\"heightfrom\"]").val(),
							heightto:	$("#form_findbar input[name=\"heightto\"]").val(),
							hourfrom:	$("#form_findbar select[name=\"hourfrom\"]").val(),
							hourto:		$("#form_findbar select[name=\"hourto\"]").val(),
							id_camera:	$("#form_findbar select[name=\"id_camera\"]").val(),
							id_labels:	$("#form_findbar select[name=\"id_labels\"]").val(),
							limit:		$("#form_findbar select[name=\"limit\"]").val(),
							monthfrom:	$("#form_findbar select[name=\"monthfrom\"]").val(),
							monthto:	$("#form_findbar select[name=\"monthto\"]").val(),
							scending:	$("#form_findbar input[name=\"scending\"]").val(),
							widthfrom:	$("#form_findbar input[name=\"widthfrom\"]").val(),
							widthto:	$("#form_findbar input[name=\"widthto\"]").val(),
							yearfrom:	$("#form_findbar select[name=\"yearfrom\"]").val(),
							yearto:		$("#form_findbar select[name=\"yearto\"]").val()
						});

						e.preventDefault();
						return false;
					});

					// when typing in find field
					$("#findbar input[name=\"find\"]").keypress(function( event ) {
						if ( event.which === 13 ) {
							event.preventDefault();
							$("#findbar input[type=\"submit\"]").click();
							return false;
						}
					});

					$("#findbar input[type=\"reset\"]").click(function(e) {
						$("form #findbar input[type=\"text\"]").val("");
						$("form #findbar select").val("");
						$("form #findbar select[name=\"limit\"]").val(250);
						e.preventDefault();
						return false;
					});

					$("#findbar_button_scending").click(function() {
						if ($("#findbar input[name=\"scending\"]").val() === "asc") {
							$(this).html("&darr;");
							$("#findbar input[name=\"scending\"]").val("desc");
						} else {
							$(this).html("&uarr;");
							$("#findbar input[name=\"scending\"]").val("asc");
						}
						return false;

					});

					// to toggle advanced search
					$("#findbar_button_more").click(function(e) {
						$("#findbar").toggleClass("full");

						if ($("#findbar").hasClass("full")) {
							$("#findbarstate").val("full");
						} else {
							$("#findbarstate").val("");
						}
						e.preventDefault();
						return false;
					});


					$("#logo").click(function(e) {
						g.switch_page("folder", {path: "/"});
						e.preventDefault();
						return false;

					});


					g.switch_page(params.page, params);

			} // if is_logged_in
		};

		// to reload page
		g.reload_page = function() {
			return g.switch_page(g.last_switch_page.page, g.last_switch_page.options);
		};

		// to push a history state
		g.push_state = function(page, options, merge_options_with_last_page_options) {

			// if page was not defined, then take the the last page we switched to
			page = page !== undefined ? page : g.last_switch_page.page;

			if (options === undefined ) {

				options = g.last_switch_page.options;
			} else {
				// should we merge the new options with the existing ones?
				if (merge_options_with_last_page_options === true) {
					options = $.extend(g.last_switch_page.options, options);
				// overwrite the options
				}
			}

			g.last_switch_page.page = page;
			g.last_switch_page.options = options;

			// tell history where we are going
			window.history.pushState(
				// object with variables
				g.object_filter_false(g.last_switch_page),
				// unused
				"",
				// the url written in the address bar
				"?" + $.param(
					g.object_filter_false($.extend({"page": page}, options))
				)
			);


		};

		g.switch_page = function(page, options, pushstate) {

			pushstate = (pushstate === undefined);

			params.page = page;

			g.last_switch_page = {
				"page": page,
				"options": options
			};

			// should we push a state? this must be avoided when requesting backing through pop-state
			if (pushstate) {
				// tell history where we are going
				/* window.history.pushState(
					// object with variables
					g.object_filter_false(g.last_switch_page),
					// unused
					"",
					// the url written in the address bar
					"?" + $.param(
						g.object_filter_false($.extend({"page": page}, options))
					)
				);
				*/

				g.push_state(page, options);

			}

			switch (page) {
				case "find":

					if (!g.is_logged_in(false)) {
						break;
					}

					g.find = options;

					options.start = options.start !== undefined ? g.pi10(options.start) : 0;
					options.limit = g.pi10(options.limit);
					options.format = "json";
					options.page = "find";

					$.postJSON(
						"?",
						options,
						function(data) {
							var event_item_click;
							var event_back_click;
							var event_forward_click;
							// var i;
							// var j;
							// var k;
							// var l;
							var labels;

							g.last_data = data;

							event_back_click = function(e) {
								e.preventDefault();
								if (g.find.start > 0) {
									g.find.start = g.find.start - g.find.limit;
								}
								g.switch_page("find", g.find);

								return false;
							};

							event_forward_click = function(e) {
								e.preventDefault();
								g.find.start = g.find.start + g.find.limit;
								g.switch_page("find", g.find);
								return false;
							};

							g.check_data_for_updates(data);

							$(".content")
								.empty()
								.addClass( (params.viewoptions !== null && params.viewoptions !== undefined) ? params.viewoptions : "details")
								.append(
									$("<div/>")
										.addClass("viewoptions")
										.append(
											$("<select/>")
												.attr("id", "viewoptions")
												.append(
													$("<option/>")
														.val("details")
														.text(g.t("Details"))
														.attr(params.viewoptions === "details" ? {selected: true} : {})
												)
												.append(
													$("<option/>")
														.val("simple")
														.text(g.t("Only images"))
														.attr(params.viewoptions === "simple" ? {selected: true} : {})
												)
												.change(function(e) {

													// 1 of 3

													e.preventDefault();

													switch ($("#viewoptions").val()) {
														case "details":
															$(".content")
																.removeClass("simple")
																.addClass("details");

															break;
														case "simple":
															$(".content")
																.removeClass("details")
																.addClass("simple");
															break;
													}

													params.viewoptions = $("#viewoptions").val();

													// record this change as a new state
													g.push_state(undefined, {viewoptions: params.viewoptions}, true);

													return true;
												})

										)
								)
								.append(
									$("<div/>")
										.addClass("browser")
										.append(
											$("<a/>")
												.attr("href", "#")
												.html("&lt;&lt;")
												.click(event_back_click)
										)
										.append(
											$("<span/>")
												.addClass("from")
												.text(g.find.start)
										)
										.append(
											"-"
										)
										.append(
											$("<span/>")
												.addClass("to")
												.text(g.pi10(g.find.start) + g.pi10(g.find.limit))
										)
										.append(
											$("<a/>")
												.attr("href", "#")
												.html("&gt;&gt;")
												.click(event_forward_click)
										)

								);

								$(".content")
									.append(

										$("<div/>")
											.addClass("clear_both")
									);

								// when clicking on an item
								event_item_click = function(e) {

										if (e.ctrlKey && e.shiftKey) {
											e.preventDefault();
											$(this).parents(".item").find(".checkbox input").click();
											return false;
										}

										// normal action - allow passthrough
										if (e.ctrlKey || e.shiftKey) {
											return true;
										}

										e.preventDefault();
										g.switch_page("item", {
											path: $(this).attr("data-path"),
											id_media: $(this).attr("id_media")
										});

										return false;
								};

								// walk folders
								//for (i in data.data) {
								Object.keys(data.data).forEach(function (i) {
									if (data.data.hasOwnProperty(i)) {

										// print breadcrumbs for this folder
										$(".content>.clear_both").before(
											g.print_breadcrumbs(data.data[i].path, false, false)
										);

										// add folder container
										$(".content>.clear_both").before(
											$("<div/>")
												.addClass("items")
												.append(
													$("<div/>")
														.addClass("clear_both")
												)
										);

										if (data.data[i].items !== undefined) {

											// walk items in this folder
											//for (j in data.data[i].items) {
											Object.keys(data.data[i].items).forEach(function (j) {
												if (data.data[i].items.hasOwnProperty(j)) {

													// label container
													labels = $("<div/>").addClass("labels");

													// walk labels in this item
													//for (k in data.data[i].items[j].id_labels) {
													Object.keys(data.data[i].items[j].id_labels).forEach(function (k) {
														if (data.data[i].items[j].id_labels.hasOwnProperty(k)) {

															// walk labels
															// for (l in g.labels) {
															Object.keys(g.labels).forEach(function (l) {
																if (g.labels.hasOwnProperty(l)) {

																	// does this label id match the item label id
																	if (g.pi10(g.labels[l].id) === g.pi10(data.data[i].items[j].id_labels[k])) {
																		// then add this to the list of labels
																		labels.append(
																			$("<div/>")
																				.addClass("label")
																				.attr({
																					"data-id_labels": g.labels[l].id,
																					title: g.labels[l].title
																				})
																				.text(g.labels[l].title.substring(0,3))
																		);
																	}

																}
															//} // eof-walk labels
															});

														}
													//} // eof-walk labels in this item
													});

													$(".content .items:last .clear_both").before(
														$("<div/>")
															.addClass("item")
															.append(
																$("<a/>")
																	.addClass("thumbnail_small")
																	.attr({
																		// href is for ctrl or shift click
																		href: "?" + $.param(g.object_filter_false($.extend(g.clone_object(params), {
																			id_media: data.data[i].items[j].id,
																			path: data.data[i].items[j].path,
																			page: "item"
																		}))),
																		// data-path is for js
																		"data-path": data.data[i].items[j].path,
																		id_media: data.data[i].items[j].id

																	})
																	.append(
																		$("<img/>")
																			.attr("src", "?page=get&version=small&id=" + data.data[i].items[j].id)
																			.addClass(params.effect ? "effect_" + params.effect : "")
																	)
																	.click(
																	event_item_click
																	)
															)
															.append(
																(data.data[i].items[j].latitude !== undefined && data.data[i].items[j].longitude !== undefined && g.pi10(data.data[i].items[j].latitude) !== -1 && g.pi10(data.data[i].items[j].longitude) !==-1)
																?
																$("<div/>")
																	.addClass("gps")
																	.text("GPS")
																:
																""

															)
															.append(
																labels
															)
													);
												}
											//}
											});
										}

									}

								//} // foreach
								});
						}
					); // eof find

					break;
				case "item": // display a single item

					if (!g.is_logged_in(false)) {
						break;
					}

					// get item data
					$.postJSON(
						"?",
						{
							page: "item",
							id_media: options.id_media,
							format: "json"
						},
						function(data) {

							var event_a_label_relation_remove;
							var event_a_view_original;
							var event_label_click;
							var ul_tmp_raws = $("<ul/>");
							// var i;
							// var j;


							g.last_data = data;

							params.id_prev = data.data.item.id_prev;
							params.id_next = data.data.item.id_next;

							g.check_data_for_updates(data);

							event_a_view_original = function(e) {
								e.preventDefault();
								window.open($(this).attr("href"), "_blank");
								return false;
							};

							// walk raw files and append to temporary ul
							//for (i in data.data.item.raws) {
							Object.keys(data.data.item.raws).forEach(function (i) {
								ul_tmp_raws.append(
									$("<li/>")
										.append(
											$("<a/>")
												.attr("href", "?page=get&version=raw&rawextension=" + data.data.item.raws[i].ext + "&id=" + data.data.item.id)
												.text(g.t("View ") + data.data.item.raws[i].ext + " - " + data.data.item.raws[i].size)
												.click(
													function(e) {
														e.preventDefault();
														window.open($(this).attr("href"), "_blank");
														return false;
													}
												)
										)
								);
							//}
							});


							$(".content")
								.empty()
								.append(
									g.print_breadcrumbs(data.data.item.path, data.data.item.name !== undefined ? data.data.item.name : false, true)
								)
								.append(

									$("<div/>")
										.addClass("item_display_container")
										.append(
											$("<div/>")
												.addClass("item_display")
												.addClass((params.effect && params.effect !== "none") ? "effects_activated" : "")
												.append(

													$("<ul/>")
														.append(
															$("<li/>")
																.append(
																	$("<a/>")
																		.addClass("toggle_exif")
																		.attr("href", "#")
																		.text(g.t("View EXIF"))
																)
														)

														.append(
															$("<li/>")
																.append(
																	$("<a/>")
																		.attr("href", "?page=get&version=original&id=" + data.data.item.id)
																		.text(g.t("View original") + " - " + data.data.item.size)
																		.click(event_a_view_original)
																)
														)
														.append(
															ul_tmp_raws.children()
														)
														.append(

															g.is_logged_in(true)
															?

																(// view trash?
																data.data.item.trash ?

																$("<li/>")
																	.append(
																		$("<a/>")
																			.attr("href", "#")
																			.text(g.t("Remove from trash"))
																	)
																:
																$("<li/>")
																	.append(
																		$("<a/>")
																			.attr("href", "#")
																			.text(g.t("Put to trash"))
																	)
																)
															:
															""

														)

												)
												.append(
													$("<a/>")
														.addClass("thumbnail_normal")
														.append(
															$("<img/>")
																.addClass(params.effect ? "effect_" + params.effect : "")
																.attr("src", "?page=get&version=normal&id=" + data.data.item.id)
														)
												)
												.append(

													(data.data.item.latitude !== "-1" && data.data.item.longitude !== "-1") ?
														$("<div/>")
															.attr("id", "map-canvas")
														:
														""
												)
												.append(
													$("<div/>")
														.addClass("dimensions")
														.text(data.data.item.width + "px x " + data.data.item.height + "px")
												)
												.append(
													$("<div/>")
														.addClass("vignette")
												)
										)



								);

							// are there short exif data?
							if (data.data.item.exif_short) {

								$(".item_display")
									.append(
										$("<div/>")
											.addClass("exif_short")
											.append(
												$("<table/>")
													.addClass("exif")
											)
									);

								// walk exif data
								//for (i in data.data.item.exif_short) {
								Object.keys(data.data.item.exif_short).forEach(function (i) {
									if (data.data.item.exif_short.hasOwnProperty(i)) {

										$(".exif_short table.exif")
											.append(
												$("<tr/>")
													.append(
														$("<td/>")
															.text(g.t(data.data.item.exif_short[i].title))
													)
													.append(
														$("<td/>")
															.text(g.t(data.data.item.exif_short[i].value))
													)
											);
									}
								//}
								});

							} // if-exif-short

							if (data.data.item.exif) {

								$(".item_display")
									.append(
										$("<div/>")
											.attr("id", "exif")
											.addClass("exif")
											.append(
												$("<table/>")
													.addClass("exif")
											)
									);

								// walk exif data
								//for (i in data.data.item.exif) {
								Object.keys(data.data.item.exif).forEach(function (i) {
									if (data.data.item.exif.hasOwnProperty(i)) {

										if (data.data.item.exif[i] !== null && typeof data.data.item.exif[i] === "object" && data.data.item.exif[i].title !== undefined && data.data.item.exif[i].value !== undefined) {
											$(".exif table.exif").append(
												$("<tr/>")
													.append(
														$("<td/>")
															.text(g.t(data.data.item.exif[i].title))
													)
													.append(
														$("<td/>")
															.text(g.t(data.data.item.exif[i].value))
													)
											);
										}
									}
								//}
								});
							} // if-exif-short

							$(".exif table,.exif_short table").append(
								$("<tr/>")
									.append(
										$("<td/>")
											.text(g.t("Views")+":")
									)
									.append(
										$("<td/>")
											.text(data.data.item.views)
									)
							);



							$(".content")
								.append(
									$("<div/>")
										.addClass("labels")
										.append(
											$("<div/>")
												.addClass("clear_both")
										)
								);


							event_label_click = function(e) {
								e.preventDefault();

								// reset the form
								$("#form_findbar input[type=\"reset\"]").click();

								$("#form_findbar select[name=\"id_labels\"]").val(g.pi10($(this).attr("data-id_labels")));

								$("#form_findbar").submit();

								return false;
							};


							event_a_label_relation_remove = function(e) {
								e.preventDefault();

								if (window.confirm(g.t("This will remove the label from this item, please confirm this."))) {

									$.postJSON(
										"?", {
											action: "unlabel_media",
											id_media:	g.pi10($(this).parents("div:first").attr("data-id_media")),
											id_labels:	g.pi10($(this).parents("div:first").attr("data-id_labels")),
											format: "json"
										}, function(data) {

											g.last_data = data;

											g.check_data_for_updates(data);
											g.reload_page();
										}
									);

								}

								return false;
							};

							// walk labels
							// for (i in data.data.item.id_labels) {
							Object.keys(data.data.item.id_labels).forEach(function (i) {
								if (data.data.item.id_labels.hasOwnProperty(i)) {

									//for (j in g.labels) {
									Object.keys(g.labels).forEach(function (j) {
										if (g.labels.hasOwnProperty(j)) {

											if (g.pi10(data.data.item.id_labels[i]) === g.pi10(g.labels[j].id)) {
												$(".content .labels .clear_both")
													.before(

														$("<div/>")
															.addClass("label")
															.attr({
																"data-id_labels": g.labels[j].id,
																"data-id_media": data.data.item.id
															})
															.append(g.labels[j].title + " [")
															.append(
																$("<a/>")
																	.attr("href", "#")
																	.text("X")
																	.click(event_a_label_relation_remove)
															)
															.append("]")
															.click(event_label_click)



													);
											}
										}

									//}
									});



								}
							//}
							});

							// add labelling form

							$(".content")
								.append(

									$("<form/>")
										.attr("id", "form_labelling")
										.addClass(
											(params.tools_display && g.is_logged_in(true)) ? "on" : ""
										)
										.append(


											$("<div/>")
												.addClass("labelling")
												.append(
													$("<button/>")
														.attr("name", "select_all")
														.text(g.t("Select all"))
												)
												.append(
													$("<button/>")
														.attr("name", "select_none")
														.text(g.t("Select none"))
												)
												.append(
													$("<input/>")
														.attr({
															type: "hidden",
															name: "action"
														})
														.val("label_media")
												)
												.append(
													g.t("Label") + ":"
												)
												.append(

													$("<select/>")
														.attr("name", "id_labels")
														.append(
															$("<option/>")
																.val("")
																.text("-- " + g.t("New label"))
														)
														.append(
															function() {
																// var w;
																var tmp = $("<select/>");

																//for (w in g.labels) {
																Object.keys(g.labels).forEach(function (w) {
																	if (g.labels.hasOwnProperty(w)) {

																		tmp.append(
																			$("<option/>")
																				.attr({
																					selected: (options.id_labels_selected !== undefined && g.pi10(options.id_labels_selected) === g.pi10(g.labels[w].id))
																				})
																				.text(g.labels[w].title + " (" + g.labels[w].amount + ")")
																				.val(g.labels[w].id)
																		);

																	}
																//}
																});
																return tmp.children();
															}()
														)

												)
												.append(
													$("<input/>")
														.attr({
															type: "text",
															name: "title",
															placeholder: g.t("New label title")
														})
												)
												.append(
													$("<input/>")
														.attr({
															type: "submit",
															name: "submit_label_media"
														})
														.addClass("submit")
														.val(g.t("OK"))
												)
										)

								)
								.append(
									$("<div/>")
										.addClass("clear_both")
								);

								$("#form_labelling").submit(function(e) {

									var id_media = [g.last_switch_page.options.id_media];

									e.preventDefault();

									$.postJSON(
										"?",
										{
											action: "label_media",
											id_labels: $(this).find("select[name=\"id_labels\"]").val(),
											id_media: id_media.join(","),
											title: $(this).find("input[name=\"title\"]").val()
										},
										function(data) {

											g.last_data = data;

											if (data.status === false) {
												window.alert(data.data.error);
												return false;
											}

											g.check_data_for_updates(data);

											g.last_switch_page.options.id_media_checked = [];
											g.last_switch_page.options.id_labels_selected = $(".labelling select[name=\"id_labels\"]").val() !== "" ? g.pi10($(".labelling select[name=\"id_labels\"]").val()) : 0;

											// walk items that are checked
											$(".item input:checked()").each(function() {
												// store the id of the item
												g.last_switch_page.options.id_media_checked.push(g.pi10($(this).val()));
											});

											g.reload_page();

											// window.alert("Done! " + data.data.status_insertions);
										}
									);

									return false;
								});

							$(".content")
								.append(
									$("<div/>")
										.addClass("browser")
										.append(
											$("<a/>")
												.attr("href", "#")
												.addClass("back").html("&lt;&lt;")
												.click(function(e) {
													g.back();
													e.preventDefault();
													return false;
												})
										)
										.append(
											$("<a/>")
												.attr("href", "#")
												.addClass("forward").html("&gt;&gt;")
												.click(function(e) {
													g.forward();
													e.preventDefault();
													return false;
												})
										)
								);

							if (g.maps_loaded() && data.data.item.latitude !== undefined && data.data.item.longitude !== undefined && data.data.item.latitude !== -1 && data.data.item.longitude !== -1) {

								// google.maps.event.addDomListener(window, "load", initialize);
								window.setTimeout(function () {

									if (!g.maps_loaded()) {
										return true;
									}

									var map;
									var mapOptions;
									var marker;
									var myLatlng;

									myLatlng = new google.maps.LatLng(data.data.item.latitude, data.data.item.longitude);

									mapOptions = {
										zoom: 16,
										center: myLatlng,
										mapTypeId: google.maps.MapTypeId.HYBRID //google.maps.MapTypeId.SATELLITE
									};

									map = new google.maps.Map(window.document.getElementById("map-canvas"), mapOptions);

									// without marker variable = no api keys
									marker = new google.maps.Marker({
										position: myLatlng,
										map: map,
										title: g.t("Here was the photo taken.")
									});

									// this is just done to please jslint
									if (marker !== undefined) {
										// put current marker in the object tree, for no reason just to do something for jslint
										g.marker_current = marker;
									}

								}, 100);

								$("#map-canvas").addClass("on");
								$(".item_display").addClass("item_display_map_on");

							} else {
								$("#map-canvas").removeClass("on");
								$(".item_display").removeClass("item_display_map_on");
							}

					}); // eof-postJSON-get-item-data

					break;
				case "folder": // display folders and items

					if (!g.is_logged_in(false)) {
						break;
					}

					$(".content")
						.addClass((params.viewoptions !== null && params.viewoptions !== undefined) ? params.viewoptions : "details")
						.empty()
						.append(
							$("<div/>")
								.addClass("viewoptions")
								.append(
									$("<select/>")
										.attr("id", "viewoptions")
										.append(
											$("<option/>")
												.val("details")
												.text(g.t("Details"))
												.attr(params.viewoptions === "details" ? {selected: true} : {})
										)
										.append(
											$("<option/>")
												.val("simple")
												.text(g.t("Only images"))
												.attr(params.viewoptions === "simple" ? {selected: true} : {})
										)
										.change(function(e) {

											// 2 of 3

											e.preventDefault();

											params.viewoptions = $("#viewoptions").val();

											switch ($("#viewoptions").val()) {
												case "details":

													$(".content")
														.removeClass("simple")
														.addClass("details");
													break;
												case "simple":
													$(".content")
														.removeClass("details")
														.addClass("simple");
													break;
											}

											g.push_state(undefined, {viewoptions: params.viewoptions}, true);

											return true;
										})
								)
						)
						.append(
							$("<div/>")
								.addClass("clear_both")
						);

					// make breadcrumbs with an up-link
					$(".content>.clear_both:last").before(
						g.print_breadcrumbs(options.path, false, true)
					);

					if (options.path === "/") {
						$(".content>.clear_both:last").before(
							$("<p>")
								.append(
									g.t("Welcome to the media archive, a photo gallery made by dotpointer in jQuery, JavaScript, PHP, MySQL, HTML and CSS. This site loads data using web API calls and the data is transferred using the JSON format. Photos are indexed through a home built indexer.")
								)
						);
					}

					$.postJSON(
						"?",
						{
							page: "folder",
							path: options.path,
							format: "json"
						},
						function(data) {

							// var i;
							// var k;
							// var l;
							var event_folder_click;
							var event_item_click;
							var labels;

							g.last_data = data;

							// when clicking on a folder
							event_folder_click = function(e) {

									// normal action - allow passthrough
									if (e.ctrlKey || e.shiftKey) {
										return true;
									}

									e.preventDefault();
									g.switch_page("folder", {
										path: $(this).attr("data-path")
									});



									return false;
							};

							// when clicking on an item
							event_item_click = function(e) {

									if (e.ctrlKey && e.shiftKey) {
										e.preventDefault();
										$(this).parents(".item").find(".checkbox input").click();
										return false;
									}

									// normal action - allow passthrough
									if (e.ctrlKey || e.shiftKey) {
										return true;
									}

									e.preventDefault();
									g.switch_page("item", {
										path: $(this).attr("data-path"),
										id_media: $(this).attr("id_media")

									});

									return false;
							};

							g.check_data_for_updates(data);

							// are there folders?
							if (data.data.folders !== undefined) {

								// insert an items container
								$(".content>.clear_both:last").before(
									$("<div/>")
										.addClass("items")
										.append(
											$("<div/>")
												.addClass("clear_both")
										)
								);

								// walk the folders
								// for (i in data.data.folders) {
								Object.keys(data.data.folders).forEach(function (i) {
									if (data.data.folders.hasOwnProperty(i)) {
										if (data.data.folders[i].folder !== false) {
											// WARNING, we MUST use > and last, otherwise
											// it gets maximum call stack exceeded!
											$(".content>.items:last").find(".clear_both:last").before(
											// add a folder
											// $(".content .items:last").append(
													$("<div/>")
														.addClass("folder")
														.append(
															$("<div/>")
																.addClass("image")
																.append(
																	$("<a/>")
																		.addClass("thumbnail_small")
																		.attr({
																			// href is for ctrl or shift click
																			href: "?" + $.param(g.object_filter_false($.extend(g.clone_object(params), {
																				path: options.path + data.data.folders[i].folder + "/",
																				page: "folder"
																			}))),
																			// data-path is for js
																			"data-path": options.path + data.data.folders[i].folder + "/"
																		})
																		.append(
																			$("<img/>")
																				.attr("src", "?page=get&version=small&id=" + data.data.folders[i].id_first_item)
																				.addClass(params.effect ? "effect_" + params.effect : "")
																		)
																		.click(event_folder_click)
																)
														)
														.append(
															$("<div/>")
																.addClass("name")
																.append(
																	$("<a/>")
																		.attr({
																			// href is for ctrl or shift click
																			href: "?" + $.param(g.object_filter_false($.extend(g.clone_object(params), {
																				path: options.path + data.data.folders[i].folder + "/",
																				page: "folder"
																			}))),
																			// data-path is for js
																			"data-path": options.path + data.data.folders[i].folder + "/"
																		})
																		.text(
																			data.data.folders[i].folder
																		)
																		.click(event_folder_click)
																)
														)
														.append(
															$("<div/>")
																.addClass("clear_both")
														)
												);
										}

									}
								//} // for in folders
								});
							}

							// add a container for items
							$(".content>.clear_both:last")
								.before(
									$("<div/>")
										.addClass("items")
										.append(
											$("<div/>")
												.addClass("clear_both")
										)
								);

							// walk items in this folder
							//for (i in data.data.items) {
							Object.keys(data.data.items).forEach(function (i) {
								if (data.data.items.hasOwnProperty(i)) {

									// label container
									labels = $("<div/>").addClass("labels");


									// walk labels in this item
									//for (k in data.data.items[i].id_labels) {


									if (data.data.items[i].id_labels !== undefined) {
										Object.keys(data.data.items[i].id_labels).forEach(function (k) {
											if (data.data.items[i].id_labels.hasOwnProperty(k)) {

												// walk labels
												// for (l in g.labels) {
												Object.keys(g.labels).forEach(function (l) {
													if (g.labels.hasOwnProperty(l)) {

														// does this label id match the item label id
														if (g.pi10(g.labels[l].id) === g.pi10(data.data.items[i].id_labels[k])) {
															// then add this to the list of labels
															labels.append(
																$("<div/>")
																	.addClass("label")
																	.attr({
																		"data-id_labels": g.labels[l].id,
																		title: g.labels[l].title
																	})
																	.text(g.labels[l].title.substring(0,3))
															);
														}

													}
												//} // eof-walk labels
												});

											}
										//} // eof-walk labels in this item
										});
									}

									// add an item
									// $(".content .items:last .clear_both").before(
									// _inside_ the last items container there is a .clear_both, put this item before that
									$(".content .items:last").find(".clear_both").before(
									//$(".content .items:last").append(

										$("<div/>")
											.addClass("item")
											.append(
												$("<a/>")
													.addClass("thumbnail_small")
													.attr({
														// href is for ctrl or shift click
														href: "?" + $.param(g.object_filter_false($.extend(g.clone_object(params), {
															id_media: data.data.items[i].id,
															path: options.path,
															page: "item"
														}))),
														// data-path is for js
														"data-path": options.path,
														id_media: data.data.items[i].id
													})
													.append(
														$("<img/>")
															.attr("src", "?page=get&version=small&id=" + data.data.items[i].id)
															.addClass(params.effect ? "effect_" + params.effect : "")
													)
													.click(event_item_click)
											)
											.append(

												(data.data.items[i].latitude !== -1 && data.data.items[i].longitude !==-1) ?
												$("<div/>")
													.addClass("gps")
													.text("GPS")
												:
												""
											)
											.append(
												labels
											)
											.append(
													$("<div/>")
														.addClass("checkbox")
														.append(
															$("<input/>")
																.attr({
																	checked: (options.id_media_checked !== undefined && $.inArray(g.pi10(data.data.items[i].id), options.id_media_checked) !== -1),
																	type: "checkbox",
																	name: "item_" + data.data.items[i].id
																})
																.val(data.data.items[i].id)
														)
											)
									);

								}
							//} // for in items
							});

							$(".content>.clear_both")
								.before(
									$("<div/>")
										.addClass("items")
										.append(
											$("<div/>")
												.addClass("clear_both")
										)
								);

							if (data.data.items.length) {

								$(".content>.clear_both:last")
								.before(

									$("<form/>")
										.attr("id", "form_labelling")
										.addClass(params.tools_display ? "on" : "")
										.append(


											$("<div/>")
												.addClass("labelling")
												.append(
													$("<button/>")
														.attr("name", "select_all")
														.text(g.t("Select all"))
												)
												.append(
													$("<button/>")
														.attr("name", "select_none")
														.text(g.t("Select none"))
												)
												.append(
													$("<input/>")
														.attr({
															type: "hidden",
															name: "action"
														})
														.val("label_media")
												)
												.append(
													g.t("Label") + ":"
												)
												.append(

													$("<select/>")
														.attr("name", "id_labels")
														.append(
															$("<option/>")
																.val("")
																.text("-- " + g.t("New label"))
														)
														.append(
															function() {
																// var w;
																var tmp = $("<select/>");

																// for (w in g.labels) {
																Object.keys(g.labels).forEach(function (w) {
																	if (g.labels.hasOwnProperty(w)) {

																		tmp.append(
																			$("<option/>")
																				.attr({
																					selected: (options.id_labels_selected !== undefined && g.pi10(options.id_labels_selected) === g.pi10(g.labels[w].id))
																				})
																				.text(g.labels[w].title + " (" + g.labels[w].amount + ")")
																				.val(g.labels[w].id)
																		);
																	}
																// }
																});
																return tmp.children();
															}()
														)
												)
												.append(
													$("<input/>")
														.attr({
															type: "text",
															name: "title",
															placeholder: g.t("New label title")
														})
												)
												.append(
													$("<input/>")
														.attr({
															type: "submit",
															name: "submit_unlabel_media"
														})
														.addClass("submit")
														.click(function() {

															// set action to labelling
															$(this).parents("form:first").find("input[name=\"action\"]").val("unlabel_media");

															// click through
															return true;

														})
														.val(g.t("Remove label"))
												)
												.append(
													$("<input/>")
														.attr({
															type: "submit",
															name: "submit_label_media"
														})
														.addClass("submit")
														.click(function() {

															// set action to labelling
															$(this).parents("form:first").find("input[name=\"action\"]").val("label_media");

															// click through
															return true;
														})
														.val(g.t("Add label"))
												)
										)
								);

								$("#form_labelling").submit(function(e) {

									var id_media = [];

									e.preventDefault();

									$(".item .checkbox input:checked").each(function() {
										id_media.push($(this).val());
									});

									if (!id_media.length) {
										window.alert("No images selected.");
										return false;
									}

									switch ($(this).find("input[name=\"action\"]").val()) {
										case "label_media":
											break;

										case "unlabel_media":

											if (!window.confirm(g.t("This will remove the selected label from") + " " + id_media.length + " " + g.t("items. Please confirm this."))) {
												return false;
											}

											break;
										default:
											window.alert(g.t("Unknown action, cannot continue."));
											return false;
									}

									$.postJSON(
										"?",
										{
											action: $(this).find("input[name=\"action\"]").val(),
											id_labels: $(this).find("select[name=\"id_labels\"]").val(),
											id_media: id_media.join(","),
											title: $(this).find("input[name=\"title\"]").val()
										},
										function(data) {

											g.last_data = data;

											if (data.status === false) {
												window.alert(data.data.error);
												return false;
											}

											g.check_data_for_updates(data);

											g.last_switch_page.options.id_media_checked = [];
											g.last_switch_page.options.id_labels_selected = $(".labelling select[name=\"id_labels\"]").val() !== "" ? g.pi10($(".labelling select[name=\"id_labels\"]").val()) : 0;

											// walk items that are checked
											$(".item input:checked()").each(function() {
												// store the id of the item
												g.last_switch_page.options.id_media_checked.push(g.pi10($(this).val()));
											});

											g.reload_page();

											// window.alert("Done! " + data.data.status_insertions);
										}
									);

									return false;
								});


								$(".checkbox input").bind("keydown", "left",function (){
									$(this).parents(".item:first").prev(".item").find(".checkbox input").focus();
								});

								$(".checkbox input").bind("keydown", "right",function (){
									$(this).parents(".item:first").next(".item").find(".checkbox input").focus();
								});


								$("button[name=\"select_all\"]").click(function(e) {
									$(".item .checkbox input").attr("checked", true);
									e.preventDefault();
									return false;
								});

								$("button[name=\"select_none\"]").click(function(e) {
									$(".item .checkbox input").attr("checked", false);
									e.preventDefault();
									return false;
								});



							} // if-items


						}
					);


					break;

				case "labels": // display folders and items

					if (!g.is_logged_in(false)) {
						break;
					}

					$(".content")
						.addClass((params.viewoptions !== null && params.viewoptions !== undefined) ? params.viewoptions : "details")
						.empty()
						.append(
							$("<div/>")
								.addClass("viewoptions")
								.append(
									$("<select/>")
										.attr("id", "viewoptions")
										.append(
											$("<option/>")
												.val("details")
												.text(g.t("Details"))
												.attr(params.viewoptions === "details" ? {selected: true} : {})
										)
										.append(
											$("<option/>")
												.val("simple")
												.text(g.t("Only images"))
												.attr(params.viewoptions === "simple" ? {selected: true} : {})
										)
										.change(function(e) {

											// 3 of 3

											e.preventDefault();

											params.viewoptions = $("#viewoptions").val();

											switch ($("#viewoptions").val()) {
												case "details":

													$(".content")
														.removeClass("simple")
														.addClass("details");
													break;
												case "simple":
													$(".content")
														.removeClass("details")
														.addClass("simple");


													break;
											}

											g.push_state(undefined, {viewoptions: params.viewoptions}, true);

											return true;
										})
								)
						)
						.append(
							$("<div/>")
								.addClass("clear_both")
						);


					$.postJSON(
						"?",
						{
							page: "labels",
							format: "json"
						},
						function(data) {

							// var i;
							// var k;
							//	var l;
							// var labels;
							var event_folder_click;
							// var event_item_click;

							g.last_data = data;

							// when clicking on a folder
							event_folder_click = function(e) {

									// normal action - allow passthrough
									if (e.ctrlKey || e.shiftKey) {
										return true;
									}

									e.preventDefault();

									// reset the form
									$("#form_findbar input[type=\"reset\"]").click();

									$("#form_findbar select[name=\"id_labels\"]").val(g.pi10($(this).attr("data-id_labels")));

									$("#form_findbar").submit();

									return false;
							};

							g.check_data_for_updates(data);

							// are there labels?
							if (data.data.labels !== undefined) {

								// insert an items container
								$(".content>.clear_both").before(
									$("<div/>")
										.addClass("items")
								);

								// walk the labels
								// for (i in data.data.labels) {
								Object.keys(data.data.labels).forEach(function (i) {
									if (data.data.labels.hasOwnProperty(i)) {
										if (data.data.labels[i].title !== false) {

											// add a folder
											$(".content .items:last")
												.append(
													$("<div/>")
														.addClass("folder")
														.append(
															$("<div/>")
																.addClass("image")
																.append(
																	$("<a/>")
																		.addClass("thumbnail_small")
																		.attr({
																			// href is for ctrl or shift click
																			href: "?" + $.param(g.object_filter_false($.extend(g.clone_object(params), {
																				path: "", // options.path + data.data.labels[i].folder + "/",
																				page: "folder"
																			}))),
																			// data-path is for js
																			"data-id_labels": data.data.labels[i].id
																		})
																		.append(
																			$("<img/>")
																				.attr("src", "?page=get&version=small&id=" + data.data.labels[i].id_first_item)
																				.addClass(params.effect ? "effect_" + params.effect : "")
																		)
																		.click(event_folder_click)
																)
														)
														.append(
															$("<div/>")
																.addClass("name")
																.append(
																	$("<a/>")
																		.attr({
																			// href is for ctrl or shift click
																			href: "?" + $.param(g.object_filter_false($.extend(g.clone_object(params), {
																				path: "", // options.path + data.data.folders[i].folder + "/",
																				page: "folder"
																			}))),
																			// data-path is for js
																			"data-id_labels": data.data.labels[i].id
																		})
																		.text(
																			data.data.labels[i].title + " - " + data.data.labels[i].amount + " " + g.t("items")
																		)
																		.click(event_folder_click)
																)
														)
														.append(
															$("<div/>")
																.addClass("clear_both")
														)
												);
										}
									}
								// } // for in folders
								});
							}

							// add a container for media
							$(".content>.clear_both").before(
								$("<div/>")
									.addClass("items")
									.append(
										$("<div/>")
											.addClass("clear_both")
									)
							);

							$(".content>.clear_both").before(
								$("<div/>")
									.addClass("items")
									.append(
										$("<div/>")
											.addClass("clear_both")
									)
							);


						}
					);

					break;
			}

		};

		g.ctrlleft = function() {

			// so what page are we in?
			switch (params.page) {
				case "find":
					break;
				case "item":
					if ($("#effect option:selected").prev().length === 0) {
						return true;
					}

					$("#effect").val($("#effect option:selected").prev().val()).change();
					break;

			}
			return true;
		};


		g.ctrlright = function() {

			// so what page are we in?
			switch (params.page) {
				case "find":
					break;
				case "item":
					if ($("#effect option:selected").next().length === 0) {
						return true;
					}
					$("#effect").val($("#effect option:selected").next().val()).change();
					break;
			}
			return true;
		};

		g.back = function() {

			// so what page are we in?
			switch (params.page) {
				case "find":
					window.location = $("a.back").attr("href");

					break;
				case "item":
					if (params.id_prev !== undefined && params.id_prev > 0) {

						g.switch_page("item", {
							path: false,
							id_media: params.id_prev
						});
					}
			break;

			}
			return true;
		};

		g.forward = function() {
			// so what page are we in?
			switch (params.page) {
				case "find":
					window.location = $("a.forward").attr("href");
					break;
				case "item":
					if (params.id_next !== undefined && params.id_next > 0) {
						g.switch_page("item", {
							path: false,
							id_media: params.id_next
						});
					}
					break;
			}
			return true;
		};

		g.pi10 = function (x) {
			return parseInt(x, 10);
		};

		// when clicking back button
		$(".browser .back").click(function() {
			g.back();
			return false;
		});

		// when clicking forward button
		$(".browser .forward").click(function() {
			g.forward();
			return false;
		});

		$(window.document).bind("keydown", "left",function (){
			g.back();
			return false;
		});

		$(window.document).bind("keydown", "right",function (){
			g.forward();
			return false;
		});

		$(window.document).bind("keydown", "ctrl+left",function (){
			g.ctrlleft();
			return false;
		});

		$(window.document).bind("keydown", "ctrl+right",function (){
			g.ctrlright();
			return false;
		});

		// when clicking on folders
		$( window.document ).on( "click", ".items .folder", function(event) {
			if (event.target.nodeName === "DIV") {
				window.location = $(this).find("a:first").attr("href");
				// calling a-click here does not work
			}
		});

		// when going back in the browser
		$(window).on("popstate", function(e) {

			// if the state event is not filled
			if (e.originalEvent.state === null) {
				// render default
				g.switch_page("folder", {path: "/"});
			// or if the state event is filled
			} else {
				// render the requested page
				g.switch_page(
					e.originalEvent.state.page,
					e.originalEvent.state.options,
					false
				);
			}
		});

		$("a.toggle_exif").click(function() {
			if ($("#exif").is(":visible")) {
				$("#exif").hide();
			} else {
				$("#exif").show();
			}
		});

		if (g.is_logged_in(false)) {
			g.main_render();
		}
	});
}());
