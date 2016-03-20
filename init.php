<?php
class Markread_Plus extends Plugin {
	private $host;
	function about() {
		return array(1.0,
			"Enhanced mark read with mark unread and other control/options",
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
		// feed name
		print "<div class=\"dlgSec\">" . $ftitle . __('  :<br/>');
		
		print "</div>";
		// period
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
		// they can specify a regex to match
		// temporary hack until i can figure out query
		if( $_REQUEST["feed"] != -2){
		print "<div class=\"dlgSec\">match expression (tt-rss search syntax): ";
		}else{
		print "<div hidden>match expression (tt-rss search syntax): ";
		}
		print "<input type=\"text\" name=\"enhmarkexpr\" data-dojo-type=\"dijit/form/TextBox\"
			data-dojo-props=\"trim:true, propercase:true\" id=\"enhmarkexpr\" style=\"width: 25em;\"/>";
		print "</div>";

		// usr can select to mark read or unread
		print "<br/><br/><div class=\"dlgSec\">";
		print "<select name=\"enhmarkType\" dojoType=\"dijit.form.Select\">
			<option selected=\"1\" value=\"markread\">".__('Mark Read')."</option>
			<option value=\"markunread\">".__('Mark Unread')."</option>";
		print "</select>";
		
		// ok or cancel
		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('enhmarkDlg').execute()\">".__('Execute')."</button>
		<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('enhmarkDlg').hide()\">".__('Cancel')."</button>
		</div><br/>";
			
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
	if($_REQUEST['marktype'] == "markunread"){
		$is_unread_part = " unread = false   ";
		$set_unread_part = " unread = true, last_read = NULL  " ;
	}else{
		$is_unread_part = " unread = true ";
		$set_unread_part = " unread = false, last_read = NOW() ";
	}
	if (isset($_REQUEST['markexpr']) && $_REQUEST['markexpr'] != "" ){
		list($markexpr,$markwords) = search_to_sql($_REQUEST['markexpr'],"");
		$is_unread_part .= " AND $markexpr  ";
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

	$date_qpart = ( $_REQUEST['marktype'] == "markunread" )? " date_entered > " : " date_entered <  ";
	$date_qpart .= (DB_TYPE == "pgsql")? " (NOW() - INTERVAL '" :
			"DATE_SUB(NOW(), INTERVAL ";
	$date_qpart .= $_REQUEST["duration"] . " " . $per;
	if(DB_TYPE == "pgsql")
		$date_qpart .= " ' )  ";
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
					SET $set_unread_part WHERE ref_id IN
						(SELECT id FROM
							(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
								AND owner_uid = $owner_uid AND $is_unread_part AND feed_id IN
									(SELECT id FROM ttrss_feeds WHERE $cat_qpart) AND $date_qpart) as tmp)");

			} else if ($feed == -2) {

				db_query("UPDATE ttrss_user_entries
							SET $set_unread_part WHERE (SELECT COUNT(*)
								FROM ttrss_user_labels2, ttrss_entries WHERE article_id = ref_id AND id = ref_id AND $date_qpart  ) > 0
								AND $is_unread_part AND owner_uid = $owner_uid");
			}

		} else if ($feed > 0) {

			db_query("UPDATE ttrss_user_entries
				SET $set_unread_part  WHERE ref_id IN
					(SELECT id FROM
						(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
							AND owner_uid = $owner_uid AND $is_unread_part AND feed_id = $feed AND $date_qpart) as tmp)");

		} else if ($feed < 0 && $feed > LABEL_BASE_INDEX) { // special, like starred

			if ($feed == -1) {
				db_query("UPDATE ttrss_user_entries
					SET $set_unread_part = NOW() WHERE ref_id IN
						(SELECT id FROM
							(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
								AND owner_uid = $owner_uid AND $is_unread_part AND marked = true AND $date_qpart) as tmp)");
			}

			if ($feed == -2) {
				db_query("UPDATE ttrss_user_entries
					SET $set_unread_part WHERE ref_id IN
						(SELECT id FROM
							(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
								AND owner_uid = $owner_uid AND $is_unread_part AND published = true AND $date_qpart) as tmp)");
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
					SET $set_unread_part WHERE ref_id IN
						(SELECT id FROM
							(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
								AND owner_uid = $owner_uid AND $is_unread_part AND $date_qpart AND $match_part) as tmp)");
			}

			if ($feed == -4) {
				db_query("UPDATE ttrss_user_entries
					SET $set_unread_part WHERE ref_id IN
						(SELECT id FROM
							(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
								AND owner_uid = $owner_uid AND $is_unread_part AND $date_qpart) as tmp)");
			}

		} else if ($feed < LABEL_BASE_INDEX) { // label

			$label_id = feed_to_label_id($feed);

			db_query("UPDATE ttrss_user_entries
				SET $set_unread_part WHERE ref_id IN
					(SELECT id FROM
						(SELECT ttrss_entries.id FROM ttrss_entries, ttrss_user_entries, ttrss_user_labels2 WHERE ref_id = id
							AND label_id = '$label_id' AND ref_id = article_id
							AND owner_uid = $owner_uid AND $is_unread_part AND $date_qpart) as tmp)");

		}

		ccache_update($feed, $owner_uid, $cat_view);

	} else { // tag
		db_query("UPDATE ttrss_user_entries
			SET $set_unread_part WHERE ref_id IN
				(SELECT id FROM
					(SELECT ttrss_entries.id FROM ttrss_entries, ttrss_user_entries, ttrss_tags WHERE ref_id = ttrss_entries.id
						AND post_int_id = int_id AND tag_name = '$feed'
						AND ttrss_user_entries.owner_uid = $owner_uid AND $is_unread_part AND $date_qpart) as tmp)");

	}
	

	ccache_update($feed_id, $owner_uid);
	print json_encode(array("message" => "UPDATE_COUNTERS"));
}

function api_version() {
		return 2;
	}

}
?>
