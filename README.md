markread_plus
=============

Mark read / mark unread  with enganced timeframes and search expression matching

** This is alpha code.  I have tested on the latest version of tt-rss and with postgresql.  I have never tested on mysql but think it will work.**
**__USE AT YOUR OWN RISK__**

To use: create a directory in your plugins directory and put the files
there.  This plugin needs to be a system plugin, so add to
your config.php.

This plugin allows you to mark items read of end read based on a user specified timeframe.  In addition you can specify a search expression,
using the same search syntax as tt-rss search (I use the same function) and the mark read/mark unread will apply to only those items that match the regex.  
Once enabled the plugin will add an item to the actions menu for "Enhanced Mark Read".  Select this and you'll get a popup
that allows you to specify both a number and interval (hours/days/weeks/months) for the mark read of the selected label/category/feed and the search expression.  Please remember that the time window specified means something different on mark read than unread.  On mark read, it marks all/matching 
items older than the specified interval as read.  On mark unread, it marks all/matching items newer than the specified interval as read.
