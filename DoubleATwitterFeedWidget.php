<?php

/**
 * Created by PhpStorm.
 * User: alan
 * Date: 11/01/17
 * Time: 15:17
 */
class DoubleATwitterFeedWidget extends WP_Widget
{

    /**
     * DoubleATwitterFeedWidget constructor.
     */
    function __construct()
    {
        parent::__construct(false, "DoubleA Twitter Feed", array("description" => "Displays tweets from a Twitter account"));
    }


    /**
     * @param array $args
     * @param array $instance
     */
    function widget( $args, $instance ) {
        // Widget output
        add_action( "wp_enqueue_scripts", array($this,"LoadScripts"));

        global $wpdb;
        $html="";

        $prefix = $wpdb->get_blog_prefix();

        //Get the screen name and display that
        $configuration = json_decode(get_option("doublea-twitter-feed-configuration"),true);

        //Styles
	    $tweet_text_colour = isset($configuration["styles"]["tweet_text_colour"]) ? $configuration["styles"]["tweet_text_colour"] : "#000";
        $tweet_link_colour = isset($configuration["styles"]["tweet_link_colour"]) ? $configuration["styles"]["tweet_link_colour"] : "blue";
	    $tweet_background_colour = isset($configuration["styles"]["tweet_background_colour"]) ? $configuration["styles"]["tweet_background_colour"] : "#FFF";
	    $tweet_hashtag_colour = isset($configuration["styles"]["tweet_hashtag_colour"]) ? $configuration["styles"]["tweet_hashtag_colour"] : "#CCC";

        $html.="<style>.tweet-text-colour{ color:".$tweet_text_colour.";} .tweet-background-colour{ background-color:".$tweet_background_colour.";} .tweet-link-colour{ color: ".$tweet_link_colour.";} .tweet-hashtag-colour{ color: ".$tweet_hashtag_colour.";}</style>";

        //Get the image
        $profile_image_url = "";
        $results = $wpdb->get_results("SELECT profile_image_url_https FROM ".$prefix."doublea_twitter_user_lookup",ARRAY_A);
        if($wpdb->num_rows != 0){
            $profile_image_url = $results[0]["profile_image_url_https"];
        }

        $screen_name = $configuration["screen_name"];
        $tweet_count = isset($instance["tweet_count"]) ? $instance["tweet_count"] : 3;

        $html.="<div class=\"doubleasoft-twitter-outer\">";

        if($screen_name != ""){
            $html.="<div style='display:inline; float:left;width:100%;margin-top:15px;'>";

            $html.="<img src='".plugin_dir_url(__FILE__)."/images/Twitter_Logo_Blue.png' width='64' height='64' /><h2 style='display: inline;vertical-align: middle;'><a href=\"https://twitter.com/".$screen_name."\" target=\"_blank\">@".$screen_name."</a></h2></div>";
        }

        //Display the tweets
        $tweets = $wpdb->get_results("SELECT a.id, a.created_at,a.text,a.url,b.text AS quoted_text FROM ".$prefix."doublea_twitter_data a LEFT JOIN ".$prefix."doublea_twitter_quoted_data b ON a.quoted_status_id_str = b.id LEFT JOIN ".$prefix."doublea_tweet_status c ON a.id=c.id WHERE IFNULL(c.status,0)=0 ORDER by a.created_at DESC LIMIT ".$tweet_count,"ARRAY_A");

        $html.="<ul style='float:left;font-size:1.5em;' class=\"doubleasoft-tweets\">";

        foreach ($tweets as $tweet){
            $date_time = new DateTime($tweet["created_at"]);

            $url = $tweet["url"];

            $tweet_content = $tweet["text"];
            $new_tweet_content = $tweet_content;

            //Replace the hastags with the url https://twitter.com/hashtag/ ?src=hash
            $result = preg_match_all('/#(\w+)/',$tweet_content,$matches,PREG_PATTERN_ORDER);

            if($result != false && $result > 0){
                for($i =0; $i < count($matches[0]); $i++) {
                    if (strlen($matches[0][$i]) > 0) {
                        $new_tweet_content = str_replace($matches[0][$i], '<a href="https://twitter.com/hashtag/' . $matches[1][$i] . '?src=hash" class="tweet-hashtag-colour" target="_blank">' . $matches[0][$i] . "</a>", $new_tweet_content);
                    }
                }
            }

            $html.="<li class=\"doubleasoft-tweet tweet-background-colour tweet-text-colour\" style=\"list-style: none;margin-top:11px;margin-left:-29px;padding:5px;border:1px #ccc solid;border-radius: 5px;\">";

            if($profile_image_url != ""){
                $html.="<img src='".$profile_image_url."' alt='Logo' style='float:left;' />";
            }

            if(strlen($url)>0) {
                $html .= "<a href=\"".$url."\" target=\"_blank\">";
            }
            $html.="<div style=\"font-size:0.5em\" class=\"doubleasoft-tweet-header\"><strong>@".$screen_name."</strong> - ".$date_time->format("d M Y H:i:s")."</div>";
            if(strlen($url) > 0){
                $html.="</a>";
            }


            //Handle the links in a tweet
            $re = '/(?:https?:\/\/|(?:www\.|[\-;:&=\+\$,\w]+@)[A-Za-z0-9\.\-]+)(?:(?:\/[\+~%\/\.\w\-_]*)?\??(?:[\-\+=&;%@\.\w_]*)#?(?:[\.\!\/\\\\\w]*))?/';
            $result = preg_match($re,$tweet_content,$matches);
            if($result != false && $result > 0){
                for($i=0;$i<count($matches);$i++){
                    $new_tweet_content = str_replace($matches[$i],'<a href="'.$matches[$i].'" target="_blank" class="tweet-link-colour">'.$matches[$i].'</a>',$tweet_content);
                }
            }


            $html.="<div style=\"font-size: 0.75em\" class=\doubleasoft-tweet-text\">".$new_tweet_content."</div>";

            //TODO Refactor into one function
            //Retweet quoted text
            $tweet_quoted_text = $tweet["quoted_text"];
            if($tweet_quoted_text != ""){
                $result = preg_match($re,$tweet_quoted_text,$matches);
                if($result != false && $result > 0){
                    for($i=0;$i<count($matches);$i++){
                        $tweet_quoted_text = str_replace($matches[$i],'<a href="'.$matches[$i].'" target="_blank">'.$matches[$i].'</a>',$tweet_quoted_text);
                    }
                }

                $html.="<div style=\"margin-left:15px;background-color:".$configuration['styles']['retweet_background_colour'].";padding:7px;font-size:0.75em;border-radius:15px;border:#bbb 1px solid;".$configuration['styles']['retweet_text_colour']." \">".$tweet_quoted_text."</div>";
            }

            $html.="</li>";

        }

        //Close tweets div
        $html.="</ul>";

        //Close the outer div
        $html .= "</div>";

        echo $html;
    }

    /**
     *
     */
    function LoadScripts(){
        wp_register_style("doublea-twitter-feed-widget",plugin_dir_url(__FILE__)."/css/default.css");
        wp_enqueue_style("doublea-twitter-feed-widget");
    }

    /**
     * @param array $new_instance
     * @param array $old_instance
     * @return array
     */
    function update( $new_instance, $old_instance ) {

        $instance = $old_instance;

        //Allow numeric value for tweet count
        if(is_numeric($new_instance["tweet_count"])){
            $instance["tweet_count"] = $new_instance["tweet_count"];
        }
        else{
            $instance["tweet_count"] = isset($old_instance["tweet_count"]) ? $old_instance["tweet_count"] : 3;
        }

        return $instance;

    }

    /**
     * @param array $instance
     */
    function form( $instance ) {
        $tweet_count = 3;

        if(array_key_exists("tweet_count",$instance)){
            if(is_numeric($instance["tweet_count"])){
                $tweet_count = $instance["tweet_count"];
            }
        }
        ?>
        <label for="<?php echo $this->get_field_id("tweet_count");?>"><?php echo __("Number of tweets to list (default = 3)");?></label><br/>
        <input type="text" name="<?php echo $this->get_field_name("tweet_count");?>" id="<?php echo $this->get_field_id("tweet_count");?>" maxlength="3" size="20" value="<?php echo $tweet_count;?>" /><br/>
        <?php
    }

}