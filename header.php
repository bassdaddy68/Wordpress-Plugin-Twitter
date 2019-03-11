<?php
/**
 * Plugin Name: Double A Software Twitter Feed
 * Plugin URI: https://doubleasoft.co.uk/wordpress/plugins/
 * Description: Accesses and displays Twitter's timeline
 * Version: 0.1
 * Author: Alan Hawes
 * Author URI:
 * License: GPLv2
 */

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
include_once (__DIR__."/Twitter/Twitter.php");
include_once (__DIR__ ."/DoubleATwitterFeedWidget.php");

class DoubleATwitterFeed{

    private $consumer_key="";
    private $consumer_secret="";
    private $access_token="";
    private $access_token_secret="";
    private $screen_name="";
    private $feed_count=0;
    private $feed_update=0;
    private $feed_next_update = 0;
    private $last_tweet_id = 0;
    private $image_url = "";
    private $styles = array();

    const CONFIGURATION = "doublea-twitter-feed-configuration";
    const CONFIGURATION_VERSION = "doublea-twitter-feed-version";
    const TWEET_MAIN_TABLE = "doublea_twitter_data";
    const TWEET_QUOTED_TABLE = "doublea_twitter_quoted_data";
    const TWEET_USER_LOOKUP_TABLE = "doublea_twitter_user_lookup";
    const WIDGET_CLASS = "DoubleATwitterFeedWidget";
    const VERSION = "0.3";

    /**
     * DoubleATwitterFeed constructor.
     */
    function __construct() {

        //Activation hook
        register_activation_hook(__FILE__,array($this,"Activation"));

        //Deactivation hook
        register_deactivation_hook(__FILE__,array($this,"DeActivation"));

        if(is_plugin_active("DoubleATwitterFeed/header.php")){

            //Check the version number
            $this->CheckVersion();

            //Setup menus
            add_action("admin_menu",array($this,"SetupMenus"));

            //Short code
            add_shortcode("doubleatwitterfeed",array($this, "DoShortCode"));

            //Widget setup
            add_action("widgets_init",array($this,"RegisterWidgets"));

            //Callback to store the data
            add_action("admin_init",array($this,"AdminInit"));

            //Load scripts
            add_action( "admin_enqueue_scripts", array($this,"LoadScripts"));

            //Update timeline if needed
            $this->UpdateTimeline();
        }
    }

    /**
     * Activation of plugin
     */
    function Activation(){
        //Create sql tables
        $this->CreateTables();

        //Register options
        $this->RegisterOptions();
    }

    /**
     *
     */
    function AdminInit(){
        add_action("admin_post_save_doublea_twitter_feed_configuration",array($this,"SaveConfiguration"));
    }

    /**
     *
     */
    private function CheckVersion(){
        if(get_option(self::CONFIGURATION_VERSION) != self::VERSION){

            //Add twitter user lookup table. For version 0.3 just the image url
            if(self::CONFIGURATION_VERSION < 0.3) {
                $this->CreateTables();

                update_option(self::CONFIGURATION_VERSION,self::VERSION);
            }
        }
    }

    /**
     *
     */
    function ConfigPage(){
        $this->SetupConfiguration();
    }


    /**
     *
     */
    private function CreateTables(){
        global $wpdb;
        $prefix = $wpdb->get_blog_prefix();

        //Create tweet table
        $sql = "CREATE TABLE IF NOT EXISTS ".$prefix.self::TWEET_MAIN_TABLE."(
            id VARCHAR(30) NOT NULL ,screen_name VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, text VARCHAR(140), truncated CHAR(5), source VARCHAR(150), in_reply_to_status_id_str VARCHAR(30),in_reply_to_user_id_str VARCHAR(30), in_reply_to_screen_name VARCHAR(30),geo VARCHAR(30),coordinates VARCHAR(30), place VARCHAR(30), is_quote_status CHAR(5),quoted_status_id_str VARCHAR(30),url VARCHAR(255), recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY(id)
        )";
        $wpdb->query($sql);

        //Created quoted status table
        $sql = "CREATE TABLE IF NOT EXISTS ".$prefix.self::TWEET_QUOTED_TABLE."(
        id VARCHAR(30) NOT NULL, screen_name VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, text VARCHAR(140), truncated CHAR(5), source VARCHAR(150), in_reply_to_status_id_str VARCHAR(30),in_reply_to_user_id_str VARCHAR(30), in_reply_to_screen_name VARCHAR(30),geo VARCHAR(30),coordinates VARCHAR(30), place VARCHAR(30), is_quote_status CHAR(5),quoted_status_id_str VARCHAR(30), url VARCHAR(255), recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY(id)
        )";
        $wpdb->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS ".$prefix.self::TWEET_USER_LOOKUP_TABLE."(
            id VARCHAR(30) NOT NULL ,screen_name VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, profile_image_url_https VARCHAR(255), PRIMARY KEY(id))";
        $wpdb->query($sql);

    }

    /**
     * DeActivation of plugin
     */
    function DeActivation(){
        unregister_widget(self::WIDGET_CLASS);
    }

    /**
     * Function to handle the shortcode
     */
    function DoShortCode($atts){
        $html = "";

        echo $html;
    }

    /**
     *
     */
    private function GetConfiguration(){
        $configuration = json_decode(get_option(self::CONFIGURATION),true);

        $this->consumer_key = $configuration["consumer_key"];
        $this->consumer_secret = $configuration["consumer_secret"];
        $this->access_token = $configuration["access_token"];
        $this->access_token_secret = $configuration["access_token_secret"];
        $this->screen_name = $configuration["screen_name"];
        $this->feed_count = is_numeric($configuration["feed_count"]) ? $configuration["feed_count"] : "10";
        $this->feed_update = is_numeric($configuration["feed_update"]) ? $configuration["feed_update"] : "15";
        $this->feed_next_update = is_numeric($configuration["feed_next_update"]) ? $configuration["feed_next_update"] : 0;
        $this->last_tweet_id = is_numeric($configuration["last_tweet_id"]) ? $configuration["last_tweet_id"] : 0;
        $this->styles = $configuration["styles"];

        //Get user lookup information
        global $wpdb;

        $result = $wpdb->get_results("SELECT ifnull(profile_image_url_https,'') AS profile_image_url_https FROM ".$wpdb->get_blog_prefix()."doublea_twitter_user_lookup",ARRAY_A);

        $this->image_url = $wpdb->num_rows == 0 ? "" : $result[0]["profile_image_url_https"];
    }

    /**
     * Called by GetTwitterUserTimeline
     */
    private function GetTwitterUserLookup($twitter){

        $results = json_decode($twitter->UserLookup($this->screen_name),true);

        if(array_key_exists("profile_image_url_https",$results[0])){
            $this->image_url = $results[0]["profile_image_url_https"];

            global $wpdb;

            $prefix = $wpdb->get_blog_prefix();

            $wpdb->query("DELETE FROM ".$prefix."doublea_twitter_user_lookup");

            $created_at = new DateTime($results[0]["created_at"]);

            $query = 'INSERT INTO '.$prefix.'doublea_twitter_user_lookup (id,screen_name,created_at, profile_image_url_https) VALUES("'.$results[0]["id_str"].'","'.$this->screen_name.'","'.$created_at->format("Y-m-d H:i:s").'","'.$results[0]["profile_image_url_https"].'")';

            $wpdb->query($query);
        }
    }

    /**
     *
     */
    public function GetTwitterUserTimeline(){

        global $wpdb;

        $this->GetConfiguration();

        if($this->screen_name != "" && $this->consumer_key != "") {
            $prefix = $wpdb->get_blog_prefix();

            $twitter = new \doublea\social\Twitter($this->consumer_key, $this->consumer_secret, $this->access_token, $this->access_token_secret);
            //$results = json_decode($twitter->UserTimeline($this->screen_name, $this->feed_count, $this->last_tweet_id,false),true);

            $results = json_decode($twitter->UserTimeline($this->screen_name, $this->feed_count, 1,false),true);

            //TODO refactor the assignment of values for null
            //Insert into twitter data table
            foreach ($results as $result) {
                $id = $result["id_str"];
                $created_at = new DateTime($result["created_at"]);
                $text = $result["text"];
                $truncated = $result["truncated"];

                $source = esc_html($result["source"]);
                $in_reply_to_status_id = isset($result["in_reply_to_status_id_str"]) ? $result["in_reply_to_status_id_str"] : "";
                $in_reply_to_user_id = isset($result["in_reply_to_user_id_str"]) ? $result["in_reply_to_user_id_str"] == null : "";
                $in_reply_to_screen_name = isset($result["in_reply_to_screen_name"]) ? $result["in_reply_to_screen_name"] : "";
                $geo = isset($result["geo"]) ? $result["geo"] : "";
                $coordinates = isset($result["coordinates"]) ? $result["coordinates"] : "";
                $place = isset($result["place"]) ? $result["place"] : "";
                $is_quote_status = isset($result["is_quote_status"]) ?  $result["is_quote_status"] : "";

                $quoted_status_id = "";
                if(array_key_exists("quoted_status_id_str",$result)){
                    $quoted_status_id = $result["quoted_status_id_str"];
                }

                //Get a url for the tweet
                $url = isset($result["entities"]["urls"][0]["url"]) ? $result["entities"]["urls"][0]["url"] : "";

                //Update the last tweet
                if($this->last_tweet_id < $id){
                    $this->last_tweet_id = $id;
                }

                //Check whether this is a duplicate
                $query = "SELECT id FROM ".$wpdb->get_blog_prefix().self::TWEET_MAIN_TABLE." WHERE id=".$id;

                if($wpdb->query($query) == 0) {

                    $insert_query = array(
                        "id" => $id,
                        "screen_name" => $this->screen_name,
                        "created_at" => $created_at->format("Y-m-d H:i:s"),
                        "text" => $text,
                        "truncated" => $truncated,
                        "source" => $source,
                        "in_reply_to_status_id_str" => $in_reply_to_status_id,
                        "in_reply_to_user_id_str" => $in_reply_to_user_id,
                        "in_reply_to_screen_name" => $in_reply_to_screen_name,
                        "geo" => $geo,
                        "coordinates" => $coordinates,
                        "place" => $place,
                        "is_quote_status" => $is_quote_status,
                        "quoted_status_id_str" => $quoted_status_id,
                        "url" => $url
                    );

                    $query = 'INSERT INTO '.$prefix.self::TWEET_MAIN_TABLE.'(`id`,`screen_name`,`created_at`,`text`,`truncated`,`source`,`in_reply_to_status_id_str`,`in_reply_to_user_id_str`,`in_reply_to_screen_name`,`geo`,`coordinates`,`place`,`is_quote_status`,`quoted_status_id_str`,`url`) VALUES("'.$id.'","'.$this->screen_name.'","'.$created_at->format("Y-m-d H:i:s").'","'.$text.'","'.$truncated.'","'.$source.'","'.$in_reply_to_status_id.'","'.$in_reply_to_user_id.'","'.$in_reply_to_screen_name.'","'.$geo.'","'.$coordinates.'","'.$place.'","'.$is_quote_status.'","'.$quoted_status_id.'","'.$url.'");';


                    $wpdb->query($query);

                    //Is this a retweet?
                    if($is_quote_status == true){
                        $created_at = new DateTime($result["quoted_status"]["created_at"]);
                        $text = $result["quoted_status"]["text"];
                        $truncated = $result["quoted_status"]["truncated"];
                        $source = $result["quoted_status"]["source"];
                        $in_reply_to_status_id = $result["quoted_status"]["in_reply_to_status_id_str"];
                        $in_reply_to_user_id = $result["quoted_status"]["in_reply_to_user_id_str"];
                        $in_reply_to_screen_name = $result["quoted_status"]["in_reply_to_screen_name"];
                        $geo = $result["quoted_status"]["geo"];
                        $coordinates = $result["quoted_status"]["coordinates"];
                        $place = $result["quoted_status"]["place"];
                        $is_quote_status = $result["quoted_status"]["is_quote_status"];

                        $wpdb->insert($prefix.self::TWEET_QUOTED_TABLE,
                            array(
                                "id" => $quoted_status_id,
                                "screen_name" => $this->screen_name,
                                "created_at" => $created_at->format("Y-m-d H:i:s"),
                                "text" => $text,
                                "truncated" => $truncated,
                                "source" => $source,
                                "in_reply_to_status_id_str" => $in_reply_to_status_id,
                                "in_reply_to_user_id_str" => $in_reply_to_user_id,
                                "in_reply_to_screen_name" => $in_reply_to_screen_name,
                                "geo" => $geo,
                                "coordinates" => $coordinates,
                                "place" => $place,
                                "is_quote_status" => $is_quote_status,
                                "quoted_status_id_str" => $quoted_status_id
                            ),
                            array(
                                "%s",
                                "%s",
                                "%s",
                                "%s",
                                "%s",
                                "%s",
                                "%s",
                                "%s",
                                "%s",
                                "%s",
                                "%s",
                                "%s",
                                "%s",
                                "%s"
                            )
                        );
                    }
                }
            }

            //Call the GetTwitterUserLookup function to update image
            $this->GetTwitterUserLookup($twitter);

            //Update the next feed time
            $this->feed_next_update = ($this->feed_update * 60) + time();
            $this->SetConfiguration();
        }
    }

    /**
     *
     */
    function LoadScripts(){
	    wp_enqueue_script('doublea-twitter-admin-script',plugin_dir_url(__FILE__)."/scripts/dblatw_admin_js.js?ver=1.1",array("jquery"));
    }

    /**
     * Registers any options needed
     */
    function RegisterOptions(){
        add_option(self::CONFIGURATION_VERSION,self::VERSION);
        if(get_option(self::CONFIGURATION) == false){
            $options = array(
                "consumer_key" => "",
                "consumer_secret" => "",
                "access_token" => "",
                "access_token_secret" => "",
                "screen_name" => "",
                "feed_count" => "",
                "feed_update" => "",
                "feed_next_update" => "0",
                "last_tweet_id" => "1"
            );

            add_option(self::CONFIGURATION,json_encode($options));
        }
    }

    /**
     *
     */
    public function RegisterWidgets(){
        register_widget(self::WIDGET_CLASS);
    }


    /**
     * Saves the config
     */
    private function SetConfiguration(){

        $options = array();

        $options["consumer_key"] = $this->consumer_key;
        $options["consumer_secret"] = $this->consumer_secret;
        $options["access_token"] = $this->access_token;
        $options["access_token_secret"] = $this->access_token_secret;
        $options["screen_name"] = $this->screen_name;
        $options["feed_count"] = $this->feed_count;
        $options["feed_update"] = $this->feed_update;
        $options["feed_next_update"] = $this->feed_next_update;
        $options["last_tweet_id"] = $this->last_tweet_id;
        $options["styles"] = $this->styles;

        update_option(self::CONFIGURATION,json_encode($options));
    }


    /**
     *
     */
    function SetupConfiguration(){

        if(isset($_GET["saved"])){
            ?>
            <div id='message' class='updated fade'><p><strong><?php  echo __("Settings Saved");?></strong></p></div>
            <?php
        }

        $this->GetConfiguration();

        $date = $this->feed_next_update == 0 ? date("Y-M-d H:i:s") : date("Y-M-d H:i:s",$this->feed_next_update);
        $date_time = new DateTime($date);
        $date_time_str = $date_time->format("d-M-Y H:i:s");
        ?>
        <h1>DoubleA Software - Twitter Feed - version <?php echo self::VERSION;?></h1>
        <h2 class="nav-tab-wrapper wp-clearfix">
            <a class="nav-tab nav-tab-active" target-div="main-configuration" href="#"><?php echo __("Main configuration");?></a>
            <a class="nav-tab" target-div="styling-configuration" href="#"><?php echo __("Styling");?></a>
            <a class="nav-tab" target-div="status-configuration" href="#"><?php echo __("Status");?></a>
        </h2>
        <form id="form-doublea-config" method="post" action="<?php echo admin_url("admin-post.php");?>">
            <input type="hidden" id="date_time" name="date_time" value="<?php echo $date_time_str;?>" />
            <?php wp_nonce_field("date__and__time_".$date_time_str);?>
            <input type="hidden" id="original_screen_name" name="original_screen_name" value="<?php echo $this->screen_name;?>" />
            <input type="hidden" name="action" value="save_doublea_twitter_feed_configuration" />
            <input type="hidden" id="button_clicked" name="button_clicked" value="false" />
            <input type="hidden" name="last_tweet_id" value="<?php $this->last_tweet_id;?>" />
            <?php //wp_nonce_field("doublea-save-config");?>
            <fieldset>
                <div class="main-configuration">

                    <hr/>
                    <h2><?php echo __("Twitter keys, required for the plugin to access the feed");?></h2>
                    <label for="consumer_key"><?php echo __("Consumer key");?></label><br/>
                    <input type="text" name="consumer_key" id="consumer_key" maxlength="50" size="150" value="<?php echo $this->consumer_key ?>" /><br/>
                    <label for="consumer_secret"><?php echo __("Consumer secret");?></label><br/>
                    <input type="text" name="consumer_secret" id="consumer_secret" maxlength="50" size="150" value="<?php echo $this->consumer_secret ?>" /><br/>
                    <label for="access_token"><?php echo __("Access token");?></label><br/>
                    <input type="text" name="access_token" id="access_token" maxlength="50" size="150" value="<?php echo $this->access_token ?>" /><br/>
                    <label for="access_token_secret"><?php echo __("Access token secret");?></label><br/>
                    <input type="text" name="access_token_secret" id="access_token_secret" maxlength="50" size="150" value="<?php echo $this->access_token_secret ?>" />
                    <hr/>
                    <h2><?php echo __("User timeline configuration");?></h2>
                    <label for="screen_name"><?php echo __("Screen name");?></label><br/>
                    <input type="text" name="screen_name" id="screen_name" maxlength="50" size="150" value="<?php echo $this->screen_name ?>" /><br/>
                    <label for="feed_count"><?php echo __("Feed count");?></label><br/>
                    <input type="text" name="feed_count" id="feed_count" maxlength="50" size="150" value="<?php echo $this->feed_count ?>" />
                    <br/>
                    <label for="feed_update"><?php echo __("Feed update");?></label><br/>
                    <select name="feed_update" id="feed_update">
                        <option value="15"<?php if($this->feed_update==15){echo " selected";}?>>15 minutes</option>
                        <option value="60"<?php if($this->feed_update==60){echo " selected";}?>>1 hour</option>
                        <option value="720"<?php if($this->feed_update==720){echo " selected";}?>>12 hours</option>
                        <option value="1440"<?php if($this->feed_update==1440){echo " selected";}?>>1 day</option>
                    </select>
                </div>
                <div class="styling-configuration">
                    <?php
                        //local vars
                        $tweet_text_colour = isset($this->styles["tweet_text_colour"]) ? $this->styles["tweet_text_colour"] : "#000";
                        $tweet_background_colour = isset($this->styles["tweet_background_colour"]) ? $this->styles["tweet_background_colour"] : "#FFF";
                        $tweet_link_colour = isset($this->styles["tweet_link_colour"]) ? $this->styles["tweet_link_colour"] : "blue";
                        $tweet_hashtag_colour = isset($this->styles["tweet_hashtag_colour"]) ? $this->styles["tweet_hashtag_colour"] : "#ccc";
                        $retweet_background_colour = isset($this->styles["retweet_background_colour"]) ? $this->styles["retweet_background_colour"] : "#FFF";
                        $retweet_text_colour = isset($this->styles["retweet_text_colour"]) ? $this->styles["retweet_text_colour"] : "#000";

                    ?>
                    <h2><?php echo __("Tweet styles");?></h2>
                    <!--Tweet text colour -->
                    <label for="tweet_text_colour"><?php echo __("Tweet text colour")?></label><br/>
                    <input type="text" name="tweet_text_colour" id="tweet_text_colour" value="<?php echo $tweet_text_colour;?>" /><br/>
                    <!-- Tweet background colour -->
                    <label for="tweet_background_colour"><?php echo __("Tweet background colour")?></label><br/>
                    <input type="text" name="tweet_background_colour" id="tweet_background_colour" value="<?php echo $tweet_background_colour?>" /><br/>
                    <!-- Tweet link colour -->
                    <label for="tweet_link_colour"><?php echo __("Tweet link colour")?></label><br/>
                    <input type="text" name="tweet_link_colour" id="tweet_link_colour" value="<?php echo $tweet_link_colour;?>" /><br/>
                    <!-- Tweet hashtag colour -->
                    <label for="tweet_hashtag_colour"><?php echo __("Tweet hashtag colour")?></label><br/>
                    <input type="text" name="tweet_hashtag_colour" id="tweet_hashtag_colour" value="<?php echo $tweet_hashtag_colour;?>" /><br/>
                    <!-- Retweet background colour -->
                    <label for="retweet_background_colour"><?php echo __("Retweet background colour");?></label><br/>
                    <input type="text" name="retweet_background_colour" id="retweet_background_colour" value="<?php echo $retweet_background_colour;?>" /><br/>
                    <!-- Retweet text colour -->
                    <label for="retweet_text_colour"><?php echo __("Retweet text colour");?></label><br/>
                    <input type="text" name="retweet_text_colour" id="retweet_text_colour" value="<?php echo $retweet_text_colour;?>" /><br/>
                    <div class="retweet-preview">
                        <p>This is a preview for the tweet</p>
                    </div>
                </div>
                <div class="status-configuration">
                    <h2><?php echo __("Other plugin information");?></h2>
                    <label style="vertical-align: top;"><?php echo __("Current image :");?></label>
                    <?php if($this->image_url == ""){
                        echo "<b>".__("No image file available")."</b>";
                    }
                    else{?>
                        <img src="<?php echo $this->image_url;?>" />
                    <?php }
                    ?>
                    <br/>
                    <label><?php echo __("Last update id");?>: <?php echo $this->last_tweet_id;?></label><br/><hr/>
                    <label><?php echo __("Next update");?>: <?php echo $date_time->format("d-M-Y H:i:s");?></label>
                    <button type="submit"><?php echo __("Update now");?></button>
                </div>
                <br/><br/>
                <button type="submit" id="save-configuration" class="btn btn-default"><?php echo __("Save configuration");?></button>
            </fieldset>
        </form>
        <script type="text/javascript">
            jQuery(document).ready(function(){
                jQuery(".nav-tab").not(".nav-tab-active").each(function(){
                    jQuery("div." + jQuery(jQuery(this)).attr("target-div")).hide();
                });
            });

            jQuery(".nav-tab").click(function(){
                var _this_ = jQuery(jQuery(this));
                jQuery(".nav-tab").removeClass("nav-tab-active");
                _this_.addClass("nav-tab-active");

                var _target = _this_.attr("target-div");

                jQuery("a[target-div]").each(function(){
                    jQuery("div." + jQuery(jQuery(this)).attr("target-div")).hide();
                })

                jQuery("div." + _target).show();
            });

            jQuery("#screen_name").blur(function(){
                if(jQuery("#screen_name").val() != jQuery("#original_screen_name").val()){
                    var _result = confirm("You have changed the screen name.\nIf you update the configuration the data\nfor the original screen_name will be deleted\nPlease confirm that this is what you want to do.");
                    if(!_result){
                        jQuery("#screen_name").val(jQuery("#original_screen_name").val());
                    }
                }
            });
        </script>
        <?php

    }

    /**
     * Save the configuration
     */
    function SaveConfiguration(){

        if(check_admin_referer("date__and__time_".$_POST["date_time"])) {

	        $this->consumer_key        = $_POST["consumer_key"];
	        $this->consumer_secret     = $_POST["consumer_secret"];
	        $this->access_token        = $_POST["access_token"];
	        $this->access_token_secret = $_POST["access_token_secret"];
	        $this->screen_name         = $_POST["screen_name"];
	        $this->feed_count          = $_POST["feed_count"];
	        $this->feed_update         = $_POST["feed_update"];


	        //Styles
	        $this->styles = array(
		        "tweet_text_colour"         => isset( $_POST["tweet_text_colour"] ) ? $_POST["tweet_text_colour"] : "#000",
		        "tweet_link_colour"         => isset( $_POST["tweet_link_colour"] ) ? $_POST["tweet_link_colour"] : "#000",
		        "tweet_background_colour"   => isset( $_POST["tweet_background_colour"] ) ? $_POST["tweet_background_colour"] : "#000",
		        "tweet_hashtag_colour"      => isset( $_POST["tweet_hashtag_colour"] ) ? $_POST["tweet_hashtag_colour"] : "#000",
		        "retweet_background_colour" => isset( $_POST["retweet_background_colour"] ) ? $_POST["retweet_background_colour"] : "#ddd",
		        "retweet_text_colour"       => isset( $_POST["retweet_text_colour"] ) ? $_POST["retweet_text_colour"] : "#000"
	        );

	        $this->SetConfiguration();
	        wp_redirect( admin_url( "admin.php?page=doublea-twitter-feed&saved=true" ) );
        }
        else{
            wp_redirect(admin_url("admin.php?page=doublea-twitter-feed&saved=false"));
        }
    }

    /**
     * Setup admin menu
     */
    function SetupMenus(){
        add_menu_page("DoubleA Twitter Feed","DoubleA Twitter Feed","manage_options","doublea-twitter-feed",array($this,"ConfigPage"),plugin_dir_url(__FILE__)."images/icon.png");
    }


    /**
     * A function to update the timeline on a timed basis.
     */
    private function UpdateTimeline(){

        $this->GetConfiguration();

        if(1==1 || $this->feed_next_update <= time()){
            $this->GetTwitterUserTimeline();
        }
    }
}

$doublea_twitter = new DoubleATwitterFeed();
