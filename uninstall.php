<?php

global $wpdb;

$prefix = $wpdb->get_blog_prefix();

$wpdb->query("DROP TABLE ".$prefix."doublea_twitter_data;");
$wpdb->query("DROP TABLE ".$prefix."doublea_twitter_quoted_data");
$wpdb->query("DROP TABLE ".$prefix."doublea_twitter_user_lookup");

delete_option("doublea-twitter-feed-configuration");
delete_option("doublea-twitter-feed-version");