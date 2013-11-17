function enh_mark_read() {
	var feed = getActiveFeedId();
	var is_cat = activeFeedIsCat();
	var query = "backend.php?op=pluginhandler&plugin=markread_plus&method=setmarkenhanced"+
		"&feed="+ feed + "&is_cat=" + is_cat;

	if (dijit.byId("enhmarkDlg"))
		dijit.byId("enhmarkDlg").destroyRecursive();
	var dialog = new dijit.Dialog({
		id: "enhmarkDlg",
		title: __("Enhanced Mark Read"),
		style: "width: 400px",
		execute: function() {
			if (this.validate()) {
				var feed = getActiveFeedId();
				var is_cat = activeFeedIsCat();
				var period = document.getElementsByName('enhmarkPeriod')[0].value;
				var duration = document.getElementsByName('enhmarkDuration')[0].value;
				console.log(period + ':' + duration);
				catchup_enhmark(period,duration, feed, is_cat);
				this.hide();
			}
		},
		href: query});

	dialog.show();
}

function catchup_enhmark(period, duration, feed, is_cat)
{
	
	if (is_cat == undefined) is_cat = false;
	
	var fn = getFeedName(feed, is_cat);
	console.log('fn is ' + fn);
	var str = ""+ fn;
	var catchup_query = "&period=" + period + "&duration=" + duration + 
		"&feed_id=" + feed + "&is_cat=" + is_cat;

	console.log('query : ' + catchup_query);
	notify_progress("Loading, please wait...", true);

	new Ajax.Request("backend.php?op=pluginhandler&plugin=markread_plus&method=catchupMarkehanced",
		{
		parameters: catchup_query,
		onComplete: function(transport) {
				handle_rpc_json(transport);

				var show_next_feed = getInitParam("on_catchup_show_next_feed") == "1";

				if (show_next_feed) {
					var nuf = getNextUnreadFeed(feed, is_cat);

					if (nuf) {
						viewfeed(nuf, '', is_cat);
					}
				} else {
					if (feed == getActiveFeedId() && is_cat == activeFeedIsCat()) {
						viewCurrentFeed();
					}
				}

				notify("");
			} });

}
