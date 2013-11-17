<?php
class Markread_Plus extends Plugin {
	private $host;
	function about() {
		return array(1.0,
			"Enhanced mark read with more control/options",
			"pcause");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ACTION_ITEM, $this);
	}
	
	function get_js() {
                return file_get_contents(dirname(__FILE__) . "/markread_plus.js");
        }

	function hook_action_item() {
		$menu = '<div dojoType="dijit.MenuItem" onclick="enh_mark_read()">Enhanced Mark Read</div>';
		return $menu;
	}

	function setmarkenhanced(){
		$active_feed_id = sprintf("%d", $_REQUEST["feed"]);
		$is_cat = ($_REQUEST["is_cat"] != "false");

		if (!$is_cat) {
			$ftitle = getFeedTitle($active_feed_id);
		} else {
			$ftitle = getCategoryTitle($active_feed_id);
		}

		print "<div class=\"dlgSec\">" . $ftitle . __(': Mark Read') ."</div>";

		print "<div class=\"dlgSecCont\">";

		print "<input dojoType=\"dijit.form.NumberTextBox\"
			style=\"font-size : 16px; width : 5em;\"
			required=\"1\" type=\"number\" name=\"enhmarkDuration\" value=\"1\"
			ata-dojo-props=\"constraints:{min:1,max:100 places:0}\">";

		print "  ".__('Unit:')." ";

		print "<select name=\"enhmarkPeriod\" dojoType=\"dijit.form.Select\">
			<option  value=\"h\">".__('Hours')."</option>
			<option selected=\"1\" value=\"d\">".__('Days')."</option>
			<option value=\"w\">".__('Weeks')."</option>
			<option value=\"m\">".__('Months')."</option>";

		

		print "</select>";

		print "</div>";

		print "<div class=\"dlgButtons\">";


		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('enhmarkDlg').execute()\">".__('Mark Read')."</button>
		<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('enhmarkDlg').hide()\">".__('Cancel')."</button>
		</div>";
	}

function catchupMarkehanced()
{
	$feed = $_REQUEST['feed_id'];
	$cat_view = ($_REQUEST['is_cat'] == "true");
	if (!$owner_uid) $owner_uid = $_SESSION['uid'];
	if (!$cat_view) {
		$ftitle = getFeedTitle($feed);
	} else {
		$ftitle = getCategoryTitle($feed);
	}
	

	switch( $_REQUEST["period"]){
		case "h":
			$per = (DB_TYPE == "pgsql")? "hour" : "HOUR";
			break;
		case "d":
			$per = (DB_TYPE == "pgsql")? "day" : "DAY";
			break;
		case "w":
			$per = (DB_TYPE == "pgsql")? "week" : "WEEK";
			break;
		case "m":
			$per = (DB_TYPE == "pgsql")? "month" : "MONTH";
			break;
	}

	$date_qpart = (DB_TYPE == "pgsql")? " date_entered < NOW() - INTERVAL '" :
			"date_entered < DATE_SUB(NOW(), INTERVAL ";
	$date_qpart .= $_REQUEST["duration"] . " " . $per;
	if(DB_TYPE == "pgsql")
		$date_qpart .= "' ";
	else
		$date_qpart .= ") ";
		

	if (is_numeric($feed)) {
		if ($cat_view) {

			if ($feed >= 0) {

				if ($feed > 0) {
					$children = getChildCategories($feed, $owner_uid);
					array_push($children, $feed);

					$children = join(",", $children);

					$cat_qpart = "cat_id IN ($children)";
				} else {
					$cat_qpart = "cat_id IS NULL";
				}

				db_query("UPDATE ttrss_user_entries
					SET unread = false, last_read = NOW() WHERE ref_id IN
						(SELECT id FROM
							(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
								AND owner_uid = $owner_uid AND unread = true AND feed_id IN
									(SELECT id FROM ttrss_feeds WHERE $cat_qpart) AND $date_qpart) as tmp)");

			} else if ($feed == -2) {

				db_query("UPDATE ttrss_user_entries
							SET unread = false,last_read = NOW() WHERE (SELECT COUNT(*)
								FROM ttrss_user_labels2, ttrss_entries WHERE article_id = ref_id AND id = ref_id AND $date_qpart) > 0
								AND unread = true AND owner_uid = $owner_uid");
			}

		} else if ($feed > 0) {

			db_query("UPDATE ttrss_user_entries
				SET unread = false, last_read = NOW() WHERE ref_id IN
					(SELECT id FROM
						(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
							AND owner_uid = $owner_uid AND unread = true AND feed_id = $feed AND $date_qpart) as tmp)");

		} else if ($feed < 0 && $feed > LABEL_BASE_INDEX) { // special, like starred

			if ($feed == -1) {
				db_query("UPDATE ttrss_user_entries
					SET unread = false, last_read = NOW() WHERE ref_id IN
						(SELECT id FROM
							(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
								AND owner_uid = $owner_uid AND unread = true AND marked = true AND $date_qpart) as tmp)");
			}

			if ($feed == -2) {
				db_query("UPDATE ttrss_user_entries
					SET unread = false, last_read = NOW() WHERE ref_id IN
						(SELECT id FROM
							(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
								AND owner_uid = $owner_uid AND unread = true AND published = true AND $date_qpart) as tmp)");
			}

			if ($feed == -3) {

				$intl = get_pref("FRESH_ARTICLE_MAX_AGE");

				if (DB_TYPE == "pgsql") {
					$match_part = "date_entered > NOW() - INTERVAL '$intl hour' ";
				} else {
					$match_part = "date_entered > DATE_SUB(NOW(),
						INTERVAL $intl HOUR) ";
				}

				db_query("UPDATE ttrss_user_entries
					SET unread = false, last_read = NOW() WHERE ref_id IN
						(SELECT id FROM
							(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
								AND owner_uid = $owner_uid AND unread = true AND $date_qpart AND $match_part) as tmp)");
			}

			if ($feed == -4) {
				db_query("UPDATE ttrss_user_entries
					SET unread = false, last_read = NOW() WHERE ref_id IN
						(SELECT id FROM
							(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
								AND owner_uid = $owner_uid AND unread = true AND $date_qpart) as tmp)");
			}

		} else if ($feed < LABEL_BASE_INDEX) { // label

			$label_id = feed_to_label_id($feed);

			db_query("UPDATE ttrss_user_entries
				SET unread = false, last_read = NOW() WHERE ref_id IN
					(SELECT id FROM
						(SELECT ttrss_entries.id FROM ttrss_entries, ttrss_user_entries, ttrss_user_labels2 WHERE ref_id = id
							AND label_id = '$label_id' AND ref_id = article_id
							AND owner_uid = $owner_uid AND unread = true AND $date_qpart) as tmp)");

		}

		ccache_update($feed, $owner_uid, $cat_view);

	} else { // tag
		db_query("UPDATE ttrss_user_entries
			SET unread = false, last_read = NOW() WHERE ref_id IN
				(SELECT id FROM
					(SELECT ttrss_entries.id FROM ttrss_entries, ttrss_user_entries, ttrss_tags WHERE ref_id = ttrss_entries.id
						AND post_int_id = int_id AND tag_name = '$feed'
						AND ttrss_user_entries.owner_uid = $owner_uid AND unread = true AND $date_qpart) as tmp)");

	}
	print json_encode(array("message" => "UPDATE_COUNTERS"));
}

function api_version() {
		return 2;
	}

}
?>
