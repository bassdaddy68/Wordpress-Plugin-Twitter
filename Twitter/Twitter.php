<?php
/**
 * Created by PhpStorm.
 * User: alan
 * Date: 04/01/17
 * Time: 12:30
 */

namespace doublea\social;

include_once "TwitterException.php";

class Twitter
{
    private $consumer_key;
    private $consumer_secret;
    private $access_token;
    private $access_token_secret;
    private $encoded;
    private $bearer_token;

    public function __construct($consumer_key, $consumer_secret, $access_token, $access_token_secret){
        $this->consumer_key = $consumer_key;
        $this->consumer_secret = $consumer_secret;
        $this->access_token = $access_token;
        $this->access_token_secret = $access_token_secret;
    }

    private function Encode($value){
        $encoded_value = base64_encode($value);

        return $encoded_value;
    }

    /**
     * @return mixed
     */
    private function GetBearerToken(){
        $ch = curl_init("https://api.twitter.com/oauth2/token");

        //Headers
        $headers = array(
            "Authorization: Basic ".$this->encoded,
            "Content-type: application/x-www-form-urlencoded;charset=UTF-8",
            "Content-Length: 29",
            //"Accept-Encoding: gzip"
        );

        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => "grant_type=client_credentials",
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true
        ));

        $result = curl_exec($ch);

        curl_close($ch);

        $result = json_decode($result,true);

        $this->bearer_token = $result["access_token"];

        return $result;
    }


    /**
     * @param $query
     */
    public function Search($query){
        //Encode consumer key
        $url_encoded_consumer_key = $this->UrlEncode($this->consumer_key);

        //Encode secret
        $url_encoded_secret = $this->UrlEncode($this->consumer_secret);

        //Concatenate
        $this->encoded = $this->Encode($url_encoded_consumer_key.":".$url_encoded_secret);

        $token = $this->GetBearerToken();

        $url = "https://api.twitter.com/1.1/search/tweets.json?q=".$query;

        $ch = curl_init();

        $headers = array(
            "Authorization: Bearer ".$this->bearer_token
        );

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_POST => false,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true
        ));

        $result = curl_exec($ch);

        curl_close($ch);

        return $result;
    }


    /**
     * @param $screen_name
     * @param $user_id
     */
    public function UserLookup($screen_name, $user_id = 0){
        //TODO handle either screen_name or user_id

        if(empty($screen_name) && (empty($user_id) || ! is_numeric($user_id))){
            throw new \Exception("A valid screen name or user id has not been provided");
        }

        $header_array = array();

        if(!empty($screen_name)){
        }

        array_push($header_array,$this->UserAuthentication("get","https://api.twitter.com/1.1/users/lookup.json","screen_name=".$screen_name,""));

        $ch = curl_init("https://api.twitter.com/1.1/users/lookup.json?screen_name=".$screen_name);

        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER => $header_array,
            CURLOPT_RETURNTRANSFER => true,
        ));

        $result = curl_exec($ch);

        $test_result = json_decode($result);

        if( array_key_exists("errors", $test_result) ){
            $full_string = "";
            foreach ($test_result["errors"] as $error){
                $full_string.="Error: ".$error["message"]." - Code: ".$error["code"]."\n";
            }

            throw new TwitterException("Twitter API exception in UserLookup.\n".$full_string,100);
        }

        return $result;
    }


    /**
     * @param $screen_name
     * @param $count
     * @param $since_id
     * @return mixed
     * @throws TwitterException
     */
    public function UserTimeline($screen_name, $count,$since_id){

        //Create signature
        $header_array = array();
        array_push($header_array, $this->UserAuthentication("get", "https://api.twitter.com/1.1/statuses/user_timeline.json", "count=" . $count . "&screen_name=" . $screen_name . "&since_id=" . $since_id, ""));

        $ch = curl_init("https://api.twitter.com/1.1/statuses/user_timeline.json?count=" . $count . "&screen_name=" . $screen_name . "&since_id=" . $since_id );

        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER => $header_array,
            CURLOPT_RETURNTRANSFER => true,
        ));

        $result = curl_exec($ch);

        $test_result = json_decode($result,true);

        if( array_key_exists("errors", $test_result) ){
            $full_string = "";
            foreach ($test_result["errors"] as $error){
                $full_string.="Error: ".$error["message"]." - Code: ".$error["code"]."\n";
            }

            throw new TwitterException("Twitter API exception in UserTimeline.\n".$full_string,100);
        }

        curl_close($ch);

        return $result;
    }


    private function UrlEncode($value){
        return urlencode($value);
    }

    /**
     * @param $base_url
     * @param $query_params
     * @param $request_body
     * returns a string header including the signature
     */
    public function UserAuthentication($http_method,$base_url, $query_params, $request_body){

        $signature="";

        //Create array for signature
        $parameters = array();
        $oauth_header_array = array();

        if($query_params !=""){
            $parameters_split = explode("&",$query_params);

            foreach ($parameters_split as $parameter_split){
                $exploded = explode("=",$parameter_split);
                $parameters[$exploded[0]] = $exploded[1];
            }
        }

        if($request_body != ""){
            $exploded = explode("=",$request_body);

            for($i=0;$i < count($exploded);$i+=2){
                $parameters[$exploded[$i]] = $exploded[$i+1];
            }
        }

        $oauth_header_array["oauth_consumer_key"] = $this->consumer_key;

        $oauth_header_array["oauth_token"] = $this->access_token;

        //Generate nonce
        $nonce = "";
        for($i=0;$i<32;$i++){
            $temp = chr(rand(65,90));
            $nonce .= $temp;
        }

        $oauth_header_array["oauth_nonce"] = $nonce;

        $oauth_header_array["oauth_version"] = "1.0";

        $oauth_header_array["oauth_signature_method"] = "HMAC-SHA1";

        $time = time();
        $oauth_header_array["oauth_timestamp"] = $time;

        //Sort array by key
        foreach ($oauth_header_array as $key => $value){
            $parameters[$key] = $value;
        }

        ksort($parameters);

        $count = 0;
        foreach ($parameters as $key => $value){
            if($count > 0){
                $signature.="&";
            }
            $signature .= rawurlencode($key)."=".rawurlencode($value);
            $count++;
        }

        //Create signature
        $signature_str = strtoupper($http_method)."&".rawurlencode($base_url)."&".rawurlencode($signature);

        $signing_key = rawurlencode($this->consumer_secret)."&".rawurlencode($this->access_token_secret);
        $signature = base64_encode(hash_hmac("sha1",$signature_str,$signing_key,true));

        $oauth_header = "Authorization: OAuth ";
        $oauth_header_array["oauth_signature"] = rawurlencode($signature);

        ksort($oauth_header_array);

        //Create the header
        $count =0;
        foreach ($oauth_header_array as $key => $value){
            if($count > 0){
                $oauth_header .= ", ";
            }
            $oauth_header .= $key."=\"".$value."\"";

            $count++;
        }

        return $oauth_header;
    }

}