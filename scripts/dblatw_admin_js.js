/**
 * Created by alan on 01/03/17.
 */

jQuery(document).ready(function(){
    var resultCount = 0;
    var pageNumber=1;
    //Update tweet
    jQuery("#butt_update_now").click(function(){
        jQuery("#butt_update_now_status").text("");
        jQuery.post(ajaxurl,{
            'action': 'doublea_twitter_update_feed'
        },function(response){
           jQuery("#butt_update_now_status").text("Update started!");
        });
    });
    /* View the tweet */
    jQuery(document).on("click","[tweet-data-id]",function(){

        jQuery("#button-hide-tweet").removeAttr("disabled");

        jQuery.post(DoubleAAjax.ajaxurl,{
            'action' : 'doublea_twitter_get_item',
            'tweet_id' : jQuery(this).attr("tweet-data-id"),

            },
            function (response) {
                jQuery("#data-tweet-id").text(response[0].id);
                jQuery("#data-tweet-created-on").text(response[0].created_at);
                jQuery("#data-tweet-text").text(response[0].text);
                jQuery("#data-tweet-status").text(response[0].status == 0 ? "OK" : "Hidden");
                jQuery("#data-tweet-status-id").val(response[0].status);
                var buttText = '';
                switch(response[0].status){
                    case '0':
                        buttText = 'Hide Tweet';
                        break;
                    case '1':
                        buttText = 'Unhide Tweet';
                        break;
                }
                jQuery("#button-hide-tweet").text(buttText);
            }
        );
    });

    /*List tweets*/
    function GetTweetList(){
        jQuery("#tweet-data-message").text("Please wait......");
        jQuery.ajax({
            'url':DoubleAAjax.ajaxurl,
            'data':'action=doublea_get_tweets&page_number=' + pageNumber,
            'success':function(response) {
                //Get the result count
                resultCount = response.recordCount;

                if(pageNumber == 1){
                    jQuery("#button-tweet-data-previous").attr("disabled","disabled");
                }
                else {
                    jQuery("#button-tweet-data-previous").removeAttr("disabled");
                }
                if(resultCount <= (pageNumber * 10 -1)){
                    jQuery("#button-tweet-data-next").attr("disabled","disabled");
                }
                else{
                    jQuery("#button-tweet-data-next").removeAttr("disabled");
                }


                jQuery("#doublea-tweet-list-body tr").remove();
                var status='', retweetStatus='';
                for (var i = 0; i < response[0].data.length; i++) {
                    retweetStatus = response[0].data[i].is_quote_status == 1 ? 'Yes' : 'No';

                    switch(response[0].data[i].status){
                        case '0':
                            status='Ok';
                            break;
                        case '1':
                            status='Hidden';
                            break;
                    }
                    jQuery("#doublea-tweet-list-body").append("<tr><td><a href='#' tweet-data-id='" + response[0].data[i].id +  "'>" + response[0].data[i].id + "</a></td><td>" + response[0].data[i].created_at + "</td><td>" + response[0].data[i].text + "</td><td>" + retweetStatus + "</td><td>" + response[0].data[i].url + "</td><td>" + status + "</td></tr>");
                }
                jQuery("#tweet-data-message").text("");
            }
        });
    }

    jQuery(".tweet_style").on("blur",function(){
        var _value = new String(jQuery(this).val());
        var _reggy = new RegExp(/^#([a-f0-9]{6}|[a-f0-9]{3})$/ig);
        if(_reggy.test(_value) === true){
            jQuery(this).next().css("background-color",jQuery(this).val());
        }
        else{
            alert("Invalid colour value used");
            jQuery(this).val("#000");
        }
    });


    jQuery("#data-configuration").click(function(){
        GetTweetList();
    });

    /* Previous button */
    jQuery("#button-tweet-data-previous").click(function(){
        jQuery("#button-tweet-data-previous").attr("disabled","disabled");
        pageNumber--;
        GetTweetList();
    })

    /* Next button */
    jQuery("#button-tweet-data-next").click(function(){
        jQuery("#button-tweet-data-next").attr("disabled","disabled");
        pageNumber++;
        GetTweetList();
    });


    /* Hide the tweet */
    jQuery("#button-hide-tweet").click(function(){
       jQuery.post(DoubleAAjax.ajaxurl,{
          'action': 'doubleatweet_hideunhide',
           'item_id': jQuery("#data-tweet-id").text(),
           'current_status_id' : jQuery("#data-tweet-status-id").val()
           },
           function(response) {
                alert(response);
           }
       );
    });

    //Display the retweet preview
    jQuery('#retweet_background_colour, #retweet_text_colour').blur(function(){
       jQuery('.retweet-preview').css({
           "background-color": jQuery('#retweet_background_colour').val(),
           "color": jQuery('#retweet_text_colour').val()
       })
    });
});